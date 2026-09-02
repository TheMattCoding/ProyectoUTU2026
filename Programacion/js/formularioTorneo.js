document.addEventListener('DOMContentLoaded', () => {
    // 1. Redirección del botón Cancelar
    const btnCancelar = document.getElementById('btn-cancelar');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', () => {
            window.location.href = 'inicio.php';
        });
    }

    // 2. Mostrar nombre de la imagen seleccionada en la portada
    const inputPortada = document.getElementById('portada-torneo');
    const textoSubirArchivo = document.getElementById('texto-subir-archivo');

    if (inputPortada && textoSubirArchivo) {
        inputPortada.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                textoSubirArchivo.textContent = file.name;
            } else {
                textoSubirArchivo.textContent = 'Seleccionar Imagen';
            }
        });
    }

    // 3. Conmutador de Tema Oscuro / Claro
    const btnThemeToggle = document.getElementById('btn-theme-toggle');
    if (btnThemeToggle) {
        btnThemeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
        });
    }
});