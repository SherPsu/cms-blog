<?php
// Script to update post status to published

// Include database connection
require_once 'config/database.php';

// Post ID to update (based on the output from check_posts.php)
$post_id = 1; // Change this if your post ID is different

// Update query
$query = "UPDATE posts SET status = 'published' WHERE post_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $post_id);

// Execute and check
if ($stmt->execute()) {
    echo "Post updated successfully! Status changed to 'published'.<br>";
    echo "You should now be able to see it on the homepage.<br>";
    echo "<a href='index.php'>Go to Homepage</a>";
} else {
    echo "Error updating post: " . $stmt->error;
}

$stmt->close();
?> 