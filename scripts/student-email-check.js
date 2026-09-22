document.addEventListener("DOMContentLoaded", function () {

    const ticketTier =
        document.getElementById("ticket_tier");

    const studentEmail =
        document.getElementById("student_email");

    const studentEmailGroup =
        document.getElementById("studentEmailGroup");


    if (!ticketTier || !studentEmail) {
        return;
    }


    ticketTier.addEventListener("change", function () {

        if (this.value === "student") {

            studentEmailGroup.style.display = "block";

            studentEmail.required = true;

        } else {

            studentEmailGroup.style.display = "none";

            studentEmail.required = false;

            studentEmail.value = "";

        }

    });


    studentEmail.addEventListener("blur", function () {

        const email =
            this.value.trim().toLowerCase();

        if (email === "") {
            return;
        }


        const validStudentEmail =
            email.endsWith("ac.za") ||
            email.endsWith("tuks.co.za");


        if (!validStudentEmail) {

            alert(
                "Student tickets require an email address ending in ac.za or tuks.co.za."
            );

            this.focus();

        }

    });

});
