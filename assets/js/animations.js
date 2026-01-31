/**
 * Animations for Admin Panel
 * Handles number counting animations and page transitions
 */

/**
 * Animate number from 0 to target value
 * @param {HTMLElement} element - The element containing the number
 * @param {number} targetValue - The target number to count to
 * @param {number} duration - Animation duration in milliseconds (default: 2000)
 */
function animateNumber(element, targetValue, duration = 2000) {
    if (!element) return;
    
    const startValue = 0;
    const startTime = performance.now();
    const isFormatted = element.textContent.includes(',') || element.textContent.includes('.');
    
    // Extract number from formatted text if needed
    let target = targetValue;
    if (typeof targetValue === 'string') {
        // Remove formatting (commas, dots, spaces)
        target = parseFloat(targetValue.replace(/[^\d.-]/g, '')) || 0;
    }
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out)
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(startValue + (target - startValue) * easeOut);
        
        // Format number if original was formatted
        if (isFormatted) {
            element.textContent = current.toLocaleString('pt-BR');
        } else {
            element.textContent = current;
        }
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            // Ensure final value is set
            if (isFormatted) {
                element.textContent = target.toLocaleString('pt-BR');
            } else {
                element.textContent = target;
            }
        }
    }
    
    requestAnimationFrame(update);
}

/**
 * Initialize number animations for all KPI values on page load
 */
function initNumberAnimations() {
    document.addEventListener('DOMContentLoaded', function() {
        const kpiValues = document.querySelectorAll('.kpi-value');
        
        kpiValues.forEach(function(element) {
            // Get the target value from the element's text content
            const text = element.textContent.trim();
            
            // Remove formatting and extract number
            const targetValue = parseFloat(text.replace(/[^\d.-]/g, '')) || 0;
            
            // Reset to 0 initially
            element.textContent = '0';
            
            // Small delay for visual effect
            setTimeout(function() {
                animateNumber(element, targetValue, 2000);
            }, 300);
        });
    });
}

/**
 * Add page enter animation class to body
 */
function initPageTransitions() {
    document.addEventListener('DOMContentLoaded', function() {
        const body = document.body;
        if (body) {
            body.classList.add('page-enter');
        }
    });
}

/**
 * Initialize all animations
 */
function initAnimations() {
    initNumberAnimations();
    initPageTransitions();
}

// Auto-initialize when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnimations);
} else {
    initAnimations();
}

