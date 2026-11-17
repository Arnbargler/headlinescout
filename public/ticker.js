(() => {
    const track = document.getElementById('headline-ticker-track');
    if (!track) return;
    let signature = track.dataset.signature || '';
    let busy = false;
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
                const source = document.createElement('strong');
                source.textContent = item.source + ' · ';
                link.appendChild(source);
                link.appendChild(document.createTextNode(item.title));
                fragment.appendChild(link);
            }
        }
        track.replaceChildren(fragment);
        track.classList.toggle('ticker-scroll',items.length>0);
        // Recreate the animation only when a headline ranking actually changes.
        track.style.animation = 'none';
        void track.offsetWidth;
        track.style.animation = '';
    };

    const poll = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('/ticker.php', {cache:'no-store'});
            if (!response.ok) return;
            const json = await response.json();
            if (Array.isArray(json.headlines)) render(json.headlines);
        } catch (_) {
            // Keep the previous ticker during temporary local server errors.
        } finally {
            busy = false;
        }
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.setInterval(poll, 15000);
})();
