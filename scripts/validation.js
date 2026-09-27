
function validateRequired(value) {
    return typeof value === 'string' && value.trim().length > 0;
}

/**
 * Basic email shape check (not a full RFC 5322 validator - just enough
 * to catch obvious typos before the form is submitted).
 * @param {string} email
 * @returns {boolean}
 */
function validateEmail(email) {
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return validateRequired(email) && emailPattern.test(email.trim());
}

/**
 * Minimum password strength: at least 8 characters, one letter and
 * one number. Kept simple and predictable rather than overly strict.
 * @param {string} password
 * @returns {boolean}
 */
function validatePassword(password) {
    var strongEnough = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
    return typeof password === 'string' && strongEnough.test(password);
}

/**
 * Shared check for student ticket-tier emails. Kept generic and
 * reusable so scripts/student-email-check.js (Karabo) can call the
 * same rule instead of duplicating the domain list.
 * @param {string} email
 * @returns {boolean}
 */
function isValidStudentEmail(email) {
    if (!validateEmail(email)) {
        return false;
    }
    var allowedDomains = ['ac.za', 'tuks.co.za'];
    var emailDomain = email.trim().toLowerCase().split('@')[1] || '';
    return allowedDomains.some(function (domain) {
        return emailDomain.endsWith(domain);
    });
}

/**
 * Shows or clears an inline error message under a given field.
 * Expects an element with id="<fieldId>-error" to exist in the markup.
 * @param {string} fieldId
 * @param {string} message - empty string clears the error
 */
function setFieldError(fieldId, message) {
    var errorEl = document.getElementById(fieldId + '-error');
    if (!errorEl) {
        return;
    }
    errorEl.textContent = message;
    errorEl.hidden = message === '';
}

/**
 * Validates a registration or login form before it is submitted.
 * Attach with: form.addEventListener('submit', validateAuthForm)
 * @param {SubmitEvent} event
 */
function validateAuthForm(event) {
    var form = event.target;
    var isValid = true;

    var emailField = form.querySelector('[name="email"]');
    if (emailField && !validateEmail(emailField.value)) {
        setFieldError('email', 'Enter a valid email address.');
        isValid = false;
    } else if (emailField) {
        setFieldError('email', '');
    }

    var passwordField = form.querySelector('[name="password"]');
    // Login only needs a non-empty password; registration needs the
    // stronger rule. Distinguish using the form's id in the markup.
    if (passwordField) {
        var isRegisterForm = form.id === 'register-form';
        var passwordOk = isRegisterForm
            ? validatePassword(passwordField.value)
            : validateRequired(passwordField.value);

        if (!passwordOk) {
            setFieldError(
                'password',
                isRegisterForm
                    ? 'Password must be at least 8 characters and include a letter and a number.'
                    : 'Enter your password.'
            );
            isValid = false;
        } else {
            setFieldError('password', '');
        }
    }

    if (!isValid) {
        event.preventDefault();
    }
}

/**
 * Validates the booking form before submission, including the
 * student email check when the "student" ticket tier is selected.
 * Attach with: form.addEventListener('submit', validateBookingForm)
 * @param {SubmitEvent} event
 */
function validateBookingForm(event) {
    var form = event.target;
    var isValid = true;

    var tierField = form.querySelector('[name="ticket_tier"]');
    var studentEmailField = form.querySelector('[name="student_email"]');

    if (tierField && tierField.value === 'student') {
        if (!studentEmailField || !isValidStudentEmail(studentEmailField.value)) {
            setFieldError('student_email', 'Enter a valid student email ending in ac.za or tuks.co.za.');
            isValid = false;
        } else {
            setFieldError('student_email', '');
        }
    }

    var ageConfirmField = form.querySelector('[name="age_confirm"]');
    if (ageConfirmField && ageConfirmField.type === 'checkbox' && ageConfirmField.hasAttribute('required')) {
        if (!ageConfirmField.checked) {
            setFieldError('age_confirm', 'You must confirm you meet the age requirement to book.');
            isValid = false;
        } else {
            setFieldError('age_confirm', '');
        }
    }

    if (!isValid) {
        event.preventDefault();
    }
}


// to include everywhere without extra per-page setup.
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', validateAuthForm);
    }

    var registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', validateAuthForm);
    }

    var bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', validateBookingForm);
    }
});
