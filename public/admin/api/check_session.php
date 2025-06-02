<?php
session_name('admin');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['logged_in' => false]);
    $_SESSION['login_expired'] = true; 
} else {
    echo json_encode(['logged_in' => true]);
}
