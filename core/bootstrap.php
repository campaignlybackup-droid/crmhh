<?php
/**
 * Application bootstrap: session, error handling, config, class loading.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors/paths to the browser

require __DIR__ . '/Helpers.php';

// Secure session configuration
$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $cookieSecure,
]);
ini_set('session.use_strict_mode', '1');
session_name('agency_crm_sid');
session_start();

$configFile = __DIR__ . '/../config/config.php';
$isInstaller = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php';

if (!file_exists($configFile) && !$isInstaller) {
    redirect('install.php');
}

require __DIR__ . '/Database.php';
require __DIR__ . '/Csrf.php';
require __DIR__ . '/Flash.php';
require __DIR__ . '/Validator.php';
require __DIR__ . '/AuditLog.php';
require __DIR__ . '/Notifier.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Permission.php';
require __DIR__ . '/View.php';
require __DIR__ . '/CsvImporter.php';

if (file_exists($configFile)) {
    date_default_timezone_set(config('app')['timezone'] ?? 'Asia/Kolkata');

    if ((bool)(config('app')['debug'] ?? false)) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    }

    set_exception_handler(function (Throwable $e) {
        app_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        fatal_error('An unexpected error occurred. The technical team has been notified.');
    });

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        app_log("PHP error [$severity]: $message in $file:$line");
        return true; // suppress default handler (prevents leaking paths)
    });

    require __DIR__ . '/../models/UserModel.php';
    require __DIR__ . '/../models/RoleModel.php';
    require __DIR__ . '/../models/TeamModel.php';
    require __DIR__ . '/../models/LeadModel.php';
    require __DIR__ . '/../models/ClientModel.php';
    require __DIR__ . '/../models/ServiceModel.php';
    require __DIR__ . '/../models/TaskModel.php';
    require __DIR__ . '/../models/ActivityModel.php';
    require __DIR__ . '/../models/CalendarModel.php';
    require __DIR__ . '/../models/ReportModel.php';
    require __DIR__ . '/../models/LeaveModel.php';
}

function csrf_check_or_die(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::verifyOrFail();
    }
}
