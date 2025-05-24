<?php
/**
 * Single Post View
 */

// Start session
session_start();

// Include necessary files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$post_id = (int)$_GET['id'];

// Get post data
$query = "SELECT p.*, u.username, c.name as category_name 
          FROM posts p 
          JOIN users u ON p.author_id = u.user_id 
          JOIN categories c ON p.category_id = c.category_id 
          WHERE p.post_id = ? AND p.status = 'published'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if post exists
if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$post = $result->fetch_assoc();

// Get post tags
$tags_query = "SELECT t.tag_id, t.name 
               FROM tags t 
               JOIN post_tags pt ON t.tag_id = pt.tag_id 
               WHERE pt.post_id = ?";

$tags_stmt = $conn->prepare($tags_query);
$tags_stmt->bind_param("i", $post_id);
$tags_stmt->execute();
$tags_result = $tags_stmt->get_result();
$tags = $tags_result->fetch_all(MYSQLI_ASSOC);

// Get approved comments
$comments_query = "SELECT c.*, u.username 
                  FROM comments c 
                  JOIN users u ON c.user_id = u.user_id 
                  WHERE c.post_id = ? AND c.status = 'approved' AND c.parent_id IS NULL
                  ORDER BY c.created_at";

$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $post_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
$comments = $comments_result->fetch_all(MYSQLI_ASSOC);

// Get user's reaction to this post (if logged in)
$user_reaction = null;
if (isset($_SESSION['user_id'])) {
    $reaction_query = "SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?";
    $reaction_stmt = $conn->prepare($reaction_query);
    $reaction_stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
    $reaction_stmt->execute();
    $reaction_result = $reaction_stmt->get_result();
    
    if ($reaction_result->num_rows > 0) {
        $user_reaction = $reaction_result->fetch_assoc()['reaction_type'];
    }
}

// Process comment submission
$comment_error = '';
$comment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        $comment_error = "You must be logged in to comment.";
    } else {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $comment_error = "Invalid request. Please try again.";
        } else {
            // Get form data
            $content = $_POST['comment'];
            $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            
            // Validate content
            if (empty($content)) {
                $comment_error = "Comment cannot be empty.";
            } else {
                // Sanitize content
                $content = sanitize_input($content);
                
                // Determine comment status (auto-approve for admins and editors)
                $status = in_array($_SESSION['user_role'], ['admin', 'editor']) ? 'approved' : 'pending';
                
                // Insert comment
                $comment_query = "INSERT INTO comments (post_id, user_id, content, status, parent_id) VALUES (?, ?, ?, ?, ?)";
                $comment_stmt = $conn->prepare($comment_query);
                $comment_stmt->bind_param("iissi", $post_id, $_SESSION['user_id'], $content, $status, $parent_id);
                
                if ($comment_stmt->execute()) {
                    if ($status === 'approved') {
                        $comment_success = "Your comment has been posted.";
                    } else {
                        $comment_success = "Your comment has been submitted and is awaiting approval.";
                    }
                    
                    // Refresh the page to show the new comment (if approved)
                    if ($status === 'approved') {
                        header("Location: post.php?id=" . $post_id . "#comments");
                        exit;
                    }
                } else {
                    $comment_error = "There was an error posting your comment. Please try again.";
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Set page title
$page_title = htmlspecialchars($post['title']);

// Include header
include 'templates/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <!-- Post Content -->
        <article>
            <?php if (!empty($post['featured_image'])): ?>
                <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="img-fluid featured-image" alt="<?php echo htmlspecialchars($post['title']); ?>">
            <?php endif; ?>
            
            <h1 class="mt-4"><?php echo htmlspecialchars($post['title']); ?></h1>
            
            <div class="post-meta mb-3">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['username']); ?></span>
                <span class="mx-2">|</span>
                <span><i class="fas fa-folder"></i> <a href="category.php?id=<?php echo $post['category_id']; ?>"><?php echo htmlspecialchars($post['category_name']); ?></a></span>
                <span class="mx-2">|</span>
                <span><i class="fas fa-clock"></i> <?php echo format_datetime($post['created_at']); ?></span>
            </div>
            
            <?php if (count($tags) > 0): ?>
                <div class="mb-3">
                    <i class="fas fa-tags"></i> 
                    <?php foreach ($tags as $index => $tag): ?>
                        <a href="tag.php?id=<?php echo $tag['tag_id']; ?>" class="badge bg-secondary text-decoration-none link-light"><?php echo htmlspecialchars($tag['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <hr>
            
            <div class="post-content">
                <?php echo $post['content']; ?>
            </div>
            
            <hr>
            
            <!-- Like/Dislike Buttons -->
            <?php
            $likes = get_reaction_count($post['post_id'], 'like', $conn);
            $dislikes = get_reaction_count($post['post_id'], 'dislike', $conn);
            ?>
            
            <div class="reaction-buttons">
                <button class="btn-reaction btn-like <?php echo ($user_reaction === 'like') ? 'active' : ''; ?>" 
                        data-post-id="<?php echo $post['post_id']; ?>" 
                        data-reaction-type="like">
                    <i class="fas fa-thumbs-up"></i> <span class="count"><?php echo $likes; ?></span>
                </button>
                
                <button class="btn-reaction btn-dislike <?php echo ($user_reaction === 'dislike') ? 'active' : ''; ?>" 
                        data-post-id="<?php echo $post['post_id']; ?>" 
                        data-reaction-type="dislike">
                    <i class="fas fa-thumbs-down"></i> <span class="count"><?php echo $dislikes; ?></span>
                </button>
            </div>
            
            <hr>
            
            <!-- Comments Section -->
            <section id="comments">
                <h4 class="mb-4"><?php echo count($comments); ?> Comments</h4>
                
                <?php if (!empty($comment_error)): ?>
                    <div class="alert alert-danger"><?php echo $comment_error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($comment_success)): ?>
                    <div class="alert alert-success"><?php echo $comment_success; ?></div>
                <?php endif; ?>
                
                <!-- Comments List -->
                <div class="comments-list">
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment" id="comment-<?php echo $comment['comment_id']; ?>">
                            <div class="comment-meta">
                                <strong><?php echo htmlspecialchars($comment['username']); ?></strong> &bull; 
                                <?php echo format_datetime($comment['created_at']); ?>
                            </div>
                            <div class="comment-content">
                                <?php echo htmlspecialchars($comment['content']); ?>
                            </div>
                            <div class="comment-actions">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="btn btn-sm btn-link btn-reply" 
                                            data-comment-id="<?php echo $comment['comment_id']; ?>" 
                                            data-comment-author="<?php echo htmlspecialchars($comment['username']); ?>">
                                        Reply
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php
                            // Get replies to this comment
                            $replies_query = "SELECT c.*, u.username 
                                             FROM comments c 
                                             JOIN users u ON c.user_id = u.user_id 
                                             WHERE c.parent_id = ? AND c.status = 'approved'
                                             ORDER BY c.created_at";
                            
                            $replies_stmt = $conn->prepare($replies_query);
                            $replies_stmt->bind_param("i", $comment['comment_id']);
                            $replies_stmt->execute();
                            $replies_result = $replies_stmt->get_result();
                            
                            if ($replies_result->num_rows > 0):
                            ?>
                                <div class="comment-replies mt-3">
                                    <?php while ($reply = $replies_result->fetch_assoc()): ?>
                                        <div class="comment comment-reply" id="comment-<?php echo $reply['comment_id']; ?>">
                                            <div class="comment-meta">
                                                <strong><?php echo htmlspecialchars($reply['username']); ?></strong> &bull; 
                                                <?php echo format_datetime($reply['created_at']); ?>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo htmlspecialchars($reply['content']); ?>
                                            </div>
                                            <div class="comment-actions">
                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <button class="btn btn-sm btn-link btn-reply" 
                                                            data-comment-id="<?php echo $comment['comment_id']; ?>" 
                                                            data-comment-author="<?php echo htmlspecialchars($reply['username']); ?>">
                                                        Reply
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Comment Form -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">Leave a Comment</div>
                        <div class="card-body">
                            <form method="post" id="comment-form" data-post-id="<?php echo $post_id; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="parent_id" id="parent-id" value="">
                                
                                <div class="mb-3">
                                    <textarea class="form-control" id="comment-content" name="comment" rows="4" required></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-4">
                        Please <a href="login.php">login</a> to leave a comment.
                    </div>
                <?php endif; ?>
            </section>
        </article>
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
        
        <!-- Author Info -->
        <div class="card mb-4">
            <div class="card-header">About the Author</div>
            <div class="card-body">
                <h5><?php echo htmlspecialchars($post['username']); ?></h5>
                <?php
                // Get author info
                $author_query = "SELECT COUNT(*) as post_count FROM posts WHERE author_id = ? AND status = 'published'";
                $author_stmt = $conn->prepare($author_query);
                $author_stmt->bind_param("i", $post['author_id']);
                $author_stmt->execute();
                $author_result = $author_stmt->get_result();
                $post_count = $author_result->fetch_assoc()['post_count'];
                ?>
                <p>This author has published <?php echo $post_count; ?> posts.</p>
                <a href="author.php?id=<?php echo $post['author_id']; ?>" class="btn btn-outline-primary btn-sm">View All Posts</a>
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
        
        <!-- Related Posts -->
        <div class="card mb-4">
            <div class="card-header">Related Posts</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <?php
                    $related_query = "SELECT post_id, title FROM posts 
                                     WHERE category_id = ? AND post_id != ? AND status = 'published' 
                                     ORDER BY created_at DESC LIMIT 5";
                    $related_stmt = $conn->prepare($related_query);
                    $related_stmt->bind_param("ii", $post['category_id'], $post_id);
                    $related_stmt->execute();
                    $related_result = $related_stmt->get_result();
                    
                    if ($related_result->num_rows > 0):
                        while ($related = $related_result->fetch_assoc()):
                    ?>
                        <li class="mb-2">
                            <a href="post.php?id=<?php echo $related['post_id']; ?>">
                                <?php echo htmlspecialchars($related['title']); ?>
                            </a>
                        </li>
                    <?php 
                        endwhile;
                    else:
                        echo '<li>No related posts found.</li>';
                    endif;
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add XML metadata link for this post -->
<link rel="alternate" type="application/xml" title="XML" href="xml/posts/post_<?php echo $post_id; ?>.xml" />

<?php
// Save post XML
$xml = generate_post_xml($post);
save_post_xml($post_id, $xml);

// Include footer
include 'templates/footer.php';

// Add classes to body for JavaScript
echo "<script>document.body.classList.add('" . (isset($_SESSION['user_id']) ? 'logged-in' : 'logged-out') . "');</script>";

// Add CSRF token meta tag
echo "<script>document.head.insertAdjacentHTML('beforeend', '<meta name=\"csrf-token\" content=\"" . $csrf_token . "\">');</script>";

// Close database connection
$conn->close();
?> 