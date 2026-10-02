

function validateRequired(value) {
    return typeof value === 'string' && value.trim().length > 0;
}

function validateEmail(email) {
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return validateRequired(email) && emailPattern.test(email.trim());
}

function validatePassword(password) {
    // At least 8 characters, one letter and one number.
    var strongEnough = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
    return typeof password === 'string' && strongEnough.test(password);
}

function validatePasswordsMatch(password, confirmPassword) {
    return typeof password === 'string' && password === confirmPassword;
}

function validatePositiveNumber(value) {
    var numeric = Number(value);
    return value !== '' && !isNaN(numeric) && numeric > 0;
}

function validateNonNegativeNumber(value) {
    var numeric = Number(value);
    return value !== '' && !isNaN(numeric) && numeric >= 0;
}

function validateDateNotPast(value) {
    if (!validateRequired(value)) {
        return false;
    }
    var enteredDate = new Date(value);
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    return !isNaN(enteredDate.getTime()) && enteredDate >= today;
}

/**
 * Shared institutional-email rule for the Student ticket tier. Kept
 * generic so scripts/student-email-check.js calls this instead of
 * duplicating the domain list.
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

/* ---------------------------------------------------------------- */
/* Error display helpers                                             */
/* ---------------------------------------------------------------- */

/**
 * Shows or clears an inline error under a field. Expects a sibling
 * element with id="<fieldId>-error" in the markup.
 */
function setFieldError(fieldId, message) {
    var errorEl = document.getElementById(fieldId + '-error');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.hidden = message === '';
    }
}

/**
 * Focuses the first field in `invalidFieldIds` that actually has an
 * error set, so keyboard/screen-reader users land on the real problem.
 */
function focusFirstInvalidField(invalidFieldIds) {
    for (var i = 0; i < invalidFieldIds.length; i++) {
        var field = document.getElementById(invalidFieldIds[i]);
        if (field) {
            field.focus();
            return;
        }
    }
}

/* ---------------------------------------------------------------- */
/* Registration / login                                              */
/* ---------------------------------------------------------------- */

function validateAuthForm(event) {
    var form = event.target;
    var invalidFields = [];

    var emailField = form.querySelector('[name="email"]');
    if (emailField) {
        if (!validateEmail(emailField.value)) {
            setFieldError('email', 'Enter a valid email address.');
            invalidFields.push('email');
        } else {
            setFieldError('email', '');
        }
    }

    var isRegisterForm = form.id === 'register-form' || form.id === 'business-register-form';

    var passwordField = form.querySelector('[name="password"]');
    if (passwordField) {
        var passwordOk = isRegisterForm ? validatePassword(passwordField.value) : validateRequired(passwordField.value);
        if (!passwordOk) {
            setFieldError('password', isRegisterForm
                ? 'Password must be at least 8 characters and include a letter and a number.'
                : 'Enter your password.');
            invalidFields.push('password');
        } else {
            setFieldError('password', '');
        }
    }

    var confirmField = form.querySelector('[name="confirm_password"]');
    if (confirmField) {
        if (!validatePasswordsMatch(passwordField ? passwordField.value : '', confirmField.value)) {
            setFieldError('confirm_password', 'Passwords do not match.');
            invalidFields.push('confirm_password');
        } else {
            setFieldError('confirm_password', '');
        }
    }

    if (invalidFields.length > 0) {
        event.preventDefault();
        focusFirstInvalidField(invalidFields);
    }
}

/* ---------------------------------------------------------------- */
/* Create / edit event                                               */
/* ---------------------------------------------------------------- */

function validateEventForm(event) {
    var form = event.target;
    var invalidFields = [];

    var nameField = form.querySelector('[name="event_name"]');
    if (nameField && !validateRequired(nameField.value)) {
        setFieldError('event_name', 'Enter an event name.');
        invalidFields.push('event_name');
    } else if (nameField) {
        setFieldError('event_name', '');
    }

    var dateField = form.querySelector('[name="event_date"]');
    if (dateField && !validateDateNotPast(dateField.value)) {
        setFieldError('event_date', 'Choose a valid date that is not in the past.');
        invalidFields.push('event_date');
    } else if (dateField) {
        setFieldError('event_date', '');
    }

    // Event gate: career category only required for "field", email domain
    // only required for "email_domain".
    var gateTypeField = form.querySelector('[name="career_gate_type"]');
    var gateType = gateTypeField ? gateTypeField.value : 'none';

    var categoryField = form.querySelector('[name="allowed_career_field"]');
    if (gateType === 'field' && categoryField) {
        if (!validateRequired(categoryField.value)) {
            setFieldError('allowed_career_field', 'Choose the required career category.');
            invalidFields.push('allowed_career_field');
        } else {
            setFieldError('allowed_career_field', '');
        }
    } else if (categoryField) {
        setFieldError('allowed_career_field', '');
    }

    var domainField = form.querySelector('[name="allowed_email_domain"]');
    if (gateType === 'email_domain' && domainField) {
        if (!validateRequired(domainField.value)) {
            setFieldError('allowed_email_domain', 'Enter the required email domain.');
            invalidFields.push('allowed_email_domain');
        } else {
            setFieldError('allowed_email_domain', '');
        }
    } else if (domainField) {
        setFieldError('allowed_email_domain', '');
    }

    // Ticket rows, if present on this form: price and capacity must be valid.
    var priceFields = form.querySelectorAll('[name="price[]"], [name^="price_"]');
    priceFields.forEach(function (field, index) {
        var fieldId = field.id || ('price_' + index);
        if (!validateNonNegativeNumber(field.value)) {
            setFieldError(fieldId, 'Price cannot be negative.');
            invalidFields.push(fieldId);
        } else {
            setFieldError(fieldId, '');
        }
    });

    var capacityFields = form.querySelectorAll('[name="capacity[]"], [name^="capacity_"]');
    capacityFields.forEach(function (field, index) {
        var fieldId = field.id || ('capacity_' + index);
        if (!validatePositiveNumber(field.value)) {
            setFieldError(fieldId, 'Capacity must be greater than 0.');
            invalidFields.push(fieldId);
        } else {
            setFieldError(fieldId, '');
        }
    });

    if (invalidFields.length > 0) {
        event.preventDefault();
        focusFirstInvalidField(invalidFields);
    }
}

/* ---------------------------------------------------------------- */
/* Booking                                                            */
/* ---------------------------------------------------------------- */

function validateBookingForm(event) {
    var form = event.target;
    var invalidFields = [];

    var quantityField = form.querySelector('[name="quantity"]');
    if (quantityField && !validatePositiveNumber(quantityField.value)) {
        setFieldError('quantity', 'Enter a quantity of 1 or more.');
        invalidFields.push('quantity');
    } else if (quantityField) {
        setFieldError('quantity', '');
    }

    // Student ticket tier -> temporary student_email is required and
    // must match the agreed institutional-domain rule.
    var ticketTypeField = form.querySelector('[name="ticket_type"]');
    var studentEmailField = form.querySelector('[name="student_email"]');
    if (ticketTypeField && ticketTypeField.value === 'Student' && studentEmailField) {
        if (!isValidStudentEmail(studentEmailField.value)) {
            setFieldError('student_email', 'Enter a valid student email ending in ac.za or tuks.co.za.');
            invalidFields.push('student_email');
        } else {
            setFieldError('student_email', '');
        }
    }

    // Age-restricted event -> age_confirm checkbox is required.
    var ageConfirmField = form.querySelector('[name="age_confirm"]');
    if (ageConfirmField && ageConfirmField.hasAttribute('required') && !ageConfirmField.checked) {
        setFieldError('age_confirm', 'You must confirm you meet the age requirement to book.');
        invalidFields.push('age_confirm');
    } else if (ageConfirmField) {
        setFieldError('age_confirm', '');
    }

    if (invalidFields.length > 0) {
        event.preventDefault();
        focusFirstInvalidField(invalidFields);
    }
}

/* ---------------------------------------------------------------- */
/* Leave a review                                                     */
/* ---------------------------------------------------------------- */

function validateReviewForm(event) {
    var form = event.target;
    var invalidFields = [];

    var ratingField = form.querySelector('[name="rating"]:checked');
    if (!ratingField) {
        setFieldError('rating', 'Select a star rating.');
        invalidFields.push('rating');
    } else {
        setFieldError('rating', '');

        var rating = Number(ratingField.value);
        if (rating < 4) {
            var complaintField = form.querySelector('[name="comment"]');
            if (complaintField && !validateRequired(complaintField.value)) {
                setFieldError('comment', 'Tell the organiser what went wrong.');
                invalidFields.push('comment');
            } else if (complaintField) {
                setFieldError('comment', '');
            }
        }
    }

    if (invalidFields.length > 0) {
        event.preventDefault();
        focusFirstInvalidField(invalidFields);
    }
}

/* ---------------------------------------------------------------- */
/* Wire up whatever forms exist on the current page                  */
/* ---------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', function () {
    var bindings = [
        ['login-form', validateAuthForm],
        ['register-form', validateAuthForm],
        ['business-register-form', validateAuthForm],
        ['create-event-form', validateEventForm],
        ['edit-event-form', validateEventForm],
        ['booking-form', validateBookingForm],
        ['leave-review-form', validateReviewForm],
    ];

    bindings.forEach(function (pair) {
        var form = document.getElementById(pair[0]);
        if (form) {
            form.addEventListener('submit', pair[1]);
        }
    });
});
