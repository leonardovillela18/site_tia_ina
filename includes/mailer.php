<?php
declare(strict_types=1);

final class SmtpMailer
{
    private $socket = null;

    public function send(string $to, string $subject, string $html): void
    {
        $this->connect();
        try {
            $this->hello();
            $this->auth();
            $this->sendMail($to, $subject, $html);
        } finally {
            $this->quit();
        }
    }

    private function connect(): void
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $useTls = true;
        if (SMTP_PORT === 465) {
            $host = 'ssl://' . $host;
        }

        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException('Falha ao conectar ao servidor SMTP: ' . $errstr);
        }
        stream_set_timeout($socket, 15);
        $this->socket = $socket;

        $this->readReply(220, 'Cumprimento inicial');

        if (SMTP_PORT !== 465 && $useTls) {
            $this->command('EHLO ' . parse_url(BASE_URL, PHP_URL_HOST), 250, 'EHLO');
            $this->command('STARTTLS', 220, 'STARTTLS');
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS');
            }
        }
    }

    private function hello(): void
    {
        $this->command('EHLO ' . parse_url(BASE_URL, PHP_URL_HOST), 250, 'EHLO');
    }

    private function auth(): void
    {
        if (SMTP_USER === '' || SMTP_PASS === '') {
            return;
        }
        $code = $this->commandAfter('AUTH LOGIN', [250, 334], 'AUTH LOGIN');
        if ($code === 250) {
            return;
        }
        $this->commandAfter(base64_encode(SMTP_USER), [334], 'usuário');
        $this->commandAfter(base64_encode(SMTP_PASS), [235], 'senha');
    }

    private function sendMail(string $to, string $subject, string $html): void
    {
        $this->command('MAIL FROM:<' . MAIL_FROM . '>', 250, 'MAIL FROM');
        $this->command('RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');

        $this->command('DATA', 354, 'DATA');

        $fromName = MAIL_FROM_NAME;
        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . MAIL_FROM . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.";
        $this->write($message);
        $this->readReply(250, 'envio do e-mail');
    }

    private function quit(): void
    {
        if ($this->socket === null) {
            return;
        }
        @fwrite($this->socket, "QUIT\r\n");
        @fclose($this->socket);
        $this->socket = null;
    }

    private function command(string $cmd, int|array $expected, string $label): int
    {
        $this->write($cmd);
        return $this->readReply($expected, $label);
    }

    private function commandAfter(string $cmd, int|array $expected, string $label): int
    {
        $this->write($cmd);
        return $this->readReply($expected, $label);
    }

    private function write(string $data): void
    {
        $result = @fwrite($this->socket, $data . "\r\n");
        if ($result === false) {
            throw new RuntimeException('Falha ao escrever no servidor SMTP');
        }
    }

    private function readReply(int|array $expected, string $label): int
    {
        $line = @fgets($this->socket, 4096);
        if ($line === false) {
            throw new RuntimeException('Servidor SMTP não respondeu (' . $label . '). Verifique as credenciais.');
        }
        $code = (int) substr($line, 0, 3);
        while (isset($line[3]) && $line[3] === '-') {
            $line = (string) @fgets($this->socket, 4096);
        }
        $expectedList = is_array($expected) ? $expected : [$expected];
        if (!in_array($code, $expectedList, true)) {
            throw new RuntimeException('SMTP: ' . $label . ' falhou (código ' . $code . '): ' . trim($line));
        }
        return $code;
    }
}

function send_recovery_email(string $email, string $link): void
{
    $subject = 'Recuperação de senha - ' . APP_NAME;
    $html = '<p>Olá!</p>'
        . '<p>Recebemos um pedido para recuperar a senha do painel administrativo de ' . APP_NAME . '.</p>'
        . '<p>Para definir uma nova senha, acesse o link abaixo (válido por ' . PASSWORD_RESET_TTL . ' minutos):</p>'
        . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
        . '<p>Se você não solicitou essa recuperação, pode ignorar este e-mail.</p>';
    (new SmtpMailer())->send($email, $subject, $html);
}