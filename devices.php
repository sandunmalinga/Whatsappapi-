<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Devices</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<head>
    <meta charset="UTF-8">
    <title>Dashboard | WhatsApp Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f9fafc;
        }
        .sidebar {
            height: 100vh;
            background: #111827;
            color: white;
            position: fixed;
            width: 250px;
            padding-top: 20px;
        }
        .sidebar a {
            color: #cbd5e1;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar a.active, .sidebar a:hover {
            background: #2563eb;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .chart-container {
            width: 100%;
            height: 300px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
 <div class="sidebar">
    <h4 class="text-center mb-4">📱 WA Sender</h4>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="devices.php">🧩 Devices</a>
    <a href="sendmessage.php">💬 Send Message</a>
    <a href="integration.php" class="active">📡 API Docs</a>
    <a href="logout.php">🚪 Logout</a>
</div>


<div class="main-content">
    <h3 class="mb-4">Manage Devices</h3>

    <!-- Add Device Form -->
    <div class="card mb-4 shadow-sm p-3">
        <h5>Add New Device</h5>
        <form id="addDeviceForm" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="name" class="form-control" placeholder="Device Name" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add Device</button>
            </div>
        </form>
        <div id="addDeviceMessage" class="mt-2"></div>
    </div>

    <!-- Device List -->
    <div id="devicesContainer" class="row g-3"></div>
</div>

<script>
const token = "<?php echo $token; ?>";
const apiBase = "http://localhost:3000/api";

// Fetch and display devices
async function loadDevices() {
    const container = document.getElementById('devicesContainer');
    container.innerHTML = 'Loading devices...';

    const res = await fetch(`${apiBase}/devices`, {
        headers: { "Authorization": token }
    });
    const devices = await res.json();

    container.innerHTML = '';
    devices.forEach(device => {
        const card = document.createElement('div');
        card.className = 'col-md-4';
        card.innerHTML = `
            <div class="card shadow-sm p-3">
                <h6>${device.name}</h6>
                <p>Status: <strong>${device.status}</strong></p>
                <div id="qr-${device.id}">
                    ${device.status === 'disconnected' ? '<em>QR not scanned yet</em>' : ''}
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-success" onclick="rescanQR(${device.id})">Rescan QR</button>
                    <button class="btn btn-sm btn-warning" onclick="renameDevice(${device.id})">Rename</button>
                    <button class="btn btn-sm btn-danger" onclick="removeDevice(${device.id})">Remove</button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

// Add device
document.getElementById('addDeviceForm').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const name = form.name.value;

    const res = await fetch(`${apiBase}/devices`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': token
        },
        body: JSON.stringify({ name })
    });
    const data = await res.json();

    const msgDiv = document.getElementById('addDeviceMessage');
    if (data.success) {
        msgDiv.innerHTML = '<div class="alert alert-success">Device added successfully!</div>';
        form.reset();
        loadDevices();
    } else {
        msgDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to add device'}</div>`;
    }
});

// Rename device
async function renameDevice(id) {
    const newName = prompt('Enter new device name:');
    if (!newName) return;

    const res = await fetch(`${apiBase}/devices/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Authorization': token },
        body: JSON.stringify({ name: newName })
    });
    const data = await res.json();
    if (data.success) {
        alert('Device renamed!');
        loadDevices();
    } else {
        alert(data.error || 'Failed to rename');
    }
}

// Remove device
async function removeDevice(id) {
    if (!confirm('Are you sure to remove this device?')) return;

    const res = await fetch(`${apiBase}/devices/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': token }
    });
    const data = await res.json();
    if (data.success) {
        alert('Device removed!');
        loadDevices();
    } else {
        alert(data.error || 'Failed to remove device');
    }
}

// Rescan QR
async function rescanQR(id) {
    const res = await fetch(`${apiBase}/qr/${id}`, { headers: { 'Authorization': token }});
    const data = await res.json();
    if (data.qr) {
        document.getElementById(`qr-${id}`).innerHTML = `<img src="${data.qr}" class="qr-img">`;
    } else {
        alert('QR not available yet. Make sure the device is disconnected.');
    }
}

// Load devices on page load
loadDevices();
</script>
</body>
</html>
