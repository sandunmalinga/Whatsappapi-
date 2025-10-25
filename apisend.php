<?php
// public_send.php
// Send WhatsApp message using public API (appkey + authkey)

$appkey = '2baf583a-7938-459e-bcdb-926407612313';   // Replace with your device's appkey
$authkey = '5ffd895c-fc22-46ed-acab-546d0ab98006'; // Replace with your device's authkey
$to = '94767262121';            // Recipient number with country code
$message = 'Hello from public API!'; // Your message
$sandbox = false;               // Optional, default false

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:3000/public/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'appkey' => $appkey,
        'authkey' => $authkey,
        'to' => $to,
        'message' => $message,
        'sandbox' => $sandbox
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['success']) && $data['success']) {
    echo "✅ Message sent successfully to {$data['sent_to']}";
} else {
    echo "❌ Failed to send message: " . ($data['error'] ?? 'Unknown error');
}
