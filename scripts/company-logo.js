'use strict';
document.querySelectorAll('[data-logo-input]').forEach(input => {
    let previous;
    input.addEventListener('change', () => {
        const preview = input.parentElement.querySelector('[data-logo-preview]');
        if (previous) URL.revokeObjectURL(previous);
        preview.replaceChildren();
        input.setCustomValidity('');
        const file = input.files[0];
        if (!file) return;
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            input.setCustomValidity('Choose a PNG, JPG or WebP logo up to 2 MB.');
            preview.textContent = input.validationMessage;
            preview.setAttribute('role', 'alert');
            return;
        }
        const image = new Image(120, 120);
        image.alt = 'Selected company logo preview';
        image.className = 'company-logo-preview';
        image.src = previous = URL.createObjectURL(file);
        preview.append(image);
    });
});
