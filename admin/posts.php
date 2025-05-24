<?php
/**
 * Post Management Page
 */

// Start session
session_start();

// Include necessary files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!is_logged_in() || !has_role(['admin', 'editor', 'author'])) {
    $_SESSION['error'] = "You must be logged in with appropriate permissions to access the admin panel.";
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = 'Manage Posts';

// Handle post deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    
    // Check if post exists
    $check_query = "SELECT author_id FROM posts WHERE post_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $post = $check_result->fetch_assoc();
        
        // Check if user has permission to delete this post
        if (has_role(['admin', 'editor']) || $_SESSION['user_id'] === $post['author_id']) {
            // Delete post
            $delete_query = "DELETE FROM posts WHERE post_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $post_id);
            
            if ($delete_stmt->execute()) {
                // Delete XML file if it exists
                $xml_file = __DIR__ . '/../xml/posts/post_' . $post_id . '.xml';
                if (file_exists($xml_file)) {
                    unlink($xml_file);
                }
                
                $_SESSION['success'] = "Post deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting post: " . $delete_stmt->error;
            }
        } else {
            $_SESSION['error'] = "You don't have permission to delete this post.";
        }
    } else {
        $_SESSION['error'] = "Post not found.";
    }
    
    // Redirect to avoid resubmission
    header("Location: posts.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtering
$where_clauses = [];
$params = [];
$types = "";

// Authors can only see their own posts
if (has_role(['author'])) {
    $where_clauses[] = "p.author_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}

// Filter by status
if (isset($_GET['status']) && in_array($_GET['status'], ['draft', 'published', 'archived'])) {
    $where_clauses[] = "p.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Filter by category
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = (int)$_GET['category'];
    $types .= "i";
}

// Search by title
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_clauses[] = "p.title LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "s";
}

// Build WHERE clause
$where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

// Get total number of posts
$count_query = "SELECT COUNT(*) as total FROM posts p $where_sql";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_posts = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $per_page);

// Get posts
$query = "SELECT p.*, u.username, c.name as category_name 
          FROM posts p 
          JOIN users u ON p.author_id = u.user_id 
          JOIN categories c ON p.category_id = c.category_id 
          $where_sql
          ORDER BY p.created_at DESC 
          LIMIT ?, ?";

// Add pagination parameters
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get categories for filter dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];

while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
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
                <h1 class="h2">Manage Posts</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="post-add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add New Post
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filter Posts
                </div>
                <div class="card-body">
                    <form action="posts.php" method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Title</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($_GET['status']) && $_GET['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo (isset($_GET['status']) && $_GET['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="posts.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Posts table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($post = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><?php echo htmlspecialchars($post['username']); ?></td>
                                    <td><?php echo htmlspecialchars($post['category_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($post['created_at'], 'M j, Y'); ?></td>
                                    <td class="table-actions">
                                        <a href="post-edit.php?id=<?php echo $post['post_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="posts.php?action=delete&id=<?php echo $post['post_id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No posts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php
// Include footer
include '../admin/includes/footer.php';

// Close database connection
$conn->close();
?> 