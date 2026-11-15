(() => {
    const root = document.documentElement;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let scene;
    const animals = {
        fish: `<svg viewBox="0 0 110 65" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="ocean-fish-orange" x2="0" y2="1"><stop stop-color="#ffbf54"/><stop offset="1" stop-color="#ef6418"/></linearGradient><clipPath id="ocean-fish-body"><ellipse cx="64" cy="33" rx="33" ry="23"/></clipPath></defs><path class="ocean-tail" d="M35 33 8 13Q0 33 8 53Z" fill="#f78c2e" stroke="#81391b" stroke-width="2"/><path d="m44 13 8-9 26 8m-31 40 12 8 15-6" fill="#f18b31" stroke="#9c481b" stroke-width="2"/><ellipse cx="64" cy="33" rx="33" ry="23" fill="url(#ocean-fish-orange)" stroke="#a24b1c" stroke-width="2"/><g clip-path="url(#ocean-fish-body)" fill="none" stroke-linecap="round"><path d="M45 9q-9 22 0 48M73 9q-12 23-2 48" stroke="#95421c" stroke-width="12"/><path d="M45 9q-9 22 0 48M73 9q-12 23-2 48" stroke="#fff9df" stroke-width="8"/></g><ellipse cx="89" cy="28" rx="5" ry="6" fill="#fff"/><circle cx="90" cy="28" r="3" fill="#15394c"/><path d="m96 38-5 2" stroke="#713913" stroke-width="2"/><path class="ocean-fin" d="M63 34q-13-9-15 7 11 6 15-7" fill="#ffb244" stroke="#aa561a"/></svg>`,
        turtle: `<svg viewBox="0 0 130 85" xmlns="http://www.w3.org/2000/svg"><g fill="#8eae51" stroke="#3f793c" stroke-width="2"><path class="ocean-flipper ocean-flipper-front" d="M72 38Q63 4 92 8l-3 32Z"/><path class="ocean-flipper ocean-flipper-back" d="M39 39Q13 6 18 30l15 20Z"/><path d="m24 44-15 5 17 4Z"/><ellipse cx="109" cy="39" rx="17" ry="12"/><path class="ocean-flipper ocean-flipper-front" d="M76 54q-4 34 20 20l-8-27Z"/><path class="ocean-flipper ocean-flipper-back" d="M37 54Q12 82 20 56l12-11Z"/></g><ellipse cx="61" cy="43" rx="40" ry="27" fill="#43874e" stroke="#b6d26b" stroke-width="4"/><path d="m51 24 19-1 15 16-9 20-23 4-18-18Z" fill="#6fa44d" stroke="#d4d879" stroke-width="2"/><path d="m51 24-7-6m26 5 9-5m6 21 14-1M76 59l8 9m-31-5-1 7M35 45l-13 3" stroke="#d4d879" stroke-width="2"/><circle cx="116" cy="36" r="3" fill="#173d42"/><path d="m121 44-6 1" stroke="#355b33" stroke-width="1.5"/></svg>`,
        dolphin: `<svg viewBox="0 0 160 85" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="ocean-dolphin-blue" x2="0" y2="1"><stop stop-color="#b4eaff"/><stop offset=".45" stop-color="#4c9ac6"/><stop offset="1" stop-color="#24729e"/></linearGradient></defs><path class="ocean-tail" d="M35 56Q16 43 8 54l14 9-9 16q19 0 22-23Z" fill="#398ab5" stroke="#246382"/><path d="M45 43q5-20 31-38l-2 28" fill="#3e8db6" stroke="#246382"/><path d="M29 60Q48 9 103 22q22 5 30 22l20 6q6 7-6 8l-19-3q-29 25-71 4l-28 7Z" fill="url(#ocean-dolphin-blue)" stroke="#306d8e" stroke-width="1.5"/><path d="M49 53q38 23 75-4-23 18-58 10Z" fill="#d2f3ff"/><path class="ocean-fin" d="M87 47Q69 72 65 72l9-25" fill="#347da6"/><circle cx="119" cy="40" r="3" fill="#12374f"/><path d="m129 49 14 4" stroke="#2d6481" fill="none"/></svg>`,
        jelly: `<svg viewBox="0 0 85 130" xmlns="http://www.w3.org/2000/svg"><g class="ocean-jelly-tentacles" fill="none" stroke="#8be8ff" stroke-width="2.5" stroke-linecap="round"><path d="M24 53q-20 18-3 37t-9 28M35 58q14 15-2 31t5 34M48 58q-16 15 2 35t-3 30M60 53q15 17-1 35t11 24"/></g><path d="M9 53C9-8 76-8 76 53q-7 11-15 0-8 12-18 0-10 12-19 0-8 10-15 0Z" fill="#74e9f5" fill-opacity=".65" stroke="#c4fbff" stroke-width="2"/><path d="M19 34Q24 12 39 13" fill="none" stroke="#e0ffff" stroke-width="4" stroke-linecap="round"/><ellipse cx="43" cy="44" rx="21" ry="9" fill="#b8fdff" fill-opacity=".35"/></svg>`,
    };
    // All fish share the same mouth coordinates so each can bite the hook.
    const fishVariants = {
        clown: animals.fish,
        blue: `<svg viewBox="0 0 110 65" xmlns="http://www.w3.org/2000/svg"><path class="ocean-tail" d="M36 33 7 12 12 33 7 54Z" fill="#ffe35c" stroke="#23549a" stroke-width="2"/><path d="M43 16 60 3 80 16M45 49 65 61 80 49" fill="#287de0" stroke="#173e83" stroke-width="2"/><ellipse cx="64" cy="33" rx="33" ry="23" fill="#39b9f4" stroke="#17468c" stroke-width="2"/><path d="M43 19Q61 33 79 18L75 46Q58 37 43 47Z" fill="#2354b5"/><path class="ocean-fin" d="M65 31 49 33 58 45Z" fill="#ffe35c"/><ellipse cx="89" cy="28" rx="5" ry="6" fill="white"/><circle cx="90" cy="28" r="3" fill="#15394c"/><path d="m96 38-5 2" stroke="#173e83" stroke-width="2"/></svg>`,
        yellow: `<svg viewBox="0 0 110 65" xmlns="http://www.w3.org/2000/svg"><path class="ocean-tail" d="M37 33 9 15 13 33 9 51Z" fill="#ffae27" stroke="#a66418" stroke-width="2"/><path d="M43 17 54 2 81 14M46 49 58 63 79 51" fill="#ffd842" stroke="#b67b16" stroke-width="2"/><ellipse cx="64" cy="33" rx="33" ry="23" fill="#ffe66a" stroke="#b67b16" stroke-width="2"/><path d="M48 13 45 52M62 11 59 55M77 13 74 52" stroke="#f4ae27" stroke-width="5"/><path class="ocean-fin" d="M65 33Q46 28 53 45Z" fill="#fff5ac" stroke="#d49821"/><ellipse cx="89" cy="28" rx="5" ry="6" fill="white"/><circle cx="90" cy="28" r="3" fill="#15394c"/><path d="m96 38-5 2" stroke="#a66418" stroke-width="2"/></svg>`,
        purple: `<svg viewBox="0 0 110 65" xmlns="http://www.w3.org/2000/svg"><path class="ocean-tail" d="M36 33 7 10 14 33 7 56Z" fill="#f28ccf" stroke="#743d98" stroke-width="2"/><path d="M42 17 51 5 62 11 73 4 83 17M45 49 62 60 80 49" fill="#b28aff" stroke="#743d98" stroke-width="2"/><ellipse cx="64" cy="33" rx="33" ry="23" fill="#cf9bfa" stroke="#743d98" stroke-width="2"/><path d="M37 38Q65 56 91 39" fill="none" stroke="#f8bcdf" stroke-width="7"/><g fill="#8654b5"><circle cx="48" cy="24" r="3"/><circle cx="63" cy="19" r="3"/><circle cx="76" cy="24" r="3"/></g><path class="ocean-fin" d="M65 32Q46 29 54 44Z" fill="#f9b3dd" stroke="#9852ae"/><ellipse cx="89" cy="28" rx="5" ry="6" fill="white"/><circle cx="90" cy="28" r="3" fill="#15394c"/><path d="m96 38-5 2" stroke="#743d98" stroke-width="2"/></svg>`,
    };
    const swimmers = new Map();
    const pending = new Set();
    let entryIndex = Math.floor(Math.random()*8);
    const later = (callback, delay) => {
        const timer=setTimeout(()=>{ pending.delete(timer); callback(); },delay);
        pending.add(timer);
    };
    const stopSwimmers = () => {
        pending.forEach(clearTimeout); pending.clear();
        swimmers.forEach(record=>{ if(record.animation) { record.animation.onfinish=null; record.animation.cancel(); } record.element.remove(); });
        swimmers.clear();
    };
    const entryPoint = (index,width,height) => {
        const surface=parseFloat(getComputedStyle(root).getPropertyValue('--ocean-surface-y'))||Math.min(300,Math.max(230,innerHeight*.32));
        const waterTop=surface+12;
        const x=Math.random()*Math.max(1,innerWidth-width),y=waterTop+Math.random()*Math.max(1,innerHeight-height-waterTop-20);
        return [
            {x:-width-30,y:waterTop},{x:innerWidth+30,y:waterTop},
            {x:innerWidth+30,y:innerHeight+30},{x:-width-30,y:innerHeight+30},
            {x:-width-30,y},{x:innerWidth+30,y},{x,y:waterTop},{x,y:innerHeight+30},
        ][index%8];
    };
    const sendAcross = (record,initial=false) => {
        if(!scene || record.status!=='swimming') return;
        const {element}=record;
        if(!element.getClientRects().length) return;
        const width=element.offsetWidth,height=element.offsetHeight;
        const edge=entryIndex++%8;
        const start=entryPoint(edge,width,height),end=entryPoint([2,3,0,1,5,4,7,6][edge],width,height);
        element.dataset.spawnEdge=String(edge);
        element.querySelector('.ocean-creature-facing').style.transform=end.x<start.x?'scaleX(-1)':'scaleX(1)';
        const distance=Math.hypot(end.x-start.x,end.y-start.y);
        const duration=Math.max(16000,Math.min(42000,distance/(record.type==='turtle'?30:48)*1000));
        record.animation=element.animate([
            {transform:`translate3d(${start.x}px,${start.y}px,0)`},
            {transform:`translate3d(${end.x}px,${end.y}px,0)`},
        ],{duration,easing:'linear',fill:'forwards'});
        if(initial) record.animation.currentTime=duration*(.18+Math.random()*.18);
        record.animation.onfinish=()=>{
            if(!scene || record.status!=='swimming') return;
            record.animation.onfinish=null;record.animation.cancel();record.animation=null;
            sendAcross(record);
        };
        if(document.hidden || motion.matches) record.animation.pause();
    };
    const spawn = (type,initial=false,variant='clown') => {
        if(!scene) return;
        if(document.hidden) { later(()=>spawn(type,initial,variant),1000);return; }
        const element=document.createElement('div');
        element.className=`ocean-creature ocean-creature-${type} ocean-managed-creature`;
        element.dataset.oceanState='swimming';element.dataset.animal=type;
        if(type==='fish')element.dataset.fishVariant=variant;
        element.innerHTML=`<div class="ocean-creature-facing"><div class="ocean-creature-art">${type==='fish'?fishVariants[variant]:animals[type]}</div></div>`;
        scene.append(element);
        const record={type,variant,element,status:'swimming',animation:null};
        swimmers.set(element,record);
        sendAcross(record,initial);
        return record;
    };
    document.addEventListener('ocean:spill-catch',event=>{
        if(!scene||document.hidden||motion.matches)return;
        const {origin,variants}=event.detail;
        const surface=parseFloat(getComputedStyle(root).getPropertyValue('--ocean-surface-y'))||260;
        variants.forEach((variant,index)=>{
            const record=spawn('fish',false,variant);
            if(!record)return;
            record.animation.onfinish=null;record.animation.cancel();
            record.spilled=true;record.status='spilling';
            const fish=record.element;
            fish.classList.add('ocean-spilled-fish');fish.dataset.oceanState='spilling';
            const x=Math.max(25,Math.min(innerWidth-70,origin.x+(index%5-2)*34));
            const y=Math.min(innerHeight-70,surface+105+Math.floor(index/5)*25);
            record.animation=fish.animate([
                {transform:`translate3d(${origin.x}px,${origin.y}px,0) rotate(-20deg)`},
                {transform:`translate3d(${(origin.x+x)/2}px,${origin.y-35}px,0) rotate(25deg)`,offset:.35},
                {transform:`translate3d(${x}px,${y}px,0) rotate(0deg)`},
            ],{duration:900+index*90,easing:'ease-in',fill:'forwards'});
            record.animation.onfinish=()=>{
                record.animation.onfinish=null;record.animation.cancel();
                record.status='swimming';fish.dataset.oceanState='swimming';
                record.animation=fish.animate([
                    {transform:`translate3d(${x}px,${y}px,0)`},
                    {transform:`translate3d(${x+12}px,${y-5}px,0)`},
                    {transform:`translate3d(${x}px,${y}px,0)`},
                ],{duration:1800,easing:'ease-in-out',iterations:Infinity});
            };
        });
    });
    document.addEventListener('ocean:consume',event=>{
        const detail=event.detail;
        const record=swimmers.get(detail?.creature);
        if(!record || record.status!=='swimming' || !scene || document.hidden || motion.matches) return;
        detail.accepted=true;
        record.status='eating';record.element.dataset.oceanState='eating';
        const rect=record.element.getBoundingClientRect();
        if(record.animation) { record.animation.onfinish=null;record.animation.cancel(); }
        const endX=detail.mouth.x-rect.width/2,endY=detail.mouth.y-rect.height/2;
        record.animation=record.element.animate([
            {transform:`translate3d(${rect.left}px,${rect.top}px,0) scale(1)`,opacity:1},
            {transform:`translate3d(${endX}px,${endY}px,0) scale(.42)`,opacity:.85,offset:.65},
            {transform:`translate3d(${endX}px,${endY}px,0) scale(0)`,opacity:0},
        ],{duration:480,easing:'ease-in',fill:'forwards'});
        record.animation.onfinish=()=>{
            record.element.remove();swimmers.delete(record.element);
            if(!record.spilled)later(()=>spawn(record.type,false,record.variant),1400+Math.random()*1400);
        };
    });
    document.addEventListener('ocean:fish-hook',event=>{
        const detail=event.detail;
        if(!scene||document.hidden||motion.matches)return;
        const record=[...swimmers.values()].find(item=>item.type==='fish'&&item.status==='swimming');
        if(!record)return;
        detail.accepted=true;record.status='fishing';record.hookId=detail.id;
        // Rotate reservations so every fish gets a turn at the bait.
        swimmers.delete(record.element);swimmers.set(record.element,record);
        // Only the reserved, reeled fish may cross the waterline into the boat.
        const surfaceScene=document.querySelector('.ocean-surface-scene');
        if(surfaceScene)surfaceScene.insertBefore(record.element,surfaceScene.querySelector('.ocean-fishing-boat'));
        record.element.dataset.oceanState='fishing';
        record.element.classList.add('is-on-line');
        const rect=record.element.getBoundingClientRect();
        if(record.animation){record.animation.onfinish=null;record.animation.cancel();}
        const facing=detail.hook.x<rect.left?-1:1;
        record.element.querySelector('.ocean-creature-facing').style.transform=facing<0?'scaleX(-1)':'scaleX(1)';
        const fishSvg=record.element.querySelector('svg'),mouth=fishSvg.createSVGPoint();
        mouth.x=96;mouth.y=38;
        const mouthScreen=mouth.matrixTransform(fishSvg.getScreenCTM()),base=record.element.getBoundingClientRect();
        const anchorX=mouthScreen.x-base.left,anchorY=mouthScreen.y-base.top;
        const hookX=detail.hook.x-anchorX,hookY=detail.hook.y-anchorY;
        record.element.style.transformOrigin=`${anchorX}px ${anchorY}px`;
        record.animation=record.element.animate([
            {transform:`translate3d(${rect.left}px,${rect.top}px,0)`},
            {transform:`translate3d(${hookX}px,${hookY}px,0)`},
        ],{duration:Math.max(1800,Math.min(5500,Math.hypot(hookX-rect.left,hookY-rect.top)/100*1000)),easing:'ease-in-out',fill:'forwards'});
        record.animation.onfinish=()=>{
            if(!scene||record.status!=='fishing')return;
            record.animation.onfinish=null;record.animation.cancel();
            const startedAt=document.timeline.currentTime;
            const trajectory=[{point:detail.hook,offset:0,scale:1,rotation:0},{point:detail.catchAt,offset:.85,scale:.8,rotation:-18},{point:detail.catchAt,offset:1,scale:.65,rotation:-18}];
            document.dispatchEvent(new CustomEvent('ocean:hook-bit',{detail:{id:detail.id,duration:1200,startedAt,trajectory}}));
            record.element.classList.add('is-hooked');
            record.animation=record.element.animate(trajectory.map(frame=>({
                transform:`translate3d(${frame.point.x-anchorX}px,${frame.point.y-anchorY}px,0) rotate(${frame.rotation}deg) scale(${frame.scale})`,
                opacity:frame.offset===1?0:1,offset:frame.offset,
            })),{duration:1200,easing:'ease-in',fill:'forwards'});
            record.animation.startTime=startedAt;
            record.animation.onfinish=()=>{
                record.element.remove();swimmers.delete(record.element);
                document.dispatchEvent(new CustomEvent('ocean:fish-caught',{detail:{id:detail.id,variant:record.variant}}));
                later(()=>spawn('fish',false,record.variant),1400);
            };
        };
    });
    document.addEventListener('ocean:cancel-fishing',()=>{
        swimmers.forEach(record=>{
            if(record.status!=='fishing')return;
            if(record.animation){record.animation.onfinish=null;record.animation.cancel();record.animation=null;}
            record.status='swimming';record.element.dataset.oceanState='swimming';record.element.classList.remove('is-hooked','is-on-line');record.element.style.removeProperty('transform-origin');
            scene?.append(record.element);
            sendAcross(record);
        });
    });
    const updateActivity = () => {
        scene?.classList.toggle('ocean-life-paused',document.hidden);
        scene?.classList.toggle('ocean-life-reduced',motion.matches);
        swimmers.forEach(record=>{
            if(document.hidden || motion.matches) record.animation?.pause();
            else if(record.animation) record.animation.play();
            else sendAcross(record);
        });
    };
    const refreshWaterRoutes=event=>{
        swimmers.forEach(record=>{
            if(record.spilled){
                if(event?.type==='resize'){
                    if(record.animation){record.animation.onfinish=null;record.animation.cancel();}
                    record.element.remove();swimmers.delete(record.element);
                }
                return;
            }
            if(record.status!=='swimming')return;
            if(record.animation) {record.animation.onfinish=null;record.animation.cancel();record.animation=null;}
            sendAcross(record,true);
        });
    };
    window.addEventListener('resize',refreshWaterRoutes,{passive:true});
    document.addEventListener('ocean:surface-ready',refreshWaterRoutes);
    const sync = () => {
        if (root.dataset.theme !== 'ocean') { stopSwimmers(); scene?.remove(); scene = null; return; }
        if (scene) return;
        let elapsed = 0;
        try {
            let started = Number(sessionStorage.getItem('scholar-ocean-journey'));
            if (!started || started > Date.now()) { started = Date.now(); sessionStorage.setItem('scholar-ocean-journey', String(started)); }
            elapsed = (Date.now() - started) % 86400000;
        } catch (_) {}
        scene = document.createElement('div');
        scene.className = 'ocean-life-scene';
        scene.setAttribute('aria-hidden', 'true');
        scene.style.setProperty('--ocean-phase', `-${elapsed}ms`);
        const seabed = document.createElement('div');
        seabed.className = 'ocean-seabed';
        scene.append(seabed);
        for (let i = 0; i < 14; i++) {
            const bubble = document.createElement('span');
            bubble.className = 'ocean-bubble';
            bubble.style.setProperty('--bubble-x', `${(i * 37.3) % 100}%`);
            bubble.style.setProperty('--bubble-size', `${4 + i % 5 * 2}px`);
            bubble.style.setProperty('--bubble-duration', `${15 + i % 7}s`);
            bubble.style.setProperty('--bubble-delay', `${-i * 2.1}s`);
            scene.append(bubble);
        }
        scene.classList.toggle('ocean-life-reduced', motion.matches);
        document.body.append(scene);
        Object.keys(animals).forEach(type=>spawn(type,true));
        ['blue','yellow','purple'].forEach(variant=>spawn('fish',true,variant));
        updateActivity();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root, { attributes:true, attributeFilter:['data-theme'] });
    document.addEventListener('visibilitychange', updateActivity);
    motion.addEventListener('change', updateActivity);
    sync();
})();
