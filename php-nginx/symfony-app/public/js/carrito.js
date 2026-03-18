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
            this.notificationContainer = document.getElementById('notificationContainer');
            this.init();
        }
        
        init() {
            // Inicializar botones de añadir al carrito
            document.querySelectorAll('.btn-add-carrito').forEach(button => {
                button.addEventListener('click', (e) => this.handleAddToCart(e));
            });
            
            // Manejar páginas de detalle de producto (previene envío del formulario con Enter)
            // TODO
            document.querySelectorAll('.cantidad-selector input').forEach(input => {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const button = e.target.closest('.producto-acciones')?.querySelector('.btn-add-carrito');
                        if (button) {
                            button.click();
                        }
                    }
                });
            });
            
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
            const badge = document.querySelector('.carrito-contador');
            if (badge) {
                badge.textContent = total;
                badge.classList.add('actualizado');
                
                // Animar badge
                setTimeout(() => {
                    badge.classList.remove('actualizado');
                }, 300);
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
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                const notif = document.getElementById(notificationId);
                if (notif) {
                    notif.style.animation = 'slideIn 0.3s reverse';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 5000);
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
