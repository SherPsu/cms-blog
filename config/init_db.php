<?php
/**
 * Database Initialization Script
 * Run this once to set up the database and tables
 */

// Include database connection
$conn = require_once __DIR__ . '/database.php';

// Read schema file
$sql = file_get_contents(__DIR__ . '/schema.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "Database tables created successfully!\n";
    
    // Process all result sets
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    // Create default admin user
    if (!$conn->error) {
        // Check if admin user already exists
        $check = $conn->query("SELECT user_id FROM users WHERE username = 'admin'");
        
        if ($check->num_rows == 0) {
            // Get current timestamp
            $now = date('Y-m-d H:i:s');
            
            // Create default admin user with password 'admin123'
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $role = 'admin';
            $stmt->bind_param("ssssss", $username, $email, $password, $role, $now, $now);
            
            $username = 'admin';
            $email = 'admin@example.com';
            
            if ($stmt->execute()) {
                echo "Default admin user created:\n";
                echo "Username: admin\n";
                echo "Password: admin123\n";
                echo "Email: admin@example.com\n";
            } else {
                echo "Error creating admin user: " . $stmt->error . "\n";
            }
            
            // Create default categories
            $categories = ['General', 'Technology', 'Travel', 'Food', 'Health'];
            
            foreach ($categories as $category) {
                $stmt = $conn->prepare("INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)");
                $description = "Posts about " . $category;
                $stmt->bind_param("ssss", $category, $description, $now, $now);
                
                if ($stmt->execute()) {
                    echo "Category created: " . $category . "\n";
                } else {
                    echo "Error creating category: " . $stmt->error . "\n";
                }
            }
        } else {
            echo "Admin user already exists.\n";
        }
        
        echo "\nDatabase initialization complete.\n";
    } else {
        echo "Error executing SQL: " . $conn->error . "\n";
    }
} else {
    echo "Error creating database tables: " . $conn->error . "\n";
}

// Close connection
$conn->close();
?> 