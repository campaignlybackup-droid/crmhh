<?php
require 'core/App.php';
require 'core/Database.php';
require 'core/Auth.php';
require 'core/Session.php';
require 'core/Validator.php';
require 'models/LeadModel.php';

Session::init();
$_SESSION['user_id'] = 1;
$_SESSION['role_slug'] = 'founder';

$lead = Database::one('SELECT * FROM leads LIMIT 1');
if (!$lead) die("No leads found to test.\n");

$id = $lead['id'];
echo "Testing update on lead ID $id...\n";

$json = [
    'id' => $id,
    'name' => 'Test Name ' . time(),
    'phone' => '1234567890',
    'email' => 'test' . time() . '@test.com',
    'company' => 'Test Co',
    'source' => 'Website',
    'status_id' => $lead['status_id'],
    'assigned_user_id' => $lead['assigned_user_id'],
    'folder_id' => $lead['folder_id']
];

try {
    LeadModel::update($id, [
        'name' => trim($json['name']), 'phone' => trim($json['phone'] ?? ''), 'email' => trim($json['email'] ?? ''),
        'company' => trim($json['company'] ?? ''), 'source' => trim($json['source'] ?? ''),
        'status_id' => $json['status_id'] ?? null, 'next_followup_date' => $json['next_followup_date'] ?? null,
        'next_step' => $json['next_step'] ?? null, 'notes' => $json['notes'] ?? null,
        'assigned_user_id' => $json['assigned_user_id'] ?? null,
        'folder_id' => !empty($json['folder_id']) ? $json['folder_id'] : null
    ]);
    
    $updated = LeadModel::find($id);
    if ($updated['name'] === $json['name']) {
        echo "SUCCESS: Lead updated in database.\n";
    } else {
        echo "FAILURE: Lead name not updated in database. Expected: {$json['name']}, Got: {$updated['name']}\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
