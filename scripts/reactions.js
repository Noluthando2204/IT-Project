/*One current reaction per attendee and post. */
(function () {
    'use strict';

    var allowedTypes = ['thumbs_up', 'clap', 'lightbulb'];

    function normaliseTotals(totals) {
        totals = totals || {};
        return {
            thumbs_up: Number(totals.thumbs_up || totals.like || 0),
            clap: Number(totals.clap || totals.applause || 0),
            lightbulb: Number(totals.lightbulb || totals.idea || 0)
        };
    }

    function currentReactionFromResponse(data) {
        var value = data.current_reaction
            || data.reaction_type
            || data.active_reaction
            || data.selected_reaction
            || '';

        return allowedTypes.indexOf(value) !== -1 ? value : '';
    }

    function setBusy(widget, busy) {
        widget.querySelectorAll('[data-reaction-type]').forEach(function (button) {
            button.disabled = busy;
        });
        widget.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function updateWidget(widget, totals, currentReaction) {
        totals = normaliseTotals(totals);

        allowedTypes.forEach(function (type) {
            var count = widget.querySelector('[data-reaction-count="' + type + '"]');
            if (count) {
                count.textContent = String(Math.max(0, totals[type]));
            }

            var button = widget.querySelector('[data-reaction-type="' + type + '"]');
            if (button) {
                var active = currentReaction === type;
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.setAttribute('data-active', active ? 'true' : 'false');
            }
        });

        widget.setAttribute('data-current-reaction', currentReaction || '');
    }

    function announce(widget, message) {
        var live = widget.querySelector('[data-reaction-message]');
        if (live) {
            live.textContent = message;
        }
    }

    function actionFor(widget, selectedType) {
        var current = widget.getAttribute('data-current-reaction') || '';
        if (current === selectedType) {
            return 'remove';
        }
        return current ? 'change' : 'add';
    }

    async function sendReaction(widget, button) {
        var postId = Number(widget.getAttribute('data-post-id'));
        var reactionType = button.getAttribute('data-reaction-type') || '';
        var csrfField = widget.querySelector('[data-csrf-token], input[name="csrf_token"]');
        var csrfToken = csrfField ? csrfField.value : '';
        var loginUrl = widget.getAttribute('data-login-url') || 'login.php';
        var authenticated = widget.getAttribute('data-authenticated') === 'true';

        if (!authenticated) {
            window.location.href = loginUrl;
            return;
        }

        if (!Number.isInteger(postId) || postId <= 0 || allowedTypes.indexOf(reactionType) === -1) {
            announce(widget, 'This reaction could not be sent.');
            return;
        }

        var reactionAction = actionFor(widget, reactionType);
        var body = new URLSearchParams();
        body.set('post_id', String(postId));
        body.set('reaction_type', reactionType);
        body.set('reaction_action', reactionAction);
        body.set('csrf_token', csrfToken);

        setBusy(widget, true);
        announce(widget, 'Updating reaction…');

        try {
            var response = await fetch('reaction.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: body.toString()
            });

            var data;
            try {
                data = await response.json();
            } catch (parseError) {
                throw new Error('invalid_response');
            }

            if (response.status === 401 || data.login_required === true) {
                window.location.href = loginUrl;
                return;
            }

            if (!response.ok || data.success !== true) {
                announce(widget, data.message || 'Your reaction could not be updated.');
                return;
            }

            var totals = data.totals || data.counts || {};
            var currentReaction = currentReactionFromResponse(data);

            if (reactionAction === 'remove') {
                currentReaction = '';
            } else if (!currentReaction) {
                currentReaction = reactionType;
            }

            updateWidget(widget, totals, currentReaction);
            announce(widget, data.message || 'Reaction updated.');
        } catch (error) {
            announce(widget, 'Your reaction could not be updated. Please try again.');
        } finally {
            setBusy(widget, false);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-reaction-widget]').forEach(function (widget) {
            widget.querySelectorAll('[data-reaction-type]').forEach(function (button) {
                button.addEventListener('click', function () {
                    sendReaction(widget, button);
                });
            });
        });
    });
}());


