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
        const copy = async () => {
            try {
                if (!navigator.clipboard || !window.isSecureContext) { throw new Error(); }
                await navigator.clipboard.writeText(url);
                message.textContent = 'Link copied.';
            } catch (error) {
                // Selection also provides a usable manual fallback when clipboard access is denied.
                let input = widget.querySelector('input');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.readOnly = true;
                    input.setAttribute('aria-label', 'Public post link');
                    widget.append(input);
                }
                input.value = url;
                input.focus();
                input.select();
                let copied = false;
                try { copied = document.execCommand('copy'); } catch (failure) { /* Manual copy remains available. */ }
                message.textContent = copied ? 'Link copied.' : 'Copy the selected link using your device’s copy command.';
                if (copied) { input.remove(); }
            }
        };
        widget.querySelector('[data-share-copy]').addEventListener('click', copy);
        widget.querySelector('[data-share-native]').addEventListener('click', async () => {
            if (!navigator.share) { await copy(); return; }
            try {
                await navigator.share({ title: widget.dataset.shareTitle, url });
                message.textContent = 'Share completed.';
            } catch (error) {
                if (error.name === 'AbortError') { message.textContent = 'Sharing cancelled.'; return; }
                await copy();
            }
        });
    });
})();
