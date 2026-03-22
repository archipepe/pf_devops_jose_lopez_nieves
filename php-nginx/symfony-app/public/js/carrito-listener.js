// Script global para actualizar el contador del carrito
document.addEventListener('DOMContentLoaded', function() {
    // Escuchar evento personalizado de carrito actualizado
    window.addEventListener('carrito-actualizado', function(event) {
        const badges = document.getElementsByClassName('carrito-contador');
        if (badges.length && event.detail.totalProductos !== undefined) {
            for (let index = 0; index < badges.length; index++) {
                const element = badges[index];
                element.textContent = event.detail.totalProductos;
                element.setAttribute('data-total-productos', event.detail.totalProductos);
                
                // Animación opcional
                element.classList.add('actualizado');
                setTimeout(() => element.classList.remove('actualizado'), 300);
            }
        }
    });
});
