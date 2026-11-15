(() => {
    // The normal shark hunts fish and reacts to swimmers and the parachutist.
    const createShark = () => {
    const root = document.documentElement;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const storageKey = 'scholar-ocean-shark';
    const sharkName='shark';
    let scene, shark, travel, greetingTimer, resizeFrame, viewportObserver, huntTimer, biteTimer;
    let position = { x:0, y:0 }, bounds, routeIndex = 0;
    let hovered = false, focused = false, greeting = false;
    let biting=false, hunted=null, lastHunt=0, snacks=0;
    let fisherChase=false,lastFisherPursuit=0;
    let breaching=false;
    let flightPhase='water', phaseElapsed=0, phaseTick=0;
    const waterline=()=>parseFloat(getComputedStyle(root).getPropertyValue('--ocean-surface-y'))||205;

    const clamp = (value, low, high) => Math.max(low, Math.min(value, high));
    const measure = () => {
        const bar = document.querySelector('.topbar')?.getBoundingClientRect();
        const surface=parseFloat(getComputedStyle(root).getPropertyValue('--ocean-surface-y'))||Math.min(300,Math.max(230,innerHeight*.32));
        const width=shark.offsetWidth,height=shark.offsetHeight;
        return {
            left:18, right:Math.max(18, innerWidth - width - 18),
            top:Math.min(Math.max(surface+12, (bar?.bottom || 0) + 70), Math.max(76, innerHeight - height - 30)),
            bottom:Math.max(48, innerHeight - height - 30),
        };
    };
    const place = (point) => {
        position = { x:clamp(point.x, bounds.left, bounds.right), y:clamp(point.y, bounds.top, bounds.bottom) };
        shark.style.transform = `translate3d(${position.x}px,${position.y}px,0)`;
    };
    const capture = () => {
        if (!shark) return;
        const rect = shark.getBoundingClientRect();
        position = { x:rect.left, y:rect.top };
    };
    const save = () => {
        if (!shark || !bounds) return;
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
        if (!shark) return;
        capture();
        if (travel) { travel.onfinish = null; travel.cancel(); travel = null; }
        shark.style.transform=`translate3d(${position.x}px,${position.y}px,0)`;
    };
    const canSwim = () => scene && !document.hidden && !motion.matches && !hovered && !focused && !greeting && !biting && !breaching;
    document.addEventListener('ocean:spill-catch',()=>{
        if(!scene)return;
        cancelBreach();freeze();hunted=null;fisherChase=false;
        shark.classList.remove('is-chasing-fisher');
        shark.dataset.huntingTarget='spilled-fish';
    });
    const cancelBreach=()=>{
        if(!breaching)return;
        freeze();breaching=false;
        shark?.classList.remove('is-breaching','is-eating');
        if(shark){bounds=measure();place(position);}
    };
    document.addEventListener('ocean:parachute-cancel',()=>{cancelBreach();if(scene)swim();});
    document.addEventListener('ocean:parachute-low',event=>{
        const state=document.querySelector('.ocean-surface-scene')?.dataset.fishingState;
        if(!canSwim()||fisherChase||document.querySelector('.ocean-spilled-fish')||['jumping','hiding','climbing'].includes(state))return;
        event.detail.accepted=true;freeze();breaching=true;hunted=null;
        shark.classList.remove('is-hunting');shark.classList.add('is-breaching');
        const target=event.detail.target,start={...position};
        const launch={x:clamp(target.x-shark.offsetWidth*.85,bounds.left,bounds.right),y:waterline()+32};
        shark.style.setProperty('--shark-facing','1');shark.style.setProperty('--shark-heading','0deg');
        travel=shark.animate([
            {transform:`translate3d(${start.x}px,${start.y}px,0)`},
            {transform:`translate3d(${launch.x}px,${launch.y}px,0)`},
        ],{duration:clamp(Math.hypot(launch.x-start.x,launch.y-start.y)/450*1000,500,1900),fill:'forwards',easing:'ease-in-out'});
        travel.onfinish=()=>{
            const approach=travel;travel=null;approach.onfinish=null;
            shark.style.transform=`translate3d(${launch.x}px,${launch.y}px,0)`;approach.cancel();
            const mouth=mouthPoint(),apex={x:launch.x+target.x-mouth.x,y:launch.y+target.y-mouth.y};
            shark.classList.add('is-eating');
            document.dispatchEvent(new CustomEvent('ocean:parachute-escape'));
            travel=shark.animate([
                {transform:`translate3d(${launch.x}px,${launch.y}px,0)`},
                {transform:`translate3d(${apex.x}px,${apex.y}px,0)`,offset:.45},
                {transform:`translate3d(${launch.x-35}px,${launch.y+50}px,0)`},
            ],{duration:1500,easing:'ease-in-out',fill:'forwards'});
            travel.onfinish=()=>{capture();sparkle({x:target.x,y:waterline()});cancelBreach();swim();};
        };
    });
    const tickAdventure = () => {
        const now=performance.now(),delta=Math.min(500,now-phaseTick);phaseTick=now;
        if(!canSwim())return;
        phaseElapsed+=delta;
        if(document.querySelector('.ocean-spilled-fish')){
            shark.dataset.huntingTarget='spilled-fish';
            if(document.querySelector('.ocean-spilled-fish[data-ocean-state="swimming"]'))hunt(true);
            return;
        }
        if(shark.dataset.huntingTarget==='spilled-fish')delete shark.dataset.huntingTarget;
        if(!huntFisherman())hunt();
    };
    const huntFisherman=()=>{
        const surface=document.querySelector('.ocean-surface-scene');
        const state=surface?.dataset.fishingState;
        const fisher=surface?.querySelector('.ocean-fisher-person');
        if(!fisher||!['hiding','climbing'].includes(state)){
            if(fisherChase){
                fisherChase=false;freeze();bounds=measure();place(position);
                shark.classList.remove('is-chasing-fisher','is-eating','is-hunting');
                delete shark.dataset.huntingTarget;swim();
            }
            return false;
        }
        if(!fisherChase){
            fisherChase=true;lastFisherPursuit=-Infinity;
            shark.classList.add('is-chasing-fisher','is-hunting');
            shark.dataset.huntingTarget='fisherman';
            document.dispatchEvent(new CustomEvent('ocean:fisher-threat'));
        }
        if(performance.now()-lastFisherPursuit<220)return true;
        lastFisherPursuit=performance.now();
        const rect=fisher.getBoundingClientRect(),mouth=mouthPoint();
        const targetX=rect.left+rect.width/2,targetY=Math.max(waterline()+16,rect.bottom-8);
        const distance=Math.hypot(targetX-mouth.x,targetY-mouth.y);
        shark.classList.toggle('is-eating',distance<95);
        freeze();
        const start={...position},facing=targetX>start.x+shark.offsetWidth/2?1:-1;
        shark.style.setProperty('--shark-facing',String(facing));shark.style.setProperty('--shark-heading','0deg');
        const end={x:clamp(targetX-shark.offsetWidth*(facing>0?.87:.13),bounds.left,bounds.right),y:Math.max(waterline()-65,targetY-shark.offsetHeight*.6)};
        travel=shark.animate([
            {transform:`translate3d(${start.x}px,${start.y}px,0)`},
            {transform:`translate3d(${end.x}px,${end.y}px,0)`},
        ],{duration:clamp(Math.hypot(end.x-start.x,end.y-start.y)/420*1000,250,4000),easing:'linear',fill:'forwards'});
        travel.onfinish=()=>{
            const completed=travel;travel=null;position=end;
            shark.style.transform=`translate3d(${end.x}px,${end.y}px,0)`;
            completed.onfinish=null;completed.cancel();
        };
        return true;
    };
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
        shark.style.setProperty('--shark-facing', end.x < start.x ? '-1' : '1');
        const heading=clamp(Math.atan2(end.y-start.y,Math.abs(end.x-start.x))*180/Math.PI,-18,18);
        shark.style.setProperty('--shark-heading',`${heading*(end.x<start.x?-1:1)}deg`);
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
        const duration=clamp(distance/38*1000,4500,26000);
        travel = shark.animate(frames,{ duration,easing:'linear',fill:'forwards' });
        travel.onfinish = () => {
            const completed = travel;
            travel = null;
            place(end);
            if (completed) { completed.onfinish=null; completed.cancel(); }
            save();
            swim();
        };
    };
    const sparkle = (origin=null) => {
        if (motion.matches) return;
        capture();
        for (let i=0;i<8;i++) {
            const dot=document.createElement('span');
            dot.className='ocean-shark-sparkle';
            dot.style.setProperty('--sparkle-x',`${origin ? origin.x+Math.random()*18-9 : position.x+shark.offsetWidth*(.25+Math.random()*.5)}px`);
            dot.style.setProperty('--sparkle-y',`${origin ? origin.y : position.y+shark.offsetHeight*.3}px`);
            dot.style.setProperty('--sparkle-dx',`${Math.round(Math.random()*90-45)}px`);
            scene.append(dot);
            dot.addEventListener('animationend',()=>dot.remove(),{ once:true });
        }
    };
    const mouthPoint = () => {
        const svg=shark.querySelector('.ocean-shark-rig');
        const point=svg.createSVGPoint();point.x=210;point.y=84;
        return point.matrixTransform(svg.getScreenCTM());
    };
    const bite = (creature,mouth) => {
        const detail={creature,mouth:{x:mouth.x,y:mouth.y},accepted:false};
        document.dispatchEvent(new CustomEvent('ocean:consume',{detail}));
        if(!detail.accepted)return;
        freeze();biting=true;hunted=null;
        shark.classList.remove('is-hunting');shark.classList.add('is-eating');
        shark.dataset.snacks=String(++snacks);
        sparkle(mouth);
        clearTimeout(biteTimer);
        biteTimer=setTimeout(()=>{
            biting=false;shark?.classList.remove('is-eating');
            if(scene)swim();
        },850);
    };
    const hunt = (spilledOnly=false) => {
        if(!canSwim()||flightPhase!=='water')return;
        const mouth=mouthPoint();
        const candidates=[...document.querySelectorAll(spilledOnly?'.ocean-spilled-fish[data-ocean-state="swimming"]':'.ocean-creature-fish[data-ocean-state="swimming"]')]
            .map(element=>({element,rect:element.getBoundingClientRect()}))
            .filter(({rect})=>rect.width>0&&rect.height>0&&rect.right>0&&rect.left<innerWidth&&rect.bottom>90&&rect.top<innerHeight)
            .map(candidate=>({...candidate,distance:Math.hypot(candidate.rect.left+candidate.rect.width/2-mouth.x,candidate.rect.top+candidate.rect.height/2-mouth.y)}))
            .sort((a,b)=>a.distance-b.distance);
        const target=candidates[0];
        if(!target) { hunted=null;shark.classList.remove('is-hunting');if(!travel)swim();return; }
        if(target.distance<Math.max(30,Math.min(52,target.rect.width*.36))) {bite(target.element,mouth);return;}
        if(hunted===target.element&&performance.now()-lastHunt<900)return;
        hunted=target.element;lastHunt=performance.now();
        freeze();shark.classList.add('is-hunting');
        const start={...position};
        const targetX=target.rect.left+target.rect.width/2,targetY=target.rect.top+target.rect.height/2;
        const facing=targetX>start.x+shark.offsetWidth/2?1:-1;
        const end={x:clamp(targetX-shark.offsetWidth*(facing>0?.87:.13),bounds.left,bounds.right),y:clamp(targetY-shark.offsetHeight*.6,bounds.top,bounds.bottom)};
        shark.style.setProperty('--shark-facing',String(facing));
        shark.style.setProperty('--shark-heading','0deg');
        travel=shark.animate([
            {transform:`translate3d(${start.x}px,${start.y}px,0)`},
            {transform:`translate3d(${end.x}px,${end.y}px,0)`},
        ],{duration:clamp(Math.hypot(end.x-start.x,end.y-start.y)/145*1000,350,6000),easing:'linear',fill:'forwards'});
        travel.onfinish=()=>{
            const completed=travel;travel=null;place(end);
            if(completed){completed.onfinish=null;completed.cancel();}
            lastHunt=0;
        };
    };
    const endGreeting = () => {
        clearTimeout(greetingTimer);
        greeting=false;
        shark?.classList.remove('is-greeting');
        shark?.setAttribute('aria-label',`Say hello to the ${sharkName}`);
        swim();
    };
    const sayHello = () => {
        if(breaching)return;
        freeze();
        greeting=true;
        clearTimeout(greetingTimer);
        shark.classList.add('is-greeting');
        const firstName=document.querySelector('.user-info strong')?.textContent.trim().split(/\s+/)[0];
        const message=firstName ? `Hello, ${firstName}! Lovely to see you!` : 'Hello! Lovely to see you!';
        shark.querySelector('.ocean-shark-greeting').textContent=message;
        shark.setAttribute('aria-label',`${message} Say hello again`);
        sparkle();
        greetingTimer=setTimeout(endGreeting,3400);
    };
    const rig = () => {
        const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
        svg.setAttribute('viewBox','0 0 240 130');
        svg.setAttribute('aria-hidden','true');
        svg.classList.add('ocean-shark-rig');
        svg.innerHTML=`<defs>
            <linearGradient id="shark-skin" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#C4EDF8"/><stop offset=".4" stop-color="#659CB5"/><stop offset="1" stop-color="#2F698C"/></linearGradient>
            <linearGradient id="shark-belly" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F5FCFF"/><stop offset="1" stop-color="#BEDFEA"/></linearGradient>
            <linearGradient id="shark-fin" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#7BB3C9"/><stop offset="1" stop-color="#356B8C"/></linearGradient>
        </defs>
        <g class="ocean-shark-tail"><path d="M59 70Q39 45 15 23L25 62Q13 74 9 98 33 103 59 82Z" fill="url(#shark-fin)" stroke="#30627F" stroke-width="1.8"/><path d="M26 65 50 77 19 90" fill="none" stroke="#BCE3EF" stroke-width="1.4" opacity=".5"/></g>
        <path d="M92 50Q102 15 124 10L137 46Z" fill="url(#shark-fin)" stroke="#30627F" stroke-width="1.6"/>
        <path d="M94 79 68 105Q84 113 111 91Z" fill="#3D7896"/>
        <path d="M44 78Q78 30 142 44 177 44 218 68 232 76 215 83L191 92Q121 119 62 83Z" fill="url(#shark-skin)" stroke="#326781" stroke-width="1.8"/>
        <path d="M64 80Q125 99 171 89 196 83 216 79L211 86Q139 118 64 84Z" fill="url(#shark-belly)"/>
        <path d="M73 59Q119 37 165 52" fill="none" stroke="#E5FAFF" stroke-width="3" stroke-linecap="round" opacity=".65"/>
        <g class="ocean-shark-fin"><path d="M126 84Q120 111 94 119L105 86Z" fill="url(#shark-fin)" stroke="#326781" stroke-width="1.6"/><path d="m111 91-10 19" stroke="#A9DAEA" stroke-width="1.5"/></g>
        <g fill="none" stroke="#37687E" stroke-width="2" stroke-linecap="round"><path d="m153 67-3 12m-5-14-3 12m-5-14-3 12"/></g>
        <ellipse cx="187" cy="65" rx="8" ry="9" fill="#F4FCFF"/><ellipse cx="190" cy="66" rx="4.8" ry="6.3" fill="#143C57"/><circle cx="191" cy="63" r="2" fill="#FFF"/>
        <path d="M178 53q8-5 15 0" fill="none" stroke="#386B84" stroke-width="2" stroke-linecap="round"/>
        <ellipse cx="219" cy="72" rx="2" ry="1.4" fill="#32617B"/>
        <path class="ocean-shark-neutral-mouth" d="M195 82q11 4 21-3" fill="none" stroke="#31566E" stroke-width="2" stroke-linecap="round"/>
        <g class="ocean-shark-chomp-mouth"><path d="M188 78Q204 87 223 76 221 103 198 99Z" fill="#243E58" stroke="#31566E" stroke-width="1.5"/><path d="m193 81 7 2-4 7Zm11 3 7-1-3 7Zm12-3 5-3-1 8ZM201 97l4-5 3 6Zm11-1 3-5 2 3Z" fill="#F3FCFF"/></g>
        <g class="ocean-shark-happy-face"><path d="M190 80Q207 88 220 78 210 103 190 80Z" fill="#354F69" stroke="#2D5169" stroke-width="1"/><path d="m195 83 5 1-2 5Zm9 2 5-1-2 5Zm9-2 4-2-1 5Z" fill="#F3FFFF"/><path d="M201 91q5-3 9-1" fill="none" stroke="#EC9BA5" stroke-width="2.5" stroke-linecap="round"/><ellipse cx="180" cy="79" rx="6" ry="2.5" fill="#EDACBD" opacity=".6"/></g>
        <g class="ocean-shark-eyelids"><ellipse cx="187" cy="65" rx="8.5" ry="9.5" fill="#70A6BD"/><path d="M181 65q6 4 13-1" fill="none" stroke="#376A84" stroke-width="1.5" stroke-linecap="round"/></g>
        <path class="ocean-shark-hit" d="M7 18 43 51Q73 29 95 38L101 13 127 5 141 40Q184 42 223 66 240 78 220 89L184 99 132 105 94 124 98 101 60 89Q32 109 5 102L18 62Z"/>`;
        return svg;
    };
    const activity = () => {
        if (!scene) return;
        if(motion.matches)cancelBreach();
        scene.classList.toggle('ocean-shark-paused',document.hidden);
        scene.classList.toggle('ocean-shark-reduced',motion.matches);
        if (document.hidden || motion.matches) {
            if(breaching)travel?.pause();
            else {freeze();save();}
        } else if(breaching)travel?.play();else swim();
        phaseTick=performance.now();
    };
    const resize = () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame=requestAnimationFrame(()=>{
            if (!shark) return;
            cancelBreach();freeze();bounds=measure();place(position);swim();
        });
    };
    const destroy = () => {
        save(); freeze();breaching=false;clearTimeout(greetingTimer); cancelAnimationFrame(resizeFrame);
        clearInterval(huntTimer);clearTimeout(biteTimer);biting=false;hunted=null;fisherChase=false;
        viewportObserver?.disconnect(); viewportObserver=null;
        scene?.remove(); scene=shark=travel=null;
        hovered=focused=greeting=false;
    };
    const sync = () => {
        if (root.dataset.theme!=='ocean') { if(scene) destroy(); return; }
        if (scene) return;
        scene=document.createElement('div'); scene.className='ocean-shark-scene';
        shark=document.createElement('button'); shark.type='button'; shark.className='ocean-shark';
        shark.dataset.sharkKind='normal';
        shark.setAttribute('aria-label',`Say hello to the ${sharkName}`);
        const facing=document.createElement('div'); facing.className='ocean-shark-facing';
        const floating=document.createElement('div'); floating.className='ocean-shark-floating'; floating.append(rig()); facing.append(floating);
        for(let i=0;i<3;i++) { const wake=document.createElement('span'); wake.className='ocean-shark-wake'; facing.append(wake); }
        const bubble=document.createElement('span'); bubble.className='ocean-shark-greeting'; bubble.setAttribute('aria-live','polite');
        shark.append(facing,bubble); scene.append(shark); document.body.append(scene);
        bounds=measure();
        let saved;
        try { saved=JSON.parse(sessionStorage.getItem(storageKey)); } catch (_) {}
        routeIndex=Number.isInteger(saved?.routeIndex) && saved.routeIndex>=0 ? saved.routeIndex : 0;
        const valid=Number.isFinite(saved?.x)&&Number.isFinite(saved?.y);
        place(valid ? { x:bounds.left+clamp(saved.x,0,1)*(bounds.right-bounds.left),y:bounds.top+clamp(saved.y,0,1)*(bounds.bottom-bounds.top) } : { x:bounds.right,y:bounds.bottom });
        shark.addEventListener('pointerenter',event=>{ if(event.pointerType==='mouse'&&!breaching) { hovered=true; freeze(); } });
        shark.addEventListener('pointerleave',event=>{ if(event.pointerType==='mouse') { hovered=false; swim(); } });
        shark.addEventListener('focus',()=>{ focused=shark.matches(':focus-visible'); if(focused&&!breaching) freeze(); });
        shark.addEventListener('blur',()=>{ focused=false; swim(); });
        shark.addEventListener('click',sayHello);
        shark.addEventListener('keydown',event=>{ if(event.key==='Escape') endGreeting(); });
        if (typeof ResizeObserver!=='undefined') { viewportObserver=new ResizeObserver(resize); viewportObserver.observe(scene); }
        flightPhase='water';phaseTick=performance.now();
        shark.dataset.flightPhase='water';
        activity();
        huntTimer=setInterval(tickAdventure,100);
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root,{ attributes:true,attributeFilter:['data-theme'] });
    document.addEventListener('visibilitychange',activity);
    motion.addEventListener('change',activity);
    window.addEventListener('resize',resize,{ passive:true });
    document.addEventListener('ocean:surface-ready',resize);
    window.addEventListener('pagehide',()=>{ if(scene) { save(); if(breaching)travel?.pause();else freeze(); } });
    window.addEventListener('pageshow',activity);
    sync();
    };
    createShark();
})();
