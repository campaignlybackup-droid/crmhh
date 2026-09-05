<?php

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $sent = $_POST['_csrf'] ?? '';
        $stored = $_SESSION['_csrf'] ?? '';
        return $sent !== '' && $stored !== '' && hash_equals($stored, $sent);
    }

    public static function verifyOrFail(): void
    {
        if (!self::verify()) {
            http_response_code(419);
            fatal_error('Your session has expired or the request could not be verified. Please go back and try again.');
        }
    }
}
