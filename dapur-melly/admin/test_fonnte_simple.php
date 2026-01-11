<?php
echo "<h2>Test Fonnte API dengan Token Baru</h2>";
echo "<pre>";

// Token baru
$token = "MFipr6soTB2SQQ3Y1vdC";
$target = "6285157997271";
$message = "Test API Fonnte - " . date('Y-m-d H:i:s');

echo "Token: $token\n";
echo "Target: $target\n";
echo "Message: $message\n\n";

$data = [
    'target' => $target,
    'message' => $message,
    'countryCode' => '62'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.fonnte.com/send');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $token"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $http_code\n";
echo "Response: " . htmlspecialchars($response) . "\n";
echo "Error: " . ($error ?: 'None') . "\n";

curl_close($ch);

echo "\n--- Response Analysis ---\n";
$response_data = json_decode($response, true);
if ($response_data) {
    echo "Status: " . ($response_data['status'] ? 'true' : 'false') . "\n";
    echo "Message: " . ($response_data['message'] ?? 'N/A') . "\n";
    echo "Message ID: " . ($response_data['message_id'] ?? 'N/A') . "\n";
} else {
    echo "Invalid JSON response\n";
}
echo "</pre>";
?>