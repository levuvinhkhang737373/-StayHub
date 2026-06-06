const http = require('http');

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
        options.headers['Content-Type'] = 'application/json';
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
  console.log('--- STARTING FEEDBACK SUBMISSION TEST ---');
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

    // 2. Log in as tenant_an
    console.log('2. Logging in as tenant...');
    const loginHeaders = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Cookie': cookieStr
    };
    if (xsrfToken) {
      loginHeaders['X-XSRF-TOKEN'] = xsrfToken;
    }
    
    const loginRes = await makeRequest(
      'http://localhost:8000/api/tenant/login',
      'POST',
      loginHeaders,
      JSON.stringify({
        username: 'tenant_an',
        password: '12345678'
      })
    );

    console.log(`Login Response Code: ${loginRes.statusCode}`);
    console.log(`Login Response: ${loginRes.body}`);

    const loginCookies = loginRes.headers['set-cookie'] || [];
    if (loginCookies.length > 0) {
      cookieStr = loginCookies.map(c => c.split(';')[0]).join('; ');
    }

    // Find XSRF token in response cookies
    loginCookies.forEach(c => {
      if (c.startsWith('XSRF-TOKEN=')) {
        xsrfToken = decodeURIComponent(c.split(';')[0].split('=')[1]);
      }
    });

    // 3. Post Feedback for Request ID 84
    console.log('3. Posting feedback...');
    const feedbackHeaders = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Cookie': cookieStr
    };
    if (xsrfToken) {
      feedbackHeaders['X-XSRF-TOKEN'] = xsrfToken;
    }

    const feedbackRes = await makeRequest(
      'http://localhost:8000/api/tenant/maintenance-requests/84/feedback',
      'POST',
      feedbackHeaders,
      JSON.stringify({
        rating: 4,
        comment: 'Dịch vụ tạm ổn!'
      })
    );

    console.log(`Feedback Response Code: ${feedbackRes.statusCode}`);
    console.log(`Feedback Response: ${feedbackRes.body}`);

  } catch (err) {
    console.error('Error in script:', err);
  }
}

run();
