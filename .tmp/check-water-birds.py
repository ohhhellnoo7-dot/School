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
 surface=c.evaluate("parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ocean-surface-y'))")
 routes=c.evaluate("[...document.querySelectorAll('.ocean-creature[data-ocean-state=swimming]')].filter(e=>e.getAnimations().length).map(e=>({type:e.dataset.animal,ys:e.getAnimations()[0].effect.getKeyframes().map(k=>new DOMMatrix(k.transform).m42)}))")
 assert routes
 assert all(all(y>=surface+10 for y in route['ys']) for route in routes),routes
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-life-scene')).clipPath").startswith('inset(')
 assert c.evaluate("document.querySelector('.ocean-shark').getBoundingClientRect().top")>=surface+10
 assert c.evaluate("document.querySelectorAll('.ocean-sunset-birds > g').length")==7
 birdStates=c.evaluate("(()=>{const e=document.querySelector('.ocean-sunset-birds'),a=e.getAnimations()[0],previous=a.currentTime;a.pause();const result=[8000,17500,24000].map(t=>{a.currentTime=t;return {t,opacity:Number(getComputedStyle(e).opacity),visibility:getComputedStyle(e).visibility}});a.currentTime=previous;a.play();return result})()")
 assert birdStates[0]['opacity']==0 and birdStates[0]['visibility']=='hidden',birdStates
 assert birdStates[1]['opacity']>.9 and birdStates[1]['visibility']=='visible',birdStates
 assert birdStates[2]['opacity']==0 and birdStates[2]['visibility']=='hidden',birdStates
 print('PASS water-only marine routes and sunset-only flock',flush=True)
 caught=0;seen=set()
 for attempt in range(70):
  time.sleep(.4)
  state=c.evaluate("document.querySelector('.ocean-surface-scene').dataset.fishingState")
  seen.add(state)
  caught=c.evaluate("Number(document.querySelector('.ocean-surface-scene').dataset.catches||0)")
  if caught:break
 assert caught>0,seen
 assert 'waiting' in seen and 'reeling' in seen,seen
 print('PASS natural fishing: cast, bite, reel and catch after',round((attempt+1)*.4,1),'seconds',flush=True)
 (root/'.tmp/ocean-fishing-day.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 time.sleep(1.8)
 assert c.evaluate("!!document.querySelector('.ocean-creature-fish')")
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
