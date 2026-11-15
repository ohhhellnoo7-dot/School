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
 c.call('Page.enable');c.call('Emulation.setDeviceMetricsOverride',{'width':1536,'height':1000,'deviceScaleFactor':1,'mobile':False})
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()});time.sleep(1.5)
 c.evaluate("document.querySelector('[data-theme-choice=autumn]').click()")
 time.sleep(1)
 assert c.evaluate("document.documentElement.dataset.theme")=='autumn'
 assert c.evaluate("document.querySelectorAll('.autumn-shed-leaf').length")==8
 assert c.evaluate("document.querySelectorAll('.autumn-garden').length")==1
 assert c.evaluate("document.querySelectorAll('.autumn-person').length")==2
 assert c.evaluate("document.querySelectorAll('.autumn-air-particle').length")==20
 assert c.evaluate("getComputedStyle(document.querySelector('.autumn-wind-field')).pointerEvents")=='none'
 assert 'autumn-school-hero.png' in c.evaluate("getComputedStyle(document.querySelector('.school-hero')).backgroundImage")
 c.evaluate("window.scrollTo(0,500)")
 time.sleep(.1)
 assert c.evaluate("document.querySelector('.autumn-garden').getBoundingClientRect().left")==0
 assert c.evaluate("getComputedStyle(document.querySelector('.autumn-garden')).position")=='fixed'
 c.evaluate("window.scrollTo(0,0)")
 print('theme colors',c.evaluate("({sidebar:getComputedStyle(document.querySelector('.sidebar')).backgroundImage,topbar:getComputedStyle(document.querySelector('.topbar')).backgroundImage,stat:getComputedStyle(document.querySelector('.stat-card')).backgroundImage})"),flush=True)
 assert 'autumn-forest-ground.png' in c.evaluate("getComputedStyle(document.querySelector('.autumn-land .autumn-ground')).backgroundImage")
 assert c.evaluate("Number(getComputedStyle(document.querySelector('.autumn-trees')).zIndex)")==2
 assert c.evaluate("getComputedStyle(document.querySelector('.autumn-trees')).maskImage")=='none'
 gust=c.evaluate("(()=>{const e=document.querySelector('.autumn-tree-crown'),a=e.getAnimations()[0];a.pause();const samples=[0,2500,7000,10000].map(t=>{a.currentTime=t;return getComputedStyle(e).transform});a.play();return samples})()")
 assert gust[0]!=gust[1] and gust[0]==gust[2]==gust[3],gust
 leaf=c.evaluate("(()=>{const e=document.querySelector('.autumn-shed-leaf'),a=e.getAnimations().find(a=>a.animationName==='autumn-branch-fall');a.pause();const samples=[500,2500,11500].map(t=>{a.currentTime=t;return Number(getComputedStyle(e).opacity)});a.play();return samples})()")
 assert leaf[0]==0 and leaf[1]>.5 and leaf[2]==0,leaf
 names=c.evaluate("document.getAnimations().map(a=>a.animationName).filter(n=>n?.startsWith('autumn-'))")
 assert all(name in names for name in ['autumn-airflow','autumn-ground-tumble','autumn-branch-fall','autumn-leaf-flutter','autumn-tree-gust','autumn-breathe','autumn-snuggle','autumn-hair','autumn-offer','autumn-eat-right','autumn-chew','autumn-chew-right','autumn-blink','autumn-hearts','autumn-steam']),names
 assert c.evaluate("getComputedStyle(document.querySelector('.autumn-garden')).pointerEvents")=='none'
 (root/'.tmp/autumn-desktop.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 c.evaluate("document.querySelector('.school-hero').outerHTML=document.querySelector('.school-hero').outerHTML")
 time.sleep(.2)
 assert c.evaluate("document.querySelectorAll('.autumn-garden').length")==1
 for width in [1536,768,390]:
  c.call('Emulation.setDeviceMetricsOverride',{'width':width,'height':900,'deviceScaleFactor':1,'mobile':width<600});time.sleep(.3)
  size=c.evaluate("({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})")
  assert size['scroll']<=size['client'],(width,size)
 (root/'.tmp/autumn-mobile.png').write_bytes(base64.b64decode(c.call('Page.captureScreenshot',{'format':'png'})['data']))
 origin=c.evaluate("sessionStorage.getItem('scholar-autumn-origin')")
 c.call('Page.navigate',{'url':(root/'.tmp/ocean-dashboard-debug.html').as_uri()+'?autumn-next=1'});time.sleep(.6)
 assert c.evaluate("document.documentElement.dataset.theme")=='autumn'
 assert c.evaluate("sessionStorage.getItem('scholar-autumn-origin')")==origin
 c.call('Emulation.setEmulatedMedia',{'features':[{'name':'prefers-reduced-motion','value':'reduce'}]});time.sleep(.2)
 assert c.evaluate("getComputedStyle(document.querySelector('.autumn-shed-leaf')).display")=='none'
 assert c.evaluate("document.querySelector('.autumn-person').getAnimations().length")==0
 c.evaluate("document.querySelector('[data-theme-choice=light]').click()")
 time.sleep(.2)
 assert c.evaluate("document.querySelectorAll('.autumn-trees,.autumn-garden,.autumn-wind-field,.autumn-land').length")==0
 print('PASS Autumn selection, animated leaves/wind/trees/picnic, refreshed hero, desktop/tablet/mobile containment, persistence, reduced motion and cleanup',flush=True)
 c.call('Browser.close')
finally:p.terminate()
