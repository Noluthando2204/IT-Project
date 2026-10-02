

(function () {
    var REACTION_TYPES = ['thumbs_up', 'clap', 'lightbulb'];
  
    document.addEventListener('DOMContentLoaded', function () {
        var reactionBar = document.querySelector('.reaction-bar');
        if (!reactionBar) {
            return;
        }

        var postId = reactionBar.closest('[data-post-id]')
            ? reactionBar.closest('[data-post-id]').getAttribute('data-post-id')
            : null;
        var isLoggedIn = reactionBar.getAttribute('data-logged-in') === '1';

        if (!postId) {
            return;
        }

        reactionBar.querySelectorAll('.reaction-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!isLoggedIn) {
                    // Totals stay visible for logged-out visitors; direct
                    // the reaction attempt to login instead of silently failing.
                    window.location.href = 'login.php';
                    return;
                }
                handleReactionClick(reactionBar, button, postId);
            });
        });
    });

    function handleReactionClick(reactionBar, button, postId) {
        var reactionType = button.getAttribute('data-reaction-type');
        var currentReaction = reactionBar.getAttribute('data-user-reaction') || '';

        // Same reaction again -> remove. Different reaction -> change.
        // No existing reaction -> add.
        var action = 'add';
        if (currentReaction === reactionType) {
            action = 'remove';
        } else if (currentReaction !== '') {
            action = 'change';
        }

        var csrfTokenField = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfTokenField ? csrfTokenField.getAttribute('content') : '';

        button.disabled = true;

        fetch('reaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                post_id: postId,
                reaction_type: reactionType,
                reaction_action: action,
                csrf_token: csrfToken,
            }),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Reaction request failed');
                }
                return response.json();
            })
            .then(function (data) {
                // Expected shape (confirm exact keys with Unathi/Gustin):
                // { success: true, totals: { thumbs_up: n, clap: n, lightbulb: n }, user_reaction: 'clap'|null }
                if (!data || !data.success) {
                    return;
                }

                REACTION_TYPES.forEach(function (type) {
                    var countEl = reactionBar.querySelector('[data-reaction-count="' + type + '"]');
                    if (countEl && data.totals && typeof data.totals[type] !== 'undefined') {
                        countEl.textContent = data.totals[type];
                    }
                });

                reactionBar.setAttribute('data-user-reaction', data.user_reaction || '');
                reactionBar.querySelectorAll('.reaction-btn').forEach(function (btn) {
                    var isActive = btn.getAttribute('data-reaction-type') === data.user_reaction;
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            })
            .catch(function () {
                // Fail quietly - the visible totals simply stay as they were.
            })
            .finally(function () {
                button.disabled = false;
            });
    }
})();
