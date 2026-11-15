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
 for selector in ['.ocean-sky-day','.ocean-sky-sunset','.ocean-sky-night','.ocean-sky-stars','.ocean-surface-sun','.ocean-surface-moon','.ocean-day-night-water']:
  c.evaluate("(()=>{const a=document.querySelector("+json.dumps(selector)+").getAnimations()[0];a.pause();a.currentTime=24000})()")
 print('header',c.evaluate("(()=>{const h=document.querySelector('.school-hero h1'),p=h.parentElement;return {text:h.textContent,hStyle:getComputedStyle(h).cssText,hOpacity:getComputedStyle(h).opacity,pOpacity:getComputedStyle(p).opacity,heroOpacity:getComputedStyle(p.parentElement).opacity,visible:getComputedStyle(h).visibility,color:getComputedStyle(h).color}})()"),flush=True)
 for animation in c.call('Runtime.evaluate',{'expression':'0'}).get('nothing',[]):pass
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-sky-night')).opacity)")>.9
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-day-night-water')).opacity)")>.6
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.ocean-surface-moon')).opacity)")>.9
 (root/'.tmp/ocean-fishing-night.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
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
