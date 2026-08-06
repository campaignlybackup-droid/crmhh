<?php

namespace App\Security;

class CSRF {
    /**
     * Generate a CSRF token.
     */
    public static function generateToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Get the CSRF token input field.
     */
    public static function getInputField(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Verify the CSRF token.
     */
    public static function verifyToken($token): bool {
        if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }
        return false;
    }
    
    /**
     * Abort if CSRF token is invalid.
     */
    public static function enforce() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!self::verifyToken($token)) {
                die("Invalid CSRF token.");
            }
        }
    }
}
