<?php
require_once 'config.php';

try {
    // Add is_holiday column to timetrack_time_entries table
    $sql = "ALTER TABLE timetrack_time_entries ADD COLUMN is_holiday BOOLEAN DEFAULT FALSE";
    $pdo->exec($sql);
    echo "Database updated successfully!";
} catch(PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?> 