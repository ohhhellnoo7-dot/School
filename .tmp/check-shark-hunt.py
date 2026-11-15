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
 c.evaluate("document.querySelector('[data-theme-choice=ocean]').click()");time.sleep(.3)
 assert c.evaluate("document.querySelectorAll('.ocean-managed-creature').length")==4
 initial=c.evaluate("(()=>{const s=document.querySelector('.ocean-shark'),r=s.getBoundingClientRect();s.getAnimations().forEach(a=>a.cancel());s.style.transform='translate3d('+r.left+'px,'+r.top+'px,0)';const face=s.querySelector('.ocean-shark-facing'),t=getComputedStyle(face).transform;face.style.transition='none';face.style.transform=t;document.querySelectorAll('.ocean-creature').forEach(e=>e.dataset.oceanState='reserved');const f=document.querySelector('.ocean-creature-fish');f.dataset.oceanState='swimming';window.testPrey=f;f.getAnimations().forEach(a=>a.cancel());const svg=document.querySelector('.ocean-shark-rig'),p=svg.createSVGPoint();p.x=210;p.y=84;const m=p.matrixTransform(svg.getScreenCTM());f.style.transform='translate3d('+(m.x-f.offsetWidth/2)+'px,'+(m.y-f.offsetHeight/2)+'px,0)';return {edge:f.dataset.spawnEdge,width:f.offsetWidth}})()")
 time.sleep(.3)
 print('encounter',c.evaluate("({shark:document.querySelector('.ocean-shark').className,prey:window.testPrey.dataset.oceanState,rect:window.testPrey.getBoundingClientRect().toJSON()})"),flush=True)
 assert c.evaluate("document.querySelector('.ocean-shark').classList.contains('is-eating')")
 assert c.evaluate("window.testPrey.dataset.oceanState")=='eating'
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-chomp-mouth')).animationName")=='shark-chomp'
 assert c.evaluate("window.testPrey.getBoundingClientRect().width")<initial['width']
 (root/'.tmp/shark-chomp.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 time.sleep(.6)
 assert not c.evaluate("window.testPrey.isConnected")
 assert c.evaluate("document.querySelectorAll('.ocean-creature').length")==3
 time.sleep(3)
 assert c.evaluate("document.querySelectorAll('.ocean-creature').length")==4
 newedge=c.evaluate("document.querySelector('.ocean-creature-fish').dataset.spawnEdge")
 assert newedge!=initial['edge'],(initial,newedge)
 assert c.evaluate("document.querySelector('.ocean-creature-fish')!==window.testPrey")
 assert c.evaluate("Number(document.querySelector('.ocean-shark').dataset.snacks)")>=1
 c.evaluate("document.querySelector('.ocean-shark').click()");time.sleep(.1)
 assert c.evaluate("document.querySelector('.ocean-shark-greeting').textContent.includes('Hello')")
 c.call('Emulation.setDeviceMetricsOverride',{'width':390,'height':900,'deviceScaleFactor':1,'mobile':True});time.sleep(.4)
 size=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
 assert size['scroll']<=size['client'],size
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.2)
 assert c.evaluate("[...document.querySelectorAll('.ocean-creature')].every(e=>e.getAnimations().every(a=>a.playState==='paused'))")
 c.evaluate("document.querySelector('[data-theme-choice=light]').click()");time.sleep(3)
 assert c.evaluate("document.querySelectorAll('.ocean-shark-scene,.ocean-life-scene,.ocean-creature').length")==0
 print('PASS real proximity bite, chomp, swallowed animal removal, fresh replacement from another edge, greeting, mobile bounds, reduced motion and cleanup')
 c.call('Browser.close')
finally:p.terminate()
