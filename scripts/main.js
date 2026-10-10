/* EventNest shared, non-database UI behaviour. Backend validates every operation. */
(function () {
    'use strict';

    function initNavToggles() {
        var buttons = document.querySelectorAll('.nav-toggle[aria-controls]');
        Array.prototype.forEach.call(buttons, function (button) {
            var target = document.getElementById(button.getAttribute('aria-controls'));
            if (!target) { button.hidden = true; return; }
            button.hidden = false;
            document.documentElement.classList.add('en-nav-ready');
            function setOpen(open) {
                button.setAttribute('aria-expanded', String(open));
                target.classList.toggle('is-open', open);
            }
            setOpen(false);
            button.addEventListener('click', function () {
                setOpen(button.getAttribute('aria-expanded') !== 'true');
            });
            target.addEventListener('click', function (event) {
                if (event.target.closest('a')) { setOpen(false); }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
                    setOpen(false); button.focus();
                }
            });
        });
    }

    function initAccountMenu() {
        Array.prototype.forEach.call(document.querySelectorAll('details.account-menu'), function (menu) {
            document.addEventListener('click', function (event) {
                if (menu.open && !menu.contains(event.target)) { menu.open = false; }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && menu.open) {
                    menu.open = false;
                    var summary = menu.querySelector('summary');
                    if (summary) { summary.focus(); }
                }
            });
        });
    }

    function initFilterPanels() {
        Array.prototype.forEach.call(document.querySelectorAll('[aria-controls="search-filters"], [data-filter-toggle]'), function (button) {
            var targetId = button.getAttribute('aria-controls') || button.getAttribute('data-filter-toggle');
            var panel = targetId ? document.getElementById(targetId) : null;
            if (!panel) { button.hidden = true; return; }
            panel.classList.add('en-filters-ready');
            function setOpen(open) {
                panel.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', String(open));
                button.textContent = open ? 'Hide filters' : 'Show filters';
            }
            // Desktop filters visible by default, small screens start closed.
            var media = window.matchMedia('(max-width: 991px)');
            setOpen(!media.matches);
            button.addEventListener('click', function () {
                setOpen(button.getAttribute('aria-expanded') !== 'true');
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true' && media.matches) {
                    setOpen(false); button.focus();
                }
            });
            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', function () { setOpen(!media.matches); });
            }
        });
    }

    function initLiveSearch() {
        Array.prototype.forEach.call(document.querySelectorAll('input[name="q"][type="search"], input[data-live-search][name="q"]'), function (input) {
            var form = input.closest('form');
            if (!form) { return; }
            var dropdown = document.createElement('div');
            dropdown.className = 'search-suggestions';
            dropdown.id = 'en-search-suggestions-' + Math.random().toString(36).slice(2);
            dropdown.setAttribute('role', 'listbox');
            dropdown.setAttribute('aria-label', 'Search suggestions');
            dropdown.hidden = true;
            form.appendChild(dropdown);
            input.setAttribute('aria-controls', dropdown.id);
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('autocomplete', 'off');
            var timer = null;
            var controller = null;
            var active = -1;
            var suggestions = [];
            var serial = 0;
            var base = (function () {
                var script = document.querySelector('script[src$="scripts/main.js"]');
                if (script) { return new URL('../', script.src); }
                return new URL('./', window.location.href);
            }());
            function close() {
                dropdown.hidden = true;
                dropdown.replaceChildren();
                suggestions = []; active = -1;
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
            }
            function setActive(index) {
                var options = dropdown.querySelectorAll('[role="option"]');
                if (!options.length) { return; }
                active = (index + options.length) % options.length;
                Array.prototype.forEach.call(options, function (option, i) {
                    var selected = i === active;
                    option.classList.toggle('is-active', selected);
                    option.setAttribute('aria-selected', String(selected));
                });
                input.setAttribute('aria-activedescendant', options[active].id);
                options[active].scrollIntoView({block: 'nearest'});
            }
            function render(items) {
                close();
                suggestions = items.filter(function (item) {
                    return item && ['event', 'category', 'hashtag'].indexOf(item.type) !== -1 &&
                        typeof item.label === 'string' && item.label.length <= 160 &&
                        typeof item.url === 'string';
                }).slice(0, 10);
                if (!suggestions.length) { return; }
                suggestions.forEach(function (item, index) {
                    // Only allow approved routes; JSON is never treated as HTML.
                    var url;
                    try { url = new URL(item.url, base); } catch (_error) { return; }
                    var accepted = {
                        event: 'event-detail.php', category: 'events.php', hashtag: 'tag.php'
                    };
                    if (url.origin !== window.location.origin ||
                        url.pathname !== new URL(accepted[item.type], base).pathname) { return; }
                    var option = document.createElement('button');
                    option.type = 'button'; option.className = 'search-suggestion';
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.id = dropdown.id + '-option-' + index;
                    var label = document.createElement('span'); label.textContent = item.label;
                    var type = document.createElement('span'); type.className = 'search-suggestion-type';
                    type.textContent = item.type === 'hashtag' ? 'Hashtag' : item.type === 'category' ? 'Career field' : 'Event';
                    option.appendChild(label); option.appendChild(type);
                    option.addEventListener('click', function () { window.location.assign(url.href); });
                    dropdown.appendChild(option);
                    option.dataset.url = url.href;
                });
                if (!dropdown.children.length) { close(); return; }
                dropdown.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }
            input.addEventListener('input', function () {
                clearTimeout(timer); serial++;
                if (controller) { controller.abort(); }
                close();
                var q = input.value.trim();
                if (Array.from(q).length < 2 || Array.from(q).length > 100) { return; }
                var requestId = serial;
                timer = setTimeout(function () {
                    controller = new AbortController();
                    var url = new URL('search-results.php', base);
                    url.searchParams.set('q', q);
                    url.searchParams.set('mode', 'suggest');
                    fetch(url.href, {headers: {'Accept': 'application/json'}, signal: controller.signal, credentials: 'same-origin'})
                        .then(function (response) { if (!response.ok) { throw new Error('Unavailable'); } return response.json(); })
                        .then(function (data) {
                            if (requestId === serial && input.value.trim() === q && data && Array.isArray(data.suggestions)) {
                                render(data.suggestions);
                            }
                        })
                        .catch(function (error) { if (error.name !== 'AbortError') { close(); } });
                }, 250);
            });
            input.addEventListener('keydown', function (event) {
                if (dropdown.hidden) { return; }
                var options = dropdown.querySelectorAll('[role="option"]');
                if (event.key === 'ArrowDown') { event.preventDefault(); setActive(active + 1); }
                else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active < 0 ? options.length - 1 : active - 1); }
                else if (event.key === 'Enter' && active >= 0 && options[active]) {
                    event.preventDefault(); window.location.assign(options[active].dataset.url);
                } else if (event.key === 'Escape') { event.preventDefault(); close(); }
            });
            document.addEventListener('pointerdown', function (event) {
                if (!form.contains(event.target)) { close(); }
            });
            form.addEventListener('submit', function () { clearTimeout(timer); if (controller) { controller.abort(); } });
        });
    }

    function initConfirmationDialogs() {
        // Opt-in API for existing actions: data-confirm="Message" on a submit button or link.
        // The existing server handler must independently check auth/ownership/CSRF.
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-confirm]');
            if (!trigger || trigger.dataset.confirmAccepted === 'true') { return; }
            var message = trigger.getAttribute('data-confirm');
            if (!message) { return; }
            event.preventDefault();
            // Native browser confirmation blocks the destructive action until explicit acceptance.
            if (!window.confirm(message)) { return; }
            if (trigger.matches('a[href]')) { window.location.assign(trigger.href); return; }
            if (trigger.matches('button[type="submit"], input[type="submit"]') && trigger.form) {
                // requestSubmit preserves required-field validation and the clicked submitter.
                trigger.dataset.confirmAccepted = 'true';
                try { trigger.form.requestSubmit(trigger); }
                finally { delete trigger.dataset.confirmAccepted; }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNavToggles();
        initAccountMenu();
        initFilterPanels();
        initLiveSearch();
        initConfirmationDialogs();
    });
}());