(() => {
    const root=document.documentElement,motion=matchMedia('(prefers-reduced-motion: reduce)');
    let man,frame,elapsed=0,clock=0,last=0,lastRequest=0;
    let state='gliding',x=0,y=0,stageStartY=0,vx=25,direction=1,limits;
    const smooth=t=>t*t*t*(t*(t*6-15)+10);
    const measure=()=>{
        const style=getComputedStyle(root),surface=parseFloat(style.getPropertyValue('--ocean-surface-y'))||205;
        const bar=document.querySelector('.topbar')?.getBoundingClientRect();
        const high=Math.max(12,Math.round((bar?.top||0)+((bar?.bottom||64)-(bar?.top||0))*.45));
        const left=Math.min(parseFloat(style.getPropertyValue('--ocean-surface-left'))+12||12,Math.max(12,innerWidth-man.offsetWidth-12));
        limits={left,right:Math.max(left,innerWidth-man.offsetWidth-16),high,low:Math.max(high,surface-man.offsetHeight+23)};
    };
    const setState=next=>{stageStartY=y;state=next;elapsed=0;if(man)man.dataset.flight=next;};
    const place=()=>{man.style.transform=`translate3d(${x}px,${y}px,0)`;};
    const tick=now=>{
        frame=null;
        if(!man||document.hidden||motion.matches)return;
        const delta=Math.max(0,Math.min(50,now-last));last=now;
        clock+=delta;elapsed+=delta;
        const {left,right,high,low}=limits;
        // Integrate position rather than snapping back to an old patrol path.
        const speed=state==='waiting'?0:direction*(state==='escaping'?48:32);
        vx+=(speed-vx)*(1-Math.exp(-delta/300));
        x=Math.max(left,Math.min(right,x+vx*delta/1000));
        if(x>=right)direction=-1;else if(x<=left)direction=1;
        const previousY=y;
        if(state==='gliding'){
            const target=high+2+Math.sin(clock/1200)*2;
            y+=(target-y)*(1-Math.exp(-delta/450));
            if(elapsed>=8500)setState('descending');
        }else if(state==='descending'){
            y=stageStartY+(low-stageStartY)*smooth(Math.min(1,elapsed/2200));
            if(elapsed>=2200)setState('low');
        }else if(state==='low'){
            y=low+Math.sin(clock/800);
            if(now-lastRequest>600){
                place();lastRequest=now;
                const rect=man.querySelector('.parachute-person').getBoundingClientRect();
                const detail={target:{x:rect.left+rect.width/2,y:rect.bottom-5},accepted:false};
                document.dispatchEvent(new CustomEvent('ocean:parachute-low',{detail}));
                if(detail.accepted)setState('waiting');
            }
            if(elapsed>1800)setState('escaping');
        }else if(state==='waiting'){
            y+=(low-y)*(1-Math.exp(-delta/300));
            if(elapsed>3000){document.dispatchEvent(new CustomEvent('ocean:parachute-cancel'));setState('escaping');}
        }else if(state==='escaping'){
            const t=Math.min(1,elapsed/1500);
            y=stageStartY+(high-stageStartY)*smooth(t);
            if(t===1)setState('gliding');
        }
        const verticalSpeed=delta?(y-previousY)*1000/delta:0;
        man.style.setProperty('--parachute-bank',`${Math.max(-9,Math.min(9,vx*.1+verticalSpeed*.025))}deg`);
        place();frame=requestAnimationFrame(tick);
    };
    const reset=()=>{
        if(!man)return;
        document.dispatchEvent(new CustomEvent('ocean:parachute-cancel'));
        measure();last=performance.now();y=limits.high;
        x=Math.max(limits.left,Math.min(limits.right,x||innerWidth*.6));vx=25;setState('gliding');place();
    };
    const activity=()=>{
        cancelAnimationFrame(frame);frame=null;last=performance.now();
        man?.classList.toggle('is-paused',document.hidden);man?.classList.toggle('is-reduced',motion.matches);
        if(!man)return;
        if(motion.matches)reset();
        else if(!document.hidden)frame=requestAnimationFrame(tick);
    };
    const sync=()=>{
        if(root.dataset.theme!=='ocean'){
            cancelAnimationFrame(frame);frame=null;document.dispatchEvent(new CustomEvent('ocean:parachute-cancel'));man?.remove();man=null;return;
        }
        if(man)return;
        man=document.createElement('div');man.className='ocean-parachutist';man.setAttribute('aria-hidden','true');
        man.innerHTML=`<svg viewBox="0 0 180 140" xmlns="http://www.w3.org/2000/svg">
            <defs><linearGradient id="parachute-red" x2="0" y2="1"><stop stop-color="#ffb18a"/><stop offset=".4" stop-color="#f75446"/><stop offset="1" stop-color="#b51f39"/></linearGradient></defs>
            <g transform="scale(1 .65)"><g class="parachute-canopy"><path d="M8 53Q22-9 90 4q70-13 82 49l-12 9-16-6-18 5-18-5-18 6-18-6-18 5-18-5-16 6Z" fill="url(#parachute-red)" stroke="#ffdbb8" stroke-width="2"/>
            <path d="M26 57Q27 15 60 6M54 58Q52 16 75 4M90 61V4m18 53q20-41-3-53m39 53Q148 15 120 6m40 51Q156 18 140 14" fill="none" stroke="#ffd4aa" stroke-width="1.5"/></g></g>
            <g fill="none" stroke="#e5e9d8" stroke-width="1"><path d="M20 38 66 69 54 38M72 37 66 69 90 40M108 37 116 69 126 38M144 37 116 69 160 38"/></g>
            <g transform="translate(0 -30)"><g class="parachute-person"><g class="parachute-arm-left"><path d="m83 120-12-7-5-13" fill="none" stroke="#dc4940" stroke-width="7" stroke-linecap="round"/><circle cx="66" cy="99" r="4" fill="#e8b58b"/></g>
            <g class="parachute-arm-right"><path d="m99 120 12-7 5-13" fill="none" stroke="#dc4940" stroke-width="7" stroke-linecap="round"/><circle cx="116" cy="99" r="4" fill="#e8b58b"/></g>
            <path d="M80 116h21l4 25H78Z" fill="#314d65" stroke="#132b41" stroke-width="2"/><path d="m82 119 17 20m0-20-17 20" stroke="#a6c4ce" stroke-width="2"/>
            <circle cx="91" cy="108" r="9" fill="#e8b58b"/><path d="M81 107q-1-15 12-13 10 2 8 14" fill="#34546d" stroke="#b5d8e3"/><path d="M85 105h12v5H85Z" fill="#9feaff"/>
            <g class="parachute-leg-left"><path d="m84 140-4 13 5 9" fill="none" stroke="#dc4940" stroke-width="7" stroke-linecap="round"/><path d="m83 163 8 0" stroke="#213c52" stroke-width="5" stroke-linecap="round"/></g>
            <g class="parachute-leg-right"><path d="m98 140 5 13-4 9" fill="none" stroke="#dc4940" stroke-width="7" stroke-linecap="round"/><path d="m98 163 8 0" stroke="#213c52" stroke-width="5" stroke-linecap="round"/></g></g></g>
        </svg><span class="parachute-caption">UP! UP! UP!</span>`;
        document.body.append(man);reset();activity();
    };
    document.addEventListener('ocean:parachute-escape',()=>{if(man)setState('escaping');});
    document.addEventListener('ocean:wave-approaching',()=>{
        if(!man||document.hidden||motion.matches)return;
        document.dispatchEvent(new CustomEvent('ocean:parachute-cancel'));
        setState('escaping');
    });
    document.addEventListener('visibilitychange',activity);motion.addEventListener('change',activity);
    window.addEventListener('resize',reset,{passive:true});document.addEventListener('ocean:surface-ready',reset);
    new MutationObserver(()=>window.scholarScheduleThemeEffect(sync)).observe(root,{attributes:true,attributeFilter:['data-theme']});
    sync();
})();
