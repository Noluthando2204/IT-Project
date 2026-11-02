(function () {
    'use strict';
    var button = document.getElementById('reset-send');
    var label = document.getElementById('reset-cooldown');
    if (!button || !label) return;
    var until = Number(button.dataset.cooldownUntil);
    function update() {
        var left = Math.max(0, Math.ceil((until - Date.now()) / 1000));
        button.disabled = left > 0;
        label.textContent = left ? "Didn't receive the email? Resend available in " + Math.floor(left / 60).toString().padStart(2, '0') + ':' + (left % 60).toString().padStart(2, '0') : '';
        if (left) setTimeout(update, 250);
    }
    update();
}());
