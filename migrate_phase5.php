<?php
require_once 'config.php';
require_once __DIR__ . '/autoload.php';

echo "<h1>Migration Phase 5 Output</h1>";

try {
    // 1. Run SQL
    $sql = file_get_contents('migration_phase5.sql');
    $pdo->exec($sql);
    echo "<p style='color: green;'>Schema updated.</p>";

    // 2. Remap old projects
    $projects = $pdo->query("SELECT id, status FROM projects")->fetchAll();
    
    $statusMap = [
        'Briefing' => 'Creative Brief',
        'Pre-Production' => 'Pre Production',
        'Shoot' => 'Production',
        'Post' => 'Editing',
        'Review' => 'Internal Review',
        'Delivered' => 'Delivery'
    ];

    $allStages = [
        'Onboarding', 'Creative Brief', 'Reference / Moodboard', 'Concept Approval', 
        'Pre Production', 'Production', 'Editing', 'Internal Review', 
        'Client Approval', 'Delivery', 'Case Study', 'Archive'
    ];

    foreach ($projects as $p) {
        $newStatus = $statusMap[$p['status']] ?? $p['status'];
        
        // Update project status if it changed
        if ($newStatus !== $p['status']) {
            $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $p['id']]);
        }

        // Check if stages exist
        $stageCount = $pdo->prepare("SELECT COUNT(*) FROM project_stages WHERE project_id = ?");
        $stageCount->execute([$p['id']]);
        
        if ($stageCount->fetchColumn() == 0) {
            // Seed stages
            $stageStatus = 'Approved'; // All stages BEFORE current are approved
            $insertStage = $pdo->prepare("INSERT INTO project_stages (project_id, stage_name, status) VALUES (?, ?, ?)");
            
            foreach ($allStages as $stageName) {
                if ($stageName === $newStatus) {
                    $stageStatus = 'In Progress';
                }
                
                $insertStage->execute([$p['id'], $stageName, $stageStatus]);
                
                if ($stageName === $newStatus) {
                    $stageStatus = 'Pending'; // All stages AFTER current are pending
                }
            }
        }
    }

    echo "<h2 style='color: green;'>Migration Phase 5 Completed Successfully!</h2>";
    echo "<p>All projects have been upgraded to the 12-stage workflow engine.</p>";
    echo "<a href='dashboard.php'>Return to Dashboard</a>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Critical Failure!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
