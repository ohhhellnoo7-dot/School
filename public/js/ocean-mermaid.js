(() => {
    const imageUrl = document.currentScript?.dataset.mermaidImage;
    if (!imageUrl) return;
    const root = document.documentElement;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const storageKey = 'scholar-ocean-mermaid';
    let scene, mermaid, travel, greetingTimer, resizeFrame, viewportObserver;
    let position = { x:0, y:0 }, bounds, routeIndex = 0;
    let hovered = false, focused = false, greeting = false;

    const clamp = (value, low, high) => Math.max(low, Math.min(value, high));
    const measure = () => {
        const width = mermaid.offsetWidth, height = mermaid.offsetHeight;
        const bar = document.querySelector('.topbar')?.getBoundingClientRect();
        return {
            left:18, right:Math.max(18, innerWidth - width - 18),
            top:Math.min(Math.max(76, (bar?.bottom || 0) + 70), Math.max(76, innerHeight - height - 30)),
            bottom:Math.max(48, innerHeight - height - 30),
        };
    };
    const place = (point) => {
        position = { x:clamp(point.x, bounds.left, bounds.right), y:clamp(point.y, bounds.top, bounds.bottom) };
        mermaid.style.transform = `translate3d(${position.x}px,${position.y}px,0)`;
    };
    const capture = () => {
        if (!mermaid) return;
        const rect = mermaid.getBoundingClientRect();
        position = { x:rect.left, y:rect.top };
    };
    const save = () => {
        if (!mermaid || !bounds) return;
        capture();
        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                x:clamp((position.x - bounds.left) / Math.max(1,bounds.right - bounds.left),0,1),
                y:clamp((position.y - bounds.top) / Math.max(1,bounds.bottom - bounds.top),0,1),
                routeIndex,
            }));
        } catch (_) {}
    };
    const freeze = () => {
        if (!mermaid) return;
        capture();
        if (travel) { travel.onfinish = null; travel.cancel(); travel = null; }
        place(position);
    };
    const canSwim = () => scene && !document.hidden && !motion.matches && !hovered && !focused && !greeting;
    const destination = () => {
        // Visit all four corners, then explore the middle edges and open water.
        const stops = [[0,0],[1,1],[1,0],[0,1],[.5,0],[1,.5],[.5,1],[0,.5],[.3,.35],[.7,.65]];
        const stop = stops[routeIndex++ % stops.length];
        const jitter = routeIndex % stops.length >= 8 ? .12 : .035;
        return {
            x:bounds.left + clamp(stop[0] + (Math.random()-.5)*jitter,0,1)*(bounds.right-bounds.left),
            y:bounds.top + clamp(stop[1] + (Math.random()-.5)*jitter,0,1)*(bounds.bottom-bounds.top),
        };
    };
    const swim = () => {
        if (!canSwim() || travel) return;
        const start = { ...position };
        let end = destination();
        if (Math.hypot(end.x-start.x,end.y-start.y) < 40) end = destination();
        mermaid.style.setProperty('--mermaid-facing', end.x < start.x ? '-1' : '1');
        const heading=clamp(Math.atan2(end.y-start.y,Math.abs(end.x-start.x))*180/Math.PI,-18,18);
        mermaid.style.setProperty('--mermaid-heading',`${heading*(end.x<start.x?-1:1)}deg`);
        const distance = Math.hypot(end.x-start.x,end.y-start.y);
        const middle = {
            x:clamp((start.x+end.x)/2 + (Math.random()-.5)*90,bounds.left,bounds.right),
            y:clamp((start.y+end.y)/2 + (Math.random()-.5)*80,bounds.top,bounds.bottom),
        };
        const frames = [0,.25,.5,.75,1].map(t => {
            const u=1-t;
            const x=u*u*start.x+2*u*t*middle.x+t*t*end.x;
            const y=u*u*start.y+2*u*t*middle.y+t*t*end.y;
            return { transform:`translate3d(${x}px,${y}px,0)`,offset:t };
        });
        travel = mermaid.animate(frames,{ duration:clamp(distance/38*1000,4500,26000),easing:'linear',fill:'forwards' });
        travel.onfinish = () => {
            const completed = travel;
            travel = null;
            place(end);
            if (completed) { completed.onfinish=null; completed.cancel(); }
            save();
            swim();
        };
    };
    const sparkle = () => {
        if (motion.matches) return;
        capture();
        for (let i=0;i<8;i++) {
            const dot=document.createElement('span');
            dot.className='ocean-mermaid-sparkle';
            dot.style.setProperty('--sparkle-x',`${position.x+mermaid.offsetWidth*(.25+Math.random()*.5)}px`);
            dot.style.setProperty('--sparkle-y',`${position.y+mermaid.offsetHeight*.3}px`);
            dot.style.setProperty('--sparkle-dx',`${Math.round(Math.random()*90-45)}px`);
            scene.append(dot);
            dot.addEventListener('animationend',()=>dot.remove(),{ once:true });
        }
    };
    const endGreeting = () => {
        clearTimeout(greetingTimer);
        greeting=false;
        mermaid?.classList.remove('is-greeting');
        mermaid?.setAttribute('aria-label','Say hello to the mermaid');
        swim();
    };
    const sayHello = () => {
        freeze();
        greeting=true;
        clearTimeout(greetingTimer);
        mermaid.classList.add('is-greeting');
        const firstName=document.querySelector('.user-info strong')?.textContent.trim().split(/\s+/)[0];
        const message=firstName ? `Hello, ${firstName}! Lovely to see you!` : 'Hello! Lovely to see you!';
        mermaid.querySelector('.ocean-mermaid-greeting').textContent=message;
        mermaid.setAttribute('aria-label',`${message} Say hello again`);
        sparkle();
        greetingTimer=setTimeout(endGreeting,3400);
    };
    const rig = () => {
        const svgNS='http://www.w3.org/2000/svg';
        const svg=document.createElementNS(svgNS,'svg');
        svg.setAttribute('viewBox','0 0 100 150');
        svg.setAttribute('aria-hidden','true');
        svg.classList.add('ocean-mermaid-rig');
        svg.innerHTML=`<defs>
            <path id="mermaid-tail-region" d="M32 64Q46 68 62 63L82 150H12L17 116 30 86Z"/>
            <path id="mermaid-arm-region" d="M67 38 75 28 77 24 77 17Q78 15 79 19L80 21 83 16Q84 14 85 15L84 20 86 16Q88 15 88 17L85 22 88 19Q90 18 90 20L85 26 81 28 75 41Q71 45 68 42Z"/>
            <path id="mermaid-hair-region" d="M0 24H31Q36 30 34 36L31 52 29 64H0Z"/>
            <image id="mermaid-source" width="100" height="150" preserveAspectRatio="none"/>
            <mask id="mermaid-body-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="100" height="150"><rect width="100" height="150" fill="white"/><use href="#mermaid-tail-region" fill="black"/><use href="#mermaid-arm-region" fill="black"/><use href="#mermaid-hair-region" fill="black"/></mask>
            <clipPath id="mermaid-tail-clip"><use href="#mermaid-tail-region"/></clipPath>
            <clipPath id="mermaid-fluke-clip"><rect y="111" width="100" height="39"/></clipPath>
            <mask id="mermaid-tail-body-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="100" height="150"><rect width="100" height="150" fill="white"/><rect y="112" width="100" height="38" fill="black"/></mask>
            <clipPath id="mermaid-arm-clip"><use href="#mermaid-arm-region"/></clipPath>
            <clipPath id="mermaid-hair-clip"><use href="#mermaid-hair-region"/></clipPath>
        </defs>
        <g class="ocean-mermaid-hair"><use href="#mermaid-source" clip-path="url(#mermaid-hair-clip)"/></g>
        <g class="ocean-mermaid-tail"><g clip-path="url(#mermaid-tail-clip)"><use href="#mermaid-source" mask="url(#mermaid-tail-body-mask)"/><g class="ocean-mermaid-fluke"><use href="#mermaid-source" clip-path="url(#mermaid-fluke-clip)"/></g></g></g>
        <use href="#mermaid-source" mask="url(#mermaid-body-mask)"/>
        <g class="ocean-mermaid-arm"><use href="#mermaid-source" clip-path="url(#mermaid-arm-clip)"/></g>
        <g class="ocean-mermaid-eyelids" fill="#F2B48E" stroke="#75442B" stroke-width=".2"><ellipse cx="46.5" cy="16.1" rx="1.9" ry="1"/><ellipse cx="53.1" cy="14.7" rx="1.4" ry="1"/></g>
        <g class="ocean-mermaid-happy-face"><path d="M49.2 20.4Q52 23.2 54 19.5Q53.2 24.1 49.2 20.4Z" fill="#B83C51"/><path d="M49.8 20.7Q52 22 53.5 20.3" stroke="#FFF7DE" stroke-width=".5" fill="none"/><ellipse cx="45.7" cy="19.6" rx="1.3" ry=".6" fill="#F28C97" opacity=".55"/></g>
        <path class="ocean-mermaid-hit" d="M39 1Q66-4 65 19L58 28 68 34 78 16 86 13 91 20 81 30 74 44 62 39 61 64Q68 92 69 112L78 146Q57 153 53 130 43 147 20 149L23 115 34 91 29 72 24 82 23 70 30 57Q7 66 9 42 2 29 23 23L27 10Z"/>
        `;
        svg.querySelector('#mermaid-source').setAttribute('href',imageUrl);
        return svg;
    };
    const activity = () => {
        if (!scene) return;
        scene.classList.toggle('ocean-mermaid-paused',document.hidden);
        scene.classList.toggle('ocean-mermaid-reduced',motion.matches);
        if (document.hidden || motion.matches) { freeze(); save(); } else swim();
    };
    const resize = () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame=requestAnimationFrame(()=>{
            if (!mermaid) return;
            freeze(); bounds=measure(); place(position); swim();
        });
    };
    const destroy = () => {
        save(); freeze(); clearTimeout(greetingTimer); cancelAnimationFrame(resizeFrame);
        viewportObserver?.disconnect(); viewportObserver=null;
        scene?.remove(); scene=mermaid=travel=null;
        hovered=focused=greeting=false;
    };
    const sync = () => {
        if (root.dataset.theme!=='ocean') { if(scene) destroy(); return; }
        if (scene) return;
        scene=document.createElement('div'); scene.className='ocean-mermaid-scene';
        mermaid=document.createElement('button'); mermaid.type='button'; mermaid.className='ocean-mermaid';
        mermaid.setAttribute('aria-label','Say hello to the mermaid');
        const facing=document.createElement('div'); facing.className='ocean-mermaid-facing';
        const floating=document.createElement('div'); floating.className='ocean-mermaid-floating'; floating.append(rig()); facing.append(floating);
        for(let i=0;i<3;i++) { const wake=document.createElement('span'); wake.className='ocean-mermaid-wake'; facing.append(wake); }
        const bubble=document.createElement('span'); bubble.className='ocean-mermaid-greeting'; bubble.setAttribute('aria-live','polite');
        mermaid.append(facing,bubble); scene.append(mermaid); document.body.append(scene);
        bounds=measure();
        let saved;
        try { saved=JSON.parse(sessionStorage.getItem(storageKey)); } catch (_) {}
        routeIndex=Number.isInteger(saved?.routeIndex) && saved.routeIndex>=0 ? saved.routeIndex : 0;
        const valid=Number.isFinite(saved?.x)&&Number.isFinite(saved?.y);
        place(valid ? { x:bounds.left+clamp(saved.x,0,1)*(bounds.right-bounds.left),y:bounds.top+clamp(saved.y,0,1)*(bounds.bottom-bounds.top) } : { x:bounds.right,y:bounds.bottom });
        mermaid.addEventListener('pointerenter',event=>{ if(event.pointerType==='mouse') { hovered=true; freeze(); } });
        mermaid.addEventListener('pointerleave',event=>{ if(event.pointerType==='mouse') { hovered=false; swim(); } });
        mermaid.addEventListener('focus',()=>{ focused=mermaid.matches(':focus-visible'); if(focused) freeze(); });
        mermaid.addEventListener('blur',()=>{ focused=false; swim(); });
        mermaid.addEventListener('click',sayHello);
        mermaid.addEventListener('keydown',event=>{ if(event.key==='Escape') endGreeting(); });
        if (typeof ResizeObserver!=='undefined') { viewportObserver=new ResizeObserver(resize); viewportObserver.observe(scene); }
        activity();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root,{ attributes:true,attributeFilter:['data-theme'] });
    document.addEventListener('visibilitychange',activity);
    motion.addEventListener('change',activity);
    window.addEventListener('resize',resize,{ passive:true });
    window.addEventListener('pagehide',()=>{ if(scene) { save(); freeze(); } });
    window.addEventListener('pageshow',activity);
    sync();
})();
