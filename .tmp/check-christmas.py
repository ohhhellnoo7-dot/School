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
 c.call('Page.navigate',{'url':(root/'.tmp/monthly-debug.html').as_uri()});time.sleep(2)
 c.evaluate("document.documentElement.dataset.theme='khmer-heritage'")
 c.evaluate("document.documentElement.dataset.theme='christmas'");time.sleep(.4)
 assert c.evaluate("document.querySelectorAll('.christmas-scene').length")==1
 assert c.evaluate("document.querySelectorAll('.christmas-flake').length")==24
 assert c.evaluate("document.querySelectorAll('.christmas-road').length")==1
 assert c.evaluate("document.querySelectorAll('.christmas-boot').length")==2
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-walker')).animationTimingFunction")=='linear'
 assert c.evaluate("getComputedStyle(document.querySelector('.teacher-attendance-hero'),'::before').backgroundImage.includes('christmas-school-hero.png')")
 assert c.evaluate("getComputedStyle(document.querySelector('.sidebar')).backgroundImage.includes('christmas-trees-gifts.svg')")

 assert c.evaluate("document.querySelector('.christmas-greeting').textContent.includes('Hello!')")
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-greeting')).visibility")=='hidden'
 c.evaluate("document.querySelector('.christmas-walker').click()");time.sleep(.1)
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-greeting')).visibility")=='visible'
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-snowman')).animationName")=='christmas-jump'
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-walker')).animationPlayState")=='paused'
 time.sleep(2.5)
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-greeting')).visibility")=='hidden'
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-walker')).animationPlayState")=='running'

 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-scene')).pointerEvents")=='none'
 for width in [1536,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.3)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert result['scroll']<=result['client'],result
  (root/f'.tmp/christmas-{width}.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.evaluate("localStorage.setItem('scholar-theme','christmas'); document.documentElement.dataset.theme='light'");time.sleep(.1)
 c.evaluate("sessionStorage.setItem('scholar-christmas-walk','45000'); document.documentElement.dataset.theme='christmas'");time.sleep(.2)
 before=c.evaluate("document.querySelector('.christmas-walker').getBoundingClientRect().x")
 c.call('Page.navigate',{'url':(root/'.tmp/monthly-debug.html').as_uri()+'?next-page=1'});time.sleep(.6)
 after=c.evaluate("document.querySelector('.christmas-walker').getBoundingClientRect().x")
 saved=c.evaluate("Number(sessionStorage.getItem('scholar-christmas-walk'))")
 assert saved>=45000,saved
 assert abs(after-before)<35,(before,after)
 assert c.evaluate("parseFloat(getComputedStyle(document.querySelector('.christmas-walker')).animationDelay)")<-40
 print('PASS page navigation preserves return-trip position',round(before),round(after))
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.1)
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-snow')).display")=='none'
 assert c.evaluate("getComputedStyle(document.querySelector('.christmas-walker')).animationName")=='none'
 c.evaluate("document.documentElement.dataset.theme='light'");time.sleep(.1)
 assert c.evaluate("document.querySelectorAll('.christmas-scene').length")==0
 c.evaluate("document.documentElement.dataset.theme='christmas'");time.sleep(.1)
 assert c.evaluate("document.querySelectorAll('.christmas-scene').length")==1
 print('PASS click greeting, jump, resume walking, theme switching, reduced motion and desktop/mobile containment')
 c.call('Browser.close')
finally:p.terminate()
