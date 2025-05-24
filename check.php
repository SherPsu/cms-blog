<?php
echo "<h1>Server Configuration Check</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>File System Check:</h2>";
echo "<p>index.php exists: " . (file_exists(__DIR__ . '/index.php') ? 'Yes' : 'No') . "</p>";
echo "<p>login.php exists: " . (file_exists(__DIR__ . '/login.php') ? 'Yes' : 'No') . "</p>";
echo "<p>admin/index.php exists: " . (file_exists(__DIR__ . '/admin/index.php') ? 'Yes' : 'No') . "</p>";

echo "<h2>Links without /cms prefix:</h2>";
echo "<ul>";
echo "<li><a href='/'>Home</a></li>";
echo "<li><a href='/index.php'>Home (explicit)</a></li>";
echo "<li><a href='/login.php'>Login</a></li>";
echo "<li><a href='/register.php'>Register</a></li>";
echo "<li><a href='/admin/index.php'>Admin Dashboard</a></li>";
echo "<li><a href='/admin/categories.php'>Categories</a></li>";
echo "</ul>";

echo "<h2>Links with /cms prefix:</h2>";
echo "<ul>";
echo "<li><a href='/cms/'>Home</a></li>";
echo "<li><a href='/cms/index.php'>Home (explicit)</a></li>";
echo "<li><a href='/cms/login.php'>Login</a></li>";
echo "<li><a href='/cms/register.php'>Register</a></li>";
echo "<li><a href='/cms/admin/index.php'>Admin Dashboard</a></li>";
echo "<li><a href='/cms/admin/categories.php'>Categories</a></li>";
echo "</ul>";

echo "<h2>Relative Links (should always work):</h2>";
echo "<ul>";
echo "<li><a href='.'>Home (relative)</a></li>";
echo "<li><a href='index.php'>Home (relative explicit)</a></li>";
echo "<li><a href='login.php'>Login (relative)</a></li>";
echo "<li><a href='register.php'>Register (relative)</a></li>";
echo "<li><a href='admin/index.php'>Admin Dashboard (relative)</a></li>";
echo "<li><a href='admin/categories.php'>Categories (relative)</a></li>";
echo "</ul>";
?> 