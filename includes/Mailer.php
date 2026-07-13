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

        $socket = @stream_socket_client(
            $cfg['host'] . ':' . $cfg['port'],
            $errno,
            $errstr,
            15
        );
        if (!$socket) return false;

        $read = function($socket) {
            $line = '';
            while ($line === '' || substr($line, 3, 1) === '-') {
                $line = @fgets($socket, 512);
                if ($line === false) break;
            }
            return $line;
        };

        $cmd = function($socket, $command) use ($read) {
            @fwrite($socket, $command . "\r\n");
            return $read($socket);
        };

        $read($socket);
        $cmd($socket, 'EHLO wabot');
        $cmd($socket, 'STARTTLS');

        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        $cmd($socket, 'EHLO wabot');
        $cmd($socket, 'AUTH LOGIN');
        $cmd($socket, base64_encode($cfg['user']));
        $cmd($socket, base64_encode($cfg['pass']));
        $cmd($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>');
        $cmd($socket, 'RCPT TO:<' . $to . '>');
        $cmd($socket, 'DATA');

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
