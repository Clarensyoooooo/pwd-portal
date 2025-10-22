<?php
// resend_email.php
// Sends emails using Resend API without Composer — works for both dev and production

function sendResendEmail($to, $subject, $htmlContent) {
    // ✅ Use your actual Resend API key here
    $apiKey = 're_V16SxPX1_7YrTzZoJ87XktLPfmmgKQgoe';
    
    // ✅ Use Resend's default sender (no domain verification needed)
    $from = 'onboarding@resend.dev';

    // Build the JSON payload
    $payload = json_encode([
        'from' => "PWD Portal <{$from}>",
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlContent
    ]);

    // Initialize cURL
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        return true;
    } else {
        error_log("Resend API Error: " . $response);
        return false;
    }
}
?>
