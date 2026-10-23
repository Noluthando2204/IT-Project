// Academic email is temporary booking input; PHP repeats this validation and never stores it.
(function () {
    'use strict';
    var inputs = document.querySelectorAll('[name="ticket_id"]');
    var wrap = document.querySelector('[data-student-email-field]');
    var email = document.querySelector('[name="student_email"]');
    if (!wrap || !email) return;
    var feedback = document.createElement('p');
    feedback.className = 'field-hint'; feedback.setAttribute('aria-live','polite');
    wrap.appendChild(feedback);
    function selectedStudent() {
        var ticket = document.querySelector('[name="ticket_id"]:checked');
        return ticket && ticket.getAttribute('data-ticket-type') === 'Student';
    }
    function check() {
        if (!selectedStudent()) { email.setCustomValidity(''); return; }
        var value = email.value.trim().toLowerCase();
        var domain = value.split('@')[1] || '';
        var valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
            && (domain.endsWith('.ac.za') || domain === 'tuks.co.za' || domain.endsWith('.tuks.co.za'));
        email.setCustomValidity(valid ? '' : 'Enter an eligible academic email ending in .ac.za or tuks.co.za.');
        feedback.textContent = valid ? 'Academic email format accepted; the server will check it again.' : 'Student tickets require an eligible academic email.';
    }
    function update() {
        var student = !!selectedStudent(); wrap.hidden = !student; email.required = student;
        if (!student) email.value = '';
        check();
    }
    inputs.forEach(function (input) { input.addEventListener('change',update); });
    email.addEventListener('input',check); update();
})();
