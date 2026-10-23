// The server returns the current selection and all three totals after each change.
(function () {
    'use strict';
    document.addEventListener('click', async function (event) {
        var button = event.target.closest('[data-reaction-button]');
        if (!button) return;
        var widget = button.closest('[data-reaction-widget]');
        if (!widget || widget.dataset.busy === 'true') return;
        var type = button.getAttribute('data-reaction-type');
        var current = widget.getAttribute('data-current-reaction');
        var controls = widget.querySelectorAll('[data-reaction-button]');
        var message = widget.querySelector('[data-reaction-message]');
        widget.dataset.busy = 'true';
        controls.forEach(function (item) { item.disabled = true; });
        try {
            var response = await fetch(widget.getAttribute('data-endpoint'), {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
                body: new URLSearchParams({post_id: widget.dataset.postId, reaction_type: type,
                    reaction_action: current === type ? 'remove' : (current ? 'change' : 'add'),
                    csrf_token: widget.getAttribute('data-csrf-token')})
            });
            var data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save reaction.');
            widget.setAttribute('data-current-reaction', data.current_reaction || '');
            controls.forEach(function (item) {
                var value = item.getAttribute('data-reaction-type');
                var selected = data.current_reaction === value;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', String(selected));
                item.setAttribute('data-active', String(selected));
                var count = item.querySelector('[data-reaction-count]');
                if (count) count.textContent = data.totals[value] || 0;
            });
            if (message) message.textContent = 'Reaction updated.';
        } catch (error) { if (message) message.textContent = 'The reaction could not be saved. Please try again.'; }
        finally { widget.dataset.busy = 'false'; controls.forEach(function (item) { item.disabled = false; }); }
    });
})();
