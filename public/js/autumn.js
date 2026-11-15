(() => {
    const root = document.documentElement;
    let garden, trees, land, wind;
    const maple = '<svg viewBox="0 0 40 44" xmlns="http://www.w3.org/2000/svg"><path d="m20 2 4 10 6-5-1 11 9-2-5 9 5 3-15 7-2 8-2-1 1-8L4 28l5-3-6-10 10 3-2-11 6 5Z" fill="currentColor"/><path d="M20 9v27m0-11-9-5m9 9 9-6" fill="none" stroke="#854115" stroke-opacity=".4" stroke-width="1.2"/></svg>';
    const picnic = `<svg class="autumn-picnic" viewBox="0 0 280 193" xmlns="http://www.w3.org/2000/svg">
        <defs><linearGradient id="AUTUMN-blanket" x2="1" y2="1"><stop stop-color="#EB885F"/><stop offset="1" stop-color="#C35D43"/></linearGradient>
        <linearGradient id="AUTUMN-hair" x2="1" y2="1"><stop stop-color="#713E2D"/><stop offset="1" stop-color="#3E251F"/></linearGradient>
        <pattern id="AUTUMN-check" width="22" height="22" patternUnits="userSpaceOnUse"><path d="M0 5h22M5 0v22" stroke="#FFE2B6" stroke-width="6" opacity=".35"/></pattern></defs>
        <ellipse cx="143" cy="179" rx="126" ry="9" fill="#91512420"/>
        <path d="m37 145 185-5 34 39-230 3Z" fill="url(#AUTUMN-blanket)" stroke="#A75137" stroke-width="1.5"/>
        <path d="m37 145 185-5 34 39-230 3Z" fill="url(#AUTUMN-check)"/>
        <g stroke="#FFDCB1" stroke-width="1"><path d="m28 182-3 4m14-4-2 4m14-4-2 4m15-4-1 4m14-4v4m14-4v4m14-4v4m14-4 1 4m14-4 1 4m14-4 1 4m14-4 2 4m14-4 2 4m14-4 2 4m14-4 3 4m14-4 3 4"/></g>
        <g class="autumn-person autumn-person-left" style="--origin:95px 150px;--head-origin:96px 85px;--lean:5deg">
            <path d="M77 127q-18 9-25 24-2 7 9 8l45-5 23 9q13 3 17-4t-25-23Z" fill="#425561" stroke="#31414D" stroke-width="2"/>
            <path d="m60 156 14-1m47 7 15 1" stroke="#F7E8D3" stroke-width="8" stroke-linecap="round"/>
            <path d="M74 91q17-15 33 0l12 42q-24 15-51-1Z" fill="#E2AB57" stroke="#A77535" stroke-width="1.5"/>
            <path d="m87 88 6 43m-17-25 28-2" stroke="#F7D393" stroke-width="3"/>
            <path d="m75 99-12 32 15 9" fill="none" stroke="#E2AB57" stroke-width="13" stroke-linecap="round"/><path d="m76 138 9 3" stroke="#F2C3A0" stroke-width="8" stroke-linecap="round"/>
            <g class="autumn-head"><path d="M67 57q-6-37 31-35 31-3 27 32l-9 29-38-1Z" fill="url(#AUTUMN-hair)"/>
                <ellipse cx="95" cy="61" rx="26" ry="28" fill="#F8CFAD" stroke="#CC9B7A"/>
                <path d="M68 57q-11-22 13-28l-6-10q20 10 30 2l-2 9q23-2 24 21-14-2-18-15-8 21-18 15l4-10q-12 16-27 16" fill="url(#AUTUMN-hair)"/>
                <path d="m77 44 11-8m17-2 10 8" stroke="#A26A42" stroke-width="2" fill="none"/>
                <g class="autumn-eyes" stroke="#56352A" stroke-width="2.8" fill="none" stroke-linecap="round"><path d="m80 61q4-5 8 0m14 0q4-5 8 0"/></g>
                <ellipse cx="78" cy="69" rx="5" ry="3" fill="#E99587" opacity=".65"/><ellipse cx="112" cy="69" rx="5" ry="3" fill="#E99587" opacity=".65"/>
                <g class="autumn-chew autumn-chew-left"><path d="M89 73q7 9 13-1" fill="#B65B50" stroke="#90463B" stroke-linecap="round"/><path d="m91 73 8-1" stroke="#FFF3E7" stroke-width="2"/></g>
            </g>
            <g class="autumn-offer"><path d="m108 103 13 15 19-7" fill="none" stroke="#E2AB57" stroke-width="12" stroke-linecap="round"/><path d="m137 111 7-2" stroke="#F2C3A0" stroke-width="8" stroke-linecap="round"/>
                <path d="m139 102 11-6 9 7-6 9-14-3Z" fill="#E7A64B" stroke="#AA702C"/><path d="m141 101 7-3 7 5-12 2Z" fill="#FFF0C9"/>
            </g>
        </g>
        <g class="autumn-person autumn-person-right" style="--origin:175px 150px;--head-origin:176px 86px;--lean:-6deg">
            <path class="autumn-hair" d="M152 42q23-26 46-3 16 19 7 39 0 20 24 30-18 19-38-1l-37-8q-22-17-2-57" fill="url(#AUTUMN-hair)" stroke="#603B2B"/>
            <path d="M159 128q-22 7-23 23l28 6 36-2q20 3 22-6-8-12-31-20Z" fill="#BA674C" stroke="#98513B" stroke-width="1.5"/>
            <path d="m151 155 14 1m29-1 14 1" stroke="#FFF1D8" stroke-width="8" stroke-linecap="round"/>
            <path d="M157 92q18-13 32 1l12 40q-29 13-52-1Z" fill="#FFF0D8" stroke="#CDAF88" stroke-width="1.5"/>
            <path d="m156 97 20 12 15-12" fill="none" stroke="#C26B48" stroke-width="8"/><path d="m177 105 9 24 10-4-12-23" fill="#C26B48"/>
            <path d="m158 104-9 22 13 10" fill="none" stroke="#FFF0D8" stroke-width="11" stroke-linecap="round"/>
            <path d="m163 136 8 1" stroke="#F1C3A4" stroke-width="7" stroke-linecap="round"/>
            <g class="autumn-head"><ellipse cx="175" cy="64" rx="26" ry="28" fill="#FAD2B7" stroke="#D7A78C"/>
                <path d="M149 60q-4-29 25-30 28-1 29 26-18 2-22-17-6 18-14 17l3-10q-8 16-21 14" fill="url(#AUTUMN-hair)"/>
                <path d="M148 44q1-34 29-34 29-1 29 35Z" fill="#FFF0DB" stroke="#D4B18C"/>
                <path d="M149 41q26-6 55 0l-1 10q-27-8-54 0Z" fill="#F3DDC5" stroke="#D4B18C"/>
                <path d="m158 22-3 14m14-21-2 20m13-20 1 20m10-14 3 15" stroke="#DDC1A3" stroke-width="2"/>
                <circle cx="179" cy="9" r="9" fill="#FFFAEE" stroke="#DFC4A6"/>
                <g class="autumn-eyes" stroke="#56352A" stroke-width="2.8" fill="none" stroke-linecap="round"><path d="m159 64q4-5 8 0m14 0q4-5 8 0"/></g>
                <ellipse cx="157" cy="72" rx="5" ry="3" fill="#E9908B" opacity=".7"/><ellipse cx="190" cy="72" rx="5" ry="3" fill="#E9908B" opacity=".7"/>
                <path class="autumn-chew autumn-chew-right" d="M170 77q5 5 10-1" fill="none" stroke="#AA5E51" stroke-width="2" stroke-linecap="round"/>
            </g>
            <g class="autumn-eat-right"><path d="m189 104 8 23-19 9" fill="none" stroke="#FFF0D8" stroke-width="11" stroke-linecap="round"/><path d="m177 137 6-1" stroke="#F1C3A4" stroke-width="7" stroke-linecap="round"/><path d="m170 130 5-9 8 2 3 7Z" fill="#E4A34F" stroke="#AE7838"/><path d="m174 125 7 1" stroke="#FFF0CC" stroke-width="3"/></g>
        </g>
        <g><path d="M38 149h23l-2 16H41Z" fill="#CA9254" stroke="#94612F"/><path d="M42 149q7-20 15 0" fill="none" stroke="#94612F" stroke-width="3"/><path d="m40 156 20 0m-18 5h17m-12-11v14m7-14v14" stroke="#E8BB7E" stroke-width="1.5"/>
            <ellipse cx="115" cy="172" rx="18" ry="4" fill="#FFF0CF" stroke="#D9AD7D"/><path d="m107 170 7-8 9 8Z" fill="#E7B05E"/><path d="m109 167 12 1" stroke="#B77C3E"/>
            <path d="M207 153h15v12q-8 5-15 0Z" fill="#FFFAE9" stroke="#C29467"/><path d="M222 155q12 0 1 9" fill="none" stroke="#C29467" stroke-width="2"/>
            <path class="autumn-steam" d="M211 151q-5-5 0-9t0-8"/><path class="autumn-steam" d="M217 149q-5-5 0-9t0-8"/>
        </g>
        <g fill="#D56C5E"><path class="autumn-heart" style="--heart-delay:0s" d="M135 45c-14-9-10-19-3-15l3 3 3-3c8-4 11 7-3 15Z"/><path class="autumn-heart" style="--heart-delay:-3s" d="M147 61c-11-7-8-15-3-12l3 2 2-2c7-3 9 5-2 12Z"/></g>
    </svg>`;
    const activity = () => [garden,trees,land,wind].forEach(scene => scene?.classList.toggle('autumn-paused', document.hidden));
    const sync = () => {
        if (root.dataset.theme !== 'autumn') {
            garden?.remove(); trees?.remove(); land?.remove(); wind?.remove(); garden = trees = land = wind = null;
            return;
        }
        if (garden) return;
        let elapsed = 0;
        try {
            let origin = Number(sessionStorage.getItem('scholar-autumn-origin'));
            if (!origin || origin > Date.now()) { origin = Date.now(); sessionStorage.setItem('scholar-autumn-origin', String(origin)); }
            elapsed = (Date.now() - origin) / 1000;
        } catch (_) {}
        garden = document.createElement('div');
        garden.className = 'autumn-garden';
        garden.setAttribute('aria-hidden', 'true');
        garden.style.setProperty('--autumn-phase', `${-(elapsed % 12)}s`);
        const treeMarkup = side => {
            const shedding = Array.from({length:8}, (_,i) => `<span class="autumn-shed-leaf" style="--leaf-x:${28+i*6}%;--leaf-y:${18+i%3*9}%;--leaf-drift:${100+i*31}px;--leaf-rise:${-12-i%3*9}px;--release:${i*.12}s;--leaf-turn:${i*47}deg;color:${['#C85C21','#F4B23D','#D78B25'][i%3]}">${maple}</span>`).join('');
            return `<div class="autumn-tree autumn-tree-${side}"><div class="autumn-tree-crown"></div><div class="autumn-branch-leaves">${shedding}</div></div>`;
        };
        const ground = '<div class="autumn-ground"></div>';
        garden.innerHTML = ground + picnic.replaceAll('AUTUMN-', 'autumn-picnic-');
        land = document.createElement('div');
        land.className = 'autumn-land';
        land.setAttribute('aria-hidden', 'true');
        land.innerHTML = ground;
        document.body.append(land);
        wind = document.createElement('div');
        wind.className = 'autumn-wind-field';
        wind.setAttribute('aria-hidden', 'true');
        wind.style.setProperty('--autumn-phase', `${-(elapsed % 12)}s`);
        for (let i = 0; i < 26; i++) {
            const particle = document.createElement('span');
            particle.className = i < 20 ? 'autumn-air-particle' : 'autumn-ground-leaf';
            particle.style.setProperty('--wind-y', `${12 + (i * 17.3) % 76}%`);
            particle.style.setProperty('--wind-lift', `${-18 - i % 5 * 9}px`);
            particle.style.setProperty('--wind-offset', `${i % 7 * .07}s`);
            particle.style.setProperty('--wind-size', `${2 + i % 3}px`);
            particle.style.setProperty('--ground-x', `${(i * 23.7) % 85}%`);
            if (i >= 20) particle.innerHTML = maple;
            wind.append(particle);
        }
        document.body.append(wind);
        trees = document.createElement('div');
        trees.className = 'autumn-trees';
        trees.setAttribute('aria-hidden', 'true');
        trees.style.setProperty('--autumn-phase', `${-(elapsed % 12)}s`);
        trees.innerHTML = treeMarkup('left');
        const sidebar = document.querySelector('.sidebar');
        (sidebar || document.body).append(trees);
        (sidebar || document.body).append(garden);
        activity();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root, {attributes:true, attributeFilter:['data-theme']});
    document.addEventListener('visibilitychange', activity);
    sync();
})();
