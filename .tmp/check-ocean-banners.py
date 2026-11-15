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
 for page in ['students','parents','classes','timetables','profile','reports']:
  c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':900,'deviceScaleFactor':1,'mobile':False})
  c.call('Page.navigate',{'url':(root/f'.tmp/ocean-page-{page}.html').as_uri()});time.sleep(.4)
  c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()");time.sleep(.1)
  result=c.evaluate("[...document.querySelectorAll('.main-content :is(.scholar-hero,.page-heading,.teacher-attendance-hero,.match-page-heading)')].map(e=>({title:e.querySelector('h1,h2')?.textContent,image:getComputedStyle(e).backgroundImage,color:getComputedStyle(e.querySelector('h1,h2')||e).color}))")
  assert result,(page,'missing header')
  assert all('ocean-school-hero.png' in x['image'] for x in result),(page,result)
  assert all(x['color']=='rgb(247, 255, 255)' for x in result),(page,result)
  size=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert size['scroll']<=size['client'],(page,size)
  print('PASS Ocean banner and original title:',page,flush=True)
  if page in ['classes','profile']:
   (root/f'.tmp/ocean-banner-{page}.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':900,'deviceScaleFactor':1,'mobile':True});time.sleep(.3)
 size=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
 print(c.evaluate("JSON.stringify([...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>392&&!e.closest('table')).slice(0,12).map(e=>({c:e.className,r:e.getBoundingClientRect().right,w:e.getBoundingClientRect().width})))"))
 assert size['scroll']<=size['client'],size
 print('PASS mobile containment')
 c.call('Browser.close')
finally:p.terminate()
