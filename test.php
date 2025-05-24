<?php
echo "Hello from the CMS directory!";
echo "<pre>";
echo "Current directory: " . __DIR__ . "\n";
echo "Files in this directory:\n";
print_r(scandir(__DIR__));
echo "</pre>";
?> 