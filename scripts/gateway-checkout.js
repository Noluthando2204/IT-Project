// Continue the server-validated checkout without a second confirmation click.
(function () {
    var form = document.getElementById('gateway-checkout');
    if (form) form.requestSubmit();
})();
