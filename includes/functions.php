<?php
/**
 * Utility functions for the CMS
 */

/**
 * Sanitize user input to prevent XSS attacks
 * 
 * @param string $data User input data
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a CSRF token and store it in session
 * 
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token from form submission
 * @return bool True if token is valid
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Redirect to a URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specified role
 * 
 * @param string|array $roles Role(s) to check for
 * @return bool True if user has the role
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Check if current user can edit a post
 * 
 * @param int $post_id Post ID
 * @param int $author_id Author ID
 * @return bool True if user can edit the post
 */
function can_edit_post($post_id, $author_id) {
    // Admins and editors can edit any post
    if (has_role(['admin', 'editor'])) {
        return true;
    }
    
    // Authors can only edit their own posts
    if (has_role('author') && $_SESSION['user_id'] == $author_id) {
        return true;
    }
    
    return false;
}

/**
 * Generate XML for a post
 * 
 * @param array $post Post data
 * @return string XML representation of the post
 */
function generate_post_xml($post) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    
    // Create root element
    $root = $dom->createElement('post');
    $dom->appendChild($root);
    
    // Add post attributes
    $root->setAttribute('id', $post['post_id']);
    $root->setAttribute('status', $post['status']);
    
    // Add post elements
    $elements = [
        'title' => $post['title'],
        'author' => $post['username'],
        'category' => $post['category_name'],
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at']
    ];
    
    foreach ($elements as $name => $value) {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $root->appendChild($element);
    }
    
    // Add content with CDATA
    $content = $dom->createElement('content');
    $cdata = $dom->createCDATASection($post['content']);
    $content->appendChild($cdata);
    $root->appendChild($content);
    
    // Add excerpt if exists
    if (!empty($post['excerpt'])) {
        $excerpt = $dom->createElement('excerpt');
        $excerpt->appendChild($dom->createTextNode($post['excerpt']));
        $root->appendChild($excerpt);
    }
    
    // Add featured image if exists
    if (!empty($post['featured_image'])) {
        $image = $dom->createElement('featured_image');
        $image->appendChild($dom->createTextNode($post['featured_image']));
        $root->appendChild($image);
    }
    
    return $dom->saveXML();
}

/**
 * Save post XML to file
 * 
 * @param int $post_id Post ID
 * @param string $xml XML content
 * @return bool True if successful
 */
function save_post_xml($post_id, $xml) {
    $dir = __DIR__ . '/../xml/posts/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $filename = $dir . 'post_' . $post_id . '.xml';
    return file_put_contents($filename, $xml) !== false;
}

/**
 * Format date/time
 * 
 * @param string $datetime Date/time string
 * @param string $format Format (default: 'M j, Y g:i a')
 * @return string Formatted date/time
 */
function format_datetime($datetime, $format = 'M j, Y g:i a') {
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Create slug from title
 * 
 * @param string $title Post title
 * @return string URL-friendly slug
 */
function create_slug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Get excerpt from content
 * 
 * @param string $content Full content
 * @param int $length Maximum length (default: 250)
 * @return string Excerpt
 */
function get_excerpt($content, $length = 250) {
    // Strip HTML tags
    $text = strip_tags($content);
    
    // Trim whitespace
    $text = trim($text);
    
    // If text is shorter than max length, return it
    if (strlen($text) <= $length) {
        return $text;
    }
    
    // Find the last space within the length limit
    $last_space = strrpos(substr($text, 0, $length), ' ');
    
    // If no space found, just cut at max length
    if ($last_space === false) {
        $last_space = $length;
    }
    
    // Trim to last space and add ellipsis
    $text = substr($text, 0, $last_space) . '...';
    
    return $text;
}

/**
 * Get count of reactions for a post
 * 
 * @param int $post_id Post ID
 * @param string $type Reaction type (like or dislike)
 * @param mysqli $conn Database connection
 * @return int Count of reactions
 */
function get_reaction_count($post_id, $type, $conn) {
    $query = "SELECT COUNT(*) as count FROM post_reactions WHERE post_id = ? AND reaction_type = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $post_id, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

/**
 * Display success or error messages
 * 
 * @param string $type Type of message ('success' or 'error')
 * @param string $message Message to display
 * @return string HTML for message
 */
function display_message($type, $message) {
    $class = ($type === 'success') ? 'alert-success' : 'alert-danger';
    return '<div class="alert ' . $class . '">' . $message . '</div>';
}

/**
 * Set updated_at timestamp to current time
 * 
 * @param string $table Table name
 * @param string $id_field Primary key field name
 * @param int $id ID value
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function update_timestamp($table, $id_field, $id, $conn) {
    $now = date('Y-m-d H:i:s');
    $query = "UPDATE $table SET updated_at = ? WHERE $id_field = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $now, $id);
    return $stmt->execute();
}
?> 