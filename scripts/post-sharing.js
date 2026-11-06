'use strict';

(() => {
    // A file can disappear or fail to load after the server has checked its path.
    document.querySelectorAll('.social-post__media img, .social-post__media video').forEach((media) => {
        const unavailable = () => {
            const container = media.closest('.social-post__media');
            if (!container || !container.isConnected) { return; }
            const fallback = document.createElement('p');
            fallback.className = 'social-post__fallback';
            fallback.setAttribute('role', 'status');
            fallback.textContent = `This ${media.tagName === 'VIDEO' ? 'video' : 'image'} is currently unavailable.`;
            container.replaceWith(fallback);
        };
        media.addEventListener('error', unavailable, { once: true });
        if (media.tagName === 'IMG' && media.complete && media.naturalWidth === 0) { unavailable(); }
    });

    document.querySelectorAll('[data-post-share]').forEach((widget) => {
        const message = widget.querySelector('[data-share-message]');
        const url = new URL(widget.dataset.shareUrl, window.location.origin).href;
        widget.querySelector('[data-share-native]').addEventListener('click', async () => {
            if (!navigator.share) { message.textContent = 'Sharing is unavailable on this browser.'; return; }
            try {
                await navigator.share({ title: widget.dataset.shareTitle, url });
                message.textContent = 'Share completed.';
            } catch (error) {
                if (error.name === 'AbortError') { message.textContent = 'Sharing cancelled.'; return; }
                message.textContent = 'Sharing could not be completed.';
            }
        });
    });
})();
