<?php
require_once 'config.php';

// Check if user has permission
if (!isAdmin() && !isManager()) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['user_id']) || !isset($_POST['date'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

$user_id = (int)$_POST['user_id'];
$date = $_POST['date'];
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : null;
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : null;
$is_holiday = isset($_POST['is_holiday']) ? 1 : 0;
$exclude_hours = isset($_POST['exclude_hours']) ? (float)$_POST['exclude_hours'] : 0;
$description = isset($_POST['description']) ? $_POST['description'] : '';

try {
    // Check if entry exists
    $stmt = $pdo->prepare("SELECT id FROM timetrack_time_entries WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $date]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing entry
        $stmt = $pdo->prepare("
            UPDATE timetrack_time_entries 
            SET start_time = ?, end_time = ?, is_holiday = ?, exclude_hours = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$start_time, $end_time, $is_holiday, $exclude_hours, $description, $existing['id']]);
    } else {
        // Insert new entry
        $stmt = $pdo->prepare("
            INSERT INTO timetrack_time_entries (user_id, date, start_time, end_time, is_holiday, exclude_hours, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $date, $start_time, $end_time, $is_holiday, $exclude_hours, $description]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
} 