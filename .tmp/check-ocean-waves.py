import base64,json,subprocess,time,urllib.request
from pathlib import Path
root=Path.cwd()
ns={'__file__':str(root/'scripts/browser-check.py')}
exec((root/'scripts/browser-check.py').read_text().split('edge = subprocess.Popen')[0],ns)
p=subprocess.Popen([r'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe','--headless=new','--disable-gpu','--no-first-run','--allow-file-access-from-files','--remote-debugging-port=9242','--remote-allow-origins=*','--user-data-dir='+str(root/'.tmp/monthly-browser'),'about:blank'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=subprocess.CREATE_NO_WINDOW)
try:
 for _ in range(60):
  try:
   pages=json.load(urllib.request.urlopen('http://127.0.0.1:9242/json'));break
  except:time.sleep(.1)
 c=ns['DevTools'](next(x['webSocketDebuggerUrl'] for x in pages if x['type']=='page'))
 c.call('Page.enable');c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':900,'deviceScaleFactor':1,'mobile':False})
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()});time.sleep(2)
 c.evaluate("document.documentElement.dataset.theme='khmer-heritage'")
 c.evaluate("document.querySelector('[data-theme-choice=light]').click();sessionStorage.removeItem('scholar-ocean-day-origin')");time.sleep(.1)
 c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()");time.sleep(.3)
 assert c.evaluate("document.querySelectorAll('.ocean-fishing-boat').length")==1
 assert c.evaluate("document.querySelector('.ocean-day-night-water').getAnimations()[0].effect.getTiming().duration")==32000
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-surface-sky')).zIndex)")==1034
 surface_before=c.evaluate("document.querySelector('.ocean-surface-sky').getBoundingClientRect().bottom")
 c.evaluate("window.scrollTo(0,450)")
 time.sleep(.2)
 assert c.evaluate("document.querySelector('.ocean-surface-sky').getBoundingClientRect().bottom")==surface_before
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.topbar')).zIndex)")>1034
 (root/'.tmp/ocean-fixed-surface-scroll.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.evaluate("window.scrollTo(0,0)")
 print('PASS fixed sky covers scrolling content above waterline while topbar stays above sky',flush=True)
 wave=c.evaluate("getComputedStyle(document.querySelector('.ocean-surface-wave'),'::before').transform")
 boat=c.evaluate("getComputedStyle(document.querySelector('.ocean-fishing-boat > svg')).transform")
 time.sleep(.25)
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-surface-wave'),'::before').transform")!=wave
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-fishing-boat > svg')).transform")!=boat
 assert c.evaluate("document.querySelector('.ocean-fishing-boat').classList.contains('is-casting') || document.querySelector('.ocean-fishing-boat > svg').getAnimations()[0].playbackRate!==1")
 print('PASS rolling waves and position-synchronized boat rocking',flush=True)
 c.evaluate("window.reelInfo=null;document.addEventListener('ocean:hook-bit',e=>window.reelInfo=e.detail)")
 for attempt in range(90):
  time.sleep(.15)
  if c.evaluate('!!window.reelInfo'):break
 assert c.evaluate('!!window.reelInfo')
 boat_during=c.evaluate("getComputedStyle(document.querySelector('.ocean-fishing-boat > svg')).transform")
 time.sleep(.15)
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-fishing-boat > svg')).transform")!=boat_during
 print('PASS boat keeps rocking during fishing',flush=True)
 samples=c.evaluate("(async()=>{const fish=document.querySelector('.ocean-creature-fish'),hook=document.querySelector('.ocean-fishing-line g'),line=document.querySelector('.ocean-fishing-line > path'),anims=[...fish.getAnimations(),...hook.getAnimations(),...line.getAnimations()];const starts=anims.map(a=>a.startTime);anims.forEach(a=>a.pause());const samples=[];for(const t of [250,600,950]){anims.forEach(a=>a.currentTime=t);await new Promise(requestAnimationFrame);await new Promise(requestAnimationFrame);const fs=fish.querySelector('svg'),fp=fs.createSVGPoint();fp.x=96;fp.y=38;const f=fp.matrixTransform(fs.getScreenCTM()),hp=fs.createSVGPoint();hp.x=8;hp.y=9;const h=hp.matrixTransform(hook.getScreenCTM()),lp=line.getPointAtLength(line.getTotalLength()),l=lp.matrixTransform(line.getScreenCTM()),origin=fs.createSVGPoint(),ho=origin.matrixTransform(hook.getScreenCTM());samples.push({time:t,f:{x:f.x,y:f.y},h:{x:h.x,y:h.y},origin:getComputedStyle(fish).transformOrigin,fishTransform:getComputedStyle(fish).transform,gap:Math.hypot(f.x-h.x,f.y-h.y),lineGap:Math.hypot(l.x-ho.x,l.y-ho.y),starts});}return samples;})()")
 print('reel alignment',samples,flush=True)
 assert all(sample['gap']<2 for sample in samples),samples
 assert all(sample['lineGap']<2 for sample in samples),samples
 assert all(max(sample['starts'])-min(sample['starts'])<1 for sample in samples),samples
 c.evaluate("document.querySelectorAll('.ocean-creature-fish,.ocean-fishing-line g,.ocean-fishing-line > path').forEach(e=>e.getAnimations().forEach(a=>a.play()))")
 time.sleep(.6)
 assert c.evaluate("Number(document.querySelector('.ocean-surface-scene').dataset.catches||0)")>0
 print('PASS hook tip stays attached to fish mouth and line endpoint throughout reel-in')
 for selector in ['.ocean-sky-day','.ocean-sky-sunset','.ocean-sky-night','.ocean-sky-stars','.ocean-surface-sun','.ocean-surface-moon','.ocean-day-night-water']:
  c.evaluate("(()=>{const a=document.querySelector("+json.dumps(selector)+").getAnimations()[0];a.pause();a.currentTime=24000})()")
 c.evaluate("document.getAnimations().filter(a=>a.animationName==='ocean-water-night').forEach(a=>{a.pause();a.currentTime=24000})")
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-sky-night')).opacity)")>.9
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-day-night-water')).opacity)")>.6
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-surface-moon')).opacity)")>.9
 (root/'.tmp/ocean-fishing-night.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 origin=c.evaluate("sessionStorage.getItem('scholar-ocean-day-origin')")
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()+'?fishing-next-page=1'});time.sleep(.5)
 assert c.evaluate("sessionStorage.getItem('scholar-ocean-day-origin')")==origin
 assert c.evaluate("parseFloat(getComputedStyle(document.querySelector('.ocean-fishing-boat')).animationDelay)")<0
 for width in [1536,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.4)
  size=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert size['scroll']<=size['client'],(width,size)
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.1)
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-fishing-boat')).animationName")=='none'
 assert c.evaluate("document.querySelector('.ocean-surface-scene').dataset.fishingState")=='idle'
 c.evaluate("document.querySelector('[data-theme-choice=light]').click()");time.sleep(.2)
 assert c.evaluate("document.querySelectorAll('.ocean-surface-scene,.ocean-surface-sky,.ocean-day-night-water').length")==0
 print('PASS fish replacement, 20-second daylight and sunset, smooth night tint and moon, mobile containment, reduced motion and cleanup')
 c.call('Browser.close')
finally:p.terminate()
