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
            if (panel) {
                var open = panel.classList.toggle('open');
                filterToggle.setAttribute('aria-expanded', String(open));
                var layout = panel.closest('.search-layout--collapsible');
                if (layout) {
                    layout.classList.toggle('is-filters-open', open);
                    filterToggle.textContent = open ? 'Hide filters' : 'Show filters';
                }
            }
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

    qsa('form[data-live-search]').forEach(function (form, index) {
        var input = qs('input[name="q"]', form);
        var box = qs('[data-search-suggestions]', form);
        if (!input || !box || form.dataset.autocompleteReady) return;
        form.dataset.autocompleteReady = 'true';
        var timer, controller, serial = 0, focused = -1;
        box.id = (input.id || 'event-search-' + index) + '-suggestions';
        box.setAttribute('role', 'listbox');
        box.setAttribute('aria-label', 'Events, career fields and hashtags');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', box.id);
        function close() {
            clearTimeout(timer);
            if (controller) controller.abort();
            serial++;
            box.hidden = true;
            box.classList.remove('open');
            box.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            focused = -1;
        }
        input.addEventListener('input', function (event) {
            close();
            var query = input.value.trim(), request = serial;
            if (event.isComposing || Array.from(query).length < 2 || Array.from(query).length > 100) return;
            // Show that suggestions are on their way, so a slow server never looks like "nothing happened".
            var loading = document.createElement('p');
            loading.className = 'suggestion-empty suggestion-loading';
            loading.setAttribute('role', 'status');
            loading.textContent = 'Searching…';
            box.appendChild(loading);
            box.hidden = false;
            box.classList.add('open');
            input.setAttribute('aria-expanded', 'true');
            timer = setTimeout(async function () {
                controller = new AbortController();
                try {
                    var endpoint = new URL(form.getAttribute('action') || 'search-results.php', location.href);
                    endpoint.search = new URLSearchParams({mode: 'suggest', q: query}).toString();
                    var response = await fetch(endpoint.href, {signal: controller.signal, headers: {Accept: 'application/json'}});
                    if (!response.ok) throw new Error('Search unavailable');
                    var data = await response.json();
                    if (request !== serial || input.value.trim() !== query) return;
                    box.replaceChildren();
                    (Array.isArray(data.suggestions) ? data.suggestions : []).slice(0, 8).forEach(function (item) {
                        if (typeof item.label !== 'string') return;
                        var destination;
                        if (item.type === 'event' && Number.isSafeInteger(item.event_id) && item.event_id > 0) {
                            destination = 'event-detail.php?event_id=' + item.event_id;
                        } else if (item.type === 'category' && Number.isSafeInteger(item.category_id) && item.category_id > 0) {
                            destination = 'events.php?category_id=' + item.category_id;
                        } else if (item.type === 'hashtag' && typeof item.tag === 'string' && /^[A-Za-z0-9_]{1,50}$/.test(item.tag)) {
                            destination = 'tag.php?tag=' + encodeURIComponent(item.tag);
                        } else { return; }
                        var link = document.createElement('a');
                        link.className = 'suggestion-item';
                        link.id = box.id + '-' + box.children.length;
                        link.setAttribute('role', 'option');
                        link.setAttribute('aria-selected', 'false');
                        link.tabIndex = -1;
                        link.href = new URL(destination, endpoint).href;
                        var label = document.createElement('span');
                        label.className = 'suggestion-label';
                        label.textContent = item.label;
                        var meta = document.createElement('span');
                        meta.className = 'suggestion-meta';
                        meta.textContent = {event: 'Event', category: 'Career field', hashtag: 'Hashtag'}[item.type];
                        link.append(label, meta);
                        box.appendChild(link);
                    });
                    if (!box.children.length) {
                        var empty = document.createElement('p');
                        empty.className = 'suggestion-empty';
                        empty.setAttribute('role', 'status');
                        empty.textContent = 'No matching events, career fields or hashtags.';
                        box.appendChild(empty);
                    }
                    box.hidden = false;
                    box.classList.toggle('open', !box.hidden);
                    input.setAttribute('aria-expanded', String(!box.hidden));
                } catch (error) {
                    if (request !== serial || error.name === 'AbortError') return;
                    close();
                    var notice = document.createElement('p');
                    notice.className = 'suggestion-empty';
                    notice.setAttribute('role', 'status');
                    notice.textContent = 'Suggestions unavailable. Press Search to try the results page.';
                    box.appendChild(notice);
                    box.hidden = false;
                    box.classList.add('open');
                    input.setAttribute('aria-expanded', 'true');
                }
            }, 200);
        });
        input.addEventListener('compositionend', function () { input.dispatchEvent(new Event('input')); });
        input.addEventListener('keydown', function (event) {
            if (event.isComposing) return;
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            var links = Array.from(box.querySelectorAll('a'));
            if (box.hidden || !links.length) return;
            if (event.key === 'Enter' && focused >= 0) {
                event.preventDefault();
                links[focused].click();
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                focused = focused < 0 ? (event.key === 'ArrowDown' ? 0 : links.length - 1)
                    : (focused + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
                links.forEach(function (link, i) { link.setAttribute('aria-selected', String(i === focused)); });
                input.setAttribute('aria-activedescendant', links[focused].id);
                links[focused].scrollIntoView({block: 'nearest'});
            }
        });
        form.addEventListener('submit', close);
        form.addEventListener('focusout', function (event) { if (!form.contains(event.relatedTarget)) close(); });
        document.addEventListener('click', function (event) { if (!input.contains(event.target) && !box.contains(event.target)) close(); });
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
/* Live countdown for the "Payment pending" hold notice; updates when the hold ends. */
(function () {
    'use strict';
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
        var left = parseInt(el.getAttribute('data-countdown'), 10) || 0;
        var timer = setInterval(function () {
            left -= 1;
            if (left <= 0) {
                clearInterval(timer);
                el.textContent = 'now. Refresh to check availability';
                return;
            }
            var m = Math.floor(left / 60), sec = left % 60;
            el.textContent = m + ':' + (sec < 10 ? '0' : '') + sec;
        }, 1000);
    });
})();

/* Live countdown for the "Payment pending" hold notice; updates when the hold ends. */
(function () {
    'use strict';
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
        var left = parseInt(el.getAttribute('data-countdown'), 10) || 0;
        var timer = setInterval(function () {
            left -= 1;
            if (left <= 0) {
                clearInterval(timer);
                el.textContent = 'now. Refresh to check availability';
                return;
            }
            var m = Math.floor(left / 60), sec = left % 60;
            el.textContent = m + ':' + (sec < 10 ? '0' : '') + sec;
        }, 1000);
    });
})();
