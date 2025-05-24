    <footer class="text-center text-muted py-3 mt-5">
        <p>&copy; <?php echo date('Y'); ?> Blog CMS. All rights reserved.</p>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Initialize TinyMCE -->
    <script>
        // Initialize TinyMCE on textareas with class 'editor'
        tinymce.init({
            selector: 'textarea.editor',
            height: 400,
            menubar: false,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | ' +
                'bold italic backcolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; }'
        });
    </script>
    
    <!-- Custom JS for admin panel -->
    <script>
        // Confirm delete
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-delete');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Initialize image preview
            const imageInputs = document.querySelectorAll('input[type="file"][data-preview]');
            
            imageInputs.forEach(input => {
                const previewId = input.dataset.preview;
                const previewElement = document.getElementById(previewId);
                
                if (previewElement) {
                    input.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const reader = new FileReader();
                            
                            reader.onload = function(e) {
                                previewElement.src = e.target.result;
                                previewElement.style.display = 'block';
                            }
                            
                            reader.readAsDataURL(this.files[0]);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html> 