/**
 * Blog CMS - Main JavaScript
 */

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AJAX for post reactions (likes/dislikes)
    initReactions();
    
    // Initialize AJAX for comments
    initComments();
    
    // Initialize rich text editor if available
    initEditor();
    
    // Add CSRF token to all AJAX requests
    setupAjaxCSRF();
});

/**
 * Initialize post reactions (likes/dislikes)
 */
function initReactions() {
    // Get all reaction buttons
    const reactionButtons = document.querySelectorAll('.btn-reaction');
    
    reactionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if user is logged in
            if (!isLoggedIn()) {
                showLoginPrompt();
                return;
            }
            
            const postId = this.dataset.postId;
            const reactionType = this.dataset.reactionType; // 'like' or 'dislike'
            const isActive = this.classList.contains('active');
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            // Send AJAX request
            $.ajax({
                url: '/api/reactions',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: {
                    post_id: postId,
                    reaction_type: reactionType,
                    action: isActive ? 'remove' : 'add'
                },
                success: function(response) {
                    if (response.success) {
                        updateReactionUI(postId, response.counts);
                    } else {
                        showAlert('error', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error details:", xhr.responseText);
                    showAlert('error', 'An error occurred. Please try again.');
                }
            });
        });
    });
}

/**
 * Update reaction buttons and counts after AJAX call
 */
function updateReactionUI(postId, counts) {
    const likeButton = document.querySelector(`.btn-like[data-post-id="${postId}"]`);
    const dislikeButton = document.querySelector(`.btn-dislike[data-post-id="${postId}"]`);
    
    if (likeButton) {
        const likeCount = likeButton.querySelector('.count');
        if (likeCount) likeCount.textContent = counts.likes;
        
        if (counts.user_reaction === 'like') {
            likeButton.classList.add('active');
        } else {
            likeButton.classList.remove('active');
        }
    }
    
    if (dislikeButton) {
        const dislikeCount = dislikeButton.querySelector('.count');
        if (dislikeCount) dislikeCount.textContent = counts.dislikes;
        
        if (counts.user_reaction === 'dislike') {
            dislikeButton.classList.add('active');
        } else {
            dislikeButton.classList.remove('active');
        }
    }
}

/**
 * Initialize AJAX for comments
 */
function initComments() {
    const commentForm = document.getElementById('comment-form');
    
    if (commentForm) {
        console.log('Comment form found, initializing handler');
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Comment form submitted');
            
            // Check if user is logged in
            if (!isLoggedIn()) {
                showLoginPrompt();
                return;
            }
            
            const postId = this.dataset.postId;
            const content = document.getElementById('comment-content').value;
            const parentId = document.getElementById('parent-id') ? document.getElementById('parent-id').value : null;
            
            console.log('Comment data:', { postId, content, parentId });
            
            if (!content.trim()) {
                showAlert('error', 'Comment cannot be empty');
                return;
            }
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            console.log('CSRF Token:', csrfToken);
            
            // Send AJAX request
            console.log('Sending AJAX request to /api/comments');
            $.ajax({
                url: '/api/comments',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                data: {
                    post_id: postId,
                    content: content,
                    parent_id: parentId
                },
                success: function(response) {
                    console.log('Comment API response:', response);
                    if (response.success) {
                        // Clear the form first
                        document.getElementById('comment-content').value = '';
                        if (document.getElementById('parent-id')) {
                            document.getElementById('parent-id').value = '';
                        }
                        
                        // Check if we have comment data (for approved comments)
                        if (response.comment) {
                            // Add new comment to the list
                            addCommentToList(response.comment);
                            showAlert('success', 'Your comment has been added successfully.');
                        } else {
                            // Comment is awaiting approval
                            showAlert('success', response.message || 'Your comment has been submitted and is awaiting approval.');
                        }
                    } else {
                        showAlert('error', response.message || 'Failed to add comment.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error details:", xhr.responseText);
                    console.error("Status:", status);
                    console.error("Error:", error);
                    showAlert('error', 'An error occurred. Please try again.');
                }
            });
        });
        
        // Handle reply buttons
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('btn-reply')) {
                const commentId = e.target.dataset.commentId;
                const commentAuthor = e.target.dataset.commentAuthor;
                
                // Set parent ID in the form
                if (document.getElementById('parent-id')) {
                    document.getElementById('parent-id').value = commentId;
                }
                
                // Focus on comment textarea and add mention
                const textarea = document.getElementById('comment-content');
                textarea.value = `@${commentAuthor} `;
                textarea.focus();
                
                // Scroll to comment form
                commentForm.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }
}

/**
 * Add a new comment to the comments list
 */
function addCommentToList(comment) {
    console.log('Adding new comment to list:', comment);
    
    const commentsList = document.querySelector('.comments-list');
    
    if (!commentsList) {
        console.error('Comments list container not found');
        return;
    }
    
    const commentHtml = `
        <div class="comment" id="comment-${comment.comment_id}">
            <div class="comment-meta">
                <strong>${comment.username}</strong> &bull; ${comment.created_at}
            </div>
            <div class="comment-content">
                ${comment.content}
            </div>
            <div class="comment-actions">
                <button class="btn btn-sm btn-link btn-reply" 
                        data-comment-id="${comment.comment_id}" 
                        data-comment-author="${comment.username}">
                    Reply
                </button>
            </div>
        </div>
    `;
    
    if (comment.parent_id) {
        // This is a reply, add it after the parent comment
        const parentComment = document.getElementById(`comment-${comment.parent_id}`);
        
        if (parentComment) {
            // Check if there's already a replies container
            let repliesContainer = parentComment.querySelector('.comment-replies');
            
            if (!repliesContainer) {
                // Create replies container if it doesn't exist
                console.log('Creating new replies container for comment', comment.parent_id);
                repliesContainer = document.createElement('div');
                repliesContainer.className = 'comment-replies mt-3';
                parentComment.appendChild(repliesContainer);
            }
            
            // Insert the new comment as HTML
            console.log('Inserting reply into replies container');
            repliesContainer.insertAdjacentHTML('beforeend', commentHtml);
        } else {
            // Parent comment not found, add to the main list
            console.warn('Parent comment not found, adding to main list instead');
            commentsList.insertAdjacentHTML('beforeend', commentHtml);
        }
    } else {
        // This is a top-level comment, add it to the main list
        console.log('Adding top-level comment to main list');
        commentsList.insertAdjacentHTML('beforeend', commentHtml);
    }
    
    // Update comment count
    const commentHeader = document.querySelector('#comments h4');
    if (commentHeader) {
        const currentCount = parseInt(commentHeader.textContent.match(/\d+/) || 0);
        commentHeader.textContent = `${currentCount + 1} Comments`;
    }
}

/**
 * Initialize rich text editor for post content
 */
function initEditor() {
    // Check if we have content editor textarea
    const editorTextarea = document.getElementById('post-content');
    
    if (editorTextarea && typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(editorTextarea)
            .catch(error => {
                console.error(error);
            });
    }
}

/**
 * Setup CSRF token for all AJAX requests
 */
function setupAjaxCSRF() {
    // Get CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    if (csrfToken) {
        // Add token to all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return document.body.classList.contains('logged-in');
}

/**
 * Show login prompt for guests
 */
function showLoginPrompt() {
    showAlert('error', 'Please <a href="/login.php">login</a> to perform this action.');
}

/**
 * Show alert message
 */
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Find the comment form or main content area
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.insertAdjacentHTML('beforebegin', alertHtml);
    } else {
        // Fallback to inserting at the top of the main content
        const mainContent = document.querySelector('main.container');
        if (mainContent) {
            mainContent.insertAdjacentHTML('afterbegin', alertHtml);
        }
    }
    
    // Auto-remove the alert after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        });
    }, 5000);
} 