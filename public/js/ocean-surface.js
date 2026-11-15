(() => {
    const root=document.documentElement;
    const motion=matchMedia('(prefers-reduced-motion: reduce)');
    const svgNS='http://www.w3.org/2000/svg';
    let scene,sky,water,boat,line,thread,hook,wave,loop,castTimer,resetTimer;
    let casting=false,castId=0,nextCast=0,boatPhase=0,catches=0,castPoints,rigFrame;
    const lineAnimations=[];
    const caughtFish=[];
    const showCatch=()=>{
        const bucket=boat?.querySelector('.ocean-bucket-catch');
        if(!bucket)return;
        bucket.innerHTML=caughtFish.slice(-5).map((variant,index)=>`<path d="m${124+index*3} 65-3-8 5 3 4-4-2 9Z" fill="${{clown:'#ffab49',blue:'#51cfff',yellow:'#ffe45c',purple:'#cf9bfa'}[variant]||'#ffab49'}" stroke="#426f83" stroke-width=".7"/>`).join('');
        bucket.setAttribute('data-fish-count',String(caughtFish.length));
    };
    const spillCatch=()=>{
        if(!caughtFish.length)return;
        const origin=boatPoint(133,64),variants=caughtFish.splice(0);
        catches=0;scene.dataset.catches='0';showCatch();
        document.dispatchEvent(new CustomEvent('ocean:spill-catch',{detail:{origin:{x:origin.x,y:origin.y},variants}}));
    };
    let panicAnimation,rodAnimation,panicSequence=null;
    let waveElapsed=0,waveTick=0;
    const activeWaves=new Set();
    let waveSerial=0,boatImpact;
    const swellHeight=position=>{
        const profile=[[0,0],[.15,1],[.3,8],[.42,24],[.52,49],[.6,70],[.65,77],[.7,68],[.76,40],[.85,14],[1,0]];
        if(position<=0||position>=1)return 0;
        for(let i=1;i<profile.length;i++){
            const [end,height]=profile[i],[start,previous]=profile[i-1];
            if(position<=end){const t=(position-start)/(end-start),smooth=t*t*(3-2*t);return previous+(height-previous)*smooth;}
        }
        return 0;
    };
    const finishWaveRide=()=>{
        if(boatImpact){boatImpact.onfinish=null;boatImpact.cancel();boatImpact=null;}
        boat?.classList.remove('is-riding-wave');
    };
    const rideWave=(record,hull)=>{
        finishWaveRide();boat.classList.add('is-riding-wave');
        const center=hull.left+hull.width*.5;
        const frames=Array.from({length:181},(_,index)=>{
            const offset=index/180,waveX=record.start+(record.end-record.start)*offset;
            const local=(center-waveX)/record.width;
            const lift=swellHeight(local);
            const stern=swellHeight(local-hull.width*.3/record.width),bow=swellHeight(local+hull.width*.3/record.width);
            const tilt=Math.max(-18,Math.min(18,Math.atan2(stern-bow,hull.width*.6)*180/Math.PI));
            return {transform:`translateY(${-lift}px) rotate(${tilt}deg)`,offset};
        });
        boatImpact=boat.querySelector('svg').animate(frames,{duration:record.duration,easing:'linear',fill:'forwards'});
        boatImpact.startTime=record.animation.startTime;
        record.riding=true;
        boatImpact.onfinish=finishWaveRide;
    };
    const clearBigWaves=()=>{
        activeWaves.forEach(record=>{record.animation.onfinish=null;record.animation.cancel();record.element.remove();});activeWaves.clear();
        finishWaveRide();
    };
    const bigWave=()=>{
        const crest=document.createElement('div');crest.className='ocean-big-wave';
        const width=Math.min(460,Math.max(280,innerWidth*.27)),id=`ocean-breaker-${waveSerial++}`;
        crest.style.width=`${width}px`;
        crest.innerHTML=`<svg viewBox="0 0 460 115" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#b7fff1" stop-opacity=".95"/><stop offset=".35" stop-color="#24d6d9" stop-opacity=".86"/><stop offset="1" stop-color="#007baf" stop-opacity=".12"/></linearGradient></defs>
            <path d="M0 111Q105 110 164 82C218 58 245 5 300 13c34 4 55 26 42 49-6-22-27-25-43-14 30 40 82 52 161 63Z" fill="url(#${id})"/>
            <path d="M27 108q114-8 173-51T296 17q38 0 43 30-17-19-36-7" fill="none" stroke="#e9fffa" stroke-width="5" stroke-linecap="round" opacity=".95"/>
            <path d="M52 110q120-13 163-50t77-32m-105 59q68-19 92-41m36 15q22 28 86 42" fill="none" stroke="#a4fff2" stroke-width="2" opacity=".7"/>
            <g class="ocean-breaker-spray" fill="#efffff"><circle cx="280" cy="8" r="2"/><circle cx="302" cy="4" r="2.5"/><circle cx="324" cy="12" r="2"/><circle cx="342" cy="25" r="1.5"/></g>
            <path d="M56 111q43-7 88-2t88-2 88 1 84 2" fill="none" stroke="#e5ffff" stroke-width="3" stroke-dasharray="13 9 4 7" opacity=".6"/></svg>`;
        scene.append(crest);
        document.dispatchEvent(new CustomEvent('ocean:wave-approaching'));
        const record={element:crest,width,start:-width,end:innerWidth+width,duration:6500,hit:false,animation:null};
        record.animation=crest.animate([
            {transform:`translate3d(${-width}px,0,0)`,opacity:0},
            {transform:`translate3d(${-width+(innerWidth+width*2)*.08}px,0,0)`,opacity:1,offset:.08},
            {transform:`translate3d(${innerWidth+width}px,0,0)`,opacity:.85},
        ],{duration:record.duration,easing:'linear',fill:'forwards'});
        activeWaves.add(record);
        record.animation.onfinish=()=>{record.animation.onfinish=null;record.animation.cancel();crest.remove();activeWaves.delete(record);};
    };
    const tickBigWave=()=>{
        const now=performance.now(),delta=Math.min(250,now-waveTick);waveTick=now;
        if(!scene||document.hidden||motion.matches)return;
        activeWaves.forEach(record=>{
            const progress=Math.min(1,Number(record.animation.currentTime||0)/record.duration);
            const waveX=record.start+(record.end-record.start)*progress;
            const hull=boat.getBoundingClientRect();
            const local=(hull.left+hull.width*.5-waveX)/record.width;
            if(!record.riding&&local<=1&&local>0)rideWave(record,hull);
            // Throw him only at the crest, after the hull has risen with it.
            if(record.riding&&!record.hit&&local<=.67&&local>.4){
                record.hit=true;
                spillCatch();
                document.dispatchEvent(new CustomEvent('ocean:big-wave'));
            }
        });
        waveElapsed+=delta;
        if(waveElapsed>=10000){waveElapsed-=10000;bigWave();}
    };
    // Rotate through every scene before repeating; each has its own opening move.
    const fisherReactions=[
        ['hat-panic','SHAAARK! My hat!!',0,-12,-15,12,-22,18],
        ['double-take','Wait... sharks can FLY?!',-9,0,-12,9,-5,12],
        ['duck','Incoming! Duck, captain!',0,10,18,-12,13,-20],
        ['tiptoe','Quiet feet. Very quiet feet.',-8,-5,-5,12,-9,7],
        ['wobble','Who put wings on that fish?!',-15,0,-22,15,0,22],
        ['salute','Air traffic says: NOPE!',0,-5,5,5,-16,-8],
        ['hop','Emergency swimming lesson!',0,-19,-6,0,-3,8],
        ['lean-back','That is NOT a seagull!',-17,0,-25,-5,-9,-12],
        ['lookout','Metal fins at twelve oclock!',8,-8,16,-10,-13,-15],
        ['shuffle','My boat needs a roof!',-14,0,-7,14,0,7],
        ['freeze','If I stand still... nope!',0,0,0,0,-2,2],
        ['knees','My knees have left the chat!',-5,7,-15,6,11,15],
        ['backstep','Wrong boat! Wrong boat!',-11,0,-12,-22,-4,-18],
        ['bow','Good day, Mr. Robo Teeth!',0,5,28,0,-6,-10],
        ['spin','Captain doing a tactical twirl!',8,-12,65,-8,-10,-40],
        ['peek','Just checking... still a shark!',-13,9,-18,11,-8,19],
        ['stomp','I ordered fish, not a spaceship!',-5,-5,-9,7,3,13],
        ['balance','Steady boat! Unsteady captain!',-18,0,-30,18,-4,30],
        ['stretch','Definitely meant to exercise!',0,-13,-10,13,-18,14],
        ['sidestep','Excuse me! Tiny detour!',16,0,12,-16,-3,-12],
        ['hat-check','Hat secure. Dignity missing.',0,-9,7,-4,-15,-11],
        ['reverse','Reverse! Where is reverse?!',-9,-2,-15,-19,-7,-25],
        ['bounce','The floor is suddenly water!',0,-23,10,0,-6,-10],
        ['limbo','Too many teeth! Limbo time!',-12,12,-35,6,8,24],
        ['hero','Fearless captain! Briefly.',12,-16,18,-12,-6,-18],
    ];
    let reactionIndex=0;
    try{reactionIndex=Math.max(0,Number(sessionStorage.getItem('scholar-ocean-fisher-reaction'))||0)%fisherReactions.length;}catch(_){}
    const stopPanic=()=>{
        panicSequence=null;
        if(panicAnimation){panicAnimation.onfinish=null;panicAnimation.cancel();panicAnimation=null;}
        if(rodAnimation){rodAnimation.cancel();rodAnimation=null;}
        const rod=boat?.querySelector('.ocean-fisher-rod');
        if(rod)boat.querySelector('.ocean-fisher-arm').append(rod);
        boat?.classList.remove('is-panicking','is-escaping');
        if(scene){delete scene.dataset.fisherReaction;if(panicAnimation===null)scene.dataset.fishingState='idle';}
        scene?.querySelectorAll('.ocean-fisher-panic,.ocean-fisher-splash').forEach(element=>element.remove());
    };
    const startPanic=event=>{
        if(!scene||document.hidden||motion.matches||panicSequence)return;
        resetCast(true);
        boat.classList.add('is-panicking');
        scene.dataset.fishingState='startled';
        const [name,message,x1,y1,r1,x2,y2,r2]=fisherReactions[reactionIndex];
        reactionIndex=(reactionIndex+1)%fisherReactions.length;
        try{sessionStorage.setItem('scholar-ocean-fisher-reaction',String(reactionIndex));}catch(_){}
        scene.dataset.fisherReaction=name;
        const direction=boat.getBoundingClientRect().right+100>innerWidth?-1:1;
        const pose=(x,y,rotation)=>`translate(${x*direction}px,${y}px) rotate(${rotation*direction}deg)`;
        const fisher=boat.querySelector('.ocean-fisher-person');
        panicSequence={fisher,pose,safeSince:0};
        const caption=document.createElement('span');caption.className='ocean-fisher-panic';caption.textContent=event.type==='ocean:big-wave'?['Whoa! BIG WAVE!','My boat just sneezed!','Man overboard! Again!','Not the boots!'][reactionIndex%4]:message;boat.append(caption);
        const splash=document.createElement('span');splash.className='ocean-fisher-splash';boat.append(splash);
        if(direction<0){splash.style.left='auto';splash.style.right='78%';caption.style.left='auto';caption.style.right='0';}
        panicSequence.caption=caption;
        const rod=boat.querySelector('.ocean-fisher-rod');
        boat.querySelector('svg').append(rod);
        rodAnimation=rod.animate([
            {transform:'translate(0,0) rotate(0deg)'},
            {transform:'translate(15px,-10px) rotate(25deg)',offset:.4},
            {transform:'translate(12px,25px) rotate(65deg)'},
        ],{duration:450,fill:'forwards',easing:'ease-out'});
        scene.dataset.fishingState='jumping';
        panicAnimation=fisher.animate([
            {transform:'translate(0,0) rotate(0deg)',offset:0},
            {transform:pose(x1*.6,y1*.5,r1*.5),offset:.12},
            {transform:pose(x2*.7,y2*.6,r2*.6),offset:.24},
            {transform:pose(48,-18,event.type==='ocean:big-wave'?38:24),offset:.44},
            {transform:pose(100,50,15),offset:1},
        ],{duration:1050,easing:'cubic-bezier(.35,0,.65,1)',fill:'forwards'});
        panicAnimation.onfinish=()=>{
            if(!panicSequence)return;
            panicAnimation.onfinish=null;panicAnimation.cancel();
            scene.dataset.fishingState='hiding';
            caption.textContent='Stay quiet... is it gone yet?';
            panicAnimation=fisher.animate([{transform:pose(100,50,15)},{transform:pose(98,48,11)},{transform:pose(100,50,15)}],{duration:1700,easing:'ease-in-out',iterations:Infinity});
        };
    };
    document.addEventListener('ocean:shark-splash',startPanic);
    document.addEventListener('ocean:big-wave',startPanic);
    const sharkNearBoat=()=>{
        const hull=boat.getBoundingClientRect();
        return [...document.querySelectorAll('.ocean-shark')].some(shark=>{
            const rect=shark.getBoundingClientRect();
            return Math.max(0,hull.left-rect.right,rect.left-hull.right)<230&&Math.max(0,hull.top-rect.bottom,rect.top-hull.bottom)<180;
        });
    };
    const watchFisherSafety=()=>{
        if(!panicSequence||document.hidden||motion.matches)return;
        const state=scene.dataset.fishingState;
        if(state!=='hiding'&&state!=='climbing')return;
        if(panicSequence.escaping)return;
        if(state==='hiding'&&document.querySelector('.ocean-spilled-fish')){
            panicSequence.safeSince=0;
            panicSequence.caption.textContent='My fish! It is eating ALL my fish!';
            return;
        }
        if(sharkNearBoat()){
            panicSequence.safeSince=0;
            if(state==='climbing'){
                const current=getComputedStyle(panicSequence.fisher).transform;
                panicAnimation.onfinish=null;panicAnimation.cancel();
                const {fisher,pose,caption}=panicSequence;
                caption.textContent='Nope! Back in the water!';
                scene.dataset.fishingState='hiding';
                panicAnimation=fisher.animate([{transform:current},{transform:pose(100,50,15)}],{duration:450,easing:'ease-in',fill:'forwards'});
                panicAnimation.onfinish=()=>{
                    if(!panicSequence)return;
                    panicAnimation.onfinish=null;panicAnimation.cancel();
                    panicAnimation=fisher.animate([{transform:pose(100,50,15)},{transform:pose(98,48,11)},{transform:pose(100,50,15)}],{duration:1700,easing:'ease-in-out',iterations:Infinity});
                };
            }
            return;
        }
        if(state!=='hiding')return;
        if(!panicSequence.safeSince){panicSequence.safeSince=performance.now();return;}
        if(performance.now()-panicSequence.safeSince<1200)return;
        beginClimb();
    };
    const beginClimb=(urgent=false)=>{
        if(!panicSequence||scene.dataset.fishingState!=='hiding')return;
        const {fisher,pose,caption}=panicSequence;
        const current=getComputedStyle(fisher).transform;
        panicSequence.escaping=urgent;
        panicAnimation.onfinish=null;
        panicAnimation.cancel();scene.dataset.fishingState='climbing';
        boat.classList.toggle('is-escaping',urgent);
        caption.textContent=urgent?'TEETH! Climb, legs, CLIMB!':'Heave! Oops... HEAVE! Slippery boat!';
        panicAnimation=fisher.animate([
            {transform:current},
            {transform:pose(78,32,-10),offset:.16},
            {transform:pose(82,39,5),offset:.27},
            {transform:pose(60,16,-16),offset:.46},
            {transform:pose(65,23,-5),offset:.57},
            {transform:pose(39,0,-14),offset:.76},
            {transform:pose(20,-4,-7),offset:.90},
            {transform:pose(0,0,0)},
        ].map(frame=>({...frame,easing:'cubic-bezier(.35,0,.3,1)'})),{duration:urgent?2900:3900,easing:'linear',fill:'forwards'});
        panicAnimation.onfinish=()=>{
            if(!panicSequence)return;
            panicAnimation.onfinish=null;panicAnimation.cancel();
            scene.dataset.fishingState='retrieving';caption.textContent='My rod! Right... back to fishing.';
            boat.classList.remove('is-escaping');
            rodAnimation.cancel();
            rodAnimation=boat.querySelector('.ocean-fisher-rod').animate([
                {transform:'translate(12px,25px) rotate(65deg)'},
                {transform:'translate(5px,5px) rotate(25deg)',offset:.6},
                {transform:'translate(0,0) rotate(0deg)'},
            ],{duration:1000,easing:'ease-in-out',fill:'forwards'});
            panicAnimation=fisher.animate([{transform:pose(0,0,0)},{transform:pose(8,3,14),offset:.5},{transform:pose(0,0,0)}],{duration:1100,fill:'forwards'});
            panicAnimation.onfinish=()=>{
                if(!panicSequence)return;
                panicAnimation.onfinish=null;panicAnimation.cancel();
                scene.dataset.fishingState='recovering';caption.textContent='Pfft! Wet boots. Hat? Rod? Still here!';
                panicAnimation=fisher.animate([
                    {transform:pose(0,0,0)},
                    {transform:pose(-3,0,-5),offset:.2},
                    {transform:pose(3,-2,5),offset:.4},
                    {transform:pose(-2,0,-3),offset:.6},
                    {transform:pose(0,0,0)},
                ],{duration:1200,easing:'ease-in-out',fill:'forwards'});
                panicAnimation.onfinish=()=>{stopPanic();scene.dataset.fishingState='idle';nextCast=performance.now()+800;};
            };
        };
    };
    document.addEventListener('ocean:fisher-threat',()=>{
        if(!scene||document.hidden||motion.matches)return;
        beginClimb(true);
    });
    const surfaceHeight=()=>Math.round(Math.min(300,Math.max(230,innerHeight*.32)));
    const syncSize=()=>{
        root.style.setProperty('--ocean-surface-y',`${surfaceHeight()}px`);
        const bar=document.querySelector('.topbar')?.getBoundingClientRect();
        const sidebar=document.querySelector('#sidebar')?.getBoundingClientRect();
        root.style.setProperty('--ocean-surface-bar-y',`${Math.max(0,bar?.bottom||0)}px`);
        root.style.setProperty('--ocean-surface-left',`${innerWidth>=992?Math.max(0,sidebar?.right||0):0}px`);
        // Fixed layers exclude the scrollbar; matching their real dimensions
        // keeps SVG hook coordinates aligned with the fish's screen pixels.
        line?.setAttribute('viewBox',`0 0 ${scene?.clientWidth||innerWidth} ${scene?.clientHeight||innerHeight}`);
        document.dispatchEvent(new CustomEvent('ocean:surface-ready'));
    };
    const boatPoint=(x,y)=>{
        const svg=boat.querySelector('svg'),point=svg.createSVGPoint();
        point.x=x;point.y=y;
        return point.matrixTransform(svg.getScreenCTM());
    };
    const syncWaveMotion=()=>{
        if(!boat||!wave||casting||motion.matches)return;
        const swell=wave.getAnimations({subtree:true}).find(animation=>animation.animationName==='ocean-wave-roll');
        const bob=boat.querySelector('svg').getAnimations().find(animation=>animation.animationName==='ocean-boat-bob');
        const journey=boat.getAnimations().find(animation=>animation.animationName==='ocean-boat-cross');
        if(!swell||!bob)return;
        const center=boat.getBoundingClientRect().left+boat.offsetWidth/2;
        const phase=((Number(swell.currentTime||0)-center/300*4000)%4000+4000)%4000;
        const left=parseFloat(getComputedStyle(root).getPropertyValue('--ocean-surface-left'))||0;
        const distance=Math.max(0,innerWidth-left-boat.offsetWidth-28);
        const returning=Math.floor((boatPhase+Number(journey?.currentTime||0))/40000)%2===1;
        const velocity=(returning?-1:1)*distance/40;
        bob.playbackRate=Math.max(.4,Math.min(1.6,1-velocity/75));
        bob.currentTime=phase;
    };
    const stopLineAnimations=()=>{ lineAnimations.splice(0).forEach(animation=>animation.cancel()); };
    const followRod=()=>{
        cancelAnimationFrame(rigFrame);
        if(!casting||!scene||document.hidden||motion.matches)return;
        const point=boat.querySelector('svg').createSVGPoint();
        point.x=116;point.y=10;
        const rod=point.matrixTransform(boat.querySelector('.ocean-fisher-arm').getScreenCTM());
        point.x=0;point.y=0;
        const end=point.matrixTransform(hook.getScreenCTM());
        thread.setAttribute('d',`M${rod.x} ${rod.y}Q${rod.x+12} ${(rod.y+end.y)/2} ${end.x} ${end.y}`);
        rigFrame=requestAnimationFrame(followRod);
    };
    const resetCast=(cancelFish=false)=>{
        cancelAnimationFrame(rigFrame);
        clearTimeout(castTimer);clearTimeout(resetTimer);stopLineAnimations();
        if(cancelFish&&casting)document.dispatchEvent(new CustomEvent('ocean:cancel-fishing'));
        casting=false;castId++;castPoints=null;
        boat?.classList.remove('is-casting','is-reeling');
        line?.classList.remove('is-visible');
        if(scene)scene.dataset.fishingState='idle';
        nextCast=performance.now()+3500;
        syncWaveMotion();
    };
    const cast=()=>{
        if(!scene||casting||panicSequence||document.hidden||motion.matches||performance.now()<nextCast)return;
        if(!document.querySelector('.ocean-creature-fish[data-ocean-state="swimming"]'))return;
        casting=true;
        const id=++castId;
        scene.dataset.fishingState='casting';boat.classList.add('is-casting');
        const bob=boat.querySelector('svg').getAnimations().find(animation=>animation.animationName==='ocean-boat-bob');
        if(bob)bob.playbackRate=1;
        const rod=boatPoint(116,10),bucket=boatPoint(133,69);
        const target={x:Math.max(24,Math.min(innerWidth-24,rod.x+22)),y:Math.min(innerHeight-100,surfaceHeight()+145)};
        castPoints={rod,target};
        thread.setAttribute('d',`M${rod.x} ${rod.y}Q${rod.x+12} ${(rod.y+target.y)/2} ${target.x} ${target.y}`);
        hook.style.transform=`translate3d(${target.x}px,${target.y}px,0)`;
        line.classList.add('is-visible');
        const length=thread.getTotalLength();
        thread.style.strokeDasharray='none';
        thread.style.strokeDashoffset='0';
        const dropFrames=Array.from({length:9},(_,index)=>{
            const t=index/8,point=thread.getPointAtLength(length*t);
            return {transform:`translate3d(${point.x}px,${point.y}px,0)`,opacity:1,offset:t};
        });
        lineAnimations.push(hook.animate(dropFrames,{duration:650,easing:'ease-out',fill:'forwards'}));
        followRod();
        castTimer=setTimeout(()=>{
            if(!scene||id!==castId)return;
            const detail={id,hook:{x:target.x+8,y:target.y+9},catchAt:{x:bucket.x,y:bucket.y},accepted:false};
            document.dispatchEvent(new CustomEvent('ocean:fish-hook',{detail}));
            if(!detail.accepted){resetCast();return;}
            scene.dataset.fishingState='waiting';
        },650);
    };
    document.addEventListener('ocean:hook-bit',event=>{
        if(!scene||event.detail.id!==castId||!casting)return;
        scene.dataset.fishingState='reeling';boat.classList.add('is-reeling');
        stopLineAnimations();
        const {trajectory,startedAt,duration}=event.detail;
        thread.style.strokeDasharray='none';
        const hookFrames=trajectory.map(frame=>({transform:`translate3d(${frame.point.x-8}px,${frame.point.y-9}px,0)`,opacity:frame.offset===1?0:1,offset:frame.offset}));
        const lineFrames=trajectory.map(frame=>({opacity:frame.offset===1?0:1,offset:frame.offset}));
        const options={duration,easing:'ease-in',fill:'forwards'};
        const hookAnimation=hook.animate(hookFrames,options),lineAnimation=thread.animate(lineFrames,options);
        hookAnimation.startTime=lineAnimation.startTime=startedAt;
        lineAnimations.push(hookAnimation,lineAnimation);
    });
    document.addEventListener('ocean:fish-caught',event=>{
        if(!scene||event.detail.id!==castId)return;
        caughtFish.push(event.detail.variant||'clown');showCatch();
        scene.dataset.catches=String(++catches);scene.dataset.fishingState='caught';
        resetTimer=setTimeout(()=>{resetCast();nextCast=performance.now()+4500;},600);
    });
    const saveBoat=()=>{
        if(!boat)return;
        const animation=boat.getAnimations().find(item=>item.animationName==='ocean-boat-cross');
        const position=(boatPhase+Number(animation?.currentTime||0))%80000;
        try{sessionStorage.setItem('scholar-ocean-boat',String(position));}catch(_){}
    };
    const activity=()=>{
        waveTick=performance.now();
        [scene,sky,water].forEach(element=>{
            element?.classList.toggle('ocean-surface-reduced',motion.matches);
        });
        // Keep the sky, water and hero on one clock; hidden tabs do not paint
        // these compositor layers. Pause the active boat/line interaction only.
        scene?.classList.toggle('ocean-surface-paused',document.hidden);
        lineAnimations.forEach(animation=>{if(document.hidden||motion.matches)animation.pause();else animation.play();});
        if(motion.matches){stopPanic();clearBigWaves();}
        activeWaves.forEach(record=>{if(document.hidden)record.animation.pause();else record.animation.play();});
        if(document.hidden)boatImpact?.pause();else boatImpact?.play();
        if(document.hidden)panicAnimation?.pause();else panicAnimation?.play();
        if(document.hidden||motion.matches)rodAnimation?.pause();else rodAnimation?.play();
        if(panicSequence)panicSequence.safeSince=0;
        if(motion.matches&&casting)resetCast(true);
        if(!document.hidden&&!motion.matches){syncWaveMotion();cast();if(casting)followRod();}
        else cancelAnimationFrame(rigFrame);
    };
    const destroy=()=>{
        saveBoat();stopPanic();clearBigWaves();resetCast(true);clearInterval(loop);
        scene?.remove();sky?.remove();water?.remove();scene=sky=water=boat=line=thread=hook=wave=null;
        root.style.removeProperty('--ocean-day-phase');root.style.removeProperty('--ocean-surface-y');
        root.style.removeProperty('--ocean-surface-bar-y');root.style.removeProperty('--ocean-surface-left');
    };
    const sync=()=>{
        if(root.dataset.theme!=='ocean'){if(scene)destroy();return;}
        if(scene)return;
        let elapsed=0;
        try{
            let origin=Number(sessionStorage.getItem('scholar-ocean-day-origin'));
            if(!origin||origin>Date.now()){origin=Date.now();sessionStorage.setItem('scholar-ocean-day-origin',String(origin));}
            elapsed=(Date.now()-origin)%32000;
            const saved=Number(sessionStorage.getItem('scholar-ocean-boat'));
            boatPhase=Number.isFinite(saved)&&saved>=0?saved%80000:0;
        }catch(_){}
        root.style.setProperty('--ocean-day-phase',`-${elapsed}ms`);
        water=document.createElement('div');water.className='ocean-day-night-water';water.setAttribute('aria-hidden','true');
        sky=document.createElement('div');sky.className='ocean-surface-sky';sky.setAttribute('aria-hidden','true');
        ['day','sunset','night','stars'].forEach(type=>{const layer=document.createElement('div');layer.className=`ocean-sky-${type}`;sky.append(layer);});
        const sun=document.createElement('div');sun.className='ocean-surface-sun';
        const moon=document.createElement('div');moon.className='ocean-surface-moon';sky.append(sun,moon);
        const birds=document.createElementNS(svgNS,'svg');birds.classList.add('ocean-sunset-birds');
        birds.setAttribute('viewBox','-15 -15 190 120');
        [[130,40],[104,27],[104,53],[78,14],[78,66],[52,2],[52,78]].forEach(([x,y],index)=>{
            const bird=document.createElementNS(svgNS,'g');bird.setAttribute('transform',`translate(${x} ${y})`);
            bird.style.setProperty('--bird-delay',`${-index*.09}s`);
            bird.innerHTML='<path class="ocean-bird-wing ocean-bird-wing-left" d="M0 0Q-7-10-14-5"/><path class="ocean-bird-wing ocean-bird-wing-right" d="M0 0Q7-10 14-5"/><circle r="1.5" fill="#173C4C"/>';
            birds.append(bird);
        });
        sky.append(birds);
        for(let i=0;i<3;i++){
            const cloud=document.createElement('div');cloud.className='ocean-sky-cloud';
            cloud.style.setProperty('--cloud-y',`${30+i*24}px`);cloud.style.setProperty('--cloud-delay',`${-i*26}s`);sky.append(cloud);
        }
        scene=document.createElement('div');scene.className='ocean-surface-scene';scene.setAttribute('aria-hidden','true');scene.dataset.fishingState='idle';scene.dataset.catches=String(catches);
        boat=document.createElement('div');boat.className='ocean-fishing-boat';boat.style.setProperty('--ocean-boat-phase',`-${boatPhase}ms`);
        boat.innerHTML=`<svg viewBox="0 0 180 110" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="ocean-boat-hull" x2="0" y2="1"><stop stop-color="#D5A869"/><stop offset="1" stop-color="#855D38"/></linearGradient></defs>
            <ellipse cx="85" cy="102" rx="76" ry="5" fill="#99F3FF" opacity=".35"/>
            <path d="M12 74q75 13 150-5-10 29-35 31H47Q25 98 12 74Z" fill="url(#ocean-boat-hull)" stroke="#6C4B31" stroke-width="2"/><path d="M19 80q75 12 137-4M33 91q56 8 107-3" fill="none" stroke="#E3BE86" stroke-width="2"/>
            <g class="ocean-fisher-person"><path d="M62 55h18l2 11H60Z" fill="#244F68"/>
            <g class="ocean-fisher-leg ocean-fisher-leg-left"><path d="m65 64-3 9" fill="none" stroke="#356E90" stroke-width="7" stroke-linecap="round"/><g class="ocean-fisher-shin ocean-fisher-shin-left"><path d="m62 73 1 8" stroke="#356E90" stroke-width="6" stroke-linecap="round"/><path d="m61 82 9 1" stroke="#142F48" stroke-width="5" stroke-linecap="round"/></g></g>
            <g class="ocean-fisher-leg ocean-fisher-leg-right"><path d="m76 64 4 9" fill="none" stroke="#4985A5" stroke-width="7" stroke-linecap="round"/><g class="ocean-fisher-shin ocean-fisher-shin-right"><path d="m80 73-1 9" stroke="#4985A5" stroke-width="6" stroke-linecap="round"/><path d="m78 83 9-1" stroke="#142F48" stroke-width="5" stroke-linecap="round"/></g></g>
            <g class="ocean-fisher-torso"><path d="M60 56q-2-20 13-22 14 2 14 22l-9 7-18-7Z" fill="#F6C454" stroke="#BD8D30"/><path d="M66 38v21m12-20v20" stroke="#E29439" stroke-width="3"/>
            <circle cx="72" cy="25" r="12" fill="#E8B38D"/><path d="M62 22q0-13 15-11l7 13" fill="#7B5B34"/><path d="M54 20q18-8 37 0" stroke="#B08C4F" stroke-width="5" stroke-linecap="round"/>
            <circle cx="79" cy="26" r="1.5" fill="#294356"/><path d="m78 32 5-1" stroke="#9B654C" stroke-width="1.5"/></g>
            <g class="ocean-fisher-back-arm"><path d="m64 44-7 10" fill="none" stroke="#CE9573" stroke-width="5" stroke-linecap="round"/><g class="ocean-fisher-back-forearm"><path d="m57 54 5 10" fill="none" stroke="#E8B38D" stroke-width="5" stroke-linecap="round"/><circle cx="62" cy="64" r="3" fill="#E8B38D"/></g></g>
            <g class="ocean-fisher-arm"><path d="m68 45 14 7" fill="none" stroke="#E8B38D" stroke-width="6" stroke-linecap="round"/><g class="ocean-fisher-forearm"><path d="m82 52 8-6" fill="none" stroke="#E8B38D" stroke-width="5" stroke-linecap="round"/><circle cx="90" cy="46" r="3" fill="#E8B38D"/></g><g class="ocean-fisher-rod"><path d="M84 49Q98 20 116 10" fill="none" stroke="#41566A" stroke-width="2.2"/><circle cx="87" cy="45" r="4" fill="#B8C8CF" stroke="#50697C"/><path d="M116 10 119 40q0 7 4 4" fill="none" stroke="#D7EBF1" stroke-width="1"/></g></g>
            </g><g class="ocean-bucket-catch"></g><path d="M123 64h21l-3 17h-15Z" fill="#B1CDD8" stroke="#648897"/><path d="M126 64q7-13 15 0" fill="none" stroke="#648897"/>
        </svg>`;
        line=document.createElementNS(svgNS,'svg');line.classList.add('ocean-fishing-line');
        thread=document.createElementNS(svgNS,'path');thread.setAttribute('fill','none');thread.setAttribute('stroke','#F1FBFCCC');thread.setAttribute('stroke-width','1');
        hook=document.createElementNS(svgNS,'g');
        hook.innerHTML='<path d="M0-7V7q0 9 7 6l3-6-5 3" fill="none" stroke="#D7EBF1" stroke-width="1.8" stroke-linecap="round"/><circle cy="-4" r="2.5" fill="#F3B25F"/>';
        line.append(thread,hook);
        wave=document.createElement('div');wave.className='ocean-surface-wave';
        showCatch();
        scene.append(line,boat,wave);document.body.append(water,sky,scene);syncSize();
        boat.addEventListener('animationiteration',event=>{if(event.animationName==='ocean-boat-cross')syncWaveMotion();});
        syncWaveMotion();
        nextCast=performance.now()+1200;
        waveElapsed=0;waveTick=performance.now();
        loop=setInterval(()=>{tickBigWave();watchFisherSafety();cast();},100);activity();
    };
    new MutationObserver(() => window.scholarScheduleThemeEffect(sync)).observe(root,{attributes:true,attributeFilter:['data-theme']});
    window.addEventListener('resize',()=>{if(scene){stopPanic();clearBigWaves();resetCast(true);syncSize();}},{passive:true});
    window.addEventListener('pagehide',saveBoat);
    document.addEventListener('visibilitychange',activity);
    motion.addEventListener('change',activity);
    sync();
})();
