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
 c.evaluate("document.documentElement.dataset.theme='light'");time.sleep(.1)
 c.evaluate("document.documentElement.dataset.theme='khmer-heritage'");time.sleep(.2)
 assert c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-art')).backgroundImage.includes('smooth-cycle')")
 assert c.evaluate("document.querySelectorAll('.khmer-apsara-pose').length")==0
 assert c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-art')).opacity")=='1'
 first=c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-art')).backgroundPosition")
 time.sleep(.25)
 second=c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-art')).backgroundPosition")
 assert first!=second,(first,second)
 assert c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-art')).backgroundSize")=='600% 400%'
 time.sleep(1.4)
 assert c.evaluate("document.querySelectorAll('.khmer-apsara-flower').length")>0
 assert c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-scene')).pointerEvents")=='none'
 for width in [1536,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.3)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert result['scroll']<=result['client'],result
  (root/f'.tmp/apsara-{width}.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.evaluate("localStorage.setItem('scholar-theme','khmer-heritage')")
 c.call('Page.navigate',{'url':(root/'.tmp/monthly-debug.html').as_uri()+'?apsara-next-page=1'});time.sleep(.5)
 assert c.evaluate("Number(sessionStorage.getItem('scholar-apsara-journey'))")>0
 assert c.evaluate("parseFloat(document.querySelector('.khmer-apsara-traveler').style.animationDelay)")<0
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.2)
 assert c.evaluate("getComputedStyle(document.querySelector('.khmer-apsara-traveler')).animationName")=='none'
 c.evaluate("document.documentElement.dataset.theme='light'");time.sleep(.1)
 assert c.evaluate("document.querySelectorAll('.khmer-apsara-scene').length")==0
 print('PASS crisp 24-frame animation without overlapping layers, synchronized flower burst, desktop/mobile containment, navigation continuity, reduced motion and theme cleanup')
 c.call('Browser.close')
finally:p.terminate()
