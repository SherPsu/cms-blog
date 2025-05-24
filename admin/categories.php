<?php
/**
 * Category Management Page
 */

// Start session
session_start();

// Include necessary files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate permissions
if (!is_logged_in() || !has_role(['admin', 'editor'])) {
    $_SESSION['error'] = "You must be logged in with appropriate permissions to access this page.";
    header("Location: ../login.php");
    exit;
}

// Set page title
$page_title = 'Manage Categories';

// Handle category deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    // Check if category exists
    $check_query = "SELECT category_id FROM categories WHERE category_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Check if category has posts
        $post_check_query = "SELECT COUNT(*) AS count FROM posts WHERE category_id = ?";
        $post_check_stmt = $conn->prepare($post_check_query);
        $post_check_stmt->bind_param("i", $category_id);
        $post_check_stmt->execute();
        $post_count = $post_check_stmt->get_result()->fetch_assoc()['count'];
        
        if ($post_count > 0) {
            $_SESSION['error'] = "Cannot delete category: it has $post_count posts associated with it. Please reassign these posts to another category first.";
        } else {
            // Delete category
            $delete_query = "DELETE FROM categories WHERE category_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $category_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success'] = "Category deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting category: " . $delete_stmt->error;
            }
        }
    } else {
        $_SESSION['error'] = "Category not found.";
    }
    
    // Redirect to avoid resubmission
    header("Location: categories.php");
    exit;
}

// Handle category addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: categories.php");
        exit;
    }
    
    // Get form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Category name is required.";
    } else {
        // Check if category name already exists
        $check_query = "SELECT category_id FROM categories WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "A category with this name already exists.";
        }
    }
    
    // If no errors, insert category
    if (empty($errors)) {
        // Get current timestamp
        $now = date('Y-m-d H:i:s');
        
        // Insert category
        $query = "INSERT INTO categories (name, description, created_at, updated_at) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $name, $description, $now, $now);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category created successfully.";
            header("Location: categories.php");
            exit;
        } else {
            $errors[] = "Error creating category: " . $stmt->error;
        }
    }
}

// Get categories
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM posts WHERE category_id = c.category_id) AS post_count 
          FROM categories c 
          ORDER BY c.name";
$result = $conn->query($query);

// Generate CSRF token
$csrf_token = generate_csrf_token();

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
                <h1 class="h2">Manage Categories</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
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
            
            <!-- Categories table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Posts</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($category = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                    <td><?php echo $category['post_count']; ?></td>
                                    <td><?php echo format_datetime($category['created_at'], 'M j, Y'); ?></td>
                                    <td class="table-actions">
                                        <a href="category-edit.php?id=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($category['post_count'] == 0): ?>
                                            <a href="categories.php?action=delete&id=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-danger btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-danger" disabled title="Cannot delete: category has posts">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="categories.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label required">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include '../admin/includes/footer.php';

// Close database connection
$conn->close();
?> 