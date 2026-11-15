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
 c.evaluate("document.documentElement.dataset.theme='ocean'")
 colors=c.evaluate("[...document.querySelectorAll('.teacher-monthly-grid tbody th:nth-child(2)')].map(e=>getComputedStyle(e).backgroundColor)")
 assert colors and all(color in ['rgb(3, 69, 101)','rgb(7, 81, 116)'] for color in colors),colors
 print('PASS opaque Ocean frozen name cells',flush=True)
 for width in [1536,1280,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.4)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert result['scroll']<=result['client'],(width,result)
  c.evaluate("document.querySelector('.teacher-attendance-panel .table-responsive').scrollLeft=500");time.sleep(.2)
  result=c.evaluate("({body:document.querySelector('.teacher-attendance-panel .table-responsive').scrollLeft,header:document.querySelector('.attendance-sticky-dates').scrollLeft})")
  assert result['body']==result['header'],result
  print('PASS viewport',width,'no page overflow; header horizontal scroll synchronized')
 c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':900,'deviceScaleFactor':1,'mobile':False})
 c.evaluate("document.querySelector('.teacher-attendance-panel').scrollIntoView();document.querySelector('.teacher-attendance-panel .table-responsive').scrollLeft=500")
 time.sleep(.3)
 (root/'.tmp/monthly-ocean-fixed.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.call('Browser.close')
finally:p.terminate()
