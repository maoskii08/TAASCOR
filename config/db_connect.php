<?php
require('mysql-config.php');
date_default_timezone_set('Asia/Manila');

try {

	$pdoConn = new PDO('mysql:host='.HOST.';dbname='.DATABASE.';charset=utf8mb4', USER, PASSWORD);
	$pdoConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
} catch(PDOException  $e) {
	error_log('Database connection failed: ' . $e->getMessage());
	http_response_code(500);
	echo "Database connection failed.";
}


?>
