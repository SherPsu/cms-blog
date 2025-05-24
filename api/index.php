<?php
/**
 * API Router
 * 
 * This file handles all API requests and routes them to the appropriate endpoint
 */

// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-API-TOKEN');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
session_start();

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Make sure DB_HOST is defined for the included API files
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

// Extract endpoint from URI
$endpoint = '';
if (preg_match('/\/api\/([^\/\?]+)/', $request_uri, $matches)) {
    $endpoint = $matches[1];
}

// Route requests to appropriate endpoint
switch ($endpoint) {
    case 'posts':
        require_once 'posts.php';
        break;
    
    case 'categories':
        require_once 'categories.php';
        break;
    
    case 'comments':
        require_once 'comments.php';
        break;
    
    case 'users':
        require_once 'users.php';
        break;
    
    case 'tags':
        require_once 'tags.php';
        break;
    
    case 'reactions':
        require_once 'reactions.php';
        break;
    
    case 'auth':
        require_once 'auth.php';
        break;
    
    default:
        // Invalid endpoint
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found'
        ]);
        break;
}

// Close database connection
$conn->close();
?> 