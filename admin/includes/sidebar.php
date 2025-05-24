<?php
/**
 * Admin Panel Sidebar
 */

// Get current page for active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['posts.php', 'post-add.php', 'post-edit.php']) ? 'active' : ''; ?>" href="posts.php">
                    <i class="fas fa-file-alt"></i>
                    Posts
                </a>
            </li>
            
            <?php if (has_role(['admin', 'editor'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['categories.php', 'category-add.php', 'category-edit.php']) ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-folder"></i>
                    Categories
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['comments.php', 'comment-edit.php']) ? 'active' : ''; ?>" href="comments.php">
                    <i class="fas fa-comments"></i>
                    Comments
                </a>
            </li>
            
            <?php if (has_role(['admin'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['users.php', 'user-add.php', 'user-edit.php']) ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    Users
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <?php if (has_role(['admin'])): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>System</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav> 