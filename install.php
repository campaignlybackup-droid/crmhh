<?php
/**
 * Agency CRM — Web Installer
 *
 * Run this once after uploading the application to Hostinger (or any
 * Apache + PHP + MySQL shared hosting). It writes config/config.php,
 * imports the database schema, and creates the first Founder account.
 *
 * For security, this script refuses to run once installation is marked
 * complete. Delete install.php from the server after installing.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

$configPath = __DIR__ . '/config/config.php';
$lockPath = __DIR__ . '/config/installed.lock';
$alreadyInstalled = file_exists($lockPath);

$errors = [];
$step = $_POST['step'] ?? (file_exists($configPath) ? 2 : 1);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function test_db_connection($host, $name, $user, $pass)
{
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return [true, $pdo];
    } catch (PDOException $e) {
        return [false, $e->getMessage()];
    }
}

if ($alreadyInstalled) {
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Already Installed</title>
    <link rel="stylesheet" href="assets/css/style.css"></head><body>
    <div class="login-wrap"><div class="login-card" style="max-width:480px">
        <h1>Already Installed</h1>
        <p class="text-muted">Agency CRM has already been installed on this server. For security, please delete <code>install.php</code> from your server.</p>
        <a class="btn btn-primary" href="index.php" style="width:100%;text-align:center">Go to Login</a>
    </div></div>
    </body></html>
    <?php
    exit;
}

if (empty($_SESSION['_install_csrf'])) {
    $_SESSION['_install_csrf'] = bin2hex(random_bytes(32));
}
$installCsrf = $_SESSION['_install_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['_csrf']) || !hash_equals($installCsrf, $_POST['_csrf'])) {
        $errors[] = 'Your session expired. Please try again.';
        $step = 1;
    } elseif ($step == 1) {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = (string)($_POST['db_pass'] ?? '');
        $appName = trim($_POST['app_name'] ?? 'Agency CRM');
        $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
        $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');

        if ($host === '' || $name === '' || $user === '') {
            $errors[] = 'Please fill in all required database fields.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $errors[] = 'Database name may only contain letters, numbers, and underscores.';
        } else {
            [$ok, $result] = test_db_connection($host, $name, $user, $pass);
            if (!$ok) {
                $errors[] = 'Could not connect to MySQL: ' . $result;
            } else {
                /** @var PDO $pdo */
                $pdo = $result;
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$name`");

                    $existingTables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
                    if (empty($existingTables)) {
                        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
                        // Execute statement by statement (PDO can't run multi-statement scripts reliably on all setups)
                        foreach (array_filter(array_map('trim', explode(";\n", preg_replace('/--.*$/m', '', $sql)))) as $statement) {
                            if ($statement === '') continue;
                            $pdo->exec($statement);
                        }
                    }

                    $secret = bin2hex(random_bytes(32));
                    $configContent = "<?php\n\nreturn [\n" .
                        "    'db' => [\n" .
                        "        'host'    => " . var_export($host, true) . ",\n" .
                        "        'name'    => " . var_export($name, true) . ",\n" .
                        "        'user'    => " . var_export($user, true) . ",\n" .
                        "        'pass'    => " . var_export($pass, true) . ",\n" .
                        "        'charset' => 'utf8mb4',\n" .
                        "    ],\n" .
                        "    'app' => [\n" .
                        "        'name'     => " . var_export($appName ?: 'Agency CRM', true) . ",\n" .
                        "        'url'      => " . var_export($appUrl, true) . ",\n" .
                        "        'timezone' => " . var_export($timezone ?: 'Asia/Kolkata', true) . ",\n" .
                        "        'secret'   => " . var_export($secret, true) . ",\n" .
                        "        'debug'    => false,\n" .
                        "    ],\n" .
                        "];\n";

                    if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
                    if (file_put_contents($configPath, $configContent) === false) {
                        $errors[] = 'Could not write config/config.php. Please check folder permissions (755) and try again.';
                    } else {
                        $step = 2;
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Database setup failed: ' . $e->getMessage();
                }
            }
        }
    }

    if ($step == 2 && empty($errors)) {
        require $configPath;
        $config = require $configPath;
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            try {
                $cfg = $config['db'];
                $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'A user with this email already exists.';
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare(
                        'INSERT INTO users (employee_code, name, email, password_hash, is_founder, status, created_at) VALUES (?,?,?,?,1,"active",NOW())'
                    )->execute(['EMP-0001', $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $founderId = (int)$pdo->lastInsertId();
                    $roleId = (int)$pdo->query("SELECT id FROM roles WHERE slug='founder'")->fetchColumn();
                    if ($roleId) {
                        $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$founderId, $roleId]);
                    }
                    $pdo->prepare("UPDATE app_settings SET `value` = '1' WHERE `key` = 'installed'")->execute();
                    $pdo->commit();

                    file_put_contents($lockPath, 'installed on ' . date('c'));
                    $step = 3;
                }
            } catch (Throwable $e) {
                $errors[] = 'Could not create the Founder account: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install &middot; Agency CRM</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
<div class="login-card" style="max-width:520px">
    <h1>Agency CRM Setup</h1>
    <div class="sub">Step <?= (int)$step ?> of 3</div>

    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

    <?php if ($step == 1): ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h($installCsrf) ?>">
            <input type="hidden" name="step" value="1">
            <h3>Database Connection</h3>
            <div class="form-group"><label>Database Host</label><input type="text" name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
            <div class="form-group"><label>Database Name</label><input type="text" name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Database User</label><input type="text" name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></div>
            <div class="form-group"><label>Database Password</label><input type="password" name="db_pass" value="<?= h($_POST['db_pass'] ?? '') ?>"></div>
            <hr>
            <h3>Application Settings</h3>
            <div class="form-group"><label>Application Name</label><input type="text" name="app_name" value="<?= h($_POST['app_name'] ?? 'Agency CRM') ?>"></div>
            <div class="form-group"><label>Application URL</label><input type="text" name="app_url" value="<?= h($_POST['app_url'] ?? '') ?>" placeholder="https://yourdomain.com/crm"></div>
            <div class="form-group"><label>Timezone</label><input type="text" name="timezone" value="<?= h($_POST['timezone'] ?? 'Asia/Kolkata') ?>"></div>
            <button class="btn btn-primary" style="width:100%">Test Connection &amp; Install Database</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= h($installCsrf) ?>">
            <input type="hidden" name="step" value="2">
            <h3>Create Founder Account</h3>
            <p class="text-muted small">This account has complete control over the CRM.</p>
            <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="8"></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="8"></div>
            <button class="btn btn-primary" style="width:100%">Create Founder Account</button>
        </form>
    <?php else: ?>
        <h3>Installation Complete</h3>
        <p>Your Agency CRM is ready. Please log in with the Founder account you just created.</p>
        <div class="alert alert-warning">For security, delete <code>install.php</code> from your server now.</div>
        <a class="btn btn-primary" href="index.php" style="width:100%;text-align:center;display:block">Go to Login</a>
    <?php endif; ?>
</div>
</div>
</body>
</html>
