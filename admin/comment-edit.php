<?php
/**
 * Admin - Edit Comment
 * 
 * Edit a comment
 */

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has sufficient permissions
if (!is_logged_in() || !has_role(['admin', 'editor'])) {
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = "Edit Comment";

// Check if comment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid comment ID.";
    header("Location: comments.php");
    exit;
}

$comment_id = (int)$_GET['id'];

// Get comment data
$query = "SELECT c.*, u.username, p.title as post_title, p.post_id 
          FROM comments c 
          JOIN users u ON c.user_id = u.user_id 
          JOIN posts p ON c.post_id = p.post_id 
          WHERE c.comment_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if comment exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Comment not found.";
    header("Location: comments.php");
    exit;
}

$comment = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    // Validate input
    $errors = [];
    
    if (empty($content)) {
        $errors[] = "Comment content is required.";
    }
    
    if (!in_array($status, ['approved', 'pending', 'spam'])) {
        $errors[] = "Invalid status.";
    }
    
    // If no errors, update comment
    if (empty($errors)) {
        $update_query = "UPDATE comments SET content = ?, status = ?, updated_at = NOW() WHERE comment_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssi", $content, $status, $comment_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Comment updated successfully.";
            header("Location: comments.php");
            exit;
        } else {
            $errors[] = "Error updating comment: " . $update_stmt->error;
        }
    }
}

// Include header
include '../admin/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../admin/includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Comment</h1>
                <a href="comments.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Comments
                </a>
            </div>
            
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Comment Details</span>
                        <span class="badge bg-<?php echo $comment['status'] === 'approved' ? 'success' : ($comment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                            <?php echo ucfirst($comment['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Author:</strong> <?php echo htmlspecialchars($comment['username']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Post:</strong> 
                        <a href="../post.php?id=<?php echo $comment['post_id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($comment['post_title']); ?>
                        </a>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> <?php echo format_datetime($comment['created_at']); ?>
                    </div>
                    <?php if ($comment['updated_at'] !== $comment['created_at']): ?>
                        <div class="mb-3">
                            <strong>Last Updated:</strong> <?php echo format_datetime($comment['updated_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Edit Comment</div>
                <div class="card-body">
                    <form action="comment-edit.php?id=<?php echo $comment_id; ?>" method="post">
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required><?php echo htmlspecialchars($comment['content']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="approved" <?php echo $comment['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo $comment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="spam" <?php echo $comment['status'] === 'spam' ? 'selected' : ''; ?>>Spam</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="comments.php?action=delete&id=<?php echo $comment_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this comment?');">
                                Delete Comment
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
// Include admin footer
include '../admin/includes/footer.php';
?> 