<?php
session_start();
if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];

// Fetch user's devices from your backend API
$devices = [];
$apiUrl = "http://localhost:3000/api/devices";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $token",
]);
$response = curl_exec($ch);
curl_close($ch);

$devicesData = json_decode($response, true);
if (is_array($devicesData)) {
    $devices = $devicesData;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Documentation | WhatsApp Manager</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { font-family: 'Poppins', sans-serif; background: #f9fafc; }
.sidebar { height: 100vh; background: #111827; color: white; position: fixed; width: 250px; padding-top: 20px; }
.sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 20px; margin: 4px 12px; border-radius: 8px; transition: background 0.2s; }
.sidebar a.active, .sidebar a:hover { background: #2563eb; color: white; }
.main-content { margin-left: 250px; padding: 30px; }
pre { background: #e5e7eb; padding: 15px; border-radius: 8px; }
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
<h3 class="mb-4">📡 Public API Documentation</h3>

<p>Select a device to get its <strong>appkey</strong> and <strong>authkey</strong> for sending messages:</p>
<select id="deviceSelect" class="form-select mb-4">
    <?php foreach($devices as $device): ?>
        <option value="<?= $device['appkey'] ?>|<?= $device['authkey'] ?>">
            <?= htmlspecialchars($device['name']) ?> (<?= $device['status'] ?>)
        </option>
    <?php endforeach; ?>
</select>

<h5>JSON Request Example:</h5>
<pre id="jsonExample"></pre>

<h6>cURL Example:</h6>
<pre id="curlExample"></pre>

<h6>PHP Example:</h6>
<pre id="phpExample"></pre>

<h6>Node.js Example:</h6>
<pre id="nodeExample"></pre>

<h6>Python Example:</h6>
<pre id="pythonExample"></pre>

</div>

<script>
const deviceSelect = document.getElementById('deviceSelect');
const jsonExample = document.getElementById('jsonExample');
const curlExample = document.getElementById('curlExample');
const phpExample = document.getElementById('phpExample');
const nodeExample = document.getElementById('nodeExample');
const pythonExample = document.getElementById('pythonExample');

function updateExamples() {
    const [appkey, authkey] = deviceSelect.value.split('|');

    const exampleJSON = {
        "appkey": appkey,
        "authkey": authkey,
        "to": "94771234567",
        "message": "Hello World"
    };

    jsonExample.textContent = JSON.stringify(exampleJSON, null, 4);

    curlExample.textContent = `curl -X POST http://localhost:3000/public/send \\
-H "Content-Type: application/json" \\
-d '${JSON.stringify(exampleJSON)}'`;

    phpExample.textContent =
""


    nodeExample.textContent =
`import fetch from 'node-fetch';

const data = ${JSON.stringify(exampleJSON, null, 4)};

fetch('http://localhost:3000/public/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
})
.then(res => res.json())
.then(console.log)
.catch(console.error);`;

    pythonExample.textContent =
`import requests
import json

url = "http://localhost:3000/public/send"
data = ${JSON.stringify(exampleJSON, null, 4)}

response = requests.post(url, headers={"Content-Type": "application/json"}, data=json.dumps(data))
print(response.json())`;
}

// Update on page load and when device changes
updateExamples();
deviceSelect.addEventListener('change', updateExamples);
</script>

</body>
</html>
