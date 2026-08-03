/**
 * Simple Lazy Loading for Images
 */
(function() {
    'use strict';

    // Wait for DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const images = document.querySelectorAll('img.lazy-img[data-src]');
        if (images.length === 0) {
            return;
        }

        if (!window.IntersectionObserver) {
            images.forEach(loadImage);
            return;
        }

        console.log(`Lazy Loading: Found ${images.length} images`);

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadImage(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '50px'
        });

        images.forEach(img => observer.observe(img));
    }

    function loadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) return;

        const srcset = img.getAttribute('data-srcset');
        if (srcset) {
            img.setAttribute('srcset', srcset);
            img.removeAttribute('data-srcset');
        }

        img.src = src;
        img.removeAttribute('data-src');
        img.classList.remove('lazy-img');
        img.classList.add('lazy-loaded');
    }
})();
