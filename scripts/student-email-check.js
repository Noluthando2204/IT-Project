document.addEventListener("DOMContentLoaded", function () {

    const ticketTiers = document.querySelectorAll('[name="ticket_id"]');

    const studentEmail =
        document.getElementById("student_email");

    const studentEmailGroup =
        document.querySelector('[data-student-email-field]');


    if (!ticketTiers.length || !studentEmail || !studentEmailGroup) {
        return;
    }


    function updateStudentField() {
        const selected = document.querySelector('[name="ticket_id"]:checked');

        if (selected && selected.getAttribute('data-ticket-type') === 'Student') {

            studentEmailGroup.hidden = false;

            studentEmail.required = true;

        } else {

            studentEmailGroup.hidden = true;

            studentEmail.required = false;

            studentEmail.setCustomValidity('');

        }

    }
    ticketTiers.forEach(function (ticket) { ticket.addEventListener('change', updateStudentField); });
    updateStudentField();


    studentEmail.addEventListener("input", function () {

        const email =
            this.value.trim().toLowerCase();

        if (!this.required || email === "") {
            this.setCustomValidity('');
            return;
        }


        const domain = email.split('@')[1] || '';
        const validStudentEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
            && (domain.endsWith('.ac.za') || domain === 'tuks.co.za' || domain.endsWith('.tuks.co.za'));


        if (!validStudentEmail) {

            this.setCustomValidity(
                "Student tickets require an email address ending in ac.za or tuks.co.za."
            );

        } else { this.setCustomValidity(''); }

    });

});
