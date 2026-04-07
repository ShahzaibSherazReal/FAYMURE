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

// Newsletter popup
(function () {
    const popup = document.getElementById('newsletterPopup');
    const closeBtn = document.getElementById('newsletterPopupClose');
    const form = document.getElementById('newsletterPopupForm');
    const emailInput = document.getElementById('newsletterPopupEmail');
    const feedback = document.getElementById('newsletterPopupFeedback');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    const footerOpenBtn = document.getElementById('openNewsletterFromFooter');

    if (!popup) return;

    function getSubscribeUrl() {
        const basePath = (typeof window.BASE_PATH !== 'undefined' && window.BASE_PATH) ? window.BASE_PATH : '';
        return basePath + '/newsletter-subscribe';
    }

    function getSubscribeUrlCandidates() {
        const fromBase = getSubscribeUrl();
        const candidates = [fromBase, '/newsletter-subscribe', 'newsletter-subscribe'];
        return candidates.filter((url, index) => url && candidates.indexOf(url) === index);
    }

    function setFeedback(message, isSuccess) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove('is-success', 'is-error', 'is-show');
        feedback.classList.add(isSuccess ? 'is-success' : 'is-error', 'is-show');
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return '';
    }

    function markPopupSeenForSession() {
        document.cookie = 'newsletter_popup_seen=1; path=/; SameSite=Lax';
    }

    function shouldAutoShowPopup() {
        return getCookie('newsletter_popup_seen') !== '1';
    }

    function openPopup() {
        popup.classList.add('is-open');
        popup.setAttribute('aria-hidden', 'false');
        if (feedback) {
            feedback.classList.remove('is-success', 'is-error', 'is-show');
            feedback.textContent = '';
        }
    }

    function closePopup() {
        popup.classList.remove('is-open');
        popup.setAttribute('aria-hidden', 'true');
        markPopupSeenForSession();
    }

    function scheduleOpen() {
        setTimeout(openPopup, 250);
    }

    if (shouldAutoShowPopup()) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            scheduleOpen();
        } else {
            document.addEventListener('DOMContentLoaded', scheduleOpen);
            window.addEventListener('load', scheduleOpen);
        }
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closePopup);
    }

    popup.addEventListener('click', (event) => {
        if (event.target === popup) {
            closePopup();
        }
    });

    if (footerOpenBtn) {
        footerOpenBtn.addEventListener('click', () => {
            openPopup();
        });
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = emailInput ? emailInput.value.trim() : '';
            if (!email) {
                setFeedback('Please enter your email address.', false);
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Subscribing...';
            }

            try {
                const urls = getSubscribeUrlCandidates();
                let lastError = '';
                let successHandled = false;

                for (const endpoint of urls) {
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: 'email=' + encodeURIComponent(email),
                            credentials: 'same-origin'
                        });

                        const text = await response.text();
                        let data = null;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            lastError = text ? 'Server returned invalid response.' : 'Empty server response.';
                            continue;
                        }

                        if (!response.ok || !data.success) {
                            lastError = data.message || 'Subscription failed. Please try again.';
                            continue;
                        }

                        setFeedback(data.message || 'Subscribed successfully! Welcome to FAYMURE updates.', true);
                        form.reset();
                        markPopupSeenForSession();
                        setTimeout(closePopup, 1600);
                        successHandled = true;
                        break;
                    } catch (e) {
                        lastError = 'Connection failed.';
                    }
                }

                if (!successHandled) {
                    setFeedback(lastError || 'Could not connect. Please try again in a moment.', false);
                }
            } catch (err) {
                setFeedback('Could not connect. Please try again in a moment.', false);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Subscribe';
                }
            }
        });
    }
})();
