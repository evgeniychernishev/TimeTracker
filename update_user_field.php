<?php
require_once 'config.php';

// Проверяем авторизацию и права доступа
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

// Проверяем входные данные
if (!isset($_POST['user_id']) || !isset($_POST['field']) || !isset($_POST['value'])) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit;
}

$user_id = (int)$_POST['user_id'];
$field = $_POST['field'];
$value = $_POST['value'];

// Проверяем, что поле является допустимым
$allowed_fields = ['payer', 'note'];
if (!in_array($field, $allowed_fields)) {
    echo json_encode(['success' => false, 'message' => 'Неверное поле']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE timetrack_users SET $field = ? WHERE id = ?");
    $stmt->execute([$value, $user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
} 