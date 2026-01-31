// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
    // Add simple fade-in effect to main container
    const container = document.querySelector('.container') || document.querySelector('.admin-layout');
    if (container) {
        container.style.opacity = '0';
        container.style.transition = 'opacity 0.8s ease-out';
        
        setTimeout(() => {
            container.style.opacity = '1';
        }, 100);
    }

    // Add hover effect to product cards (JS fallback/enhancement)
    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.borderColor = 'var(--color-gold)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.borderColor = '#333';
        });
    });

    // Anti-Right Click (Security Theater as requested)
    document.addEventListener('contextmenu', event => event.preventDefault());

    // Disable F12/Inspect (Basic deterrent)
    document.onkeydown = function(e) {
        if(event.keyCode == 123) {
            return false;
        }
        if(e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) {
            return false;
        }
        if(e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) {
            return false;
        }
        if(e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) {
            return false;
        }
        if(e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) {
            return false;
        }
    }
});
