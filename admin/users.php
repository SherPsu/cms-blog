<?php
/**
 * Admin - Users
 * 
 * Manage users
 */

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has sufficient permissions
if (!is_logged_in() || !has_role(['admin'])) {
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = "Manage Users";

// Handle actions
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Prevent admin from deleting themselves
    if ($user_id === (int)$_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: users.php");
        exit;
    }
    
    // Verify user exists
    $check_query = "SELECT user_id FROM users WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        // Delete user
        $delete_query = "DELETE FROM users WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting user: " . $delete_stmt->error;
        }
    } else {
        $_SESSION['error_message'] = "User not found.";
    }
    
    // Redirect to avoid resubmission
    header("Location: users.php");
    exit;
}

// Handle status updates
if (isset($_GET['action']) && $_GET['action'] === 'status' && isset($_GET['id']) && isset($_GET['status'])) {
    $user_id = (int)$_GET['id'];
    $status = $_GET['status'] === 'active' ? 1 : 0;
    
    // Prevent admin from deactivating themselves
    if ($user_id === (int)$_SESSION['user_id'] && $status === 0) {
        $_SESSION['error_message'] = "You cannot deactivate your own account.";
        header("Location: users.php");
        exit;
    }
    
    // Update user status
    $update_query = "UPDATE users SET active = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $status, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "User status updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating user status: " . $update_stmt->error;
    }
    
    // Redirect to avoid resubmission
    header("Location: users.php");
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

// Filter by role
if (isset($_GET['role']) && in_array($_GET['role'], ['admin', 'editor', 'author', 'subscriber'])) {
    $where_clauses[] = "role = ?";
    $params[] = $_GET['role'];
    $types .= "s";
}

// Filter by status
if (isset($_GET['active']) && ($_GET['active'] === '0' || $_GET['active'] === '1')) {
    $where_clauses[] = "active = ?";
    $params[] = (int)$_GET['active'];
    $types .= "i";
}

// Search by username or email
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%" . $_GET['search'] . "%";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "ss";
}

// Build WHERE clause
$where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);

// Get total number of users
$count_query = "SELECT COUNT(*) as total FROM users $where_sql";
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$query = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ?, ?";

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
                <h1 class="h2">Manage Users</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="user-add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filter Users
                </div>
                <div class="card-body">
                    <form action="users.php" method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Username/Email</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo (isset($_GET['role']) && $_GET['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="editor" <?php echo (isset($_GET['role']) && $_GET['role'] === 'editor') ? 'selected' : ''; ?>>Editor</option>
                                <option value="author" <?php echo (isset($_GET['role']) && $_GET['role'] === 'author') ? 'selected' : ''; ?>>Author</option>
                                <option value="subscriber" <?php echo (isset($_GET['role']) && $_GET['role'] === 'subscriber') ? 'selected' : ''; ?>>Subscriber</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="active" class="form-label">Status</label>
                            <select class="form-select" id="active" name="active">
                                <option value="">All Statuses</option>
                                <option value="1" <?php echo (isset($_GET['active']) && $_GET['active'] === '1') ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo (isset($_GET['active']) && $_GET['active'] === '0') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Filter</button>
                            <a href="users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                ($user['role'] === 'editor' ? 'warning' : 
                                                ($user['role'] === 'author' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($user['created_at'], 'M j, Y'); ?></td>
                                    <td class="table-actions">
                                        <!-- Status change actions -->
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <?php if (!$user['active']): ?>
                                                <a href="users.php?action=status&id=<?php echo $user['user_id']; ?>&status=active" class="btn btn-sm btn-success" title="Activate">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="users.php?action=status&id=<?php echo $user['user_id']; ?>&status=inactive" class="btn btn-sm btn-warning" title="Deactivate">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <!-- Edit and delete actions -->
                                        <a href="user-edit.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <a href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Users pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?><?php echo isset($_GET['active']) ? '&active=' . $_GET['active'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
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