document.addEventListener('DOMContentLoaded', () => {
    initStatCounters();
    initTestimonialNav();
    initSmoothAnchors();
});

function initStatCounters() {
    const stats = document.querySelectorAll('[data-careers-stat] [data-count]');
    if (!stats.length) return;

    const animate = (el) => {
        const target = parseFloat(el.dataset.count || '0');
        const isFloat = String(el.dataset.count).includes('.');
        const duration = 1200;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = target * eased;
            el.textContent = isFloat ? value.toFixed(1) : Math.round(value).toString();
            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            animate(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.4 });

    stats.forEach((el) => observer.observe(el));
}

function initTestimonialNav() {
    const wrap = document.querySelector('[data-careers-testimonials]');
    if (!wrap) return;

    const track = wrap.querySelector('.careers-testimonials__track');
    const prev = wrap.querySelector('[data-testimonial-prev]');
    const next = wrap.querySelector('[data-testimonial-next]');

    if (!track || !prev || !next) return;

    const scrollAmount = () => Math.min(track.clientWidth * 0.85, 520);

    prev.addEventListener('click', () => {
        track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
    });

    next.addEventListener('click', () => {
        track.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
    });
}

function initSmoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', (e) => {
            const id = link.getAttribute('href');
            if (!id || id === '#') return;
            const target = document.querySelector(id);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}
