<?php
// backend/app/helpers/EmailHelper.php

require_once __DIR__ . '/../../config/env_loader.php';
loadEnv(__DIR__ . '/../../.env');

class EmailHelper {
    private $host = 'smtp.gmail.com';
    private $port = 465;
    private $username = '';
    private $password = '';
    private $fromName = 'Fitrova App';

    public function __construct($senderEmail = null) {
        $smtpUser = getenv('SMTP_USER') ?: 'jackcojahk@gmail.com';
        $smtpPass = getenv('SMTP_PASS') ?: 'bhguhqnfzyoclbly';
        
        $this->username = $smtpUser;
        $this->password = $smtpPass;
        
        if ($senderEmail) {
            $this->username = $senderEmail;
        }
    }

    public function sendVerificationCode($toEmail, $code) {
        $subject = "Your Fitrova Verification Code";
        $message = "
            <html>
            <body style='font-family: sans-serif; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #10B981;'>Welcome to Fitrova!</h2>
                    <p>Thank you for signing up. Please use the following code to verify your email address:</p>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; text-align: center; padding: 20px; background: #f9fafb; border-radius: 8px; margin: 20px 0;'>
                        $code
                    </div>
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you didn't create an account, you can safely ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #9CA3AF;'>&copy; 2026 Fitrova. All rights reserved.</p>
                </div>
            </body>
            </html>
        ";

        return $this->sendMail($toEmail, $subject, $message);
    }

    private function sendMail($to, $subject, $message) {
        $brevoKey = getenv('BREVO_API_KEY');
        $sendgridKey = getenv('SENDGRID_API_KEY');
        $resendKey = getenv('RESEND_API_KEY');

        // Only try HTTP API if Brevo key is a valid REST API key (not an SMTP password starting with xsmtpsib-)
        if (!empty($brevoKey) && strpos($brevoKey, 'xsmtpsib-') === false) {
            $sent = $this->sendViaBrevo($to, $subject, $message, $brevoKey);
            if ($sent) return true;
        } elseif (!empty($sendgridKey)) {
            $sent = $this->sendViaSendGrid($to, $subject, $message, $sendgridKey);
            if ($sent) return true;
        } elseif (!empty($resendKey)) {
            $sent = $this->sendViaResend($to, $subject, $message, $resendKey);
            if ($sent) return true;
        }

        // Always fallback to direct Gmail SMTP socket (using App Password)
        return $this->sendViaSmtp($to, $subject, $message);
    }

    private function sendViaBrevo($to, $subject, $message, $apiKey) {
        echo "    [EMAIL] Sending via Brevo API...\n";
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        $payload = json_encode([
            'sender' => ['name' => $this->fromName, 'email' => $this->username],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $message
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "    [OK] Sent via Brevo!\n";
            return true;
        }

        error_log("Brevo API Error (HTTP $httpCode): $response");
        echo "    [ERROR] Brevo failed: $response\n";
        return false;
    }

    private function sendViaSendGrid($to, $subject, $message, $apiKey) {
        echo "    [EMAIL] Sending via SendGrid API...\n";
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        $payload = json_encode([
            'personalizations' => [['to' => [['email' => $to]]]],
            'from' => ['email' => $this->username, 'name' => $this->fromName],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $message]]
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "    [OK] Sent via SendGrid!\n";
            return true;
        }

        error_log("SendGrid API Error (HTTP $httpCode): $response");
        echo "    [ERROR] SendGrid failed: $response\n";
        return false;
    }

    private function sendViaResend($to, $subject, $message, $apiKey) {
        echo "    [EMAIL] Sending via Resend API...\n";
        $ch = curl_init('https://api.resend.com/emails');
        
        $fromEmail = (strpos($this->username, '@gmail.com') !== false) 
            ? 'onboarding@resend.dev' 
            : $this->username;

        $payload = json_encode([
            'from' => $this->fromName . ' <' . $fromEmail . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $message
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            echo "    [OK] Sent via Resend!\n";
            return true;
        }

        error_log("Resend API Error (HTTP $httpCode): $response");
        echo "    [ERROR] Resend failed: $response\n";
        return false;
    }

    private function sendViaSmtp($to, $subject, $message) {
        // Since we don't have PHPMailer, we'll try to use a simple SMTP socket
        // Gmail requires SSL (port 465) or TLS (port 587)
        
        $date = date('r');
        $messageId = "<" . md5(uniqid(time())) . "@" . $this->host . ">";

        $header = "From: " . $this->fromName . " <" . $this->username . ">\r\n";
        $header .= "Date: $date\r\n";
        $header .= "Message-ID: $messageId\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=UTF-8\r\n";

        try {
            $socket = fsockopen("ssl://" . $this->host, $this->port, $errno, $errstr, 30);
            if (!$socket) return false;

            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 220) return false;

            fwrite($socket, "EHLO " . $this->host . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            fwrite($socket, "AUTH LOGIN\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 334) return false;

            fwrite($socket, base64_encode($this->username) . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 334) return false;

            fwrite($socket, base64_encode($this->password) . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 235) return false;

            fwrite($socket, "MAIL FROM: <" . $this->username . ">\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            fwrite($socket, "DATA\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 354) return false;

            fwrite($socket, "To: $to\r\nSubject: $subject\r\n$header\r\n\r\n$message\r\n.\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    private function getResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }

    private function getResponseCode($response) {
        $lines = explode("\n", trim($response));
        $lastLine = trim(end($lines));
        return (int)substr($lastLine, 0, 3);
    }
}
?>

