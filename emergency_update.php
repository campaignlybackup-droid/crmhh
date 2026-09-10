<?php
// Bypasses Hostinger's broken Git system and fetches files directly from GitHub.
echo "<h1>Emergency Code Updater</h1><pre>";

$files = [
    'controllers/proposals.php',
    'models/ProposalModel.php',
    'views/proposals/form.php',
    'controllers/dashboard.php'
];

$baseUrl = 'https://raw.githubusercontent.com/campaignlybackup-droid/crmhh/main/';

foreach ($files as $file) {
    echo "Downloading $file... ";
    $content = file_get_contents($baseUrl . $file);
    if ($content) {
        file_put_contents(__DIR__ . '/' . $file, $content);
        echo "<span style='color:green'>SUCCESS</span>\n";
    } else {
        echo "<span style='color:red'>FAILED</span>\n";
    }
}

echo "</pre><h2>Update Complete! You can now visit your proposals area.</h2>";
?>
