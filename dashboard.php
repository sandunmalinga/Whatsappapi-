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
<title>Dashboard | WhatsApp Manager</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

<div class="sidebar">
    <h4 class="text-center mb-4">📱 WA Sender</h4>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="devices.php">🧩 Devices</a>
    <a href="sendmessage.php">💬 Send Message</a>
    <a href="integration.php" class="active">📡 API Docs</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <h3 class="mb-4">Dashboard Overview</h3>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card text-center p-3">
                <h5>Total Devices</h5>
                <h2 id="totalDevices">--</h2>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-center p-3">
                <h5>Total Messages</h5>
                <h2 id="totalMessages">--</h2>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-center p-3">
                <h5>Active Devices</h5>
                <h2 id="activeDevices">--</h2>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card p-3">
                <h5>📈 Message Trend (Last 7 Days)</h5>
                <canvas id="lineChart" class="chart-container"></canvas>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card p-3">
                <h5>📊 Message Status Distribution</h5>
                <canvas id="pieChart" class="chart-container"></canvas>
            </div>
        </div>
    </div>

    <p id="error" class="text-danger text-center mt-3 fw-semibold"></p>
</div>

<script>
const token = "<?php echo $token; ?>";
const apiBase = "http://localhost:3000/api";

async function loadDashboard() {
    try {
        const res = await fetch(`${apiBase}/dashboard`, {
            headers: { "Authorization": token }
        });

        const data = await res.json();

        if (!res.ok || data.error) {
            document.getElementById('error').innerText = "❌ Failed to load dashboard: " + (data.error || "Unknown error");
            return;
        }

        // Update stats
        document.getElementById('totalDevices').innerText = data.totalDevices || 0;
        document.getElementById('totalMessages').innerText = data.totalMessages || 0;
        document.getElementById('activeDevices').innerText = data.activeDevices || 0;

        // Line Chart
        const ctx1 = document.getElementById('lineChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.messageTrends?.dates || [],
                datasets: [{
                    label: 'Messages Sent',
                    data: data.messageTrends?.counts || [],
                    borderColor: '#2563eb',
                    tension: 0.3,
                    fill: false
                }]
            }
        });

        // Pie Chart
        const ctx2 = document.getElementById('pieChart').getContext('2d');
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['Sent', 'Failed', 'Pending'],
                datasets: [{
                    data: [
                        data.statusStats?.sent || 0,
                        data.statusStats?.failed || 0,
                        data.statusStats?.pending || 0
                    ],
                    backgroundColor: ['#16a34a', '#dc2626', '#facc15']
                }]
            }
        });
    } catch (err) {
        console.error(err);
        document.getElementById('error').innerText = "❌ Failed to connect to API";
    }
}

loadDashboard();
</script>
</body>
</html>
