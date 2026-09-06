document.addEventListener("DOMContentLoaded", () => {

    // --- FILTROS EN TIEMPO REAL Y VALIDACIÓN DE PERFIL ---
    const formPerfil = document.getElementById("form-perfil");
    const inputNombre = document.getElementById("nombre");
    const inputApellido = document.getElementById("apellido");
    const inputTelefono = document.getElementById("telefono");
    const inputUsername = document.getElementById("nombre-usuario");
    const inputCorreo = document.getElementById("correo");

    // 1. Nombre y Apellido: solo letras y espacios (máximo 15 caracteres)
    [inputNombre, inputApellido].forEach(input => {
        if (input) {
            input.addEventListener("input", (e) => {
                e.target.value = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, "");
            });
        }
    });

    // 2. Celular: solo números y máximo 9 dígitos
    if (inputTelefono) {
        inputTelefono.addEventListener("input", (e) => {
            e.target.value = e.target.value.replace(/\D/g, "");
        });
    }

    // 3. Username: letras y números sin caracteres especiales (máximo 20 caracteres)
    if (inputUsername) {
        inputUsername.addEventListener("input", (e) => {
            e.target.value = e.target.value.replace(/[^a-zA-Z0-9]/g, "");
        });
    }

    // 4. Validaciones del formulario de perfil antes de enviar
    if (formPerfil) {
        formPerfil.addEventListener("submit", (e) => {
            if (inputTelefono.value.length !== 9) {
                e.preventDefault();
                alert("El número de celular debe tener exactamente 9 dígitos.");
                return;
            }

            if (!inputCorreo.value.toLowerCase().endsWith("@gmail.com")) {
                e.preventDefault();
                alert("El correo electrónico debe finalizar obligatoriamente en @gmail.com");
                return;
            }

            if (inputUsername.value.length > 20) {
                e.preventDefault();
                alert("El nombre de usuario no puede superar los 20 caracteres.");
                return;
            }
        });
    }

    // --- SECCIÓN SEGURIDAD Y BORRADO ---
    const formSeguridad = document.getElementById("form-seguridad");
    if (formSeguridad) {
        formSeguridad.addEventListener("submit", (e) => {
            const passNueva = document.getElementById("nueva-contrasena").value;
            const passConfirmar = document.getElementById("confirmar-contrasena").value;

            if (passNueva.length < 8) {
                e.preventDefault();
                alert("La nueva contraseña debe tener al menos 8 caracteres.");
                return;
            }

            if (passNueva !== passConfirmar) {
                e.preventDefault();
                alert("Las contraseñas no coinciden. Por favor verifica.");
            }
        });
    }

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