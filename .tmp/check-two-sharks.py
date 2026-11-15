import base64,json,subprocess,time,urllib.request
from pathlib import Path
root=Path.cwd()
ns={'__file__':str(root/'scripts/browser-check.py')}
exec((root/'scripts/browser-check.py').read_text().split('edge = subprocess.Popen')[0],ns)
p=subprocess.Popen([r'C:/Program Files/Google/Chrome/Application/chrome.exe','--headless=new','--disable-gpu','--no-first-run','--allow-file-access-from-files','--remote-debugging-port=9263','--remote-allow-origins=*','--user-data-dir='+str(root/'.tmp/two-sharks-isolated'),'about:blank'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=subprocess.CREATE_NO_WINDOW)
try:
 for _ in range(60):
  try:
   pages=json.load(urllib.request.urlopen('http://127.0.0.1:9263/json'));break
  except:time.sleep(.1)
 c=ns['DevTools'](next(x['webSocketDebuggerUrl'] for x in pages if x['type']=='page'))
 original_call=c.call
 def traced(method,params=None):
  print(method,flush=True)
  return original_call(method,params)
 c.call=traced
 c.call('Page.enable');c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':900,'deviceScaleFactor':1,'mobile':False})
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()});time.sleep(2)
 c.evaluate("document.documentElement.dataset.theme='khmer-heritage'")
 c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()");time.sleep(.5)
 assert c.evaluate("document.querySelectorAll('.ocean-shark').length")==2
 assert c.evaluate("document.querySelector('[data-shark-kind=normal]').dataset.flightPhase")=='water'
 assert c.evaluate("document.querySelector('[data-shark-kind=robot]').dataset.flightPhase")=='sky'
 assert c.evaluate("document.querySelector('#robot-shark-metal')!==null")
 c.evaluate("window.robotSplashes=0;document.addEventListener('ocean:shark-splash',()=>window.robotSplashes++)")
 time.sleep(12)
 assert c.evaluate("document.querySelector('[data-shark-kind=robot]').dataset.flightPhase")=='water'
 assert c.evaluate("document.querySelector('[data-shark-kind=normal]').dataset.flightPhase")=='water'
 assert c.evaluate("window.robotSplashes")>=1
 assert c.evaluate("document.querySelector('.ocean-fishing-boat').classList.contains('is-panicking')")
 (root/'.tmp/two-sharks.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 time.sleep(5)
 assert not c.evaluate("document.querySelector('.ocean-fishing-boat').classList.contains('is-panicking')")
 c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':900,'deviceScaleFactor':1,'mobile':True});time.sleep(.4)
 assert c.evaluate("document.documentElement.scrollWidth<=document.documentElement.clientWidth")
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.3)
 assert c.evaluate("[...document.querySelectorAll('.ocean-shark')].every(s=>s.dataset.flightPhase==='water'&&s.getAnimations().length===0)")
 c.evaluate("document.documentElement.dataset.theme='light'");time.sleep(.3)
 assert c.evaluate("document.querySelectorAll('.ocean-shark-scene').length")==0
 c.evaluate("document.documentElement.dataset.theme='ocean'");time.sleep(.3)
 assert c.evaluate("document.querySelectorAll('.ocean-shark').length")==2
 print('PASS two independent sharks, robot artwork, sky/dive cycle, fisherman escape and return, mobile overflow, reduced motion, theme cleanup and restart')
 c.call('Browser.close')
finally:p.terminate()


