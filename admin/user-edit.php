<?php
/**
 * Admin - Edit User
 * 
 * Edit a user
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
$page_title = "Edit User";

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: users.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Get user data
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: users.php");
    exit;
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $active = isset($_POST['active']) ? 1 : 0;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (!in_array($role, ['admin', 'editor', 'author', 'subscriber'])) {
        $errors[] = "Invalid role.";
    }
    
    // Check if username or email already exists (for other users)
    $check_query = "SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ssi", $username, $email, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Username or email is already in use.";
    }
    
    // If current user is editing themselves, they cannot deactivate their account
    if ($user_id === (int)$_SESSION['user_id'] && !$active) {
        $errors[] = "You cannot deactivate your own account.";
        $active = 1; // Force active status
    }
    
    // If no errors, update user
    if (empty($errors)) {
        // Prepare update query (with or without password)
        if (!empty($password)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $update_query = "UPDATE users SET username = ?, email = ?, role = ?, active = ?, password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssisi", $username, $email, $role, $active, $hashed_password, $user_id);
        } else {
            $update_query = "UPDATE users SET username = ?, email = ?, role = ?, active = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssii", $username, $email, $role, $active, $user_id);
        }
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "User updated successfully.";
            
            // If current user updated their own username, update session
            if ($user_id === (int)$_SESSION['user_id']) {
                $_SESSION['username'] = $username;
            }
            
            header("Location: users.php");
            exit;
        } else {
            $errors[] = "Error updating user: " . $update_stmt->error;
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
                <h1 class="h2">Edit User</h1>
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Users
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
            
            <div class="card">
                <div class="card-header">User Information</div>
                <div class="card-body">
                    <form action="user-edit.php?id=<?php echo $user_id; ?>" method="post">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="editor" <?php echo $user['role'] === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                    <option value="author" <?php echo $user['role'] === 'author' ? 'selected' : ''; ?>>Author</option>
                                    <option value="subscriber" <?php echo $user['role'] === 'subscriber' ? 'selected' : ''; ?>>Subscriber</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" <?php echo $user['active'] ? 'checked' : ''; ?> <?php echo $user_id === (int)$_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="active">
                                        Active
                                    </label>
                                    <?php if ($user_id === (int)$_SESSION['user_id']): ?>
                                        <input type="hidden" name="active" value="1">
                                        <small class="text-muted d-block">You cannot deactivate your own account.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Leave blank to keep current password.</small>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            
                            <?php if ($user_id !== (int)$_SESSION['user_id']): ?>
                                <a href="users.php?action=delete&id=<?php echo $user_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                    Delete User
                                </a>
                            <?php endif; ?>
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