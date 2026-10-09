/*
  Client-side checks improve feedback only
 */
(function () {
    'use strict';

    var studentDomains = ['ac.za', 'tuks.co.za'];

    function isVisible(field) {
        if (!field || field.disabled || field.type === 'hidden') {
            return false;
        }

        var wrapper = field.closest('[hidden]');
        return wrapper === null;
    }

    function errorId(field) {
        return (field.id || field.name || 'field') + '-error';
    }

    function clearError(field) {
        if (!field) {
            return;
        }

        field.removeAttribute('aria-invalid');
        var id = errorId(field);
        var error = document.getElementById(id);
        if (error) {
            error.remove();
        }

        var describedBy = (field.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(function (value) {
                return value && value !== id;
            });

        if (describedBy.length) {
            field.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            field.removeAttribute('aria-describedby');
        }
    }

    function showError(field, message) {
        clearError(field);

        var id = errorId(field);
        var error = document.createElement('p');
        error.id = id;
        error.className = 'field-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;

        field.setAttribute('aria-invalid', 'true');
        var describedBy = (field.getAttribute('aria-describedby') || '').trim();
        field.setAttribute(
            'aria-describedby',
            describedBy ? describedBy + ' ' + id : id
        );

        var container = field.closest('.form-field, .field, .form-group, [data-field-wrapper]');
        if (container) {
            container.appendChild(error);
        } else {
            field.insertAdjacentElement('afterend', error);
        }
    }

    function getValue(field) {
        if (!field) {
            return '';
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? field.value : '';
        }

        return String(field.value || '').trim();
    }

    function emailLooksValid(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function studentEmailIsValid(value) {
        if (!emailLooksValid(value)) {
            return false;
        }

        var domain = value.toLowerCase().split('@').pop();
        return domain === 'tuks.co.za'
            || domain.endsWith('.tuks.co.za')
            || domain.endsWith('.ac.za');
    }

    function selectedTicketType(form) {
        var checked = form.querySelector('[name="ticket_id"]:checked');
        if (checked) {
            return String(checked.getAttribute('data-ticket-type') || '').trim();
        }

        var select = form.querySelector('select[name="ticket_id"]');
        if (select && select.selectedIndex >= 0) {
            var option = select.options[select.selectedIndex];
            return String(option.getAttribute('data-ticket-type') || '').trim();
        }

        var explicit = form.querySelector('[data-selected-ticket-type]');
        return explicit ? String(explicit.getAttribute('data-selected-ticket-type') || '').trim() : '';
    }

    function eventGateType(form) {
        var selectors = [
            '[name="career_gate_type"]',
            '[name="career_goal_type"]',
            '[name="CareerGateType"]',
            '[name="CareerGoalType"]'
        ];

        for (var i = 0; i < selectors.length; i += 1) {
            var field = form.querySelector(selectors[i]);
            if (field) {
                return String(field.value || '').trim();
            }
        }

        return '';
    }

    function findField(form, names) {
        for (var i = 0; i < names.length; i += 1) {
            var field = form.querySelector('[name="' + names[i] + '"]');
            if (field) {
                return field;
            }
        }
        return null;
    }

    function toggleConditionalFields(form) {
        var gateType = eventGateType(form);
        var careerField = findField(form, ['allowed_career_field', 'AllowedCareerField']);
        var emailDomain = findField(form, ['allowed_email_domain', 'AllowedEmailDomain']);

        [
            { field: careerField, needed: gateType === 'field' },
            { field: emailDomain, needed: gateType === 'email_domain' }
        ].forEach(function (item) {
            if (!item.field) {
                return;
            }

            var wrapper = item.field.closest('[data-gate-field], .conditional-field');
            if (wrapper) {
                wrapper.hidden = !item.needed;
            }

            item.field.required = item.needed;
            if (!item.needed) {
                clearError(item.field);
            }
        });

        var studentEmail = form.querySelector('[name="student_email"]');
        if (studentEmail) {
            var studentNeeded = selectedTicketType(form).toLowerCase() === 'student';
            var studentWrapper = studentEmail.closest('[data-student-email], .student-email-field, .conditional-field');
            if (studentWrapper) {
                studentWrapper.hidden = !studentNeeded;
            }
            studentEmail.required = studentNeeded;
            if (!studentNeeded) {
                clearError(studentEmail);
            }
        }
    }

    function validateRequired(field) {
        if (!field.required || !isVisible(field)) {
            return true;
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            if (!field.checked) {
                showError(field, 'Please complete this field before continuing.');
                return false;
            }
            return true;
        }

        if (getValue(field) === '') {
            showError(field, 'Please complete this field before continuing.');
            return false;
        }

        return true;
    }

    function validateEmail(field) {
        if (!isVisible(field)) {
            return true;
        }

        var value = getValue(field);
        if (value === '') {
            return true;
        }

        if ((field.type === 'email' || /email/i.test(field.name || '')) && !emailLooksValid(value)) {
            showError(field, 'Enter a valid email address.');
            return false;
        }

        return true;
    }

    function validateNumber(field) {
        if (!isVisible(field)) {
            return true;
        }

        var name = String(field.name || '').toLowerCase();
        var numericField = field.type === 'number' || /(quantity|capacity|price)$/.test(name) || /\[(quantity|capacity|price)\]/.test(name);
        if (!numericField || getValue(field) === '') {
            return true;
        }

        var value = Number(field.value);
        if (!Number.isFinite(value)) {
            showError(field, 'Enter a valid number.');
            return false;
        }

        if ((name.indexOf('quantity') !== -1 || name.indexOf('capacity') !== -1) && value <= 0) {
            showError(field, 'Enter a value greater than zero.');
            return false;
        }

        if (name.indexOf('price') !== -1 && value < 0) {
            showError(field, 'Price cannot be negative.');
            return false;
        }

        if (field.min !== '' && value < Number(field.min)) {
            showError(field, 'The value is below the minimum allowed.');
            return false;
        }

        if (field.max !== '' && value > Number(field.max)) {
            showError(field, 'The value is above the maximum allowed.');
            return false;
        }

        return true;
    }

    function validateDate(field) {
        if (!isVisible(field) || (field.type !== 'date' && field.type !== 'datetime-local')) {
            return true;
        }

        var value = getValue(field);
        if (value === '') {
            return true;
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            showError(field, 'Enter a valid date.');
            return false;
        }

        return true;
    }

    function validatePasswords(form) {
        var confirmField = findField(form, ['confirm_password', 'password_confirm', 'confirmPassword']);
        if (!confirmField || !isVisible(confirmField) || getValue(confirmField) === '') {
            return true;
        }

        var passwordField = findField(form, ['new_password', 'password', 'Password']);
        if (!passwordField) {
            return true;
        }

        if (String(passwordField.value) !== String(confirmField.value)) {
            showError(confirmField, 'The password confirmation does not match.');
            return false;
        }

        return true;
    }

    function validateGate(form) {
        var gateType = eventGateType(form);
        var careerField = findField(form, ['allowed_career_field', 'AllowedCareerField']);
        var emailDomain = findField(form, ['allowed_email_domain', 'AllowedEmailDomain']);
        var valid = true;

        if (gateType === 'field' && careerField && getValue(careerField) === '') {
            showError(careerField, 'Choose the career field allowed for this event.');
            valid = false;
        }

        if (gateType === 'email_domain' && emailDomain) {
            var domain = getValue(emailDomain).replace(/^@/, '');
            if (domain === '' || !/^[a-z0-9.-]+\.[a-z]{2,}$/i.test(domain)) {
                showError(emailDomain, 'Enter a valid allowed email domain.');
                valid = false;
            }
        }

        return valid;
    }

    function validateStudentEmail(form) {
        if (selectedTicketType(form).toLowerCase() !== 'student') {
            return true;
        }

        var field = form.querySelector('[name="student_email"]');
        if (!field) {
            return true;
        }

        if (!studentEmailIsValid(getValue(field))) {
            showError(field, 'Use an academic email ending in ac.za or tuks.co.za.');
            return false;
        }

        return true;
    }

    function validateAgeConfirmation(form) {
        var field = form.querySelector('[name="age_confirm"]');
        if (!field || !isVisible(field)) {
            return true;
        }

        var requiredByPage = field.required
            || field.getAttribute('data-required-age') === 'true'
            || form.getAttribute('data-age-restricted') === 'true';

        if (requiredByPage && !field.checked) {
            showError(field, 'Confirm that you meet the event age requirement.');
            return false;
        }

        return true;
    }

    function validateForm(form) {
        toggleConditionalFields(form);

        var fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
        var valid = true;

        fields.forEach(function (field) {
            clearError(field);
        });

        fields.forEach(function (field) {
            if (!validateRequired(field)) {
                valid = false;
                return;
            }

            if (!validateEmail(field) || !validateNumber(field) || !validateDate(field)) {
                valid = false;
            }
        });

        if (!validatePasswords(form)) {
            valid = false;
        }
        if (!validateGate(form)) {
            valid = false;
        }
        if (!validateStudentEmail(form)) {
            valid = false;
        }
        if (!validateAgeConfirmation(form)) {
            valid = false;
        }

        if (!valid) {
            var firstInvalid = form.querySelector('[aria-invalid="true"]');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }

        return valid;
    }

    function shouldValidate(form) {
        if (form.hasAttribute('data-skip-eventnest-validation')) {
            return false;
        }

        if (form.getAttribute('data-validate') === 'true') {
            return true;
        }

        var relevantNames = [
            'email', 'password', 'confirm_password', 'new_password',
            'quantity', 'student_email', 'age_confirm',
            'career_gate_type', 'career_goal_type',
            'allowed_career_field', 'allowed_email_domain',
            'rating'
        ];

        return relevantNames.some(function (name) {
            return form.querySelector('[name="' + name + '"]') !== null;
        }) || form.querySelector('[name^="tickets["]') !== null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            if (!shouldValidate(form)) {
                return;
            }

            toggleConditionalFields(form);

            form.addEventListener('change', function (event) {
                var field = event.target;
                clearError(field);
                toggleConditionalFields(form);
            });

            form.addEventListener('input', function (event) {
                clearError(event.target);
            });

            form.addEventListener('submit', function (event) {
                if (!validateForm(form)) {
                    event.preventDefault();
                }
            });
        });
    });
}());
