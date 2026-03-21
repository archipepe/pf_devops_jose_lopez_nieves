(function() {
    const form = document.getElementById('checkout-form');
    const btn = document.getElementById('btn-procesar');
    
    form.addEventListener('submit', function(e) {
        btn.disabled = true;
        btn.classList.add('loading');
        btn.textContent = 'Procesando...';
    });
    
    // Validación básica
    form.querySelectorAll('input[required]').forEach(input => {
        input.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.classList.add('error');
        });
        
        input.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
})();