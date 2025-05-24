<?php
/**
 * Homepage - Latest Posts
 */

// Start session
session_start();

// Include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Set page title
$page_title = 'Blog Home';

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5; // Number of posts per page
$offset = ($page - 1) * $per_page;

// Get posts with pagination
$query = "SELECT p.*, u.username, c.name as category_name 
          FROM posts p 
          JOIN users u ON p.author_id = u.user_id 
          JOIN categories c ON p.category_id = c.category_id 
          WHERE p.status = 'published' 
          ORDER BY p.created_at DESC 
          LIMIT ?, ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);

// Get total number of published posts for pagination
$count_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$count_result = $conn->query($count_query);
$total_posts = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $per_page);

// Include header
include 'templates/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <h1 class="mb-4">Latest Blog Posts</h1>
        
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="card mb-4">
                    <?php if (!empty($post['featured_image'])): ?>
                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h2 class="card-title">
                            <a href="post.php?id=<?php echo $post['post_id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                        </h2>
                        
                        <div class="post-meta mb-3">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['username']); ?></span>
                            <span class="mx-2">|</span>
                            <span><i class="fas fa-folder"></i> <a href="category.php?id=<?php echo $post['category_id']; ?>"><?php echo htmlspecialchars($post['category_name']); ?></a></span>
                            <span class="mx-2">|</span>
                            <span><i class="fas fa-clock"></i> <?php echo format_datetime($post['created_at']); ?></span>
                        </div>
                        
                        <div class="card-text">
                            <?php echo get_excerpt($post['content']); ?>
                        </div>
                        
                        <div class="mt-3">
                            <a href="post.php?id=<?php echo $post['post_id']; ?>" class="btn btn-primary">Read More</a>
                        </div>
                    </div>
                    
                    <div class="card-footer text-muted">
                        <?php
                        // Get likes and dislikes count
                        $likes = get_reaction_count($post['post_id'], 'like', $conn);
                        $dislikes = get_reaction_count($post['post_id'], 'dislike', $conn);
                        ?>
                        <div class="d-flex align-items-center">
                            <span class="me-3">
                                <i class="fas fa-thumbs-up"></i> <?php echo $likes; ?> 
                            </span>
                            <span class="me-3">
                                <i class="fas fa-thumbs-down"></i> <?php echo $dislikes; ?>
                            </span>
                            
                            <?php
                            // Get comment count
                            $comment_query = "SELECT COUNT(*) as count FROM comments WHERE post_id = ? AND status = 'approved'";
                            $comment_stmt = $conn->prepare($comment_query);
                            $comment_stmt->bind_param("i", $post['post_id']);
                            $comment_stmt->execute();
                            $comment_result = $comment_stmt->get_result();
                            $comment_count = $comment_result->fetch_assoc()['count'];
                            $comment_stmt->close();
                            ?>
                            
                            <span>
                                <i class="fas fa-comments"></i> <?php echo $comment_count; ?> Comments
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info">No posts found.</div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Sidebar -->
        <div class="card mb-4">
            <div class="card-header">Search</div>
            <div class="card-body">
                <form action="search.php" method="get">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" placeholder="Search for...">
                        <button class="btn btn-primary" type="submit">Go!</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Categories Widget -->
        <div class="card mb-4">
            <div class="card-header">Categories</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-12">
                        <ul class="list-unstyled mb-0">
                            <?php
                            $cat_query = "SELECT category_id, name, (SELECT COUNT(*) FROM posts WHERE category_id = c.category_id AND status = 'published') as post_count FROM categories c ORDER BY name";
                            $cat_result = $conn->query($cat_query);
                            while ($category = $cat_result->fetch_assoc()):
                            ?>
                                <li class="mb-2">
                                    <a href="category.php?id=<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?> 
                                        <span class="badge bg-secondary"><?php echo $category['post_count']; ?></span>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Posts Widget -->
        <div class="card mb-4">
            <div class="card-header">Recent Posts</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <?php
                    $recent_query = "SELECT post_id, title FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 5";
                    $recent_result = $conn->query($recent_query);
                    while ($recent = $recent_result->fetch_assoc()):
                    ?>
                        <li class="mb-2">
                            <a href="post.php?id=<?php echo $recent['post_id']; ?>">
                                <?php echo htmlspecialchars($recent['title']); ?>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'templates/footer.php';

// Close database connection
$conn->close();
?> 