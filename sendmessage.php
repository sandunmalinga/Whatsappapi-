<?php
session_start();

if (!isset($_SESSION['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_SESSION['token'];
$message = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId = $_POST['device'] ?? '';
    $to = $_POST['to'] ?? '';
    $msg = $_POST['message'] ?? '';

    if ($deviceId && $to && $msg) {
        $curl = curl_init();
        $postData = [
            "deviceId" => $deviceId,
            "to" => $to,
            "message" => $msg
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:3000/api/create-message",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: $token"
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            $message = "❌ cURL error: " . curl_error($curl);
        }

        curl_close($curl);

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['success']) && $data['success']) {
            $success = "✅ Message sent to $to successfully!";
        } else {
            $message = "❌ Failed to send message: " . ($data['error'] ?? $response ?? 'Unknown error');
        }
    } else {
        $message = "❌ All fields are required.";
    }
}

// Fetch devices
$devicesList = [];
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "http://localhost:3000/api/devices",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: $token"
    ],
]);
$response = curl_exec($curl);

if (curl_errno($curl)) {
    $message = "❌ cURL error: " . curl_error($curl);
} else {
    $respData = json_decode($response, true);
    // The API returns an array directly
    if (is_array($respData)) {
        $devicesList = $respData;
    } else {
        $message = "❌ No devices found or invalid API response. Response: " . htmlspecialchars($response);
    }
}
curl_close($curl);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send WhatsApp Message</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
body { font-family: 'Poppins', sans-serif; background-color: #f9fafc; }
.sidebar { height: 100vh; background: #111827; color: white; position: fixed; width: 250px; padding-top: 20px; }
.sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 20px; margin: 4px 12px; border-radius: 8px; transition: background 0.2s; }
.sidebar a.active, .sidebar a:hover { background: #2563eb; color: white; }
.main-content { margin-left: 250px; padding: 30px; }
.card { border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
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
    <h3 class="mb-4">Send WhatsApp Message</h3>

    <?php if ($message) echo "<div class='alert alert-danger'>$message</div>"; ?>
    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>

    <div class="card p-4 mb-4">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label>Select Device</label>
                <select name="device" class="form-control" required>
                    <option value="">-- Select Device --</option>
                    <?php foreach ($devicesList as $d): 
                        $status = ucfirst($d['status'] ?? 'unknown'); ?>
                        <option value="<?= htmlspecialchars($d['id']); ?>">
                            <?= htmlspecialchars($d['name']); ?> (Status: <?= $status; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label>Phone Number</label>
                <input type="text" name="to" class="form-control" placeholder="e.g., 94771234567" required>
            </div>
            <div class="col-12">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary w-100">Send Message</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
