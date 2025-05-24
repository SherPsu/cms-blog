<?php
/**
 * Logout Script
 */

// Start session
session_start();

// Include auth functionality
require_once 'includes/auth.php';

// Log user out
logout();

// Redirect to login page
header("Location: login.php");
exit;
?> 