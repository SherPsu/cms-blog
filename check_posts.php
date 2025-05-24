<?php
// Simple script to check post status

// Include database connection
require_once 'config/database.php';

// Query to get posts
$query = "SELECT post_id, title, status, created_at FROM posts ORDER BY created_at DESC";
$result = $conn->query($query);

echo "<h2>Posts in Database:</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th></tr>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["post_id"] . "</td>";
        echo "<td>" . htmlspecialchars($row["title"]) . "</td>";
        echo "<td>" . $row["status"] . "</td>";
        echo "<td>" . $row["created_at"] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No posts found in the database</td></tr>";
}

echo "</table>";
?> 