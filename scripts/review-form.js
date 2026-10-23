// Give immediate feedback while review.php repeats all eligibility and content checks.
(function () {
    'use strict';
    var form = document.querySelector('.review-form form');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        var rating = form.querySelector('[name="rating"]:checked');
        if (rating && Number(rating.value) >= 4 && !form.querySelector('[name="compliment_tags[]"]:checked')) {
            event.preventDefault();
            var first = form.querySelector('[name="compliment_tags[]"]');
            first.setCustomValidity('Choose at least one compliment.'); first.reportValidity(); first.focus();
        }
    });
    form.querySelectorAll('[name="compliment_tags[]"]').forEach(function (field) {
        field.addEventListener('change', function () { form.querySelector('[name="compliment_tags[]"]').setCustomValidity(''); });
    });
})();
