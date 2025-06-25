<?php
require_once 'config.php';

// Check if user has permission to view groups
if (!isAdmin() && !isManager()) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$current_members = isset($_GET['current_members']);

if ($current_members) {
    // Get current members for the group
    $stmt = $pdo->prepare("SELECT user_id FROM TimeTrack_user_groups WHERE group_id = ?");
    $stmt->execute([$group_id]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['current_members' => $members]);
} else {
    // Get all members with their roles
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, r.name as role
        FROM TimeTrack_users u
        JOIN TimeTrack_user_groups ug ON u.id = ug.user_id
        LEFT JOIN TimeTrack_roles r ON u.role_id = r.id
        WHERE ug.group_id = ?
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$group_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($members);
} 