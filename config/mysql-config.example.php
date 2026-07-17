<?php
// Copy to mysql-config.php for local use. The real file is ignored by Git.
define("HOST", getenv("DB_HOST") ?: "127.0.0.1:3306");
define("USER", getenv("DB_USERNAME") ?: "root");
define("PASSWORD", getenv("DB_PASSWORD") ?: "");
define("DATABASE", getenv("DB_DATABASE") ?: "taascor_hris");
?>
