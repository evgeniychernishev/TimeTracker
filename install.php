<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'timetrack';

try {
    // Create connection
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $database";
    $conn->exec($sql);
    echo "Database created successfully<br>";

    // Select database
    $conn->exec("USE $database");

    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS timetrack_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        stage VARCHAR(50),
        description TEXT,
        role ENUM('admin', 'manager', 'employee') NOT NULL,
        hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Users table created successfully<br>";

    // Create groups table
    $sql = "CREATE TABLE IF NOT EXISTS timetrack_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES timetrack_groups(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);
    echo "Groups table created successfully<br>";

    // Create user_groups table (many-to-many relationship)
    $sql = "CREATE TABLE IF NOT EXISTS timetrack_user_groups (
        user_id INT NOT NULL,
        group_id INT NOT NULL,
        PRIMARY KEY (user_id, group_id),
        FOREIGN KEY (user_id) REFERENCES timetrack_users(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES timetrack_groups(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "User groups table created successfully<br>";

    // Create time_entries table
    $sql = "CREATE TABLE IF NOT EXISTS timetrack_time_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES timetrack_users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "Time entries table created successfully<br>";

    // Create view for calendar
    $sql = "CREATE OR REPLACE VIEW timetrack_calendar_view AS
        SELECT 
            te.date,
            u.id as user_id,
            u.first_name,
            u.last_name,
            u.role,
            te.start_time,
            te.end_time,
            TIMESTAMPDIFF(HOUR, te.start_time, te.end_time) as hours_worked
        FROM timetrack_time_entries te
        JOIN timetrack_users u ON te.user_id = u.id";
    $conn->exec($sql);
    echo "Calendar view created successfully<br>";

    // Insert test data
    // Insert admin user
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO timetrack_users (login, password, first_name, last_name, role, hourly_rate) 
            VALUES ('admin', '$password', 'Admin', 'User', 'admin', 50.00)";
    $conn->exec($sql);

    // Insert test manager
    $password = password_hash('manager123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO timetrack_users (login, password, first_name, last_name, role, hourly_rate) 
            VALUES ('manager', '$password', 'Manager', 'User', 'manager', 40.00)";
    $conn->exec($sql);

    // Insert test employee
    $password = password_hash('employee123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO timetrack_users (login, password, first_name, last_name, role, hourly_rate) 
            VALUES ('employee', '$password', 'Employee', 'User', 'employee', 30.00)";
    $conn->exec($sql);

    // Insert test group
    $sql = "INSERT INTO timetrack_groups (name, description) VALUES ('Development Team', 'Software development team')";
    $conn->exec($sql);

    // Add users to group
    $sql = "INSERT INTO timetrack_user_groups (user_id, group_id) VALUES (1, 1), (2, 1), (3, 1)";
    $conn->exec($sql);

    // Insert test time entries
    $sql = "INSERT INTO timetrack_time_entries (user_id, date, start_time, end_time) 
            VALUES 
            (3, CURDATE(), '09:00:00', '17:00:00'),
            (3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '17:00:00')";
    $conn->exec($sql);


$sql = "CREATE TABLE timetrack_user_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('one-time', 'recurring', 'permanent') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (user_id) REFERENCES timetrack_users(id) ON DELETE CASCADE
);
";
$conn->exec($sql);

    echo "Test data inserted successfully<br>";
    echo "Installation completed successfully!";


    $sql = "ALTER TABLE timetrack_time_entries ADD COLUMN is_holiday BOOLEAN DEFAULT FALSE";
    $pdo->exec($sql);
    echo "Database updated successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?> 