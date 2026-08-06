<?php

namespace App\Middleware;

class AuthMiddleware {
    /**
     * Check if user is logged in.
     */
    public static function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    /**
     * Check if user is a Super Admin.
     */
    public static function requireSuperAdmin() {
        self::requireLogin();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
            die("Unauthorized access.");
        }
    }
}
