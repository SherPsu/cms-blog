<?php
/**
 * Admin Panel Header
 */

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Set default page title
if (!isset($page_title)) {
    $page_title = 'Admin Panel';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Blog CMS Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Dashboard CSS -->
    <style>
        body {
            font-size: .875rem;
        }
        
        .feather {
            width: 16px;
            height: 16px;
            vertical-align: text-bottom;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                top: 5rem;
            }
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .sidebar .nav-link .feather {
            margin-right: 4px;
            color: #727272;
        }
        
        .sidebar .nav-link.active {
            color: #2470dc;
        }
        
        .sidebar .nav-link:hover .feather,
        .sidebar .nav-link.active .feather {
            color: inherit;
        }
        
        /* Navbar */
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        
        /* Content */
        .table-actions {
            width: 120px;
        }
        
        /* Form styles */
        .required:after {
            content: '*';
            color: red;
            margin-left: 3px;
        }
        
        /* Status badges */
        .badge.bg-draft {
            background-color: #6c757d;
        }
        
        .badge.bg-published {
            background-color: #198754;
        }
        
        .badge.bg-pending {
            background-color: #fd7e14;
        }
        
        .badge.bg-archived {
            background-color: #0dcaf0;
        }
        
        /* Preview card for images */
        .preview-card {
            max-width: 300px;
            margin-top: 10px;
        }
        
        .preview-card img {
            max-width: 100%;
            height: auto;
        }
        
        /* Card header styling - black to match main header */
        .card-header {
            background-color: #212529;
            color: white;
        }
    </style>
    
    <!-- TinyMCE for rich text editing -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.7/tinymce.min.js" integrity="sha512-n3hvKfyZKhizUWFkXLOHJrings+TYQKRcHt/PJ5alMKAm85RMYtMx8QnE+uQrPbLBCXYYo0hnPMWzSoRrS2/Sw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="index.php">Blog CMS</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap d-flex">
                <a class="nav-link px-3 text-white" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Sign out
                </a>
            </div>
        </div>
    </header>
    
    <?php
    // Display alert messages
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">';
        echo $_SESSION['success'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show m-3" role="alert">';
        echo $_SESSION['error'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['error']);
    }
    ?>
</body>
</html> 