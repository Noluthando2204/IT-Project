/* ==========================================================================
   EventNest - scripts/main.js
   Owner: Thando

   Shared, non-database interface behaviour. Loaded with "defer" by
   shared-components/header.php on every page.

   First pass (dependency gate): mobile menu buttons and account menu.
   Planned for the 8 October bundle: live search suggestions (q + mode),
   filter-panel opening and confirmation dialogs.
   ========================================================================== */

(function () {
    'use strict';

    /**
     * Menu buttons (Screen 54): the guest button opens the public navigation;
     * the logged-in button opens the role sidebar. A button whose target is
     * not on the page is removed so it never appears as a dead control.
     */
    function initNavToggles() {
        var buttons = document.querySelectorAll('.nav-toggle');

        Array.prototype.forEach.call(buttons, function (toggleButton) {
            var targetId = toggleButton.getAttribute('aria-controls');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                toggleButton.parentNode.removeChild(toggleButton);
                return;
            }

            function setOpen(isOpen) {
                toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                target.classList.toggle('is-open', isOpen);
            }

            toggleButton.addEventListener('click', function () {
                setOpen(toggleButton.getAttribute('aria-expanded') !== 'true');
            });

            // Escape closes the menu and returns focus to its button.
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && toggleButton.getAttribute('aria-expanded') === 'true') {
                    setOpen(false);
                    toggleButton.focus();
                }
            });
        });
    }

    /** Closes the "Hi, <name>" account menu on Escape or an outside click. */
    function initAccountMenu() {
        var accountMenu = document.querySelector('.account-menu');
        if (!accountMenu) {
            return;
        }

        document.addEventListener('click', function (event) {
            if (accountMenu.open && !accountMenu.contains(event.target)) {
                accountMenu.open = false;
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && accountMenu.open) {
                accountMenu.open = false;
                var summary = accountMenu.querySelector('summary');
                if (summary) {
                    summary.focus();
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNavToggles();
        initAccountMenu();
    });
})();