// Native rating values; review.php remains authoritative for every rule.
(function () {
    'use strict';
    var form = document.querySelector('.review-form form');
    if (!form) return;
    var stars = Array.from(form.querySelectorAll('[name="rating"]'));
    var chips = Array.from(form.querySelectorAll('[name="compliment_tags[]"]'));
    var high = form.querySelector('[data-high-rating]');
    var low = form.querySelector('[data-low-rating]');
    var comment = form.querySelector('[name="complaint_text"]');
    var contact = form.querySelector('[data-contact-permission]');
    var email = form.querySelector('[name="contact_email"]');
    var submit = form.querySelector('[data-review-submit]');
    var previous = 0;
    function update() {
        var selected = stars.find(function (star) { return star.checked; });
        var rating = selected ? Number(selected.value) : 0;
        var positive = rating >= 4;
        if (positive !== (previous >= 4)) {
            comment.value = '';
            chips.forEach(function (chip) { chip.checked = false; });
        }
        previous = rating;
        stars.forEach(function (star) { star.closest('label').classList.toggle('is-filled', Number(star.value) <= rating); });
        form.querySelector('[data-rating-cue]').textContent = rating ? rating + ' out of 5 stars' : 'Choose a star to begin';
        high.hidden = !positive;
        low.hidden = !(rating > 0 && !positive);
        chips.forEach(function (chip) { chip.disabled = !positive; });
        comment.disabled = low.hidden;
        comment.required = !low.hidden;
        var validComment = comment.value.trim().length > 0 && Array.from(comment.value).length <= 5000;
        comment.setCustomValidity(!low.hidden && !validComment ? 'Describe what could improve (up to 5,000 characters).' : '');
        form.querySelector('[data-contact-choice]').hidden = !rating;
        contact.disabled = !rating;
        var wantsContact = !!rating && contact.checked;
        form.querySelector('[data-contact-email]').hidden = !wantsContact;
        email.disabled = !wantsContact;
        email.required = wantsContact;
        var validEmail = !wantsContact || (email.value.trim() !== '' && email.validity.valid && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()));
        form.querySelector('#review-email-error').textContent = validEmail ? '' : 'Enter a valid contact email, or uncheck follow-up.';
        email.setAttribute('aria-invalid', String(!validEmail));
        submit.hidden = !rating || (!positive && !validComment);
        submit.disabled = !validEmail;
    }
    chips.forEach(function (chip) {
        chip.addEventListener('change', function () {
            if (chip.checked) chips.forEach(function (other) { if (other !== chip) other.checked = false; });
        });
    });
    stars.forEach(function (star) {
        star.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); star.checked = true; update(); }
        });
    });
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', function (event) {
        update();
        if (submit.hidden || submit.disabled || !form.checkValidity()) {
            event.preventDefault();
            form.querySelector('[data-review-error]').textContent = 'Choose a rating and complete the required feedback or contact email.';
            form.reportValidity();
            return;
        }
        queueMicrotask(function () {
            if (event.defaultPrevented) return;
            submit.disabled = true;
            submit.textContent = 'Submitting…';
            form.setAttribute('aria-busy', 'true');
        });
    });
    window.addEventListener('pageshow', function () { submit.textContent = 'Submit review'; form.removeAttribute('aria-busy'); update(); });
    update();
})();
