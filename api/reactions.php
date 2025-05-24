<?php
/**
 * Reactions API Endpoint
 * 
 * POST /api/reactions - Add or remove a reaction
 * GET /api/reactions?post_id=X - Get reactions for a post
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
        // Get reactions for a post
        handleGetReactions();
        break;

    case 'POST':
        // Add or remove a reaction
        handlePostReaction();
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
 * Handle GET requests - Get reactions for a post
 */
function handleGetReactions() {
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
    
    // Get reaction counts
    $counts = [
        'likes' => get_reaction_count($post_id, 'like', $conn),
        'dislikes' => get_reaction_count($post_id, 'dislike', $conn)
    ];
    
    // Check if user is logged in, get their reaction
    $user_reaction = null;
    if (isset($_SESSION['user_id'])) {
        $query = "SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user_reaction = $result->fetch_assoc()['reaction_type'];
        }
    }
    
    // Return reaction data
    echo json_encode([
        'success' => true,
        'counts' => $counts,
        'user_reaction' => $user_reaction
    ]);
}

/**
 * Handle POST requests - Add or remove a reaction
 */
function handlePostReaction() {
    global $conn;
    
    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to react to posts'
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
    
    // Get request data
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
    $reaction_type = isset($_POST['reaction_type']) && in_array($_POST['reaction_type'], ['like', 'dislike']) ? $_POST['reaction_type'] : null;
    $action = isset($_POST['action']) && in_array($_POST['action'], ['add', 'remove']) ? $_POST['action'] : 'add';
    
    // Validate required fields
    if (!$post_id || !$reaction_type) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Post ID and reaction type are required'
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
    
    $user_id = $_SESSION['user_id'];
    
    // Check if user already reacted to this post
    $check_query = "SELECT reaction_id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $post_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $existing = $check_result->fetch_assoc();
        
        if ($action === 'remove' || $existing['reaction_type'] !== $reaction_type) {
            // Remove existing reaction
            $delete_query = "DELETE FROM post_reactions WHERE post_id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("ii", $post_id, $user_id);
            $delete_stmt->execute();
            
            // If just removing or reaction types are different, add new reaction
            if ($action === 'add' && $existing['reaction_type'] !== $reaction_type) {
                // Get current timestamp
                $current_time = date('Y-m-d H:i:s');
                
                $insert_query = "INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiss", $post_id, $user_id, $reaction_type, $current_time);
                $insert_stmt->execute();
            }
        }
    } elseif ($action === 'add') {
        // Get current timestamp
        $current_time = date('Y-m-d H:i:s');
        
        // Add new reaction
        $insert_query = "INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iiss", $post_id, $user_id, $reaction_type, $current_time);
        $insert_stmt->execute();
    }
    
    // Get updated reaction counts
    $counts = [
        'likes' => get_reaction_count($post_id, 'like', $conn),
        'dislikes' => get_reaction_count($post_id, 'dislike', $conn)
    ];
    
    // Get user's current reaction
    $current_query = "SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bind_param("ii", $post_id, $user_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    
    $user_reaction = null;
    if ($current_result->num_rows > 0) {
        $user_reaction = $current_result->fetch_assoc()['reaction_type'];
    }
    
    // Return updated counts and user reaction
    echo json_encode([
        'success' => true,
        'message' => 'Reaction updated successfully',
        'counts' => $counts,
        'user_reaction' => $user_reaction
    ]);
}
?> 