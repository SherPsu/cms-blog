<?php
/**
 * Add New Post
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
$page_title = 'Add New Post';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: post-add.php");
        exit;
    }
    
    // Get form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $excerpt = isset($_POST['excerpt']) && !empty($_POST['excerpt']) ? trim($_POST['excerpt']) : get_excerpt($content);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $status = isset($_POST['status']) && in_array($_POST['status'], ['draft', 'published', 'archived']) ? $_POST['status'] : 'draft';
    $featured_image = isset($_POST['featured_image']) ? trim($_POST['featured_image']) : '';
    $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
    
    // Validate required fields
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required.";
    }
    
    if (empty($content)) {
        $errors[] = "Content is required.";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category.";
    } else {
        // Verify category exists
        $cat_query = "SELECT category_id FROM categories WHERE category_id = ?";
        $cat_stmt = $conn->prepare($cat_query);
        $cat_stmt->bind_param("i", $category_id);
        $cat_stmt->execute();
        
        if ($cat_stmt->get_result()->num_rows === 0) {
            $errors[] = "Selected category does not exist.";
        }
    }
    
    // If no errors, insert post
    if (empty($errors)) {
        // Get current timestamp
        $now = date('Y-m-d H:i:s');
        $author_id = $_SESSION['user_id'];
        
        // Insert post
        $query = "INSERT INTO posts (title, content, excerpt, author_id, category_id, status, featured_image, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssiissss", $title, $content, $excerpt, $author_id, $category_id, $status, $featured_image, $now, $now);
        
        if ($stmt->execute()) {
            $post_id = $conn->insert_id;
            
            // Process tags
            foreach ($tags as $tag_name) {
                $tag_name = trim($tag_name);
                
                if (!empty($tag_name)) {
                    // Check if tag already exists
                    $tag_query = "SELECT tag_id FROM tags WHERE name = ?";
                    $tag_stmt = $conn->prepare($tag_query);
                    $tag_stmt->bind_param("s", $tag_name);
                    $tag_stmt->execute();
                    $tag_result = $tag_stmt->get_result();
                    
                    if ($tag_result->num_rows > 0) {
                        // Tag exists, get ID
                        $tag_id = $tag_result->fetch_assoc()['tag_id'];
                    } else {
                        // Create new tag
                        $new_tag_query = "INSERT INTO tags (name, created_at) VALUES (?, ?)";
                        $new_tag_stmt = $conn->prepare($new_tag_query);
                        $new_tag_stmt->bind_param("ss", $tag_name, $now);
                        $new_tag_stmt->execute();
                        $tag_id = $conn->insert_id;
                    }
                    
                    // Associate tag with post
                    $post_tag_query = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                    $post_tag_stmt = $conn->prepare($post_tag_query);
                    $post_tag_stmt->bind_param("ii", $post_id, $tag_id);
                    $post_tag_stmt->execute();
                }
            }
            
            // Generate and save XML
            $post_query = "SELECT p.*, u.username, c.name as category_name 
                          FROM posts p 
                          JOIN users u ON p.author_id = u.user_id 
                          JOIN categories c ON p.category_id = c.category_id 
                          WHERE p.post_id = ?";
            
            $post_stmt = $conn->prepare($post_query);
            $post_stmt->bind_param("i", $post_id);
            $post_stmt->execute();
            $post = $post_stmt->get_result()->fetch_assoc();
            
            $xml = generate_post_xml($post);
            save_post_xml($post_id, $xml);
            
            // Redirect to edit page with success message
            $_SESSION['success'] = "Post created successfully.";
            
            if ($status === 'published') {
                header("Location: ../post.php?id=" . $post_id);
            } else {
                header("Location: post-edit.php?id=" . $post_id);
            }
            exit;
        } else {
            $errors[] = "Error creating post: " . $stmt->error;
        }
    }
}

// Get categories for dropdown
$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];

while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
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
                <h1 class="h2">Add New Post</h1>
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
            
            <form method="post" action="post-add.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <!-- Post Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label required">Title</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a title.</div>
                        </div>
                        
                        <!-- Post Content -->
                        <div class="mb-3">
                            <label for="content" class="form-label required">Content</label>
                            <textarea class="form-control editor" id="content" name="content" rows="12" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                            <div class="invalid-feedback">Please enter content.</div>
                        </div>
                        
                        <!-- Post Excerpt -->
                        <div class="mb-3">
                            <label for="excerpt" class="form-label">Excerpt (optional)</label>
                            <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo isset($_POST['excerpt']) ? htmlspecialchars($_POST['excerpt']) : ''; ?></textarea>
                            <div class="form-text">If left empty, an excerpt will be automatically generated from the content.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Publishing Options -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Publishing</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label required">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                        <option value="archived" <?php echo (isset($_POST['status']) && $_POST['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Save Post</button>
                                    <a href="posts.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Categories -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Category</h5>
                            </div>
                            <div class="card-body">
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a category.</div>
                            </div>
                        </div>
                        
                        <!-- Tags -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Tags</h5>
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control" id="tags" name="tags" value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>" placeholder="Enter tags separated by commas">
                                <div class="form-text">Separate tags with commas (e.g. news, tech, tutorials)</div>
                            </div>
                        </div>
                        
                        <!-- Featured Image -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Featured Image</h5>
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control" id="featured_image" name="featured_image" value="<?php echo isset($_POST['featured_image']) ? htmlspecialchars($_POST['featured_image']) : ''; ?>" placeholder="Enter image URL">
                                <div class="form-text">Enter the URL of the featured image.</div>
                                
                                <?php if (isset($_POST['featured_image']) && !empty($_POST['featured_image'])): ?>
                                    <div class="preview-card mt-3">
                                        <img src="<?php echo htmlspecialchars($_POST['featured_image']); ?>" alt="Featured image preview" class="img-fluid">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
    // Preview featured image when URL is entered
    document.addEventListener('DOMContentLoaded', function() {
        const featuredImageInput = document.getElementById('featured_image');
        
        featuredImageInput.addEventListener('change', function() {
            const imageUrl = this.value.trim();
            const previewCard = this.parentElement.querySelector('.preview-card') || document.createElement('div');
            
            previewCard.className = 'preview-card mt-3';
            
            if (imageUrl) {
                previewCard.innerHTML = `<img src="${imageUrl}" alt="Featured image preview" class="img-fluid">`;
                
                if (!this.parentElement.querySelector('.preview-card')) {
                    this.parentElement.appendChild(previewCard);
                }
            } else if (this.parentElement.querySelector('.preview-card')) {
                this.parentElement.querySelector('.preview-card').remove();
            }
        });
    });
    
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