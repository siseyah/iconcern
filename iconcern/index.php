<?php
/**
 * Main Entry Point - Redirects to appropriate dashboard based on user role
 */
require_once 'config/config.php';
requireLogin();

// Redirect based on user role
$role = getUserRole();

if ($role === 'student') {
    header('Location: student/dashboard.php');
    exit();
} elseif ($role === 'admin' || $role === 'staff') {
    header('Location: admin/dashboard.php');
    exit();
} else {
    // Unknown role, redirect to login
    header('Location: login.php');
    exit();
}

