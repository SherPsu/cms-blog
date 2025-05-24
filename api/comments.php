<?php
/**
 * Comments API Endpoint
 * 
 * GET /api/comments?post_id=X - Get comments for a post
 * POST /api/comments - Add a new comment
 * PUT /api/comments - Update a comment
 * DELETE /api/comments?id=X - Delete a comment
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
        // Get comments for a post
        handleGetComments();
        break;

    case 'POST':
        // Add a new comment
        handlePostComment();
        break;

    case 'PUT':
        // Update a comment
        handlePutComment();
        break;

    case 'DELETE':
        // Delete a comment
        handleDeleteComment();
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
 * Handle GET requests - Get comments for a post
 */
function handleGetComments() {
    global $conn;
    
    // Check if post ID is provided
    if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Post ID is required'
        ]);
        return;
    }
    
    $post_id = (int)$_GET['post_id'];
    
    // Check if post exists
    $post_query = "SELECT post_id FROM posts WHERE post_id = ?";
    $post_stmt = $conn->prepare($post_query);
    $post_stmt->bind_param("i", $post_id);
    $post_stmt->execute();
    
    if ($post_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        return;
    }
    
    // Get only approved comments, unless user is admin/editor
    $status_condition = "c.status = 'approved'";
    if (is_logged_in() && has_role(['admin', 'editor'])) {
        $status_condition = "1=1"; // Get all comments regardless of status
    }
    
    // Get top-level comments
    $query = "SELECT c.*, u.username 
             FROM comments c 
             JOIN users u ON c.user_id = u.user_id 
             WHERE c.post_id = ? AND c.parent_id IS NULL AND $status_condition
             ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($comment = $result->fetch_assoc()) {
        // Get replies for this comment
        $replies_query = "SELECT c.*, u.username 
                         FROM comments c 
                         JOIN users u ON c.user_id = u.user_id 
                         WHERE c.parent_id = ? AND $status_condition
                         ORDER BY c.created_at ASC";
        
        $replies_stmt = $conn->prepare($replies_query);
        $replies_stmt->bind_param("i", $comment['comment_id']);
        $replies_stmt->execute();
        $replies_result = $replies_stmt->get_result();
        
        $replies = [];
        while ($reply = $replies_result->fetch_assoc()) {
            // Format created_at date
            $reply['created_at'] = format_datetime($reply['created_at']);
            $replies[] = $reply;
        }
        
        // Format created_at date
        $comment['created_at'] = format_datetime($comment['created_at']);
        $comment['replies'] = $replies;
        $comments[] = $comment;
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
}

/**
 * Handle POST requests - Add a new comment
 */
function handlePostComment() {
    global $conn;
    
    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to comment'
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
    
    // Get post data
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
    $content = isset($_POST['content']) ? $_POST['content'] : null;
    $parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Validate required fields
    if (!$post_id || !$content) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Post ID and content are required'
        ]);
        return;
    }
    
    // Check if post exists
    $post_query = "SELECT post_id FROM posts WHERE post_id = ?";
    $post_stmt = $conn->prepare($post_query);
    $post_stmt->bind_param("i", $post_id);
    $post_stmt->execute();
    
    if ($post_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
        return;
    }
    
    // Check if parent comment exists (if provided)
    if ($parent_id) {
        $parent_query = "SELECT comment_id FROM comments WHERE comment_id = ? AND post_id = ?";
        $parent_stmt = $conn->prepare($parent_query);
        $parent_stmt->bind_param("ii", $parent_id, $post_id);
        $parent_stmt->execute();
        
        if ($parent_stmt->get_result()->num_rows === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Parent comment not found'
            ]);
            return;
        }
    }
    
    // Sanitize content
    $content = sanitize_input($content);
    
    // Set comment status (auto-approve for admins and editors)
    $status = 'pending';
    if (has_role(['admin', 'editor'])) {
        $status = 'approved';
    }
    
    // Get current timestamp
    $current_time = date('Y-m-d H:i:s');
    
    // Insert comment
    $query = "INSERT INTO comments (post_id, user_id, content, status, parent_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iississ", $post_id, $_SESSION['user_id'], $content, $status, $parent_id, $current_time, $current_time);
    
    if ($stmt->execute()) {
        $comment_id = $conn->insert_id;
        
        // If comment is approved, return the new comment data
        if ($status === 'approved') {
            // Get comment data
            $comment_query = "SELECT c.*, u.username 
                             FROM comments c 
                             JOIN users u ON c.user_id = u.user_id 
                             WHERE c.comment_id = ?";
            
            $comment_stmt = $conn->prepare($comment_query);
            $comment_stmt->bind_param("i", $comment_id);
            $comment_stmt->execute();
            $comment = $comment_stmt->get_result()->fetch_assoc();
            
            // Format created_at date
            $comment['created_at'] = format_datetime($comment['created_at']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'comment' => $comment
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Comment submitted and is awaiting approval'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error adding comment: ' . $stmt->error
        ]);
    }
}

/**
 * Handle PUT requests - Update a comment
 */
function handlePutComment() {
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
    if (!isset($data['comment_id']) || !is_numeric($data['comment_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Comment ID is required'
        ]);
        return;
    }
    
    $comment_id = (int)$data['comment_id'];
    
    // Check if comment exists and get user ID
    $check_query = "SELECT user_id, status FROM comments WHERE comment_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $comment_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found'
        ]);
        return;
    }
    
    $comment = $check_result->fetch_assoc();
    
    // Check if user has permission to edit (owner or admin/editor)
    if ($comment['user_id'] !== $_SESSION['user_id'] && !has_role(['admin', 'editor'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to edit this comment'
        ]);
        return;
    }
    
    // Build update query based on provided fields
    $update_fields = [];
    $params = [];
    $types = "";
    
    if (isset($data['content'])) {
        $update_fields[] = "content = ?";
        $params[] = sanitize_input($data['content']);
        $types .= "s";
    }
    
    // Only admins/editors can update status
    if (isset($data['status']) && has_role(['admin', 'editor'])) {
        if (in_array($data['status'], ['pending', 'approved', 'spam'])) {
            $update_fields[] = "status = ?";
            $params[] = $data['status'];
            $types .= "s";
        }
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
    
    // Add comment_id to params
    $params[] = $comment_id;
    $types .= "i";
    
    // Build and execute query
    $query = "UPDATE comments SET " . implode(", ", $update_fields) . ", updated_at = CURRENT_TIMESTAMP WHERE comment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error updating comment: ' . $stmt->error
        ]);
    }
}

/**
 * Handle DELETE requests - Delete a comment
 */
function handleDeleteComment() {
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
    
    // Check if comment ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Comment ID is required'
        ]);
        return;
    }
    
    $comment_id = (int)$_GET['id'];
    
    // Check if comment exists and get user ID
    $check_query = "SELECT user_id FROM comments WHERE comment_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $comment_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found'
        ]);
        return;
    }
    
    $comment_user_id = $check_result->fetch_assoc()['user_id'];
    
    // Check if user has permission to delete (owner or admin/editor)
    if ($comment_user_id !== $_SESSION['user_id'] && !has_role(['admin', 'editor'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to delete this comment'
        ]);
        return;
    }
    
    // Delete comment
    $query = "DELETE FROM comments WHERE comment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $comment_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting comment: ' . $stmt->error
        ]);
    }
}
?> 