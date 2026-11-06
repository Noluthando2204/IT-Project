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

    function validSouthAfricanMobile(value) {
        return /^0\d{9}$/.test(value) && ['060','061','062','063','064','065','066','067','068','069','071','072','073','074','076','077','078','079','081','082','083','084'].indexOf(value.slice(0, 3)) !== -1;
    }

    function checkPhone(field) {
        var value = field.value;
        var message = '';
        if (!value && field.required) message = 'Phone number is required.';
        else if (!value) message = '';
        else if (!/^\d+$/.test(value)) message = 'Phone number must contain digits only.';
        else if (value.length !== 10) message = 'Enter exactly 10 digits.';
        else if (!validSouthAfricanMobile(value)) message = 'Enter a valid South African mobile number.';
        errorFor(field, message);
        field.setCustomValidity(message);
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

        var password = form.querySelector('[name="new_password"], [name="password"][data-password-rules]');
        var confirm = form.querySelector('[name="confirm_password"]');
        var passwordBytes = password ? new TextEncoder().encode(password.value).length : 0;
        if (password && password.value && (Array.from(password.value).length < 16 || passwordBytes > 72 || /[\s\u0085]/u.test(password.value) || !/[A-Z]/.test(password.value) || !/[a-z]/.test(password.value) || !/[0-9]/.test(password.value) || !/[^A-Za-z0-9\s]/.test(password.value))) {
            errorFor(password, passwordBytes > 72 ? 'This password is too long. Choose a shorter one that meets the rules below.' : 'Use at least 16 characters with uppercase and lowercase letters, a number and a special character, without spaces.');
            valid = false; firstInvalid = firstInvalid || password;
        }

        var phone = form.querySelector('[name="phone"][data-sa-mobile]');
        if (phone) {
            checkPhone(phone);
            if (!phone.validity.valid) { valid = false; firstInvalid = firstInvalid || phone; }
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

    document.querySelectorAll('[data-password-rules]').forEach(function (field) {
        var wrap=field.closest('.field'), panel=wrap.querySelector('[data-password-rules-panel]');
        var confirm=field.form.querySelector('[name="confirm_password"]');
        var match=confirm && confirm.closest('.field').querySelector('[data-password-match]');
        function update() {
            var value=field.value;
            var checks={length:Array.from(value).length>=16,max:new TextEncoder().encode(value).length<=72,
                upper:/[A-Z]/.test(value),lower:/[a-z]/.test(value),number:/[0-9]/.test(value),
                special:/[^A-Za-z0-9\s]/.test(value),space:!/[\s\u0085]/u.test(value)};
            if (panel) panel.hidden = value.length === 0;
            if (panel) panel.querySelectorAll('[data-rule]').forEach(function (rule) {
                var met=checks[rule.dataset.rule];
                rule.classList.toggle('is-met',met);
                if (!rule.dataset.label) rule.dataset.label = rule.textContent.slice(2);
                rule.textContent=(met?'\u2713 Met: ':'\u2717 Needed: ')+rule.dataset.label;
            });
            if (match) {
                var equal=confirm.value!=='' && value===confirm.value;
                match.textContent=confirm.value===''?'':equal?'Passwords match.':'Passwords do not match.';
                match.classList.toggle('is-match',equal);
            }
        }
        field.addEventListener('input',update);
        if (confirm) confirm.addEventListener('input',update);
        update();
    });
    document.querySelectorAll('[name="phone"][data-sa-mobile]').forEach(function (field) {
        field.addEventListener('input', function () { checkPhone(field); });
        field.addEventListener('blur', function () { if (field.value) checkPhone(field); });
    });
})();
