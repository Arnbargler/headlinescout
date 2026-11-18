(() => {
    const periods = [...document.querySelectorAll('.word-period[data-period]')];
    if (!periods.length) return;
    const symbols = {up:'↑', down:'↓', flat:'→', new:'NEW'};
    const descriptions = {up:'Trending up', down:'Trending down', flat:'No change', new:'New to top ten'};
    let busy = false;

    const snapshot = (period) => new Map(
        [...period.querySelectorAll('li[data-word]')].map((li, index) => [
            li.dataset.word,
            {position:index, count:Number(li.dataset.count)}
        ])
    );

    const createRow = (entry, index, before) => {
        const li = document.createElement('li');
        const direction = Object.hasOwn(symbols,entry.direction) ? entry.direction : 'flat';
        const name = String(entry.word);
        const count = Number(entry.count);
        li.className = 'word-trend-' + direction;
        li.dataset.word = name;
        li.dataset.count = String(count);
        if (!before.has(name) || before.get(name).position !== index || before.get(name).count !== count) {
            li.classList.add('word-change-flash');
        }

        const label = document.createElement('span');
        label.className = 'word-label';
        label.textContent = name;

        const bar = document.createElement('span');
        bar.className = 'word-bar';
        bar.setAttribute('aria-hidden', 'true');
        const fill = document.createElement('span');
        fill.className = 'word-bar-fill';
        fill.style.width = String(Math.max(0, Math.min(100, Number(entry.percent) || 0))) + '%';
        bar.appendChild(fill);

        const total = document.createElement('strong');
        total.className = 'word-total';
        total.textContent = String(count);

        const arrow = document.createElement('span');
        arrow.className = 'word-direction direction-' + direction;
        arrow.textContent = symbols[direction];
        arrow.title = direction === 'new' ? 'Not in previous top 10' : 'Previous period: ' + entry.previous + ' mentions';
        arrow.setAttribute('aria-label',descriptions[direction]);
        li.append(label,bar,total,arrow);
        return li;
    };

    const renderPeriod = (period, rows) => {
        const before = snapshot(period);
        const previousList = [...period.querySelectorAll('li[data-word]')];
        // Do not rebuild or flash when rankings, counts and bar values are unchanged.
        const same = rows.length === previousList.length &&
            rows.every((r,i) => previousList[i].dataset.word === String(r.word) &&
                Number(previousList[i].dataset.count) === Number(r.count) &&
                previousList[i].classList.contains('word-trend-' + r.direction) &&
                Math.abs(Number(previousList[i].querySelector('.word-bar-fill')?.style.width.replace('%','')) - Number(r.percent)) < .01);
        if (same) return;

        const list = document.createElement('ol');
        list.className = 'word-ranking';
        rows.forEach((r,index) => list.appendChild(createRow(r,index,before)));

        period.querySelector('.word-ranking, .word-empty')?.remove();
        if (rows.length) {
            period.appendChild(list);
        } else {
            const empty = document.createElement('p');
            empty.className = 'word-empty';
            empty.textContent = 'No matching words in this period.';
            period.appendChild(empty);
        }
    };

    const poll = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('/word-stats.php',{cache:'no-store'});
            if (!response.ok) return;
            const payload = await response.json();
            if (!Array.isArray(payload.periods)) return;
            for (const result of payload.periods) {
                const period = periods.find(p => p.dataset.period === result.key);
                if (period && Array.isArray(result.rows)) renderPeriod(period,result.rows);
            }
        } catch (_) {
            // Retain existing charts if the endpoint is temporarily unavailable.
        } finally {
            busy = false;
        }
    };

    window.setInterval(poll,30000);
    document.addEventListener('visibilitychange',() => { if (!document.hidden) poll(); });
})();
