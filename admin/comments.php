<?php
/**
 * Admin - Comments
 * 
 * Manage comments
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
$page_title = "Manage Comments";

// Handle actions
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $comment_id = (int)$_GET['id'];
    
    // Verify comment exists
    $check_query = "SELECT comment_id FROM comments WHERE comment_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $comment_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        // Delete comment
        $delete_query = "DELETE FROM comments WHERE comment_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $comment_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "Comment deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting comment: " . $delete_stmt->error;
        }
    } else {
        $_SESSION['error_message'] = "Comment not found.";
    }
    
    // Redirect to avoid resubmission
    header("Location: comments.php");
    exit;
}

// Handle status updates
if (isset($_GET['action']) && $_GET['action'] === 'status' && isset($_GET['id']) && isset($_GET['status'])) {
    $comment_id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    // Validate status
    if (in_array($status, ['approved', 'pending', 'spam'])) {
        // Update comment status
        $update_query = "UPDATE comments SET status = ? WHERE comment_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $status, $comment_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Comment status updated successfully.";
        } else {
            $_SESSION['error_message'] = "Error updating comment status: " . $update_stmt->error;
        }
    } else {
        $_SESSION['error_message'] = "Invalid status.";
    }
    
    // Redirect to avoid resubmission
    header("Location: comments.php");
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtering
$where_clauses = [];
$params = [];
$types = "";

// Filter by status
if (isset($_GET['status']) && in_array($_GET['status'], ['approved', 'pending', 'spam'])) {
    $where_clauses[] = "c.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Search by content
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_clauses[] = "c.content LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "s";
}

// Build WHERE clause
$where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

// Get total number of comments
$count_query = "SELECT COUNT(*) as total FROM comments c $where_sql";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_comments = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_comments / $per_page);

// Get comments
$query = "SELECT c.*, u.username, p.title as post_title, p.post_id 
          FROM comments c 
          JOIN users u ON c.user_id = u.user_id 
          JOIN posts p ON c.post_id = p.post_id 
          $where_sql
          ORDER BY c.created_at DESC 
          LIMIT ?, ?";

// Add pagination parameters
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

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
                <h1 class="h2">Manage Comments</h1>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filter Comments
                </div>
                <div class="card-body">
                    <form action="comments.php" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Content</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="spam" <?php echo (isset($_GET['status']) && $_GET['status'] === 'spam') ? 'selected' : ''; ?>>Spam</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="comments.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Comments table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Author</th>
                            <th>Post</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($comment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="comment-edit.php?id=<?php echo $comment['comment_id']; ?>">
                                            <?php echo htmlspecialchars(get_excerpt($comment['content'], 50)); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($comment['username']); ?></td>
                                    <td>
                                        <a href="../post.php?id=<?php echo $comment['post_id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars(get_excerpt($comment['post_title'], 30)); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $comment['status'] === 'approved' ? 'success' : ($comment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($comment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($comment['created_at'], 'M j, Y'); ?></td>
                                    <td class="table-actions">
                                        <!-- Status change actions -->
                                        <?php if ($comment['status'] !== 'approved'): ?>
                                            <a href="comments.php?action=status&id=<?php echo $comment['comment_id']; ?>&status=approved" class="btn btn-sm btn-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($comment['status'] !== 'pending'): ?>
                                            <a href="comments.php?action=status&id=<?php echo $comment['comment_id']; ?>&status=pending" class="btn btn-sm btn-warning" title="Mark as Pending">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($comment['status'] !== 'spam'): ?>
                                            <a href="comments.php?action=status&id=<?php echo $comment['comment_id']; ?>&status=spam" class="btn btn-sm btn-danger" title="Mark as Spam">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Edit and delete actions -->
                                        <a href="comment-edit.php?id=<?php echo $comment['comment_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="comments.php?action=delete&id=<?php echo $comment['comment_id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this comment?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No comments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Comments pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php
// Include admin footer
include '../admin/includes/footer.php';
?> 