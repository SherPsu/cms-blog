<?php
/**
 * Database Check Script
 * 
 * This script displays the current state of the database tables
 * to verify the cleanup was successful.
 */

// Include database configuration
require_once 'config/database.php';

echo "===== Database State =====\n\n";

// List of tables to check
$tables = ['users', 'posts', 'comments', 'categories'];

foreach ($tables as $table) {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($result->num_rows === 0) {
        echo "Table '{$table}' doesn't exist\n";
        continue;
    }
    
    // Get count of records
    $count_result = $conn->query("SELECT COUNT(*) as count FROM {$table}");
    $count = $count_result->fetch_assoc()['count'];
    
    echo "Table '{$table}': {$count} records\n";
    
    // Show specific details for important tables
    if ($table === 'users') {
        $users = $conn->query("SELECT user_id, username, email, role FROM users");
        echo "Users:\n";
        while ($user = $users->fetch_assoc()) {
            echo "  - ID {$user['user_id']}: {$user['username']} ({$user['email']}) - {$user['role']}\n";
        }
        echo "\n";
    } else if ($table === 'posts') {
        $posts = $conn->query("SELECT post_id, title, status FROM posts");
        echo "Posts:\n";
        while ($post = $posts->fetch_assoc()) {
            echo "  - ID {$post['post_id']}: {$post['title']} (Status: {$post['status']})\n";
        }
        echo "\n";
    } else if ($table === 'categories') {
        $categories = $conn->query("SELECT category_id, name FROM categories");
        echo "Categories:\n";
        while ($category = $categories->fetch_assoc()) {
            echo "  - ID {$category['category_id']}: {$category['name']}\n";
        }
        echo "\n";
    }
}

$conn->close();
echo "===== End of Database Check =====\n"; 