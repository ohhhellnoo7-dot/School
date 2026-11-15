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
 c.call('Page.navigate',{'url':(root/'.tmp/student-debug.html').as_uri()});time.sleep(2)
 c.evaluate("document.documentElement.dataset.theme='ocean'")
 for width in [1536,1280,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.4)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  baseline=c.evaluate("(()=>{const e=document.querySelector('#class-attendance');e.style.display='none';const w=document.documentElement.scrollWidth;e.style.removeProperty('display');return w})()")
  assert result['scroll']<=max(baseline,result['client']),(width,result,baseline)
  c.evaluate("document.querySelector('#class-attendance > .table-responsive').scrollLeft=500");time.sleep(.2)
  result=c.evaluate("({body:document.querySelector('#class-attendance > .table-responsive').scrollLeft,header:document.querySelector('.attendance-sticky-dates').scrollLeft})")
  assert result['body']==result['header'],result
  print('PASS viewport',width,'student table adds no page overflow; header synchronized')
 c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':900,'deviceScaleFactor':1,'mobile':False});time.sleep(.3)
 top=c.evaluate("parseFloat(getComputedStyle(document.querySelector('.attendance-sticky-dates')).top)")
 surface=c.evaluate("parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ocean-surface-y'))")
 assert top>=surface+16,(top,surface)
 c.evaluate("document.querySelector('#class-attendance').scrollIntoView()")
 time.sleep(.2)
 cells=c.evaluate("[...document.querySelectorAll('.student-monthly-grid tbody .attendance-cell')].map(e=>({bg:getComputedStyle(e).backgroundColor,color:getComputedStyle(e.querySelector('span')).color})).slice(0,40)")
 assert all(e['bg'] not in ['rgb(187, 247, 208)','rgb(254, 240, 138)','rgb(254, 202, 202)'] for e in cells),cells
 (root/'.tmp/student-ocean-fixed.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 print('PASS Ocean header stays below waves and attendance cells have no bright status fills')
 print('student marks' ,c.evaluate("({tables:document.querySelectorAll('[data-attendance-grid]').length,scanLabels:document.querySelectorAll('#class-attendance .attendance-scan-half').length})"))
 c.call('Browser.close')
finally:p.terminate()
