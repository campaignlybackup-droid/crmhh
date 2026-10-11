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
        $stored = $_SESSION['_csrf'] ?? '';
        if ($stored === '') {
            return false;
        }

        // Check POST and REQUEST parameters
        $sent = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_REQUEST['_csrf'] ?? $_REQUEST['csrf_token'] ?? '';

        // Check request headers
        if ($sent === '') {
            $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
        }

        // Check JSON payload
        if ($sent === '') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                if (!empty($raw)) {
                    $json = json_decode($raw, true);
                    if (is_array($json)) {
                        $sent = $json['_csrf'] ?? $json['csrf_token'] ?? '';
                    }
                }
            }
        }

        return $sent !== '' && hash_equals($stored, $sent);
    }

    public static function verifyOrFail(): void
    {
        if (!self::verify()) {
            http_response_code(419);
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
                || str_contains($_SERVER['REQUEST_URI'] ?? '', 'api');

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Session expired or CSRF token mismatch. Please refresh and try again.']);
                exit;
            }
            fatal_error('Your session has expired or the request could not be verified. Please go back and try again.');
        }
    }
}

