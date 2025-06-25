<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'TimeTrack';

try {
    // Create connection
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Select database
    $conn->exec("USE $database");
/*
$password = password_hash('Pupkin', PASSWORD_DEFAULT);
    $sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
            VALUES ('Pupkin', '$password', 'Pupkin', ' ', 'employee', 2300.00)";
    $conn->exec($sql);
  
$password = password_hash('RyanGosling', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('RyanGosling', '$password', 'RyanGosling', ' ', 'employee', 2200.00)";
$conn->exec($sql);

$password = password_hash('Si', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Si', '$password', 'Si', ' ', 'employee', 2400.00)";
$conn->exec($sql);

$password = password_hash('point', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('point', '$password', 'point', ' ', 'employee', 2350.00)";
 $conn->exec($sql);
 */ 
$password = password_hash('Yuriy', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Yuriy', '$password', 'Yuriy', ' ', 'employee', 2350.00)";
  $conn->exec($sql);

$password = password_hash('Lowers', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Lowers', '$password', 'Lowers', ' ', 'employee', 2350.00)";
     $conn->exec($sql);
   
$password = password_hash('Zoro', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Zoro', '$password', 'Zoro', ' ', 'employee', 2350.00)";
 $conn->exec($sql);

$password = password_hash('Voronin', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Voronin', '$password', 'Voronin', ' ', 'employee', 2350.00)";
     $conn->exec($sql);
                     /*              
$password = password_hash('Beerman', PASSWORD_DEFAULT);
$sql = "INSERT INTO TimeTrack_users (login, password, first_name, last_name, role, hourly_rate) 
        VALUES ('Beerman', '$password', 'Beerman', ' ', 'employee', 2350.00)";
                       */                  
                                        
//Pupkin			RyanGosling			Si			Gosst			.			Yuriy	
//		Lowers			Zoro			Voronin			Beerman		
                                                                            
//$conn->exec($sql);
} catch(PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
    