// Main JavaScript for FAYMURE Website

// Search Modal
const searchBtn = document.querySelector('.search-btn');
const searchModal = document.getElementById('searchModal');
const closeSearch = document.querySelector('.close-search');

if (searchBtn) {
    searchBtn.addEventListener('click', () => {
        searchModal.style.display = 'block';
    });
}

if (closeSearch) {
    closeSearch.addEventListener('click', () => {
        searchModal.style.display = 'none';
    });
}

window.addEventListener('click', (e) => {
    if (e.target === searchModal) {
        searchModal.style.display = 'none';
    }
});

// Language Toggle
const langToggle = document.getElementById('langToggle');
if (langToggle) {
    langToggle.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

// Handle language change
const langLinks = document.querySelectorAll('.lang-dropdown a');
langLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const lang = link.getAttribute('href').split('=')[1];
        window.location.href = `?lang=${lang}`;
    });
});

// Carousel functionality
let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');
const dots = document.querySelectorAll('.dot');

function showSlide(n) {
    if (slides.length === 0) return;
    
    if (n >= slides.length) {
        currentSlide = 0;
    }
    if (n < 0) {
        currentSlide = slides.length - 1;
    }
    
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    if (slides[currentSlide]) {
        slides[currentSlide].classList.add('active');
    }
    if (dots[currentSlide]) {
        dots[currentSlide].classList.add('active');
    }
}

function moveCarousel(n) {
    currentSlide += n;
    showSlide(currentSlide);
}

function currentSlideFunc(n) {
    currentSlide = n - 1;
    showSlide(currentSlide);
}

// Auto-advance carousel
if (slides.length > 0) {
    setInterval(() => {
        moveCarousel(1);
    }, 5000);
}

// Initialize carousel
if (slides.length > 0) {
    showSlide(0);
}

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Form validation
const forms = document.querySelectorAll('form');
forms.forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#dc3545';
            } else {
                field.style.borderColor = '';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});

// Visitor tracking (time on page) — URL from server (TRACK_VISIT_URL) so it works on live
(function () {
    if (!navigator.sendBeacon && !window.fetch) return;
    var start = Date.now();
    var path = window.location.pathname + (window.location.search || '');
    var url = (typeof window.TRACK_VISIT_URL !== 'undefined' && window.TRACK_VISIT_URL)
        ? window.TRACK_VISIT_URL
        : ((typeof window.BASE_PATH !== 'undefined' ? window.BASE_PATH : '') || '') + '/track-visit';
    var lastSent = start;

    function send(durationSec, useBeacon) {
        var fd = new FormData();
        fd.append('page', path);
        fd.append('duration', String(durationSec));
        if (useBeacon !== false && navigator.sendBeacon) {
            navigator.sendBeacon(url, fd);
        } else if (window.fetch) {
            try { fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }); } catch (e) {}
        }
    }

    function flush() {
        var now = Date.now();
        var sec = Math.round((now - lastSent) / 1000);
        if (sec > 0) {
            lastSent = now;
            send(sec, true);
        }
    }

    window.addEventListener('beforeunload', flush);
    window.addEventListener('pagehide', flush);
    setInterval(function () {
        var now = Date.now();
        var sec = Math.round((now - lastSent) / 1000);
        if (sec >= 25) {
            lastSent = now;
            send(sec, false);
        }
    }, 25000);
})();
