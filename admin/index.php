<?php
/**
 * Admin Dashboard
 */

// Start session
session_start();

// Include necessary files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin privileges
if (!is_logged_in() || !has_role(['admin', 'editor', 'author'])) {
    $_SESSION['error'] = "You must be logged in with appropriate permissions to access the admin panel.";
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = 'Admin Dashboard';

// Get counts for dashboard
$post_count = 0;
$comment_count = 0;
$user_count = 0;
$category_count = 0;

// Count posts (authors can only see their own posts)
$post_query = "SELECT COUNT(*) as count FROM posts";
if (has_role(['author'])) {
    $post_query .= " WHERE author_id = " . $_SESSION['user_id'];
}
$post_result = $conn->query($post_query);
if ($post_result) {
    $post_count = $post_result->fetch_assoc()['count'];
}

// Count comments (only on author's posts if author)
$comment_query = "SELECT COUNT(*) as count FROM comments";
if (has_role(['author'])) {
    $comment_query .= " WHERE post_id IN (SELECT post_id FROM posts WHERE author_id = " . $_SESSION['user_id'] . ")";
}
$comment_result = $conn->query($comment_query);
if ($comment_result) {
    $comment_count = $comment_result->fetch_assoc()['count'];
}

// Count users (admins only)
if (has_role(['admin'])) {
    $user_result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($user_result) {
        $user_count = $user_result->fetch_assoc()['count'];
    }
}

// Count categories
$category_result = $conn->query("SELECT COUNT(*) as count FROM categories");
if ($category_result) {
    $category_count = $category_result->fetch_assoc()['count'];
}

// Get recent posts
$recent_posts_query = "SELECT p.post_id, p.title, p.created_at, p.status, u.username 
                      FROM posts p 
                      JOIN users u ON p.author_id = u.user_id";
if (has_role(['author'])) {
    $recent_posts_query .= " WHERE p.author_id = " . $_SESSION['user_id'];
}
$recent_posts_query .= " ORDER BY p.created_at DESC LIMIT 5";

$recent_posts_result = $conn->query($recent_posts_query);
$recent_posts = [];
if ($recent_posts_result) {
    while ($post = $recent_posts_result->fetch_assoc()) {
        $recent_posts[] = $post;
    }
}

// Get recent comments
$recent_comments_query = "SELECT c.comment_id, c.content, c.created_at, c.status, u.username, p.post_id, p.title 
                         FROM comments c 
                         JOIN users u ON c.user_id = u.user_id 
                         JOIN posts p ON c.post_id = p.post_id";
if (has_role(['author'])) {
    $recent_comments_query .= " WHERE p.author_id = " . $_SESSION['user_id'];
}
$recent_comments_query .= " ORDER BY c.created_at DESC LIMIT 5";

$recent_comments_result = $conn->query($recent_comments_query);
$recent_comments = [];
if ($recent_comments_result) {
    while ($comment = $recent_comments_result->fetch_assoc()) {
        // Truncate comment content
        $comment['content'] = get_excerpt($comment['content'], 100);
        $recent_comments[] = $comment;
    }
}

// Include admin header
include '../admin/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../admin/includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="post-add.php" class="btn btn-sm btn-outline-secondary">New Post</a>
                        <?php if (has_role(['admin', 'editor'])): ?>
                            <a href="category-add.php" class="btn btn-sm btn-outline-secondary">New Category</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats cards -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Posts</h6>
                                    <h2 class="mb-0"><?php echo $post_count; ?></h2>
                                </div>
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="posts.php" class="text-white text-decoration-none">View details</a>
                            <i class="fas fa-arrow-circle-right text-white"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Comments</h6>
                                    <h2 class="mb-0"><?php echo $comment_count; ?></h2>
                                </div>
                                <i class="fas fa-comments fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="comments.php" class="text-white text-decoration-none">View details</a>
                            <i class="fas fa-arrow-circle-right text-white"></i>
                        </div>
                    </div>
                </div>
                
                <?php if (has_role(['admin'])): ?>
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Users</h6>
                                    <h2 class="mb-0"><?php echo $user_count; ?></h2>
                                </div>
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="users.php" class="text-white text-decoration-none">View details</a>
                            <i class="fas fa-arrow-circle-right text-white"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Categories</h6>
                                    <h2 class="mb-0"><?php echo $category_count; ?></h2>
                                </div>
                                <i class="fas fa-folder fa-2x"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="categories.php" class="text-white text-decoration-none">View details</a>
                            <i class="fas fa-arrow-circle-right text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent content section -->
            <div class="row">
                <!-- Recent posts -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Posts</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_posts) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Author</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_posts as $post): ?>
                                                <tr>
                                                    <td>
                                                        <a href="post-edit.php?id=<?php echo $post['post_id']; ?>">
                                                            <?php echo htmlspecialchars($post['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($post['username']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                                            <?php echo ucfirst($post['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo format_datetime($post['created_at'], 'M j, Y'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No posts found.</p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="posts.php" class="btn btn-sm btn-outline-primary">View All Posts</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent comments -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Comments</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_comments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Comment</th>
                                                <th>Post</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_comments as $comment): ?>
                                                <tr>
                                                    <td>
                                                        <a href="comment-edit.php?id=<?php echo $comment['comment_id']; ?>" title="<?php echo htmlspecialchars($comment['content']); ?>">
                                                            <?php echo htmlspecialchars($comment['content']); ?>
                                                        </a>
                                                        <div class="small text-muted">by <?php echo htmlspecialchars($comment['username']); ?></div>
                                                    </td>
                                                    <td>
                                                        <a href="../post.php?id=<?php echo $comment['post_id']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars(get_excerpt($comment['title'], 30)); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $comment['status'] === 'approved' ? 'success' : ($comment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($comment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo format_datetime($comment['created_at'], 'M j, Y'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No comments found.</p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="comments.php" class="btn btn-sm btn-outline-primary">View All Comments</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
// Include admin footer
include '../admin/includes/footer.php';
?> 