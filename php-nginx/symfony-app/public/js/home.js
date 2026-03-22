// Menú hamburguesa para móvil
(function() {
    'use strict';
    
    const menuToggle = document.getElementById('menuToggle');
    const mainNavMobile = document.getElementById('mainNavMobile');
    
    if (menuToggle && mainNavMobile) {
        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', function(event) {
            const isClickInside = mainNavMobile.contains(event.target) || menuToggle.contains(event.target);
            
            if (!isClickInside && mainNavMobile.classList.contains('active')) {
                mainNavMobile.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Toggle menú
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isActive = mainNavMobile.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', isActive);
            
            // Cambiar icono
            menuToggle.textContent = isActive ? '✕' : '☰';
        });
        
        // Cerrar menú al hacer clic en un enlace (navegación)
        mainNavMobile.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    mainNavMobile.classList.remove('active');
                    menuToggle.setAttribute('aria-expanded', 'false');
                    menuToggle.textContent = '☰';
                }
            });
        });
        
        // Cerrar menú al redimensionar la ventana si pasa a desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && mainNavMobile.classList.contains('active')) {
                mainNavMobile.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.textContent = '☰';
            }
        });
    }
})();
