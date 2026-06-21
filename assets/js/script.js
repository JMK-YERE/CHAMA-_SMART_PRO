// assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Toggle forms
    document.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = document.querySelector(this.dataset.toggle);
            if(target) {
                target.classList.toggle('hidden');
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Confirm actions
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if(!confirm(this.dataset.confirm || 'Je, una uhakika?')) {
                e.preventDefault();
            }
        });
    });
    
    // Print reports
    document.querySelector('#printReport')?.addEventListener('click', function() {
        window.print();
    });
    
    // Mobile menu toggle
    document.querySelector('.menu-toggle')?.addEventListener('click', function() {
        document.querySelector('.header-right').classList.toggle('show');
    });
});
