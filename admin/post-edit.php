<?php
/**
 * Admin - Edit Post
 * 
 * Edit a post
 */

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has sufficient permissions
if (!is_logged_in() || !has_role(['admin', 'editor', 'author'])) {
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = "Edit Post";

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid post ID.";
    header("Location: posts.php");
    exit;
}

$post_id = (int)$_GET['id'];

// Get post data
$query = "SELECT p.*, u.username 
          FROM posts p 
          JOIN users u ON p.author_id = u.user_id 
          WHERE p.post_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if post exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Post not found.";
    header("Location: posts.php");
    exit;
}

$post = $result->fetch_assoc();

// Check if user has permission to edit this post
if (!has_role(['admin', 'editor']) && $post['author_id'] !== $_SESSION['user_id']) {
    $_SESSION['error_message'] = "You don't have permission to edit this post.";
    header("Location: posts.php");
    exit;
}

// Get categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];

while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $excerpt = isset($_POST['excerpt']) ? $_POST['excerpt'] : '';
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $tags = isset($_POST['tags']) ? $_POST['tags'] : '';
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($content)) {
        $errors[] = "Content is required.";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category.";
    }
    
    if (!in_array($status, ['draft', 'published', 'archived'])) {
        $errors[] = "Invalid status.";
    }
    
    // If no errors, update post
    if (empty($errors)) {
        $update_query = "UPDATE posts SET 
                         title = ?, 
                         content = ?, 
                         excerpt = ?, 
                         category_id = ?, 
                         status = ?, 
                         tags = ?, 
                         updated_at = NOW() 
                         WHERE post_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sssissi", $title, $content, $excerpt, $category_id, $status, $tags, $post_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Post updated successfully.";
            header("Location: posts.php");
            exit;
        } else {
            $errors[] = "Error updating post: " . $update_stmt->error;
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
                <h1 class="h2">Edit Post</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="posts.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Posts
                    </a>
                </div>
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
                        <span>Post Details</span>
                        <span class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($post['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Author:</strong> <?php echo htmlspecialchars($post['username']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Created:</strong> <?php echo format_datetime($post['created_at']); ?>
                    </div>
                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                        <div class="mb-3">
                            <strong>Last Updated:</strong> <?php echo format_datetime($post['updated_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Edit Post</div>
                <div class="card-body">
                    <form action="post-edit.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="post-content" name="content" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="excerpt" class="form-label">Excerpt</label>
                            <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                            <small class="text-muted">A short summary of the post (optional).</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php echo $category['category_id'] === $post['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="archived" <?php echo $post['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($post['tags'] ?? ''); ?>">
                            <small class="text-muted">Separate tags with commas.</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="posts.php?action=delete&id=<?php echo $post_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                Delete Post
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