(() => {
    const track = document.getElementById('headline-ticker-track');
    if (!track) return;

    let signature = track.dataset.signature || '';
    let busy = false;
    let rotating = false;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const render = (items) => {
        const nextSignature = JSON.stringify(items);
        if (nextSignature === signature) return;
        signature = nextSignature;
        const fragment = document.createDocumentFragment();
        if (!items.length) {
            const message = document.createElement('span');
            message.className = 'ticker-empty';
            message.textContent = 'No trending headlines yet — scan a source to begin.';
            fragment.appendChild(message);
        } else {
            for (const item of items) {
                const link = document.createElement('a');
                link.href = item.url;
                link.rel = 'noopener noreferrer';
                link.target = '_blank';
                link.className = 'ticker-story';
                link.textContent = item.title;
                fragment.appendChild(link);
            }
        }

        rotating = false;
        track.style.transition = 'none';
        track.style.transform = 'translateY(0)';
        track.replaceChildren(fragment);
    };

    const rotate = () => {
        if (document.hidden || reducedMotion.matches || rotating ||
            track.children.length < 2 ||
            track.closest('.ticker-viewport')?.matches(':hover') ||
            track.closest('.ticker-viewport')?.matches(':focus-within')) return;

        rotating = true;
        const height = track.firstElementChild.getBoundingClientRect().height;
        track.style.transition = 'transform 650ms cubic-bezier(.25,.75,.32,1)';
        track.style.transform = `translateY(-${height}px)`;
    };

    track.addEventListener('transitionend', (event) => {
        if (!rotating || event.target !== track || event.propertyName !== 'transform') return;
        track.style.transition = 'none';
        track.appendChild(track.firstElementChild);
        track.style.transform = 'translateY(0)';
        rotating = false;
    });

    const poll = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('/ticker.php', {cache:'no-store'});
            if (!response.ok) return;
            const json = await response.json();
            if (Array.isArray(json.headlines)) render(json.headlines);
        } catch (_) {
            // Retain the displayed headlines during temporary server errors.
        } finally {
            busy = false;
        }
    };

    window.setInterval(rotate, 6500);
    window.setInterval(poll, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
