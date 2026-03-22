(function() {
    'use strict';
    
    // Configuración
    const CONFIG = {
        AJAX_TIMEOUT: 30000, // 30 segundos
        MAX_RETRIES: 3,
        RETRY_DELAY: 1000,
        CSRF_TOKEN: productoCsrfToken
    };
    
    class CarritoManager {
        constructor() {
            this.currentRequest = null;
            this.pendingRequests = new Map();
            this.debounceTimers = new Map();
            this.container = document.getElementById('carrito-container');
            this.notificationContainer = document.getElementById('notificationContainer');
            this.init();
        }
        
        init() {
            // Inicializar botones de añadir al carrito
            document.querySelectorAll('.btn-add-carrito').forEach(button => {
                button.addEventListener('click', (e) => this.handleAddToCart(e));
            });
            
            // Delegación de eventos para manejar todos los botones del carrito
            if (this.container) {
                this.container.addEventListener('click', (e) => {
                    const target = e.target.closest('[data-action]');
                    if (!target) return;
                    
                    e.preventDefault();
                    
                    const action = target.dataset.action;
                    const itemId = target.dataset.itemId;
                    
                    switch(action) {
                        case 'eliminar':
                            this.eliminarItem(itemId, target);
                            break;
                        case 'vaciar':
                            this.vaciarCarrito(target);
                            break;
                        case 'checkout':
                            this.procederCheckout(target);
                            break;
                    }
                });
                
                this.container.addEventListener('input', (e) => {
                    const input = e.target.closest('.input-cantidad');
                    if (!input) return;
                    
                    const itemId = input.dataset.itemId;
                    let cantidad = parseInt(input.value, 10);
                    
                    // Si el valor no es un número válido
                    if (isNaN(cantidad)) {
                        // Si se escribe algo como "1.", "1+", etc.
                        // Extraemos solo los números del string
                        const soloNumeros = input.value.replace(/[^\d]/g, '');
                        cantidad = soloNumeros ? parseInt(soloNumeros, 10) : 1;
                        input.value = cantidad; // Corregimos el input
                    }
                    
                    // Validar rango
                    if (cantidad < 1) {
                        cantidad = 1;
                        input.value = 1;
                        this.showNotification('La cantidad mínima es 1', 'warning');
                    } else if (cantidad > 10) {
                        cantidad = 10;
                        input.value = 10;
                        this.showNotification('La cantidad máxima es 10', 'warning');
                    }
                    
                    // Debounce para no hacer peticiones en cada tecla
                    this.debounce(`cantidad-${itemId}`, () => {
                        this.actualizarCantidad(itemId, cantidad, input);
                    }, 500);
                });

                // También manejar las teclas especiales
                this.container.addEventListener('keydown', (e) => {
                    const input = e.target.closest('.input-cantidad');
                    if (!input) return;
                    
                    // Permitir teclas de navegación
                    const teclasPermitidas = [
                        'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight',
                        'Tab', 'Enter', 'Backspace', 'Delete', 'Home', 'End'
                    ];
                    
                    if (teclasPermitidas.includes(e.key)) {
                        return; // Dejamos que el navegador maneje estas teclas
                    }
                    
                    // Bloquear caracteres no numéricos
                    if (!/^\d$/.test(e.key) && e.key !== ' ') {
                        e.preventDefault();
                    }
                });
            }            
            
            // Limpiar peticiones al salir de la página
            window.addEventListener('beforeunload', () => {
                this.abortAllRequests();
            });
            
            // Recuperar estado al volver con el botón atrás
            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    this.restoreButtonState();
                }
            });
        }

        debounce(key, fn, delay) {
            if (this.debounceTimers.has(key)) {
                clearTimeout(this.debounceTimers.get(key));
            }
            
            this.debounceTimers.set(key, setTimeout(() => {
                this.debounceTimers.delete(key);
                fn();
            }, delay));
        }
        
        async handleAddToCart(event) {
            const button = event.currentTarget;
            const productoId = button.dataset.productoId;
            const cantidadInput = button.closest('.producto-acciones')?.querySelector('[data-cantidad-input]');
            const cantidad = cantidadInput ? parseInt(cantidadInput.value, 10) : 1;
            
            // Validar cantidad
            if (isNaN(cantidad) || cantidad < 1 || cantidad > 10) {
                this.showNotification('La cantidad debe estar entre 1 y 10', 'warning');
                return;
            }
            
            // Abortar petición anterior si existe
            if (this.currentRequest) {
                this.abortRequest(productoId);
            }
            
            // Deshabilitar botón
            this.setButtonLoading(button, true);
            
            // Crear AbortController para esta petición
            const controller = new AbortController();
            this.currentRequest = { controller, productoId, button };
            this.pendingRequests.set(productoId, controller);
            
            try {
                // Timeout para la petición
                const timeoutId = setTimeout(() => {
                    controller.abort();
                    this.pendingRequests.delete(productoId);
                    this.setButtonLoading(button, false);
                    this.showNotification('La petición ha tardado demasiado. Por favor, inténtalo de nuevo.', 'warning');
                }, CONFIG.AJAX_TIMEOUT);
                
                // Hacer la petición
                const response = await this.makeRequest(productoId, cantidad, controller.signal);
                
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    const data = await response.json();
                    this.handleSuccessResponse(data, button);
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    this.handleErrorResponse(errorData, button);
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log('Petición abortada para producto:', productoId);
                } else {
                    console.error('Error en la petición:', error);
                    this.showNotification('Error al añadir el producto. Por favor, inténtalo de nuevo.', 'error');
                }
            } finally {
                this.pendingRequests.delete(productoId);
                if (this.currentRequest?.productoId === productoId) {
                    this.currentRequest = null;
                }
                this.setButtonLoading(button, false);
            }
        }
        
        async makeRequest(productoId, cantidad, signal) {
            const formData = new FormData();
            formData.append('cantidad', cantidad);
            
            return fetch(`/carrito/add/${productoId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CONFIG.CSRF_TOKEN
                },
                body: formData,
                signal,
                credentials: 'same-origin'
            });
        }
        
        handleSuccessResponse(data, button) {
            // Actualizar contador del carrito
            this.updateCartBadge(data.totalProductos);
            
            // Mostrar notificación de éxito
            const productoNombre = button.dataset.productoNombre || 'Producto';
            this.showNotification(`${productoNombre} añadido al carrito`, 'success');
            
            // Disparar evento personalizado
            window.dispatchEvent(new CustomEvent('carrito-actualizado', {
                detail: data
            }));
        }
        
        handleErrorResponse(data, button) {
            let mensaje = 'Error al añadir el producto';
            
            if (data.error) {
                mensaje = data.error;
            } else if (data.message) {
                mensaje = data.message;
            }
            
            this.showNotification(mensaje, 'error');
        }
        
        setButtonLoading(button, isLoading) {
            if (!button) return;
            
            if (isLoading) {
                button.classList.add('loading');
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            } else {
                button.classList.remove('loading');
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }
        
        abortRequest(productoId) {
            const controller = this.pendingRequests.get(productoId);
            if (controller) {
                controller.abort();
                this.pendingRequests.delete(productoId);
                
                // Restaurar botón si existe
                const button = document.querySelector(`.btn-add-carrito[data-producto-id="${productoId}"]`);
                if (button) {
                    this.setButtonLoading(button, false);
                }
            }
            
            if (this.currentRequest?.productoId === productoId) {
                this.currentRequest = null;
            }
        }
        
        abortAllRequests() {
            this.pendingRequests.forEach(controller => {
                controller.abort();
            });
            this.pendingRequests.clear();
            this.currentRequest = null;
        }
        
        restoreButtonState() {
            // Al volver con el botón atrás, asegurar que los botones están habilitados
            document.querySelectorAll('.btn-add-carrito').forEach(button => {
                this.setButtonLoading(button, false);
            });
        }
        
        updateCartBadge(total) {
            const badges = document.getElementsByClassName('carrito-contador');
            if (badges.length) {
                for (let index = 0; index < badges.length; index++) {
                    const element = badges[index];
                    element.textContent = total;
                    element.classList.add('actualizado');
                    
                    // Animar badge
                    setTimeout(() => {
                        element.classList.remove('actualizado');
                    }, 300);
                }
            }
        }
        
        showNotification(message, type = 'info') {
            if (!this.notificationContainer) return;
            
            const notificationId = 'notif-' + Date.now();
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.id = notificationId;
            notification.setAttribute('role', 'alert');
            notification.innerHTML = `
                <span>${message}</span>
                <span class="notification-close" onclick="this.closest('.notification').remove()" aria-label="Cerrar">✕</span>
            `;
            
            this.notificationContainer.appendChild(notification);
            
            // Auto-eliminar después de 1 segundo
            setTimeout(() => {
                const notif = document.getElementById(notificationId);
                if (notif) {
                    notif.style.animation = 'slideIn 0.3s reverse';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 1000);
        }

        async actualizarCantidad(itemId, cantidad, inputElement) {
            // Abortar petición anterior para este item
            if (this.pendingRequests.has(`update-${itemId}`)) {
                this.pendingRequests.get(`update-${itemId}`).abort();
            }
            
            const controller = new AbortController();
            this.pendingRequests.set(`update-${itemId}`, controller);
            
            // Marcar item como actualizando
            const itemRow = inputElement.closest('.carrito-item');
            if (itemRow) {
                itemRow.classList.add('updating');
            }
            
            inputElement.classList.add('loading');
            
            try {
                const timeoutId = setTimeout(() => {
                    controller.abort();
                    this.showNotification('La petición ha tardado demasiado', 'warning');
                }, CONFIG.AJAX_TIMEOUT);
                
                const formData = new FormData();
                formData.append('cantidad', cantidad);
                
                const response = await fetch(`/carrito/update/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CONFIG.CSRF_TOKEN
                    },
                    body: formData,
                    signal: controller.signal,
                    credentials: 'same-origin'
                });
                
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    const data = await response.json();
                    this.manejarActualizacionExitosa(itemId, data, itemRow);
                } else {
                    const error = await response.json().catch(() => ({}));
                    this.manejarError(error, 'Error al actualizar cantidad');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Error:', error);
                    this.showNotification('Error al actualizar la cantidad', 'error');
                }
            } finally {
                this.pendingRequests.delete(`update-${itemId}`);
                if (itemRow) {
                    itemRow.classList.remove('updating');
                }
                inputElement.classList.remove('loading');
            }
        }
        
        async eliminarItem(itemId, buttonElement) {
            if (!confirm('¿Eliminar producto del carrito?')) {
                return;
            }
            
            if (this.pendingRequests.has(`delete-${itemId}`)) {
                this.pendingRequests.get(`delete-${itemId}`).abort();
            }
            
            const controller = new AbortController();
            this.pendingRequests.set(`delete-${itemId}`, controller);
            
            const itemRow = buttonElement.closest('.carrito-item');
            if (itemRow) {
                itemRow.classList.add('deleting');
            }
            
            buttonElement.classList.add('loading');
            
            try {
                const response = await fetch(`/carrito/remove/${itemId}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CONFIG.CSRF_TOKEN
                    },
                    signal: controller.signal,
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    // Animar eliminación
                    if (itemRow) {
                        setTimeout(() => {
                            itemRow.remove();
                            this.verificarCarritoVacio();
                        }, 300);
                    }
                    
                    this.actualizarTotales(data);
                    this.showNotification('Producto eliminado del carrito', 'success');
                    
                    window.dispatchEvent(new CustomEvent('carrito-actualizado', {
                        detail: data
                    }));
                } else {
                    if (itemRow) {
                        itemRow.classList.remove('deleting');
                    }
                    this.showNotification('Error al eliminar el producto', 'error');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Error:', error);
                    this.showNotification('Error al eliminar el producto', 'error');
                }
                if (itemRow) {
                    itemRow.classList.remove('deleting');
                }
            } finally {
                this.pendingRequests.delete(`delete-${itemId}`);
                buttonElement.classList.remove('loading');
            }
        }
        
        async vaciarCarrito(buttonElement) {
            if (!confirm('¿Vaciar el carrito completamente?')) {
                return;
            }
            
            if (this.currentRequest) {
                this.currentRequest.abort();
            }
            
            const controller = new AbortController();
            this.currentRequest = controller;
            
            buttonElement.classList.add('loading');
            
            try {
                const response = await fetch('/carrito/clear', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CONFIG.CSRF_TOKEN
                    },
                    signal: controller.signal,
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    // Animar eliminación de todos los items
                    const items = document.querySelectorAll('.carrito-item');
                    items.forEach((item, index) => {
                        setTimeout(() => {
                            item.classList.add('deleting');
                        }, index * 50);
                    });
                    
                    setTimeout(() => {
                        this.mostrarCarritoVacio();
                    }, items.length * 50 + 300);
                    
                    this.actualizarTotales(data);
                    this.showNotification('Carrito vaciado', 'success');
                    
                    window.dispatchEvent(new CustomEvent('carrito-actualizado', {
                        detail: data
                    }));
                } else {
                    this.showNotification('Error al vaciar el carrito', 'error');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Error:', error);
                    this.showNotification('Error al vaciar el carrito', 'error');
                }
            } finally {
                this.currentRequest = null;
                buttonElement.classList.remove('loading');
            }
        }
        
        procederCheckout(buttonElement) {
            buttonElement.classList.add('loading');
            
            // Pequeña pausa para mostrar feedback
            setTimeout(() => {
                window.location.href = checkoutURL;
            }, 300);
        }
        
        manejarActualizacionExitosa(itemId, data, itemRow) {
            // Actualizar subtotal del item
            const subtotalElement = itemRow?.querySelector('.producto-subtotal');
            if (subtotalElement && data.nuevoSubtotal) {
                subtotalElement.textContent = data.nuevoSubtotal + ' €';
            }
            
            this.actualizarTotales(data);
            this.showNotification('Cantidad actualizada', 'success');
            
            window.dispatchEvent(new CustomEvent('carrito-actualizado', {
                detail: data
            }));
        }
        
        manejarError(error, mensajeDefault) {
            this.showNotification(error.message || mensajeDefault, 'error');
        }
        
        actualizarTotales(data) {
            if (!data) return;
            
            const totalProductosSpan = document.getElementById('total-productos-count');
            const totalProductosPrecioSpan = document.getElementById('total-productos-precio');
            const totalCarritoSpan = document.getElementById('total-carrito');
            
            if (totalProductosSpan && data.totalProductos !== undefined) {
                totalProductosSpan.textContent = data.totalProductos;
            }
            
            if (totalProductosPrecioSpan && data.total !== undefined) {
                totalProductosPrecioSpan.textContent = data.total.toFixed(2).replace('.', ',') + ' €';
            }
            
            if (totalCarritoSpan && data.total !== undefined) {
                totalCarritoSpan.textContent = data.total.toFixed(2).replace('.', ',') + ' €';
                
                // Animación del total
                totalCarritoSpan.closest('.resumen-fila')?.classList.add('updating');
                setTimeout(() => {
                    totalCarritoSpan.closest('.resumen-fila')?.classList.remove('updating');
                }, 300);
            }
            
            // Actualizar contador del header
            const badges = document.getElementsByClassName('carrito-contador');
            if (badges.length && data.totalProductos !== undefined) {
                for (let index = 0; index < badges.length; index++) {
                    const element = badges[index];
                    element.textContent = data.totalProductos;
                    element.classList.add('actualizado');
                    setTimeout(() => element.classList.remove('actualizado'), 300);
                }
            }
        }
        
        verificarCarritoVacio() {
            const items = document.querySelectorAll('.carrito-item');
            if (items.length === 0) {
                this.mostrarCarritoVacio();
            }
        }
        
        mostrarCarritoVacio() {
            if (this.container) {
                this.container.remove();
            }
            
            const html = `
                <div class="carrito-vacio">
                    <div class="carrito-vacio-icono">🛒</div>
                    <h2>Tu carrito está vacío</h2>
                    <p>¡Explora nuestros productos y encuentra lo que buscas!</p>
                    <a href="` + productosIndex + `" class="btn-ver-productos">
                        Ver productos
                    </a>
                </div>
            `;
            
            const mainContainer = document.getElementsByClassName('container')[0];
            mainContainer.append(document.createRange().createContextualFragment(html));
        }
    }
    
    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.carritoManager = new CarritoManager();
        });
    } else {
        window.carritoManager = new CarritoManager();
    }
    
})();
