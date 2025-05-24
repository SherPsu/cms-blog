<?php
/**
 * Edit Category
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
$page_title = 'Edit Category';

// Check if category ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid category ID.";
    header("Location: categories.php");
    exit;
}

$category_id = (int)$_GET['id'];

// Get category data
$query = "SELECT * FROM categories WHERE category_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if category exists
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Category not found.";
    header("Location: categories.php");
    exit;
}

$category = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: category-edit.php?id=" . $category_id);
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
        // Check if category name already exists (excluding current category)
        $check_query = "SELECT category_id FROM categories WHERE name = ? AND category_id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $name, $category_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "A category with this name already exists.";
        }
    }
    
    // If no errors, update category
    if (empty($errors)) {
        // Update category
        $query = "UPDATE categories SET name = ?, description = ?, updated_at = NOW() WHERE category_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $name, $description, $category_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category updated successfully.";
            header("Location: categories.php");
            exit;
        } else {
            $errors[] = "Error updating category: " . $stmt->error;
        }
    }
}

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
                <h1 class="h2">Edit Category</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="categories.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Categories
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
            
            <div class="card">
                <div class="card-body">
                    <form method="post" action="category-edit.php?id=<?php echo $category_id; ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label required">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                            <div class="invalid-feedback">Please enter a category name.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Created At</label>
                            <p class="form-control-plaintext"><?php echo format_datetime($category['created_at']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Updated</label>
                            <p class="form-control-plaintext"><?php echo format_datetime($category['updated_at']); ?></p>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Form validation
    (function() {
        'use strict';
        
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch all forms with needs-validation class
            const forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        });
    })();
</script>

<?php
// Include footer
include '../admin/includes/footer.php';

// Close database connection
$conn->close();
?> 