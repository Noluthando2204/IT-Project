(function () {
    var button = document.querySelector('[data-resend-seconds]');
    if (!button) return;
    var deadline = Date.now() + Number(button.dataset.resendSeconds) * 1000;
    function update() {
        var seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        button.disabled = seconds > 0;
        button.textContent = seconds ? 'Resend link in ' + seconds + 's' : 'Resend link';
        if (seconds) window.setTimeout(update, 250);
    }
    update();
})();
