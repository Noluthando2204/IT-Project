/* EventNest shared, non-database UI behaviour.
   Backend validates every operation. */
(function () {
    'use strict';

    function initNavToggles() {
        var buttons = document.querySelectorAll('.nav-toggle[aria-controls]');
        var activeButtons = [];

        Array.prototype.forEach.call(buttons, function (button) {
            var targetId = button.getAttribute('aria-controls');
            var target = document.getElementById(targetId);

            if (!target) {
                button.hidden = true;
                return;
            }

            button.hidden = false;

            function setOpen(open) {
                button.setAttribute('aria-expanded', String(open));
                target.classList.toggle('is-open', open);
            }

            setOpen(false);

            button.addEventListener('click', function () {
                var isOpen = button.getAttribute('aria-expanded') === 'true';
                setOpen(!isOpen);
            });

            target.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    setOpen(false);
                }
            });

            activeButtons.push({
                button: button,
                setOpen: setOpen
            });
        });

        if (activeButtons.length > 0) {
            document.documentElement.classList.add('js', 'en-nav-ready');
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            activeButtons.forEach(function (item) {
                if (item.button.getAttribute('aria-expanded') === 'true') {
                    item.setOpen(false);
                    item.button.focus();
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavToggles);
    } else {
        initNavToggles();
    }
})();