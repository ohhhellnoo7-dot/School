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
 c.evaluate("document.querySelector('[data-theme-choice=light]').click();sessionStorage.removeItem('scholar-ocean-shark')");time.sleep(.1)
 c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()")
 eaten=0
 for attempt in range(65):
  time.sleep(.5)
  eaten=c.evaluate("Number(document.querySelector('.ocean-shark')?.dataset.snacks||0)")
  if eaten:break
 assert eaten>0,c.evaluate("({shark:document.querySelector('.ocean-shark').className,animals:[...document.querySelectorAll('.ocean-creature')].map(e=>({type:e.dataset.animal,state:e.dataset.oceanState,rect:e.getBoundingClientRect().toJSON()}))})")
 print('PASS autonomous shark chased and ate an animal after',round((attempt+1)*.5,1),'seconds')
 c.call('Browser.close')
finally:p.terminate()
