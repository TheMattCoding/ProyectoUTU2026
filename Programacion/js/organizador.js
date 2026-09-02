document.addEventListener('DOMContentLoaded', () => {
    // Alternar modo oscuro
    const btnTema = document.querySelector('.theme-toggle-btn');
    if (btnTema) {
        btnTema.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const esOscuro = document.body.classList.contains('dark-mode');
            localStorage.setItem('theme', esOscuro ? 'dark' : 'light');
        });

        // Cargar preferencia guardada
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
    }
});