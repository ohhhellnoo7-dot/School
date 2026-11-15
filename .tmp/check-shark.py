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
 assert c.evaluate("document.querySelectorAll('.ocean-shark').length")==1
 assert c.evaluate("document.querySelectorAll('.ocean-mermaid').length")==0
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-scene')).pointerEvents")=='none'
 assert c.evaluate("document.querySelector('.ocean-shark').getAnimations().length")>0
 start=c.evaluate("document.querySelector('.ocean-shark').getBoundingClientRect().toJSON()")
 time.sleep(.3)
 after=c.evaluate("document.querySelector('.ocean-shark').getBoundingClientRect().toJSON()")
 assert start['x']!=after['x'] or start['y']!=after['y']
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-floating')).animationName")=='shark-swim-body'
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-fin')).animationName")=='shark-fin'
 (root/'.tmp/shark-swimming.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 point=c.evaluate("(()=>{const svg=document.querySelector('.ocean-shark-rig');const p=svg.createSVGPoint();p.x=187;p.y=65;const q=p.matrixTransform(svg.getScreenCTM());return {x:q.x,y:q.y}})()")
 mx=point['x'];my=point['y']
 c.call('Input.dispatchMouseEvent',{'type':'mouseMoved','x':mx,'y':my})
 c.call('Input.dispatchMouseEvent',{'type':'mousePressed','x':mx,'y':my,'button':'left','clickCount':1})
 c.call('Input.dispatchMouseEvent',{'type':'mouseReleased','x':mx,'y':my,'button':'left','clickCount':1});time.sleep(.2)
 c.call('Input.dispatchMouseEvent',{'type':'mouseMoved','x':0,'y':0})

 assert c.evaluate("document.querySelector('.ocean-shark').classList.contains('is-greeting')")
 assert c.evaluate("document.querySelector('.ocean-shark-greeting').textContent.includes('Hello, Alex!')")
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-fin')).animationName")=='shark-wave'
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-happy-face')).opacity")=='1'
 assert c.evaluate("document.querySelector('.ocean-shark').getAnimations().length")==0
 (root/'.tmp/shark-greeting.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 time.sleep(3.5)
 assert not c.evaluate("document.querySelector('.ocean-shark').classList.contains('is-greeting')")
 assert c.evaluate("document.querySelector('.ocean-shark').getAnimations().length")>0
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()+'?shark-next=1'});time.sleep(.5)
 assert c.evaluate("JSON.parse(sessionStorage.getItem('scholar-ocean-shark')).routeIndex")>0
 for width in [1536,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.3)
  result=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,r:document.querySelector('.ocean-shark').getBoundingClientRect().toJSON()})")
  assert result['scroll']<=result['client'],result
  assert result['r']['left']>=0 and result['r']['right']<=width,result
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.2)
 assert c.evaluate("document.querySelector('.ocean-shark').getAnimations().length")==0
 assert c.evaluate("getComputedStyle(document.querySelector('.ocean-shark-tail')).animationName")=='none'
 c.evaluate("document.querySelector('.ocean-shark').click()");time.sleep(.1)
 assert c.evaluate("document.querySelector('.ocean-shark-greeting').textContent.includes('Hello')")
 c.evaluate("document.querySelector('[data-theme-choice=light]').click()");time.sleep(.1)
 assert c.evaluate("document.querySelectorAll('.ocean-shark-scene').length")==0
 print('PASS shark roaming, articulated movement, smile/wave greeting, resume, navigation, responsive bounds, reduced motion and cleanup')
 c.call('Browser.close')
finally:p.terminate()
