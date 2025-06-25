<?php
require_once 'config.php';

// Check if user has permission to manage groups
if (!isAdmin() && !isManager()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$members = isset($_POST['members']) ? json_decode($_POST['members'], true) : [];

if (!$group_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Group ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Remove all current members
    $stmt = $pdo->prepare("DELETE FROM timetrack_user_groups WHERE group_id = ?");
    $stmt->execute([$group_id]);
    
    // Add new members
    if (!empty($members)) {
        $values = [];
        $params = [];
        foreach ($members as $user_id) {
            $values[] = "(?, ?)";
            $params[] = $group_id;
            $params[] = $user_id;
        }
        
        $sql = "INSERT INTO timetrack_user_groups (group_id, user_id) VALUES " . implode(',', $values);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update group members']);
} 