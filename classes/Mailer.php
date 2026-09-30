<?php

class Mailer {
    private static $lastError = '';

    public static function getLastError() {
        return self::$lastError;
    }

    public static function send($toEmail, $toName, $subject, $bodyText) {
        self::$lastError = '';
        $toEmail = trim((string)$toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'Invalid recipient email address.';
            return false;
        }

        if (!SMTP_ENABLED) {
            self::$lastError = 'SMTP is disabled.';
            return false;
        }

        if (SMTP_HOST === '' || SMTP_USERNAME === '') {
            self::$lastError = 'SMTP host or username is not configured.';
            return false;
        }

        try {
            return self::sendViaSmtp($toEmail, $toName, $subject, $bodyText);
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Mailer exception: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendViaSmtp($toEmail, $toName, $subject, $bodyText) {
        $host = SMTP_HOST;
        $port = (int)SMTP_PORT;
        $encryption = strtolower((string)SMTP_ENCRYPTION);
        $remote = $encryption === 'ssl'
            ? 'ssl://' . $host . ':' . $port
            : $host . ':' . $port;

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            return self::fail("Could not connect to {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, 20);

        if (!self::expect($socket, [220], $response)) {
            fclose($socket);
            return self::fail('SMTP greeting failed: ' . $response);
        }

        $ehloHost = 'localhost';
        self::command($socket, 'EHLO ' . $ehloHost);
        if (!self::expect($socket, [250], $response)) {
            fclose($socket);
            return self::fail('EHLO failed: ' . $response);
        }

        if ($encryption === 'tls') {
            self::command($socket, 'STARTTLS');
            if (!self::expect($socket, [220], $response)) {
                fclose($socket);
                return self::fail('STARTTLS failed: ' . $response);
            }
            if (!self::enableTls($socket)) {
                fclose($socket);
                return self::fail('Could not enable TLS encryption.');
            }
            self::command($socket, 'EHLO ' . $ehloHost);
            if (!self::expect($socket, [250], $response)) {
                fclose($socket);
                return self::fail('EHLO after STARTTLS failed: ' . $response);
            }
        }

        self::command($socket, 'AUTH LOGIN');
        if (!self::expect($socket, [334], $response)) {
            fclose($socket);
            return self::fail('AUTH LOGIN failed: ' . $response);
        }

        self::command($socket, base64_encode(SMTP_USERNAME));
        if (!self::expect($socket, [334], $response)) {
            fclose($socket);
            return self::fail('SMTP username rejected: ' . $response);
        }

        self::command($socket, base64_encode(SMTP_PASSWORD));
        if (!self::expect($socket, [235], $response)) {
            fclose($socket);
            return self::fail('SMTP password rejected. Check app password in config/smtp.php.');
        }

        $from = SMTP_FROM_EMAIL;
        self::command($socket, 'MAIL FROM:<' . $from . '>');
        if (!self::expect($socket, [250], $response)) {
            fclose($socket);
            return self::fail('MAIL FROM failed: ' . $response);
        }

        self::command($socket, 'RCPT TO:<' . $toEmail . '>');
        if (!self::expect($socket, [250, 251], $response)) {
            fclose($socket);
            return self::fail('RCPT TO failed for ' . $toEmail . ': ' . $response);
        }

        self::command($socket, 'DATA');
        if (!self::expect($socket, [354], $response)) {
            fclose($socket);
            return self::fail('DATA command failed: ' . $response);
        }

        $safeSubject = self::encodeHeader($subject);
        $safeToName = self::encodeHeader($toName !== '' ? $toName : $toEmail);
        $fromName = self::encodeHeader(SMTP_FROM_NAME);
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . preg_replace('/[^a-z0-9.-]/i', '', $host) . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $fromName . ' <' . $from . '>',
            'To: ' . $safeToName . ' <' . $toEmail . '>',
            'Subject: ' . $safeSubject,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: OSWD-Complaint-System',
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($bodyText);
        fwrite($socket, $payload . "\r\n.\r\n");

        if (!self::expect($socket, [250], $response)) {
            fclose($socket);
            return self::fail('Message rejected: ' . $response);
        }

        self::command($socket, 'QUIT');
        fclose($socket);
        return true;
    }

    private static function enableTls($socket) {
        $methods = [];
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $methods[] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $methods[] = STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        $methods[] = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        foreach ($methods as $method) {
            if (@stream_socket_enable_crypto($socket, true, $method)) {
                return true;
            }
        }

        return false;
    }

    private static function fail($message) {
        self::$lastError = $message;
        error_log('Mailer: ' . $message);
        return false;
    }

    private static function command($socket, $line) {
        fwrite($socket, $line . "\r\n");
    }

    private static function expect($socket, array $codes, &$response = '') {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $response = trim($response);
        $code = (int)substr($response, 0, 3);
        return in_array($code, $codes, true);
    }

    private static function dotStuff($text) {
        $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);
        return implode("\r\n", $lines);
    }

    private static function encodeHeader($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
