<?php
require_once 'config.php';

// Check if user has permission
if (!isAdmin() && !isManager()) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

// Check if required parameters are provided
if (!isset($_GET['user_id']) || !isset($_GET['date'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

$user_id = (int)$_GET['user_id'];
$date = $_GET['date'];

try {
    // Get the time entry
    $stmt = $pdo->prepare("
        SELECT * FROM timetrack_time_entries 
        WHERE user_id = ? AND date = ?
    ");
    $stmt->execute([$user_id, $date]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry) {
        echo json_encode(['success' => true, 'entry' => $entry]);
    } else {
        echo json_encode(['success' => true, 'entry' => null]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
} 