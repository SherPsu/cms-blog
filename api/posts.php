<?php
/**
 * Posts API Endpoint
 * 
 * GET /api/posts - Get all posts
 * GET /api/posts?id=X - Get specific post
 * POST /api/posts - Create a new post
 * PUT /api/posts - Update an existing post
 * DELETE /api/posts?id=X - Delete a post
 */

// Check if this file is being accessed directly
if (!defined('DB_HOST')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Direct access not allowed'
    ]);
    exit;
}

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        // Get all posts or specific post
        handleGet();
        break;

    case 'POST':
        // Create a new post
        handlePost();
        break;

    case 'PUT':
        // Update existing post
        handlePut();
        break;

    case 'DELETE':
        // Delete a post
        handleDelete();
        break;

    default:
        // Method not allowed
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}

/**
 * Handle GET requests
 */
function handleGet() {
    global $conn;
    
    // Check if ID is provided
    if (isset($_GET['id'])) {
        // Get specific post
        $post_id = (int)$_GET['id'];
        
        $query = "SELECT p.*, u.username, c.name as category_name 
                 FROM posts p 
                 JOIN users u ON p.author_id = u.user_id 
                 JOIN categories c ON p.category_id = c.category_id 
                 WHERE p.post_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Post not found'
            ]);
            return;
        }
        
        $post = $result->fetch_assoc();
        
        // Get post tags
        $tags = [];
        $tags_query = "SELECT t.tag_id, t.name FROM tags t 
                      JOIN post_tags pt ON t.tag_id = pt.tag_id 
                      WHERE pt.post_id = ?";
        
        $tags_stmt = $conn->prepare($tags_query);
        $tags_stmt->bind_param("i", $post_id);
        $tags_stmt->execute();
        $tags_result = $tags_stmt->get_result();
        
        while ($tag = $tags_result->fetch_assoc()) {
            $tags[] = $tag;
        }
        
        $post['tags'] = $tags;
        
        // Return the post with tags
        echo json_encode([
            'success' => true,
            'data' => $post
        ]);
    } else {
        // Get all posts with optional filters
        $where_clauses = [];
        $params = [];
        $types = "";
        
        // Filter by status
        if (isset($_GET['status']) && in_array($_GET['status'], ['draft', 'published', 'archived'])) {
            $where_clauses[] = "p.status = ?";
            $params[] = $_GET['status'];
            $types .= "s";
        }
        
        // Filter by category
        if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
            $where_clauses[] = "p.category_id = ?";
            $params[] = (int)$_GET['category_id'];
            $types .= "i";
        }
        
        // Filter by author
        if (isset($_GET['author_id']) && is_numeric($_GET['author_id'])) {
            $where_clauses[] = "p.author_id = ?";
            $params[] = (int)$_GET['author_id'];
            $types .= "i";
        }
        
        // Build WHERE clause
        $where_sql = "";
        if (!empty($where_clauses)) {
            $where_sql = "WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $offset = ($page - 1) * $per_page;
        
        // Get posts
        $query = "SELECT p.*, u.username, c.name as category_name 
                 FROM posts p 
                 JOIN users u ON p.author_id = u.user_id 
                 JOIN categories c ON p.category_id = c.category_id 
                 $where_sql
                 ORDER BY p.created_at DESC
                 LIMIT ?, ?";
        
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $params[] = $offset;
        $params[] = $per_page;
        $types .= "ii";
        
        // Bind parameters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $posts = [];
        
        while ($post = $result->fetch_assoc()) {
            $posts[] = $post;
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM posts p $where_sql";
        $count_stmt = $conn->prepare($count_query);
        
        // Reset params array to exclude pagination
        array_pop($params);
        array_pop($params);
        
        // Bind parameters for count query
        if (!empty($params)) {
            $count_stmt->bind_param(substr($types, 0, -2), ...$params);
        }
        
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];
        
        // Return posts with pagination info
        echo json_encode([
            'success' => true,
            'data' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int)$total,
                'total_pages' => ceil($total / $per_page)
            ]
        ]);
    }
}

/**
 * Handle POST requests - Create a new post
 */
function handlePost() {
    global $conn;
    
    // Check if user is logged in and has appropriate role
    if (!is_logged_in() || !has_role(['admin', 'editor', 'author'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        return;
    }
    
    // Verify CSRF token if not using API token
    if (!isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$csrf_token || !verify_csrf_token($csrf_token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ]);
            return;
        }
    }
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['title']) || !isset($data['content']) || !isset($data['category_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
        return;
    }
    
    // Sanitize inputs
    $title = sanitize_input($data['title']);
    $content = $data['content']; // HTML content allowed
    $excerpt = isset($data['excerpt']) ? sanitize_input($data['excerpt']) : get_excerpt($content);
    $category_id = (int)$data['category_id'];
    $status = isset($data['status']) && in_array($data['status'], ['draft', 'published', 'archived']) ? $data['status'] : 'draft';
    $featured_image = isset($data['featured_image']) ? sanitize_input($data['featured_image']) : null;
    $author_id = $_SESSION['user_id'];
    
    // Validate category exists
    $cat_query = "SELECT category_id FROM categories WHERE category_id = ?";
    $cat_stmt = $conn->prepare($cat_query);
    $cat_stmt->bind_param("i", $category_id);
    $cat_stmt->execute();
    
    if ($cat_stmt->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid category'
        ]);
        return;
    }
    
    // Insert post
    $query = "INSERT INTO posts (title, content, excerpt, author_id, category_id, status, featured_image) 
             VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssiiss", $title, $content, $excerpt, $author_id, $category_id, $status, $featured_image);
    
    if ($stmt->execute()) {
        $post_id = $conn->insert_id;
        
        // Handle tags if provided
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag_name) {
                // First check if tag exists
                $tag_name = sanitize_input($tag_name);
                $tag_query = "SELECT tag_id FROM tags WHERE name = ?";
                $tag_stmt = $conn->prepare($tag_query);
                $tag_stmt->bind_param("s", $tag_name);
                $tag_stmt->execute();
                $tag_result = $tag_stmt->get_result();
                
                if ($tag_result->num_rows > 0) {
                    $tag_id = $tag_result->fetch_assoc()['tag_id'];
                } else {
                    // Create new tag
                    $new_tag_query = "INSERT INTO tags (name) VALUES (?)";
                    $new_tag_stmt = $conn->prepare($new_tag_query);
                    $new_tag_stmt->bind_param("s", $tag_name);
                    $new_tag_stmt->execute();
                    $tag_id = $conn->insert_id;
                }
                
                // Associate tag with post
                $post_tag_query = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $post_tag_stmt = $conn->prepare($post_tag_query);
                $post_tag_stmt->bind_param("ii", $post_id, $tag_id);
                $post_tag_stmt->execute();
            }
        }
        
        // Generate XML for the post
        $post_query = "SELECT p.*, u.username, c.name as category_name 
                      FROM posts p 
                      JOIN users u ON p.author_id = u.user_id 
                      JOIN categories c ON p.category_id = c.category_id 
                      WHERE p.post_id = ?";
        
        $post_stmt = $conn->prepare($post_query);
        $post_stmt->bind_param("i", $post_id);
        $post_stmt->execute();
        $post_result = $post_stmt->get_result();
        $post = $post_result->fetch_assoc();
        
        $xml = generate_post_xml($post);
        save_post_xml($post_id, $xml);
        
        // Return success with post ID
        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => 'Post created successfully',
            'post_id' => $post_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creating post: ' . $stmt->error
        ]);
    }
}

/**
 * Handle PUT requests - Update an existing post
 */
function handlePut() {
    global $conn;
    
    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        return;
    }
    
    // Verify CSRF token if not using API token
    if (!isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$csrf_token || !verify_csrf_token($csrf_token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ]);
            return;
        }
    }
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['post_id']) || !is_numeric($data['post_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Post ID is required'
        ]);
        return;
    }
    
    $post_id = (int)$data['post_id'];
    
    // Check if post exists and user has permission to edit
    $check_query = "SELECT author_id FROM posts WHERE post_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        return;
    }
    
    $author_id = $check_result->fetch_assoc()['author_id'];
    
    if (!can_edit_post($post_id, $author_id)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to edit this post'
        ]);
        return;
    }
    
    // Build update query based on provided fields
    $update_fields = [];
    $params = [];
    $types = "";
    
    if (isset($data['title'])) {
        $update_fields[] = "title = ?";
        $params[] = sanitize_input($data['title']);
        $types .= "s";
    }
    
    if (isset($data['content'])) {
        $update_fields[] = "content = ?";
        $params[] = $data['content'];
        $types .= "s";
        
        // Update excerpt if content changed and excerpt not provided
        if (!isset($data['excerpt'])) {
            $update_fields[] = "excerpt = ?";
            $params[] = get_excerpt($data['content']);
            $types .= "s";
        }
    }
    
    if (isset($data['excerpt'])) {
        $update_fields[] = "excerpt = ?";
        $params[] = sanitize_input($data['excerpt']);
        $types .= "s";
    }
    
    if (isset($data['category_id']) && is_numeric($data['category_id'])) {
        $category_id = (int)$data['category_id'];
        
        // Validate category exists
        $cat_query = "SELECT category_id FROM categories WHERE category_id = ?";
        $cat_stmt = $conn->prepare($cat_query);
        $cat_stmt->bind_param("i", $category_id);
        $cat_stmt->execute();
        
        if ($cat_stmt->get_result()->num_rows > 0) {
            $update_fields[] = "category_id = ?";
            $params[] = $category_id;
            $types .= "i";
        }
    }
    
    if (isset($data['status']) && in_array($data['status'], ['draft', 'published', 'archived'])) {
        $update_fields[] = "status = ?";
        $params[] = $data['status'];
        $types .= "s";
    }
    
    if (isset($data['featured_image'])) {
        $update_fields[] = "featured_image = ?";
        $params[] = sanitize_input($data['featured_image']);
        $types .= "s";
    }
    
    // Check if there are fields to update
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    // Add post_id to params
    $params[] = $post_id;
    $types .= "i";
    
    // Build and execute query
    $query = "UPDATE posts SET " . implode(", ", $update_fields) . ", updated_at = CURRENT_TIMESTAMP WHERE post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Handle tags if provided
        if (isset($data['tags']) && is_array($data['tags'])) {
            // Remove existing tags
            $delete_tags_query = "DELETE FROM post_tags WHERE post_id = ?";
            $delete_tags_stmt = $conn->prepare($delete_tags_query);
            $delete_tags_stmt->bind_param("i", $post_id);
            $delete_tags_stmt->execute();
            
            // Add new tags
            foreach ($data['tags'] as $tag_name) {
                // First check if tag exists
                $tag_name = sanitize_input($tag_name);
                $tag_query = "SELECT tag_id FROM tags WHERE name = ?";
                $tag_stmt = $conn->prepare($tag_query);
                $tag_stmt->bind_param("s", $tag_name);
                $tag_stmt->execute();
                $tag_result = $tag_stmt->get_result();
                
                if ($tag_result->num_rows > 0) {
                    $tag_id = $tag_result->fetch_assoc()['tag_id'];
                } else {
                    // Create new tag
                    $new_tag_query = "INSERT INTO tags (name) VALUES (?)";
                    $new_tag_stmt = $conn->prepare($new_tag_query);
                    $new_tag_stmt->bind_param("s", $tag_name);
                    $new_tag_stmt->execute();
                    $tag_id = $conn->insert_id;
                }
                
                // Associate tag with post
                $post_tag_query = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $post_tag_stmt = $conn->prepare($post_tag_query);
                $post_tag_stmt->bind_param("ii", $post_id, $tag_id);
                $post_tag_stmt->execute();
            }
        }
        
        // Update XML for the post
        $post_query = "SELECT p.*, u.username, c.name as category_name 
                      FROM posts p 
                      JOIN users u ON p.author_id = u.user_id 
                      JOIN categories c ON p.category_id = c.category_id 
                      WHERE p.post_id = ?";
        
        $post_stmt = $conn->prepare($post_query);
        $post_stmt->bind_param("i", $post_id);
        $post_stmt->execute();
        $post_result = $post_stmt->get_result();
        $post = $post_result->fetch_assoc();
        
        $xml = generate_post_xml($post);
        save_post_xml($post_id, $xml);
        
        echo json_encode([
            'success' => true,
            'message' => 'Post updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating post: ' . $stmt->error
        ]);
    }
}

/**
 * Handle DELETE requests - Delete a post
 */
function handleDelete() {
    global $conn;
    
    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        return;
    }
    
    // Verify CSRF token if not using API token
    if (!isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$csrf_token || !verify_csrf_token($csrf_token)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ]);
            return;
        }
    }
    
    // Check if post ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Post ID is required'
        ]);
        return;
    }
    
    $post_id = (int)$_GET['id'];
    
    // Check if post exists and user has permission to delete
    $check_query = "SELECT author_id FROM posts WHERE post_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        return;
    }
    
    $author_id = $check_result->fetch_assoc()['author_id'];
    
    // Only admins, editors, or the post author can delete
    if (!has_role(['admin', 'editor']) && ($_SESSION['user_id'] !== $author_id)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to delete this post'
        ]);
        return;
    }
    
    // Delete post
    $query = "DELETE FROM posts WHERE post_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $post_id);
    
    if ($stmt->execute()) {
        // Delete XML file if it exists
        $xml_file = __DIR__ . '/../xml/posts/post_' . $post_id . '.xml';
        if (file_exists($xml_file)) {
            unlink($xml_file);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Post deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting post: ' . $stmt->error
        ]);
    }
}
?> 