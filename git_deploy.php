<?php
// Simple script to force a Git pull via PHP and show the exact error
echo "<h1>Git Deployment Log</h1>";
echo "<pre style='background: #111; color: #0f0; padding: 20px; border-radius: 5px;'>";

$output = [];
$output[] = "Fetching from origin...";
$output[] = shell_exec('git fetch origin main 2>&1');

$output[] = "\nResetting local changes...";
$output[] = shell_exec('git reset --hard origin/main 2>&1');

$output[] = "\nPulling latest code...";
$output[] = shell_exec('git pull origin main 2>&1');

$output[] = "\nGit Status:";
$output[] = shell_exec('git status 2>&1');

echo htmlspecialchars(implode("\n", $output));
echo "</pre>";
echo "<h3>If you see 'Already up to date', the deployment was successful!</h3>";
?>
