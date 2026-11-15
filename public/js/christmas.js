(() => {
    const root = document.documentElement;
    let scene;
    const walkKey = 'scholar-christmas-walk';
    const walkCycle = 80000; // Two 40-second trips, including the return journey.
    let walkOffset = 0;
    let walkStarted = null;
    let greetingActive = false;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const pauseWalk = () => {
        if (walkStarted === null) return;
        walkOffset = (walkOffset + performance.now() - walkStarted) % walkCycle;
        walkStarted = null;
    };
    const resumeWalk = () => {
        if (scene && !document.hidden && !reducedMotion.matches && !greetingActive && walkStarted === null) walkStarted = performance.now();
    };
    const saveWalk = () => {
        const offset = (walkOffset + (walkStarted === null ? 0 : performance.now() - walkStarted)) % walkCycle;
        try { sessionStorage.setItem(walkKey, String(offset)); } catch (_) {}
    };
    const sync = () => {
        if (root.dataset.theme !== 'christmas') {
            pauseWalk();
            if (scene) saveWalk();
            scene?.remove();
            scene = null;
            greetingActive = false;
            return;
        }
        if (scene) return;
        scene = document.createElement('div');
        scene.className = 'christmas-scene';
        scene.setAttribute('aria-hidden', 'true');
        const snow = document.createElement('div');
        snow.className = 'christmas-snow';
        for (let i = 0; i < 24; i++) {
            const flake = document.createElement('span');
            flake.className = 'christmas-flake';
            flake.style.setProperty('--x', `${(i * 41.7) % 100}%`);
            flake.style.setProperty('--size', `${3 + i % 4}px`);
            flake.style.setProperty('--duration', `${12 + i % 9}s`);
            flake.style.setProperty('--delay', `${-i * 1.7}s`);
            snow.append(flake);
        }
        const road = document.createElement('div');
        road.className = 'christmas-road';
        road.setAttribute('aria-hidden', 'true');
        scene.append(road, snow);
        const walker = document.createElement('button');
        walker.type = 'button';
        walker.setAttribute('aria-label', 'Say hello to the snowman');
        walker.className = 'christmas-walker';
        try {
            const saved = Number(sessionStorage.getItem(walkKey));
            if (Number.isFinite(saved) && saved >= 0) walkOffset = saved % walkCycle;
        } catch (_) {}
        walker.style.animationDelay = `-${walkOffset}ms`;
        walker.innerHTML = `<span class="christmas-greeting">Hello! <span>Happy holidays</span></span>
            <span class="christmas-avatar"><svg class="christmas-snowman" viewBox="0 0 100 128" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="50" cy="121" rx="32" ry="4" fill="#123d3220"/>
                <ellipse class="christmas-boot christmas-boot-left" cx="37" cy="122" rx="13" ry="5" fill="#164e38"/>
                <ellipse class="christmas-boot christmas-boot-right" cx="63" cy="122" rx="13" ry="5" fill="#164e38"/>
                <g stroke-linecap="round" stroke-linejoin="round">
                    <path d="M28 82 10 66m4 4-1-10m2 12-9-1M74 81 89 60m-3 4 9-2m-7 0-1-10" stroke="#79513b" stroke-width="4"/>
                    <ellipse cx="50" cy="95" rx="28" ry="27" fill="#f2faff" stroke="#bed6de" stroke-width="2"/>
                    <circle cx="50" cy="55" r="22" fill="#fff" stroke="#bed6de" stroke-width="2"/>
                    <path d="M31 38q6-30 28-27l10 26" fill="#b83248"/>
                    <circle cx="59" cy="12" r="6" fill="#fff"/>
                    <path d="M29 38q21-8 42 0" stroke="#fff" stroke-width="8"/>
                    <circle cx="43" cy="51" r="2.5" fill="#223d3b"/><circle cx="58" cy="51" r="2.5" fill="#223d3b"/>
                    <path d="m50 56 14 4-14 3" fill="#ef923b"/>
                    <path class="christmas-smile" d="M42 66q8 7 16 0" stroke="#223d3b" stroke-width="2" fill="none"/>
                    <circle cx="35" cy="60" r="4" fill="#f5bec3"/><circle cx="65" cy="60" r="4" fill="#f5bec3"/>
                    <path d="M33 75q17 8 35 0" stroke="#247452" stroke-width="10"/>
                    <path d="m57 79 6 19 10-3-9-20" fill="#247452"/>
                    <path d="m62 87 7-2m-4 8 7-2" stroke="#d9bc70" stroke-width="2"/>
                    <circle cx="48" cy="91" r="3" fill="#223d3b"/><circle cx="48" cy="104" r="3" fill="#223d3b"/>
                </g>
            </svg></span>`;
        walker.querySelector('.christmas-avatar').style.animationDelay = `-${walkOffset}ms`;
        scene.append(walker);
        // Only the snowman receives clicks; the rest of the decoration stays transparent.
        scene.removeAttribute('aria-hidden');
        snow.setAttribute('aria-hidden', 'true');
        walker.querySelector('svg').setAttribute('aria-hidden', 'true');
        const greeting = walker.querySelector('.christmas-greeting');
        greeting.setAttribute('aria-hidden', 'true');
        let greetingTimer;
        walker.addEventListener('click', () => {
            if (!scene?.contains(walker)) return;
            pauseWalk();
            greetingActive = true;
            clearTimeout(greetingTimer);
            walker.classList.remove('christmas-saying-hello');
            // Restart the short jump when greeting the snowman again.
            void walker.offsetWidth;
            walker.classList.add('christmas-saying-hello');
            walker.setAttribute('aria-label', 'Hello! Happy holidays! Say hello again');
            greetingTimer = setTimeout(() => {
                if (!scene?.contains(walker)) return;
                walker.classList.remove('christmas-saying-hello');
                walker.setAttribute('aria-label', 'Say hello to the snowman');
                greetingActive = false;
                resumeWalk();
            }, 2400);
        });
        document.body.append(scene);
        scene.classList.toggle('christmas-paused', document.hidden);
        scene.classList.toggle('christmas-reduced', reducedMotion.matches);
        resumeWalk();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { pauseWalk(); if (scene) saveWalk(); }
        scene?.classList.toggle('christmas-paused', document.hidden);
        if (!document.hidden) resumeWalk();
    });
    window.addEventListener('pagehide', () => { pauseWalk(); if (scene) saveWalk(); });
    window.addEventListener('pageshow', resumeWalk);
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) pauseWalk();
        scene?.classList.toggle('christmas-reduced', reducedMotion.matches);
        if (!reducedMotion.matches) resumeWalk();
    });
    sync();
})();
