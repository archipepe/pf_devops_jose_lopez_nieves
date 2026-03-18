// Script global para actualizar el contador del carrito
document.addEventListener('DOMContentLoaded', function() {
    // Escuchar evento personalizado de carrito actualizado
    window.addEventListener('carrito-actualizado', function(event) {
        const badge = document.querySelector('.carrito-contador');
        if (badge && event.detail.totalProductos !== undefined) {
            badge.textContent = event.detail.totalProductos;
            badge.setAttribute('data-total-productos', event.detail.totalProductos);
            
            // Animación opcional
            badge.classList.add('actualizado');
            setTimeout(() => badge.classList.remove('actualizado'), 300);
        }
    });
});
