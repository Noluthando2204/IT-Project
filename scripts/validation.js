/*
  Client-side checks improve feedback only
 */
(function () {
    'use strict';

    function errorFor(field, message) {
        var wrap = field.closest('.field') || field.parentNode;
        var error = wrap ? wrap.querySelector('.field-error') : null;
        if (!error && wrap) {
            error = document.createElement('div');
            error.className = 'field-error';
            wrap.appendChild(error);
        }
        if (error) error.textContent = message || '';
        field.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    function validEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function validate(form) {
        var firstInvalid = null;
        var valid = true;
        Array.prototype.slice.call(form.querySelectorAll('[required]')).forEach(function (field) {
            if (field.disabled || field.closest('[hidden]')) return;
            var empty = field.type === 'checkbox' ? !field.checked : !String(field.value || '').trim();
            if (field.type === 'radio') {
                empty = !Array.prototype.some.call(form.querySelectorAll('input[type="radio"]'), function (radio) {
                    return radio.name === field.name && radio.checked && !radio.disabled;
                });
            }
            errorFor(field, empty ? 'This field is required.' : '');
            if (empty) { valid = false; firstInvalid = firstInvalid || field; }
        });

        Array.prototype.slice.call(form.querySelectorAll('input[type="email"]')).forEach(function (field) {
            if (field.value && !validEmail(field.value.trim())) {
                errorFor(field, 'Enter a valid email address.');
                valid = false; firstInvalid = firstInvalid || field;
            }
        });

        var password = form.querySelector('[name="password"], [name="new_password"]');
        var confirm = form.querySelector('[name="confirm_password"]');
        var passwordBytes = password ? new TextEncoder().encode(password.value).length : 0;
        if (password && password.value && (passwordBytes < 8 || passwordBytes > 72)) {
            errorFor(password, 'Use a password between 8 and 72 bytes long.');
            valid = false; firstInvalid = firstInvalid || password;
        }
        if (password && confirm && confirm.value !== password.value) {
            errorFor(confirm, 'Passwords do not match.');
            valid = false; firstInvalid = firstInvalid || confirm;
        }

        Array.prototype.slice.call(form.querySelectorAll('input[type="number"]')).forEach(function (field) {
            if (field.value === '') return;
            var value = Number(field.value);
            var min = field.min !== '' ? Number(field.min) : null;
            var max = field.max !== '' ? Number(field.max) : null;
            var integerRequired = !field.step || field.step === '1';
            if (!Number.isFinite(value) || (integerRequired && !Number.isInteger(value)) || (min !== null && value < min) || (max !== null && value > max)) {
                errorFor(field, 'Enter a valid value within the permitted range.');
                valid = false; firstInvalid = firstInvalid || field;
            }
        });

        var gate = form.querySelector('[name="career_gate_type"]');
        if (gate) {
            var fieldSelector = form.querySelector('[name="allowed_career_field"]');
            var domain = form.querySelector('[name="allowed_email_domain"]');
            if (gate.value === 'field' && (!fieldSelector || !fieldSelector.value)) {
                if (fieldSelector) errorFor(fieldSelector, 'Select the required career field.');
                valid = false; firstInvalid = firstInvalid || fieldSelector;
            }
            if (gate.value === 'email_domain' && (!domain || !domain.value.trim())) {
                if (domain) errorFor(domain, 'Enter the permitted email domain.');
                valid = false; firstInvalid = firstInvalid || domain;
            }
        }

        var studentEmail = form.querySelector('[name="student_email"]');
        if (studentEmail && !studentEmail.closest('[hidden]') && studentEmail.required) {
            var emailValue = studentEmail.value.trim().toLowerCase();
            var domainValue = emailValue.split('@')[1] || '';
            if (!validEmail(emailValue) || !(domainValue.endsWith('.ac.za') || domainValue === 'tuks.co.za' || domainValue.endsWith('.tuks.co.za'))) {
                errorFor(studentEmail, 'Use an eligible academic email ending in .ac.za or tuks.co.za.');
                valid = false; firstInvalid = firstInvalid || studentEmail;
            }
        }

        if (!valid && firstInvalid) firstInvalid.focus();
        return valid;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.matches('[data-validate]') && !validate(form)) event.preventDefault();
    });
})();
