<?php
/**
 * Database Cleanup Script
 * 
 * This script clears all user-generated content from the database
 * while retaining admin accounts.
 */

// Include database configuration
require_once 'config/database.php';

// Start with a confirmation
echo "Starting database cleanup...\n";

try {
    // Helper function to check if a table exists
    function table_exists($conn, $table) {
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        return $result->num_rows > 0;
    }
    
    // 1. Delete all comments
    if (table_exists($conn, 'comments')) {
        $conn->query("DELETE FROM comments");
        echo "✓ Comments deleted\n";
    } else {
        echo "! Comments table not found, skipping\n";
    }
    
    // 2. Delete all reactions (likes/dislikes) if the table exists
    if (table_exists($conn, 'reactions')) {
        $conn->query("DELETE FROM reactions");
        echo "✓ Reactions deleted\n";
    } else {
        echo "! Reactions table not found, skipping\n";
    }
    
    // 3. Delete all posts
    if (table_exists($conn, 'posts')) {
        $conn->query("DELETE FROM posts");
        echo "✓ Posts deleted\n";
    } else {
        echo "! Posts table not found, skipping\n";
    }
    
    // 4. Delete all regular users, keep admin accounts
    if (table_exists($conn, 'users')) {
        $delete_users = $conn->prepare("DELETE FROM users WHERE role != 'admin'");
        $delete_users->execute();
        $deleted_users = $delete_users->affected_rows;
        echo "✓ {$deleted_users} non-admin users deleted\n";
    } else {
        echo "! Users table not found, skipping\n";
    }
    
    // 5. Reset auto-increment values
    $tables = ['comments', 'posts', 'users'];
    foreach ($tables as $table) {
        if (table_exists($conn, $table)) {
            $conn->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            echo "✓ Reset auto-increment for {$table}\n";
        }
    }
    
    // 6. Create sample content (optional)
    // Add a sample post for the admin
    if (table_exists($conn, 'users') && table_exists($conn, 'posts') && table_exists($conn, 'categories')) {
        $admin_query = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        if ($admin_query->num_rows > 0) {
            $admin = $admin_query->fetch_assoc();
            $admin_id = $admin['user_id'];
            
            // Get a category ID
            $category_query = $conn->query("SELECT category_id FROM categories LIMIT 1");
            if ($category_query->num_rows > 0) {
                $category = $category_query->fetch_assoc();
                $category_id = $category['category_id'];
                
                // Add a sample post
                $current_time = date('Y-m-d H:i:s');
                $insert_post = $conn->prepare("INSERT INTO posts (title, content, excerpt, author_id, category_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $title = "Welcome to the Content Management System";
                $content = "<p>This is a sample post created during database cleanup.</p><p>Your CMS is now ready for deployment!</p>";
                $excerpt = "Your CMS is now ready for deployment!";
                $status = "published";
                
                $insert_post->bind_param("sssiisss", $title, $content, $excerpt, $admin_id, $category_id, $status, $current_time, $current_time);
                $insert_post->execute();
                echo "✓ Sample post created\n";
            } else {
                echo "! No categories found, skipping sample post creation\n";
            }
        } else {
            echo "! No admin users found, skipping sample post creation\n";
        }
    } else {
        echo "! Required tables for sample post creation not found, skipping\n";
    }
    
    echo "\nDatabase cleanup complete! The system is ready for deployment.\n";
    echo "Admin accounts have been preserved.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 