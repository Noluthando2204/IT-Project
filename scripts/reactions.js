'use strict';

(() => {
    const widgets = document.querySelectorAll('[data-reaction-widget]');

    if (!widgets.length) {
        return;
    }

    const setMessage = (widget, message) => {
        const target = widget.querySelector('[data-reaction-message]');
        if (target) {
            target.textContent = message || '';
        }
    };

    const updateWidget = (widget, totals, currentReaction) => {
        widget.dataset.currentReaction = currentReaction || '';

        widget.querySelectorAll('[data-reaction-button]').forEach((button) => {
            const type = button.dataset.reactionType || '';
            const selected = currentReaction === type;
            const count = button.querySelector('[data-reaction-count]');

            button.classList.toggle('is-selected', selected);
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.dataset.active = selected ? 'true' : 'false';
            button.dataset.reactionAction = selected
                ? 'remove'
                : (currentReaction ? 'change' : 'add');

            if (count && Object.prototype.hasOwnProperty.call(totals, type)) {
                count.textContent = String(totals[type]);
            }
        });
    };

    widgets.forEach((widget) => {
        widget.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-reaction-button]');
            if (!button || !widget.contains(button) || widget.dataset.busy === 'true') {
                return;
            }

            const postId = button.dataset.postId || widget.dataset.postId || '';
            const reactionType = button.dataset.reactionType || '';
            const currentReaction = widget.dataset.currentReaction || '';
            const reactionAction = currentReaction === reactionType
                ? 'remove'
                : (currentReaction === '' ? 'add' : 'change');
            const endpoint = widget.dataset.endpoint || 'reaction.php';
            const csrfInput = widget.querySelector('[data-csrf-token]');
            const csrfToken = widget.dataset.csrfToken || (csrfInput ? csrfInput.value : '');

            if (!postId || !reactionType || !csrfToken) {
                setMessage(widget, 'Unable to prepare this reaction. Refresh the page and try again.');
                return;
            }

            const related = Array.from(widgets).filter((item) => item.dataset.postId === postId);
            related.forEach((item) => { item.dataset.busy = 'true'; });
            const buttons = related.flatMap((item) => Array.from(item.querySelectorAll('[data-reaction-button]')));
            buttons.forEach((item) => { item.disabled = true; });
            setMessage(widget, 'Saving reaction…');

            const body = new URLSearchParams({
                csrf_token: csrfToken,
                post_id: postId,
                reaction_type: reactionType,
                reaction_action: reactionAction
            });

            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: body.toString()
                });

                let result = null;
                try {
                    result = await response.json();
                } catch (error) {
                    result = null;
                }

                if (response.status === 401 || (result && result.login_required)) {
                    const loginUrl = widget.dataset.loginUrl || 'login.php';
                    window.location.assign(loginUrl);
                    return;
                }

                if (!response.ok || !result || result.success !== true) {
                    throw new Error(
                        result && result.message
                            ? result.message
                            : 'Unable to confirm your reaction. Refresh to check its saved state.'
                    );
                }

                related.forEach((item) => updateWidget(item, result.totals || {}, result.current_reaction || ''));

                setMessage(
                    widget,
                    result.current_reaction
                        ? 'Reaction saved.'
                        : 'Reaction removed.'
                );
            } catch (error) {
                setMessage(
                    widget,
                    'Unable to confirm your reaction. Refresh to check its saved state.'
                );
            } finally {
                window.clearTimeout(timeout);
                related.forEach((item) => { item.dataset.busy = 'false'; });
                buttons.forEach((item) => {
                    item.disabled = false;
                });
            }
        });
    });
})();