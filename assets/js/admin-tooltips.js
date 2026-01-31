// Admin Tooltips System
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips for elements with data-tooltip attribute
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        let tooltip = null;
        let timeout = null;
        
        element.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            if (!text) return;
            
            // Clear any existing timeout
            if (timeout) clearTimeout(timeout);
            
            // Create tooltip
            tooltip = document.createElement('div');
            tooltip.className = 'admin-tooltip';
            tooltip.textContent = text;
            document.body.appendChild(tooltip);
            
            // Position tooltip
            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            
            let top = rect.top - tooltipRect.height - 8;
            let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
            
            // Adjust if tooltip goes off screen
            if (left < 8) left = 8;
            if (left + tooltipRect.width > window.innerWidth - 8) {
                left = window.innerWidth - tooltipRect.width - 8;
            }
            
            if (top < 8) {
                top = rect.bottom + 8;
            }
            
            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
            
            // Show with animation
            setTimeout(() => {
                tooltip.classList.add('show');
            }, 10);
        });
        
        element.addEventListener('mouseleave', function() {
            if (tooltip) {
                tooltip.classList.remove('show');
                timeout = setTimeout(() => {
                    if (tooltip && tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                    tooltip = null;
                }, 200);
            }
        });
    });
});

