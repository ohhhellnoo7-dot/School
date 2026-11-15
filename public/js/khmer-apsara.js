(() => {
    const imageUrl = document.currentScript?.dataset.apsaraImage;
    if (!imageUrl) return;
    const root = document.documentElement;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const key = 'scholar-apsara-journey';
    let scene, traveler, flowerTimer;
    let phase = 0;
    const save = () => {
        if (!traveler) return;
        const time = traveler.getAnimations()[0]?.currentTime;
        const position = (phase + (typeof time === 'number' ? time : 0)) % 112000;
        try { sessionStorage.setItem(key, String(position)); } catch (_) {}
    };
    const releaseFlower = () => {
        if (!scene || document.hidden || motion.matches) return;
        const bounds = traveler.getBoundingClientRect();
        const flower = document.createElement('span');
        flower.className = 'khmer-apsara-flower';
        const pink = Math.random() > .45;
        flower.style.setProperty('--flower-x', `${bounds.left + bounds.width * .10 + Math.random() * 9}px`);
        flower.style.setProperty('--flower-y', `${bounds.top + bounds.height * .58}px`);
        flower.style.setProperty('--flower-size', `${12 + Math.random() * 8}px`);
        flower.style.setProperty('--flower-duration', `${14 + Math.random() * 7}s`);
        flower.style.setProperty('--flower-drift', `${Math.round(Math.random() * 90 - 45)}px`);
        flower.innerHTML = `<svg viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg"><g fill="${pink ? '#e38ba8' : '#e6ba56'}" stroke="${pink ? '#bc627e' : '#bd8c2d'}" stroke-width=".6"><ellipse cx="15" cy="8" rx="4.5" ry="7"/><ellipse cx="15" cy="8" rx="4.5" ry="7" transform="rotate(72 15 15)"/><ellipse cx="15" cy="8" rx="4.5" ry="7" transform="rotate(144 15 15)"/><ellipse cx="15" cy="8" rx="4.5" ry="7" transform="rotate(216 15 15)"/><ellipse cx="15" cy="8" rx="4.5" ry="7" transform="rotate(288 15 15)"/></g><circle cx="15" cy="15" r="3.5" fill="#fff1ac"/></svg>`;
        scene.append(flower);
        flower.addEventListener('animationend', () => flower.remove(), { once:true });
    };
    const updateActivity = () => {
        clearTimeout(flowerTimer);
        scene?.classList.toggle('khmer-apsara-paused', document.hidden);
        scene?.classList.toggle('khmer-apsara-reduced', motion.matches);
        if (scene && !document.hidden && !motion.matches) {
            const art = traveler.querySelector('.khmer-apsara-art');
            const animation = art.getAnimations().find(item => item.animationName === 'apsara-pose-cycle');
            const cycleTime = Number(animation?.currentTime || 0) % 2400;
            const untilRelease = (1400 - cycleTime + 2400) % 2400;
            const scatter = () => {
                if (!scene || document.hidden || motion.matches) return;
                for (let i = 0; i < 3; i++) releaseFlower();
                flowerTimer = setTimeout(scatter, 2400);
            };
            flowerTimer = setTimeout(scatter, untilRelease);

        }
    };
    const sync = () => {
        if (root.dataset.theme !== 'khmer-heritage') {
            save();
            clearTimeout(flowerTimer);
            scene?.remove();
            scene = traveler = null;
            return;
        }
        if (scene) return;
        scene = document.createElement('div');
        scene.className = 'khmer-apsara-scene';
        scene.setAttribute('aria-hidden','true');
        traveler = document.createElement('div');
        traveler.className = 'khmer-apsara-traveler';
        try {
            const saved = Number(sessionStorage.getItem(key));
            phase = Number.isFinite(saved) && saved >= 0 ? saved % 112000 : 0;
        } catch (_) {}
        traveler.style.animationDelay = `-${phase}ms`;
        const art = document.createElement('div');
        art.className = 'khmer-apsara-art';
        art.style.backgroundImage = `url("${imageUrl}")`;
        traveler.append(art);
        scene.append(traveler);
        document.body.append(scene);
        updateActivity();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root,{ attributes:true, attributeFilter:['data-theme'] });
    document.addEventListener('visibilitychange', updateActivity);
    motion.addEventListener('change', updateActivity);
    window.addEventListener('pagehide', () => { save(); clearTimeout(flowerTimer); });
    window.addEventListener('pageshow', updateActivity);
    sync();
})();
