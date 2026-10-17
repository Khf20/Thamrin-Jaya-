// Subsidiary carousels remain scrollable without JavaScript and never auto-advance.
document.querySelectorAll('.layout-subsidiary .cms-hero').forEach(hero => {
    const track = hero.querySelector('.cms-hero-track');
    const controls = hero.querySelector('[data-carousel-controls]');
    if (!track || !controls) return;

    const slides = [...track.querySelectorAll('.cms-hero-slide')];
    const previous = controls.querySelector('[data-carousel-prev]');
    const next = controls.querySelector('[data-carousel-next]');
    const status = controls.querySelector('[data-carousel-status]');
    let index = 0;

    const update = () => {
        index = Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / track.clientWidth)));
        previous.disabled = index === 0;
        next.disabled = index === slides.length - 1;
        status.textContent = `${index + 1} / ${slides.length}`;
    };
    const move = direction => track.scrollTo({
        left: Math.max(0, Math.min(slides.length - 1, index + direction)) * track.clientWidth,
        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    });

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    controls.hidden = false;
    update();
});

// A product inquiry must leave the modal before navigating to the contact cluster.
document.querySelectorAll('.layout-subsidiary dialog .preview-content a[href*="#cluster-contact"]').forEach(link => {
    link.addEventListener('click', () => link.closest('dialog').close());
});
