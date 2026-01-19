document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(".contactos-form");
    const msg = document.getElementById("success-message");

    if (!form || !msg) return;

    form.addEventListener("submit", async function (e) {
        e.preventDefault();

        msg.textContent = "A enviar...";
        msg.classList.remove("error");
        msg.classList.add("show");

        const formData = new FormData(form);

        try {
            const response = await fetch("enviar.php", {
                method: "POST",
                body: formData,
            });

            const data = await response.json();

            msg.textContent = data.message || "Pedido processado.";

            if (data.success) {
                msg.classList.remove("error");
                form.reset();
                const motivoSelect = document.getElementById("motivo");
                const assuntoWrapper = document.getElementById("assunto-wrapper");
                const assuntoInput = document.getElementById("assunto");

                if (motivoSelect && assuntoWrapper && assuntoInput) {
                    assuntoWrapper.style.display = "none";
                    assuntoInput.required = false;
                }
            } else {
                msg.classList.add("error");
            }
        } catch (error) {
            msg.textContent = "Não foi possível enviar o pedido de contacto. Tente novamente mais tarde.";
            msg.classList.add("error");
        }

        setTimeout(() => msg.classList.remove("show"), 6000);
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const motivoSelect = document.getElementById("motivo");
    const assuntoWrapper = document.getElementById("assunto-wrapper");
    const assuntoInput = document.getElementById("assunto");

    if (!motivoSelect || !assuntoWrapper || !assuntoInput) return;

    const toggleAssunto = () => {
        if (motivoSelect.value === "outro") {
            assuntoWrapper.style.display = "block";
            assuntoInput.required = true;
        } else {
            assuntoWrapper.style.display = "none";
            assuntoInput.required = false;
            assuntoInput.value = "";
        }

    };

    toggleAssunto();
    motivoSelect.addEventListener("change", toggleAssunto);
});