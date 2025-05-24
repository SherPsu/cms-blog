<?php
echo "<h1>CMS Test Page</h1>";
echo "<p>If you can see this page, the server is correctly configured.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<h2>Available PHP Files:</h2>";
echo "<ul>";
foreach (glob("*.php") as $filename) {
    echo "<li><a href='$filename'>$filename</a></li>";
}
echo "</ul>";
?> 