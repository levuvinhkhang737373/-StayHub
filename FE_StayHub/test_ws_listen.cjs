const http = require('http');
const WebSocket = require('ws');

async function makeRequest(url, method, headers = {}, body = null) {
  return new Promise((resolve, reject) => {
    const urlObj = new URL(url);
    const options = {
      hostname: urlObj.hostname,
      port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
      path: urlObj.pathname + urlObj.search,
      method: method,
      headers: {
        ...headers,
        'Host': urlObj.host
      }
    };

    if (body) {
      if (typeof body === 'string') {
        options.headers['Content-Length'] = Buffer.byteLength(body);
      } else {
        const bodyStr = new URLSearchParams(body).toString();
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        options.headers['Content-Length'] = Buffer.byteLength(bodyStr);
        body = bodyStr;
      }
    }

    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => {
        data += chunk;
      });
      res.on('end', () => {
        resolve({
          statusCode: res.statusCode,
          headers: res.headers,
          body: data
        });
      });
    });

    req.on('error', (e) => {
      reject(e);
    });

    if (body) {
      req.write(body);
    }
    req.end();
  });
}

async function run() {
  console.log('--- STARTING WS LISTENER TEST ---');
  try {
    // 1. Get CSRF Cookie
    console.log('1. Fetching CSRF cookie...');
    const csrfRes = await makeRequest('http://localhost:8000/sanctum/csrf-cookie', 'GET');
    const cookiesHeader = csrfRes.headers['set-cookie'] || [];
    let cookieStr = cookiesHeader.map(c => c.split(';')[0]).join('; ');
    
    let xsrfToken = '';
    cookiesHeader.forEach(c => {
      if (c.startsWith('XSRF-TOKEN=')) {
        xsrfToken = decodeURIComponent(c.split(';')[0].split('=')[1]);
      }
    });

    // 2. Log in as admin
    console.log('2. Logging in as admin...');
    const loginHeaders = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Cookie': cookieStr
    };
    if (xsrfToken) {
      loginHeaders['X-XSRF-TOKEN'] = xsrfToken;
    }
    
    const loginRes = await makeRequest(
      'http://localhost:8000/api/admin/login',
      'POST',
      loginHeaders,
      JSON.stringify({
        username: 'admin',
        password: '12345678'
      })
    );

    const loginCookies = loginRes.headers['set-cookie'] || [];
    if (loginCookies.length > 0) {
      cookieStr = loginCookies.map(c => c.split(';')[0]).join('; ');
    }

    // 3. Connect to WebSocket
    console.log('3. Connecting to Reverb WebSocket...');
    const ws = new WebSocket('ws://localhost:8009/app/rhtxfafogu4wbww3eufp?protocol=7&client=js&version=7.0.6&flash=false');

    ws.on('open', () => {
      console.log('WS Connection Opened.');
    });

    ws.on('message', async (data) => {
      const msg = JSON.parse(data.toString());
      console.log('WS Received:', JSON.stringify(msg, null, 2));

      // If we receive pusher:connection_established, we subscribe
      if (msg.event === 'pusher:connection_established') {
        const socketId = JSON.parse(msg.data).socket_id;
        console.log(`Socket ID: ${socketId}`);

        console.log('4. Requesting auth for private-admin-maintenance...');
        const authRes = await makeRequest(
          'http://localhost:8000/broadcasting/auth',
          'POST',
          {
            'Accept': 'application/json',
            'Cookie': cookieStr
          },
          {
            socket_id: socketId,
            channel_name: 'admin-maintenance'
          }
        );

        console.log(`Auth Response (${authRes.statusCode}): ${authRes.body}`);
        const authData = JSON.parse(authRes.body);

        console.log('5. Sending subscribe frame...');
        ws.send(JSON.stringify({
          event: 'pusher:subscribe',
          data: {
            channel: 'admin-maintenance',
            auth: authData.auth
          }
        }));
      }
    });

    ws.on('error', (err) => {
      console.error('WS Error:', err);
    });

    ws.on('close', () => {
      console.log('WS Connection Closed.');
    });

  } catch (err) {
    console.error('Error in script:', err);
  }
}

run();
