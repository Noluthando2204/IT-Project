(function () {
    'use strict';
    var page = document.querySelector('[data-payment-pending="1"]');
    if (!page) return;
    var deadline = Date.now() + 60000;
    async function check() {
        try {
            var response = await fetch(page.dataset.statusUrl, {credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) return;
            var result = await response.json();
            if (result.status && result.status !== 'pending') { window.location.reload(); return; }
        } catch (_) { /* Leave the truthful pending state visible. */ }
        if (Date.now() < deadline) setTimeout(check, 3000);
    }
    setTimeout(check, 3000);
}());
