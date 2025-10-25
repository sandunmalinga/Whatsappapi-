import express from "express";
import cors from "cors";
import jwt from "jsonwebtoken";
import bcrypt from "bcrypt";
import mysql from "mysql2/promise";
import qrcode from "qrcode";
import { v4 as uuidv4 } from "uuid";
import makeWASocket, { useMultiFileAuthState, DisconnectReason } from "@whiskeysockets/baileys";
import fs from "fs";
import path from "path";

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const PORT = 3000;
const JWT_SECRET = "supersecretkey";

// Database connection
const db = mysql.createPool({
  host: "localhost",
  user: "root",
  password: "",
  database: "whatsapp_multi",
});

// Map to store active device sessions
const devices = new Map();

// Create sessions directory
const sessionsDir = path.join(process.cwd(), "sessions");
if (!fs.existsSync(sessionsDir)) fs.mkdirSync(sessionsDir, { recursive: true });

// ----------------------
// Auth Routes
// ----------------------
app.post("/api/register", async (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: "Missing fields" });

  try {
    const hash = await bcrypt.hash(password, 10);
    await db.query("INSERT INTO users (username, password) VALUES (?, ?)", [username, hash]);
    res.json({ success: true });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Registration failed" });
  }
});

app.post("/api/login", async (req, res) => {
  const { username, password } = req.body;
  const [rows] = await db.query("SELECT * FROM users WHERE username=?", [username]);
  const user = rows[0];
  if (!user) return res.status(401).json({ error: "Invalid credentials" });

  const match = await bcrypt.compare(password, user.password);
  if (!match) return res.status(401).json({ error: "Invalid credentials" });

  const token = jwt.sign({ userId: user.id }, JWT_SECRET, { expiresIn: "1d" });
  res.json({ token });
});

// JWT middleware
function authMiddleware(req, res, next) {
  const token = req.headers["authorization"];
  if (!token) return res.status(401).json({ error: "Unauthorized" });

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    req.userId = decoded.userId;
    next();
  } catch {
    res.status(401).json({ error: "Invalid token" });
  }
}

// ----------------------
// WhatsApp Device Management
// ----------------------
async function startWhatsAppDevice(deviceId) {
  try {
    const sessionPath = path.join(sessionsDir, String(deviceId));
    if (!fs.existsSync(sessionPath)) fs.mkdirSync(sessionPath, { recursive: true });

    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    const sock = makeWASocket({ auth: state, printQRInTerminal: true });

    devices.set(deviceId, { sock, qr: "", connected: false });

    sock.ev.on("connection.update", async (update) => {
      const { connection, qr, lastDisconnect } = update;

      if (qr) {
        devices.get(deviceId).qr = await qrcode.toDataURL(qr);
        console.log(`📱 QR Code generated for device ${deviceId}`);
      }

      if (connection === "open") {
        console.log(`✅ Device ${deviceId} connected`);
        devices.get(deviceId).connected = true;
        devices.get(deviceId).qr = "";
        await db.query("UPDATE devices SET status='connected' WHERE id=?", [deviceId]);
      }

      if (connection === "close") {
        const reason = lastDisconnect?.error?.output?.statusCode;
        console.log(`❌ Device ${deviceId} disconnected: ${reason}`);
        devices.get(deviceId).connected = false;
        await db.query("UPDATE devices SET status='disconnected' WHERE id=?", [deviceId]);

        if (reason !== DisconnectReason.loggedOut) {
          console.log(`🔁 Restarting session for device ${deviceId}...`);
          await startWhatsAppDevice(deviceId);
        }
      }
    });

    sock.ev.on("creds.update", saveCreds);
  } catch (err) {
    console.error("Failed to start device:", deviceId, err);
  }
}

// ----------------------
// Device APIs
// ----------------------
app.post("/api/devices", authMiddleware, async (req, res) => {
  const { name } = req.body;
  if (!name) return res.status(400).json({ error: "Device name required" });

  const appkey = uuidv4();
  const authkey = uuidv4();

  const [result] = await db.query(
    "INSERT INTO devices (user_id, name, appkey, authkey, status, created_at) VALUES (?, ?, ?, ?, 'disconnected', NOW())",
    [req.userId, name, appkey, authkey]
  );

  const deviceId = result.insertId;
  await startWhatsAppDevice(deviceId);

  res.json({ success: true, deviceId, appkey, authkey });
});

app.get("/api/devices", authMiddleware, async (req, res) => {
  const [rows] = await db.query("SELECT * FROM devices WHERE user_id=?", [req.userId]);
  res.json(rows);
});

app.get("/api/qr/:deviceId", authMiddleware, (req, res) => {
  const device = devices.get(parseInt(req.params.deviceId));
  if (!device) return res.status(404).json({ error: "Device not found" });
  res.json({ qr: device.qr });
});

// ----------------------
// Protected Send API (JWT)
// ----------------------
app.post("/api/create-message", authMiddleware, async (req, res) => {
  const { to, message, deviceId } = req.body;
  if (!to || !message || !deviceId) return res.status(400).json({ error: "Missing parameters" });

  const active = devices.get(parseInt(deviceId));
  if (!active || !active.sock) return res.status(423).json({ error: "Device not connected" });

  try {
    await active.sock.sendMessage(`${to}@s.whatsapp.net`, { text: message });
    await db.query(
      "INSERT INTO messages (device_id, phone, message, status, sent_at) VALUES (?, ?, ?, 'sent', NOW())",
      [deviceId, to, message]
    );
    res.json({ success: true });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: "Send failed" });
  }
});

// ----------------------
// ✅ Single Correct Public Send API
// ----------------------
app.post("/public/send", async (req, res) => {
  try {
    const { appkey, authkey, to, message } = req.body;
    if (!appkey || !authkey || !to || !message) {
      return res.status(400).json({ success: false, error: "Missing parameters" });
    }

    // Find device in DB
    const [rows] = await db.query(
      "SELECT * FROM devices WHERE appkey=? AND authkey=?",
      [appkey, authkey]
    );
    const device = rows[0];
    if (!device) return res.status(401).json({ success: false, error: "Invalid appkey/authkey" });

    // Get active session
    const active = devices.get(parseInt(device.id));
    if (!active || !active.sock) {
      return res.status(400).json({ success: false, error: "Device not connected" });
    }

    const jid = to.includes("@s.whatsapp.net") ? to : `${to}@s.whatsapp.net`;
    await active.sock.sendMessage(jid, { text: message });

    await db.query(
      "INSERT INTO messages (device_id, phone, message, status, sent_at) VALUES (?, ?, ?, 'sent', NOW())",
      [device.id, to, message]
    );

    res.json({ success: true, sent_to: to, message: "Message sent successfully" });
  } catch (err) {
    console.error("Public send error:", err);
    res.status(500).json({ success: false, error: "Internal server error" });
  }
});

app.get("/api/dashboard", authMiddleware, async (req, res) => {
  try {
    console.log("Fetching dashboard for userId:", req.userId);

    const [devices] = await db.query(
      "SELECT COUNT(*) as total FROM devices WHERE user_id=?", [req.userId]
    );

    const [active] = await db.query(
      "SELECT COUNT(*) as total FROM devices WHERE user_id=? AND status='connected'", [req.userId]
    );

    const [messages] = await db.query(
      "SELECT COUNT(*) as total FROM messages m JOIN devices d ON d.id=m.device_id WHERE d.user_id=?", [req.userId]
    );

    const [trend] = await db.query(`
      SELECT DATE(sent_at) as date, COUNT(*) as count 
      FROM messages m JOIN devices d ON d.id=m.device_id 
      WHERE d.user_id=? AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      GROUP BY DATE(sent_at)
    `, [req.userId]);

    const [statusStats] = await db.query(`
      SELECT 
        SUM(m.status='sent') as sent,
        SUM(m.status='failed') as failed,
        SUM(m.status='pending') as pending
      FROM messages m 
      JOIN devices d ON d.id=m.device_id 
      WHERE d.user_id=?
    `, [req.userId]);

    res.json({
      totalDevices: devices[0].total,
      activeDevices: active[0].total,
      totalMessages: messages[0].total,
      messageTrends: {
        dates: trend.map(r => r.date),
        counts: trend.map(r => r.count)
      },
      statusStats: statusStats[0]
    });
  } catch (err) {
    console.error("Dashboard Error:", err);
    res.status(500).json({ error: "Failed to load dashboard data" });
  }
});


// ----------------------
// Rename Device
// ----------------------
app.put("/api/devices/:id", authMiddleware, async (req, res) => {
    const deviceId = parseInt(req.params.id);
    const { name } = req.body;

    if (!name) return res.status(400).json({ success: false, error: "Name is required" });

    try {
        // Update device name in DB
        await db.query("UPDATE devices SET name=? WHERE id=? AND user_id=?", [name, deviceId, req.userId]);

        res.json({ success: true });
    } catch (err) {
        console.error("Rename device error:", err);
        res.status(500).json({ success: false, error: "Failed to rename device" });
    }
});

// ----------------------
// Remove Device
// ----------------------
app.delete("/api/devices/:id", authMiddleware, async (req, res) => {
    const deviceId = parseInt(req.params.id);

    try {
        // Remove from memory
        if (devices.has(deviceId)) {
            const sock = devices.get(deviceId).sock;
            sock?.logout?.(); // attempt to logout WA session
            devices.delete(deviceId);
        }

        // Delete from DB
        await db.query("DELETE FROM devices WHERE id=? AND user_id=?", [deviceId, req.userId]);

        res.json({ success: true });
    } catch (err) {
        console.error("Remove device error:", err);
        res.status(500).json({ success: false, error: "Failed to remove device" });
    }
});


// Get devices for logged-in user
app.get("/api/my-devices", authMiddleware, async (req, res) => {
  try {
    const [rows] = await db.query(
      "SELECT id, name, appkey, authkey FROM devices WHERE user_id = ?",
      [req.userId]
    );

    res.json({ success: true, devices: rows });
  } catch (err) {
    console.error("Fetch devices error:", err);
    res.status(500).json({ success: false, error: "Failed to fetch devices" });
  }
});


// ----------------------
// Start Server
// ----------------------
app.listen(PORT, "0.0.0.0", () => {
  console.log(`✅ Server running on http://localhost:${PORT}`);
});
