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
        
        echo "    [DEBUG] Connecting to SMTP as: " . $this->username . "\n";
        
        if ($senderEmail) {
            $this->username = $senderEmail;
        }
    }

    public function sendVerificationCode($toEmail, $code) {
        // [DEV DEBUG] Print the code to the terminal for easy testing
        echo "    [DEBUG] Verification code for $toEmail is: $code\n";

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
        // Since we don't have PHPMailer, we'll try to use a simple SMTP socket
        // Gmail requires SSL (port 465) or TLS (port 587)
        
        $date = date('r');
        $messageId = "<" . md5(uniqid(time())) . "@" . $this->host . ">";

        $header = "From: " . $this->fromName . " <" . $this->username . ">\r\n";
        $header .= "Date: $date\r\n";
        $header .= "Message-ID: $messageId\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=UTF-8\r\n";

        // For this MVP, we will try to use the native PHP mail() if configured
        // BUT since the user wants it to work with their App Password, 
        // a simple SMTP socket implementation is needed.
        
        try {
            $socket = fsockopen("ssl://" . $this->host, $this->port, $errno, $errstr, 30);
            if (!$socket) return false;

            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 220) return false;

            echo "    [SMTP] SENDING: EHLO\n";
            fwrite($socket, "EHLO " . $this->host . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            echo "    [SMTP] SENDING: AUTH LOGIN\n";
            fwrite($socket, "AUTH LOGIN\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 334) return false;

            fwrite($socket, base64_encode($this->username) . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 334) return false;

            fwrite($socket, base64_encode($this->password) . "\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 235) return false;

            echo "    [SMTP] SENDING: MAIL FROM\n";
            fwrite($socket, "MAIL FROM: <" . $this->username . ">\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            echo "    [SMTP] SENDING: RCPT TO\n";
            fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            echo "    [SMTP] SENDING: DATA\n";
            fwrite($socket, "DATA\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 354) return false;

            fwrite($socket, "To: $to\r\nSubject: $subject\r\n$header\r\n\r\n$message\r\n.\r\n");
            $resp = $this->getResponse($socket);
            if ($this->getResponseCode($resp) !== 250) return false;

            echo "    [SMTP] SENDING: QUIT\n";
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
            echo "    [SMTP] " . trim($str) . "\n"; // DEBUG LOG
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
