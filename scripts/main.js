/* EventNest shared, non-database UI behaviour. Backend validates every operation. */
(function () {
    'use strict';
    document.querySelectorAll('input[type="password"]').forEach(function (input, index) {
        if (input.closest('.password-control')) return;
        if (!input.id) input.id = 'password-field-' + index;
        var wrapper = document.createElement('div');
        wrapper.className = 'password-control';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-toggle';
        toggle.setAttribute('aria-controls', input.id);
        toggle.setAttribute('aria-pressed', 'false');
        toggle.setAttribute('aria-label', 'Show password');
        toggle.innerHTML = '<svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9c4 7 14 7 18 0M5 12l-2 3m6-1-1 4m7-4 1 4m3-6 2 3"/></svg>'
            + '<svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        toggle.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(show));
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
        wrapper.appendChild(toggle);
    });
})();

(function () {
    'use strict';

    function qs(selector, root) { return (root || document).querySelector(selector); }
    function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

    document.addEventListener('submit', function(event) {
        var message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) event.preventDefault();
    });

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-nav-toggle]');
        if (toggle) {
            var links = qs('[data-nav-links]');
            if (links) {
                var open = links.classList.toggle('open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }

        var filterToggle = event.target.closest('[data-filter-toggle]');
        if (filterToggle) {
            var panel = qs('[data-filter-panel], .filters-panel, .filter-panel, .event-filters');
            if (panel) { var open = panel.classList.toggle('open'); filterToggle.setAttribute('aria-expanded',String(open)); }
        }
    });

    qsa('.ticket-option').forEach(function (option) {
        option.addEventListener('click', function () {
            qsa('.ticket-option').forEach(function (item) { item.classList.remove('selected'); });
            option.classList.add('selected');
        });
    });

    var gateSelect = qs('[name="career_gate_type"]');
    function updateGateFields() {
        if (!gateSelect) return;
        var fieldWrap = qs('[data-gate-field]');
        var domainWrap = qs('[data-gate-domain]');
        if (fieldWrap) fieldWrap.hidden = gateSelect.value !== 'field';
        if (domainWrap) domainWrap.hidden = gateSelect.value !== 'email_domain';
        var fieldInput = qs('[name="allowed_career_field"]');
        var domainInput = qs('[name="allowed_email_domain"]');
        if (fieldInput) fieldInput.required = gateSelect.value === 'field';
        if (domainInput) domainInput.required = gateSelect.value === 'email_domain';
    }
    if (gateSelect) {
        gateSelect.addEventListener('change', updateGateFields);
        updateGateFields();
    }

    var ratingInputs = qsa('input[name="rating"]');
    function updateReviewFields() {
        var checked = qs('input[name="rating"]:checked');
        var rating = checked ? parseInt(checked.value, 10) : 0;
        var high = qs('[data-high-rating]');
        var low = qs('[data-low-rating]');
        if (high) high.hidden = !(rating >= 4);
        if (low) low.hidden = !(rating > 0 && rating < 4);
        var tags = qs('[name="compliment_tags[]"]');
        var comment = qs('[name="complaint_text"]');
        if (tags) tags.required = false;
        if (comment) comment.required = rating > 0 && rating < 4;
    }
    ratingInputs.forEach(function (input) { input.addEventListener('change', updateReviewFields); });
    updateReviewFields();

    var contactCheckbox = qs('[data-contact-permission]');
    function updateContactField() {
        var wrap = qs('[data-contact-email]');
        var input = wrap ? qs('input[type="email"]', wrap) : null;
        if (wrap && contactCheckbox) wrap.hidden = !contactCheckbox.checked;
        if (input && contactCheckbox) input.required = contactCheckbox.checked;
    }
    if (contactCheckbox) {
        contactCheckbox.addEventListener('change', updateContactField);
        updateContactField();
    }

    qsa('form[data-live-search]').forEach(function (form) {
        var input = qs('input[name="q"]', form);
        var box = qs('[data-search-suggestions]', form);
        if (!input || !box) return;
        var timer, controller, serial = 0, focused = -1;
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        box.id = input.id + '-suggestions';
        input.setAttribute('aria-controls', box.id);
        function close() { box.hidden = true; box.classList.remove('open'); input.setAttribute('aria-expanded','false'); focused = -1; }
        input.addEventListener('input', function () {
            clearTimeout(timer); if (controller) controller.abort();
            var request = ++serial, query = input.value.trim();
            if (query.length < 2) { close(); return; }
            timer = setTimeout(async function () {
                controller = new AbortController();
                try {
                    var endpoint = form.getAttribute('action') || 'search-results.php';
                    var response = await fetch(endpoint + '?mode=suggest&q=' + encodeURIComponent(query), {signal: controller.signal, headers:{Accept:'application/json'}});
                    if (!response.ok) throw new Error('Search unavailable');
                    var data = await response.json(); if (request !== serial) return;
                    box.replaceChildren();
                    (data.suggestions || []).forEach(function (item) {
                        var url = new URL(item.url, location.href);
                        if (url.origin !== location.origin) return;
                        var link = document.createElement('a'); link.className = 'suggestion-item'; link.href = url.href;
                        link.textContent = item.label; box.appendChild(link);
                    });
                    box.hidden = box.children.length === 0; box.classList.toggle('open', !box.hidden);
                    input.setAttribute('aria-expanded', String(!box.hidden)); focused = -1;
                } catch (error) { if (request === serial) close(); }
            },260);
        });
        form.addEventListener('keydown', function (event) {
            var links = Array.from(box.querySelectorAll('a'));
            if (event.key === 'Escape') { close(); input.focus(); }
            if (!links.length || box.hidden) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                focused = focused < 0 ? (event.key === 'ArrowDown' ? 0 : links.length - 1)
                    : (focused + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
                links[focused].focus();
            }
        });
        document.addEventListener('click', function(event) { if (!form.contains(event.target)) close(); });
    });

    document.addEventListener('click', function (event) {
        qsa('[data-suggestions].open').forEach(function (box) {
            if (!box.parentNode.contains(event.target)) box.classList.remove('open');
        });
    });
})();

(function () {
    var field = document.querySelector('[data-media-preview]'), box = document.querySelector('[data-upload-preview]'), previous;
    if (!field || !box) return;
    field.addEventListener('change',function () {
        if (previous) URL.revokeObjectURL(previous); box.replaceChildren();
        var file = field.files[0]; if (!file) return;
        var media = document.createElement(file.type.startsWith('video/') ? 'video' : 'img');
        if (media.tagName === 'VIDEO') media.controls = true; else media.alt = 'Selected post image preview';
        previous = URL.createObjectURL(file); media.src = previous; box.appendChild(media);
    });
})();

(function () {
    var form = document.querySelector('.booking-page form'), total = document.querySelector('[data-booking-total]');
    if (!form || !total) return;
    function update() {
        var ticket = form.querySelector('[name="ticket_id"]:checked'), quantity = form.querySelector('[name="quantity"]');
        if (!ticket) { total.textContent = 'Select a ticket'; return; }
        var estimate = Number(ticket.dataset.ticketPrice) * Number(quantity.value);
        total.textContent = Number.isFinite(estimate) && estimate >= 0 ? 'R' + estimate.toFixed(2) : 'Check quantity';
    }
    form.addEventListener('input',update); form.addEventListener('change',update); update();
})();
// Reservation Timer Logic
document.addEventListener('DOMContentLoaded', () => {
    const timerBanner = document.getElementById('reservation-timer');
    const timerText = document.getElementById('timer-text');
    
    if (timerBanner && timerText) {
        // Get the deadline from a data attribute we will set on the body or banner
        // We will read it from a global variable set by PHP
        if (typeof window.eventnestBookingDeadline !== 'undefined') {
            timerBanner.style.display = 'block';
            const deadline = window.eventnestBookingDeadline; // Unix timestamp in seconds

            const updateTimer = () => {
                const now = Math.floor(Date.now() / 1000);
                const timeLeft = deadline - now;

                if (timeLeft <= 0) {
                    timerText.textContent = "Reservation expired";
                    timerBanner.style.backgroundColor = "#D9363E"; // Red
                    // Redirect to events page
                    window.location.href = "events.php"; 
                    return;
                }

                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                // Format as MM:SS
                timerText.textContent = `Ticket reserved for ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                if (timeLeft <= 20) {
                    timerBanner.style.backgroundColor = "#D9363E"; // Red warning at 20s
                    timerText.textContent = `Hurry! Reservation expires in ${String(seconds).padStart(2, '0')} seconds`;
                }

                setTimeout(updateTimer, 1000);
            };

            updateTimer();
        }
    }
});// Reservation Timer Logic
document.addEventListener('DOMContentLoaded', () => {
    const timerBanner = document.getElementById('reservation-timer');
    const timerText = document.getElementById('timer-text');
    
    if (timerBanner && timerText) {
        if (typeof window.eventnestBookingDeadline !== 'undefined') {
            timerBanner.style.display = 'block';
            const deadline = window.eventnestBookingDeadline; 

            const updateTimer = () => {
                const now = Math.floor(Date.now() / 1000);
                let timeLeft = deadline - now;

                // Fix: Snap to 4:00 if it loads at 3:55 due to network delay
                if (timeLeft > 230 && timeLeft < 240) {
                    timeLeft = 240; 
                }

                if (timeLeft <= 0) {
                    timerText.textContent = "Reservation expired";
                    window.location.href = "events.php"; 
                    return;
                }

                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerText.textContent = `Ticket reserved for ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

                if (timeLeft <= 20) {
                    timerBanner.classList.add('is-warning');
                    timerText.textContent = `Hurry! Expires in ${String(seconds).padStart(2, '0')}s`;
                } else {
                    timerBanner.classList.remove('is-warning');
                }

                setTimeout(updateTimer, 1000);
            };

            updateTimer();
        }
    }
});