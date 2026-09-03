document.addEventListener("DOMContentLoaded", () => {
    // Validar coincidencia de nueva contraseña
    const formSeguridad = document.getElementById("form-seguridad");
    if (formSeguridad) {
        formSeguridad.addEventListener("submit", (e) => {
            const passNueva = document.getElementById("nueva-contrasena").value;
            const passConfirmar = document.getElementById("confirmar-contrasena").value;

            if (passNueva.length < 6) {
                e.preventDefault();
                alert("La nueva contraseña debe tener al menos 6 caracteres.");
                return;
            }

            if (passNueva !== passConfirmar) {
                e.preventDefault();
                alert("Las contraseñas no coinciden. Por favor verifica.");
            }
        });
    }

    // Confirmación al eliminar la cuenta
    const formBorrar = document.getElementById("form-borrar-cuenta");
    if (formBorrar) {
        formBorrar.addEventListener("submit", (e) => {
            const confirmacion = confirm("¿Estás completamente seguro de que deseas eliminar tu cuenta? Esta acción no se puede deshacer.");
            if (!confirmacion) {
                e.preventDefault();
            }
        });
    }
});