(function () {
    'use strict';
    var banner = document.getElementById('reservation-timer');
    var text = document.getElementById('timer-text');
    if (!banner || !text) return;
    var expiry = Number(banner.dataset.expires);
    var offset = Number(banner.dataset.serverNow) - Date.now();
    if (!Number.isFinite(expiry) || !Number.isFinite(offset)) return;
    var key = 'eventnest.timer.position';
    function place(x, y) {
        var rect = banner.getBoundingClientRect();
        banner.style.left = Math.max(8, Math.min(x, window.innerWidth - rect.width - 8)) + 'px';
        banner.style.top = Math.max(8, Math.min(y, window.innerHeight - rect.height - 8)) + 'px';
        banner.style.right = 'auto'; banner.style.bottom = 'auto';
    }
    try {
        var stored = JSON.parse(localStorage.getItem(key));
        if (stored && Number.isFinite(stored.x) && Number.isFinite(stored.y)) place(stored.x, stored.y);
    } catch (_) { /* Storage may be disabled. */ }
    var handle = banner.querySelector('.timer-drag');
    var drag = null;
    handle.addEventListener('pointerdown', function (event) {
        if (event.button !== 0) return;
        var rect = banner.getBoundingClientRect();
        drag = {x: event.clientX - rect.left, y: event.clientY - rect.top};
        handle.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    handle.addEventListener('pointermove', function (event) {
        if (drag) place(event.clientX - drag.x, event.clientY - drag.y);
    });
    function save() {
        if (!drag) return;
        drag = null;
        var rect = banner.getBoundingClientRect();
        try { localStorage.setItem(key, JSON.stringify({x: rect.left, y: rect.top})); } catch (_) {}
    }
    handle.addEventListener('pointerup', save);
    handle.addEventListener('pointercancel', save);
    window.addEventListener('resize', function () {
        var rect = banner.getBoundingClientRect(); place(rect.left, rect.top);
    });
    async function expire() {
        text.textContent = 'Checking reservation…';
        try {
            var response = await fetch(banner.dataset.expireUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({booking_id: banner.dataset.bookingId, csrf_token: banner.dataset.csrf})
            });
            if (!response.ok) throw new Error('Unavailable');
            var result = await response.json();
            if (result.checkout || result.status === 'paid') { banner.hidden = true; banner.style.display = 'none'; return; }
            if (result.status === 'canceled' || result.status === 'failed') {
                window.location.assign(banner.dataset.expiredUrl); return;
            }
        } catch (_) { text.textContent = 'Rechecking reservation…'; }
        setTimeout(expire, 3000);
    }
    function tick() {
        var left = Math.max(0, Math.ceil((expiry - Date.now() - offset) / 1000));
        banner.classList.toggle('is-warning', left <= 20);
        if (!left) { banner.classList.add('is-expired'); expire(); return; }
        text.textContent = 'Ticket reserved for ' + String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        setTimeout(tick, 250);
    }
    tick();
}());
