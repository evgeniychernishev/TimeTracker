<?php
require_once 'config.php';

// Check if user has permission
if (!isAdmin() && !isManager()) {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['selected_days'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

$selectedDays = json_decode($_POST['selected_days'], true);
$startTime = isset($_POST['start_time']) ? $_POST['start_time'] : null;
$endTime = isset($_POST['end_time']) ? $_POST['end_time'] : null;
$isHoliday = isset($_POST['is_holiday']) ? 1 : 0;
$excludeHours = isset($_POST['exclude_hours']) ? (float)$_POST['exclude_hours'] : 0;
$description = isset($_POST['description']) ? $_POST['description'] : '';
$applyToAll = isset($_POST['apply_to_all']) ? 1 : 0;

try {
    $pdo->beginTransaction();
    
    foreach ($selectedDays as $day) {
        $user_id = (int)$day['user_id'];
        $date = $day['date'];
        
        // Check if entry exists
        $stmt = $pdo->prepare("SELECT id FROM timetrack_time_entries WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing entry
            $updateFields = [];
            $params = [];
            
            if ($startTime !== null) {
                $updateFields[] = "start_time = ?";
                $params[] = $startTime;
            }
            
            if ($endTime !== null) {
                $updateFields[] = "end_time = ?";
                $params[] = $endTime;
            }
            
            $updateFields[] = "is_holiday = ?";
            $params[] = $isHoliday;
            
            $updateFields[] = "exclude_hours = ?";
            $params[] = $excludeHours;
            
            if ($description !== '') {
                $updateFields[] = "description = ?";
                $params[] = $description;
            }
            
            $params[] = $existing['id'];
            
            $stmt = $pdo->prepare("
                UPDATE timetrack_time_entries 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
        } else {
            // Insert new entry
            $stmt = $pdo->prepare("
                INSERT INTO timetrack_time_entries (user_id, date, start_time, end_time, is_holiday, exclude_hours, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $date, $startTime, $endTime, $isHoliday, $excludeHours, $description]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
} 