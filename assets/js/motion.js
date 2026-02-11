/**
 * FAYMURE - Premium Micro-Animations
 * 
 * Performance-optimized motion using IntersectionObserver and GPU-friendly transforms.
 * 
 * Usage:
 * - Add class "reveal" to elements you want to animate on scroll
 * - Optional: add data-delay="120" (in milliseconds) for staggered timing
 * - Add class "stagger" to parent container for automatic child delays
 * 
 * Example:
 *   <div class="reveal" data-delay="120">Content</div>
 *   <div class="stagger">
 *     <div class="reveal">Item 1</div>
 *     <div class="reveal">Item 2</div>
 *   </div>
 */

(function() {
    'use strict';

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ============================================
    // A) IntersectionObserver Reveal
    // ============================================
    function initReveals() {
        const reveals = document.querySelectorAll('.reveal');
        
        if (reveals.length === 0) return;
        
        // If reduced motion or no IntersectionObserver, show immediately
        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            reveals.forEach(el => el.classList.add('in-view'));
            return;
        }

        const observerOptions = {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    
                    // Apply delay if specified
                    const delay = element.getAttribute('data-delay');
                    if (delay) {
                        element.style.transitionDelay = delay + 'ms';
                    }
                    
                    element.classList.add('in-view');
                    
                    // Unobserve after first reveal (one-time animation)
                    observer.unobserve(element);
                }
            });
        }, observerOptions);

        reveals.forEach(el => observer.observe(el));
    }

    // ============================================
    // B) Header Scroll State
    // ============================================
    function initHeaderScroll() {
        const header = document.getElementById('siteHeader') || document.querySelector('.main-header');
        
        if (!header) return;

        let ticking = false;

        function updateHeader() {
            if (window.scrollY > 20) {
                header.classList.add('is-scrolled');
            } else {
                header.classList.remove('is-scrolled');
            }
            ticking = false;
        }

        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(updateHeader);
                ticking = true;
            }
        }

        // Use passive listener for better performance
        window.addEventListener('scroll', onScroll, { passive: true });
        
        // Initial check
        updateHeader();
    }

    // ============================================
    // Initialize on DOM ready
    // ============================================
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initReveals();
                initHeaderScroll();
            });
        } else {
            initReveals();
            initHeaderScroll();
        }
    }

    init();
})();

