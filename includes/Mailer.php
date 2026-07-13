<?php

class Mailer
{
    private static function getConfig(): array
    {
        return [
            'host' => getenv('SMTP_HOST') ?: '',
            'port' => (int)(getenv('SMTP_PORT') ?: 587),
            'user' => getenv('SMTP_USER') ?: '',
            'pass' => getenv('SMTP_PASS') ?: '',
            'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'no-reply@wabot.app',
            'from_name' => getenv('SMTP_FROM_NAME') ?: 'Wabot',
        ];
    }

    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $cfg = self::getConfig();
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $cfg['from_name'] . " <" . $cfg['from_email'] . ">\r\n";

        if ($cfg['host'] !== '' && $cfg['user'] !== '') {
            return self::sendSmtp($to, $subject, $htmlBody, $cfg);
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $encodedSubject, $htmlBody, $headers);
    }

    private static function sendSmtp(string $to, string $subject, string $htmlBody, array $cfg): bool
    {
        $errno = 0;
        $errstr = '';
        $timeout = 10;

        $remote = $cfg['host'] . ':' . $cfg['port'];

        if ($cfg['port'] == 465) {
            $remote = 'ssl://' . $remote;
        }

        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);
        if (!$socket) return false;

        stream_set_timeout($socket, $timeout);

        $read = function($socket) {
            $line = '';
            $start = time();
            while ($line === '' || substr($line, 3, 1) === '-') {
                if (time() - $start > 10) break;
                $line = @fgets($socket, 512);
                if ($line === false) break;
            }
            return $line;
        };

        $cmd = function($socket, $command) use ($read) {
            @fwrite($socket, $command . "\r\n");
            return $read($socket);
        };

        $resp = $read($socket);
        if (substr($resp, 0, 3) !== '220') return false;

        $resp = $cmd($socket, 'EHLO wabot');
        if (substr($resp, 0, 3) !== '250') return false;

        if ($cfg['port'] != 465 && stripos($resp, 'STARTTLS') !== false) {
            $resp = $cmd($socket, 'STARTTLS');
            if (substr($resp, 0, 3) !== '220') return false;
            @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $resp = $cmd($socket, 'EHLO wabot');
            if (substr($resp, 0, 3) !== '250') return false;
        }

        $resp = $cmd($socket, 'AUTH LOGIN');
        if (substr($resp, 0, 3) !== '334') return false;

        $resp = $cmd($socket, base64_encode($cfg['user']));
        if (substr($resp, 0, 3) !== '334') return false;

        $resp = $cmd($socket, base64_encode($cfg['pass']));
        if (substr($resp, 0, 3) !== '235') return false;

        $resp = $cmd($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>');
        if (substr($resp, 0, 3) !== '250') return false;

        $resp = $cmd($socket, 'RCPT TO:<' . $to . '>');
        if (substr($resp, 0, 3) !== '250') return false;

        $resp = $cmd($socket, 'DATA');
        if (substr($resp, 0, 3) !== '354') return false;

        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $cfg['from_name'] . " <" . $cfg['from_email'] . ">\r\n";
        $headers .= "To: " . $to . "\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "\r\n";

        @fwrite($socket, $headers . $htmlBody . "\r\n.\r\n");
        $read($socket);
        $cmd($socket, 'QUIT');

        @fclose($socket);
        return true;
    }
}
