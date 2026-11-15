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
 c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()");time.sleep(.5)
 assert c.evaluate("document.querySelectorAll('.ocean-creature').length")==4
 assert c.evaluate("document.querySelectorAll('.ocean-bubble').length")==14
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-life-scene')).pointerEvents")=='none'
 assert c.evaluate("getComputedStyle(document.body,'::before').backgroundImage.includes('ocean-water-backdrop.png')")
 assert c.evaluate("getComputedStyle(document.querySelector('.panel')).backgroundImage.includes('rgba')")
 assert c.evaluate("getComputedStyle(document.querySelector('.panel')).backdropFilter")=='none'
 assert c.evaluate("getComputedStyle(document.querySelector('.panel')).color")=='rgb(241, 252, 255)'

 assert c.evaluate("getComputedStyle(document.querySelector('.school-hero')).backgroundImage.includes('ocean-school-hero.png')")
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-seabed')).backgroundImage.includes('ocean-coral-seabed.png')")
 x=c.evaluate("document.querySelector('.ocean-creature-turtle').getBoundingClientRect().x");time.sleep(.2)
 assert c.evaluate("document.querySelector('.ocean-creature-turtle').getBoundingClientRect().x")!=x
 for width in [1536,1280,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.4)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert result['scroll']<=result['client'],(width,result)
  (root/f'.tmp/ocean-{width}.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.1)
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-creature-turtle')).animationName")=='none'
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-bubble')).display")=='none'
 c.evaluate("document.querySelector('[data-theme-choice=light]').click()");time.sleep(.1)
 assert c.evaluate("document.querySelectorAll('.ocean-life-scene').length")==0
 print('PASS Ocean banner, coral seabed, four animated animals, bubbles, theme cleanup, reduced motion and desktop/mobile containment')
 c.call('Browser.close')
finally:p.terminate()
