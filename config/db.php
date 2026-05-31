<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            

define('DB_NAME', 'jeem_mall');

define('BASE_URL', '/jeem mall');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    

    error_log('[JEEM MALL DB ERROR] ' . $e->getMessage());

    

    http_response_code(503);
    die('⚠️  Database connection failed. Please contact the administrator.');
}
