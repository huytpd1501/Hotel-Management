<?php
// Định nghĩa ROOT_PATH (chỉ cần chạy 1 lần cho toàn bộ project)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__)); 
    // ví dụ: C:/xampp/htdocs/hotel/hotel-api-php
}

// autoload Database
require_once ROOT_PATH . '/config/Database.php';
