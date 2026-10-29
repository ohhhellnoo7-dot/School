"""Local Edge smoke check; screenshots and browser profile stay in storage/app/qa."""
import base64
import json
import os
from pathlib import Path
import socket
import struct
import subprocess
import time
import urllib.request
from urllib.parse import urlparse


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/qa'
OUT.mkdir(parents=True, exist_ok=True)


class DevTools:
    def __init__(self, url):
        target = urlparse(url)
        self.sock = socket.create_connection((target.hostname, target.port), timeout=10)
        key = base64.b64encode(os.urandom(16)).decode()
        self.sock.sendall((f'GET {target.path} HTTP/1.1\r\nHost: {target.netloc}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n').encode())
        response = b''
        while not response.endswith(b'\r\n\r\n'):
            chunk = self.sock.recv(1)
            if not chunk:
                raise RuntimeError('Browser closed during WebSocket handshake')
            response += chunk
        if b' 101 ' not in response.split(b'\r\n')[0]:
            raise RuntimeError('Browser WebSocket handshake failed')
        self.sequence = 0
        self.errors = []

    def read(self, size):
        result = b''
        while len(result) < size:
            chunk = self.sock.recv(size - len(result))
            if not chunk:
                raise RuntimeError('Browser connection closed')
            result += chunk
        return result

    def call(self, method, params=None):
        self.sequence += 1
        payload = json.dumps({'id': self.sequence, 'method': method, 'params': params or {}}).encode()
        mask = os.urandom(4)
        header = bytes([129, 128 | len(payload)]) if len(payload) < 126 else bytes([129, 254]) + struct.pack('!H', len(payload))
        self.sock.sendall(header + mask + bytes(value ^ mask[i % 4] for i, value in enumerate(payload)))
        while True:
            first, second = self.read(2)
            size = second & 127
            if size == 126:
                size = struct.unpack('!H', self.read(2))[0]
            elif size == 127:
                size = struct.unpack('!Q', self.read(8))[0]
            data = self.read(size)
            if first & 15 != 1:
                continue
            message = json.loads(data)
            if message.get('method') == 'Runtime.exceptionThrown':
                self.errors.append(message['params']['exceptionDetails'].get('text', 'JavaScript error'))
            if message.get('id') == self.sequence:
                if 'error' in message:
                    raise RuntimeError(message['error'])
                return message.get('result', {})

    def evaluate(self, expression):
        result = self.call('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
        if 'exceptionDetails' in result:
            raise RuntimeError(result['exceptionDetails'])
        return result.get('result', {}).get('value')

    def wait_for(self, expression):
        for _ in range(100):
            if self.evaluate(expression):
                return
            time.sleep(.1)
        raise RuntimeError('Page did not reach expected state')


edge = subprocess.Popen([
    r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--remote-debugging-port=9229', '--remote-allow-origins=*',
    '--user-data-dir=' + str(OUT / 'edge-profile'), 'about:blank',
], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW)
browser = None
try:
    for _ in range(100):
        try:
            with urllib.request.urlopen('http://127.0.0.1:9229/json', timeout=1) as response:
                pages = json.load(response)
            page = next(page for page in pages if page['type'] == 'page')
            break
        except (OSError, StopIteration):
            time.sleep(.1)
    else:
        raise RuntimeError('Edge did not start')
    browser = DevTools(page['webSocketDebuggerUrl'])
    print('Connected to Edge', flush=True)
    browser.call('Runtime.enable')
    browser.call('Page.enable')
    browser.call('Emulation.setDeviceMetricsOverride', {'width': 1440, 'height': 1100, 'deviceScaleFactor': 1, 'mobile': False})
    browser.call('Page.navigate', {'url': 'http://127.0.0.1:8000/login'})
    print('Opened login', flush=True)
    browser.wait_for("!!document.getElementById('email')")
    browser.evaluate("document.getElementById('email').value='admin@school.test'; document.getElementById('password').value='School@2026!'; document.querySelector('form').requestSubmit(); true")
    browser.wait_for("location.pathname==='/dashboard' && document.readyState==='complete' && typeof Chart !== 'undefined'")
    print('Authenticated dashboard loaded', flush=True)
    time.sleep(1)
    summary = browser.evaluate("({title:document.title, charts:Object.keys(Chart.instances).length, width:innerWidth, contentWidth:document.documentElement.scrollWidth, cards:document.querySelectorAll('.stat-card').length})")
    assert summary['charts'] == 4, summary
    assert summary['contentWidth'] <= summary['width'], summary
    (OUT / 'dashboard-desktop.png').write_bytes(base64.b64decode(browser.call('Page.captureScreenshot', {'format': 'png', 'captureBeyondViewport': True})['data']))
    browser.call('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 1, 'mobile': True})
    time.sleep(.6)
    mobile = browser.evaluate("({width:innerWidth, contentWidth:document.documentElement.scrollWidth})")
    assert mobile['contentWidth'] <= mobile['width'], mobile
    browser.evaluate("document.querySelector('[data-bs-target=\"#sidebar\"]').click(); true")
    time.sleep(.4)
    assert browser.evaluate("document.getElementById('sidebar').classList.contains('show')"), 'Mobile navigation failed'
    browser.evaluate("bootstrap.Offcanvas.getInstance(document.getElementById('sidebar')).hide(); true")
    time.sleep(.4)
    (OUT / 'dashboard-mobile.png').write_bytes(base64.b64decode(browser.call('Page.captureScreenshot', {'format': 'png', 'captureBeyondViewport': True})['data']))
    assert not browser.errors, browser.errors
    print(json.dumps({'desktop': summary, 'mobile': mobile, 'javascriptErrors': browser.errors, 'screenshots': str(OUT)}))
finally:
    if browser:
        browser.sock.close()
    edge.terminate()
    try:
        edge.wait(timeout=5)
    except subprocess.TimeoutExpired:
        edge.kill()
