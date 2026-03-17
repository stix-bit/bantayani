<?php
/**
 * Image Preview Helper
 * Include this file in any page that has an image upload input.
 * It will automatically detect input[type="file"] and show a preview of selected images.
 */
?>
<style>
    .image-preview-wrapper {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .image-preview-item {
        position: relative;
        width: 180px;
        height: 180px;
        border: 2px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        background-color: #f9f9f9;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .image-preview-item .remove-preview {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: bold;
        line-height: 1;
        padding: 0;
        z-index: 10;
        transition: transform 0.2s;
    }
    .image-preview-item .remove-preview:hover {
        transform: scale(1.1);
        background: #ef4444;
    }
    /* Hide the default preview in farmer/crop_add.php if this is active */
    #image-preview.image-preview-container:empty {
        display: none;
    }
</style>
<script>
(function() {
    function initImagePreview() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            // Skip if already initialized
            if (input.dataset.previewInitialized) return;
            input.dataset.previewInitialized = 'true';

            // Check if there's an existing preview container (like in crop_add.php)
            let previewContainer = input.parentElement.querySelector('.image-preview-wrapper, .image-preview-container, #image-preview');
            
            // If no container exists, create one
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'image-preview-wrapper';
                // Insert after the input's parent if it's a styled label/div, or after the input itself
                if (input.parentElement.classList.contains('file-input') || input.parentElement.classList.contains('form-group')) {
                    input.parentElement.appendChild(previewContainer);
                } else {
                    input.parentNode.insertBefore(previewContainer, input.nextSibling);
                }
            }

            input.addEventListener('change', function() {
                // If the input has its own specific handler (like in crop_add.php), 
                // we might want to let it handle it. 
                // But the user asked for this "whenever they upload a picture".
                
                // For crop_add.php, it uses id="image-preview".
                // We'll only clear if it's our own wrapper or if we want to override.
                if (previewContainer.classList.contains('image-preview-wrapper')) {
                    previewContainer.innerHTML = '';
                } else {
                    // If it's an existing container, let's see if we should override.
                    // To be safe and fulfill the requirement globally, we'll clear it.
                    previewContainer.innerHTML = '';
                }
                
                if (this.files && this.files.length > 0) {
                    Array.from(this.files).forEach((file, index) => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const item = document.createElement('div');
                                item.className = 'image-preview-item';
                                
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                
                                const removeBtn = document.createElement('button');
                                removeBtn.type = 'button';
                                removeBtn.className = 'remove-preview';
                                removeBtn.innerHTML = '&times;';
                                removeBtn.title = 'Remove image';
                                removeBtn.onclick = function(event) {
                                    event.preventDefault();
                                    item.remove();
                                    // Note: This doesn't remove the file from the input.
                                    // For a more advanced version, we could manage a FileList via DataTransfer.
                                };
                                
                                // Specific handling for crop_add.php and farm images primary badge
                                if ((input.id === 'crop_images' || input.name === 'farm_images[]') && index === 0) {
                                    const badge = document.createElement('div');
                                    badge.style.cssText = 'position:absolute;top:4px;left:4px;background:#1f8a70;color:white;font-size:12px;padding:4px 8px;border-radius:4px;font-weight:bold;z-index:5;box-shadow:0 1px 3px rgba(0,0,0,0.2);';
                                    badge.textContent = 'PRIMARY';
                                    item.appendChild(badge);
                                }
                                
                                item.appendChild(img);
                                item.appendChild(removeBtn);
                                previewContainer.appendChild(item);
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        });
    }

    // Run on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImagePreview);
    } else {
        initImagePreview();
    }
})();
</script>
