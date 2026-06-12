const http = require('http');

const PORT = 8082;
const TARGET_HOST = '127.0.0.1';
const TARGET_PORT = 80;

const server = http.createServer((req, res) => {
  // Log the incoming request
  console.log(`[PROXY] ${req.method} ${req.url}`);

  // Set CORS headers to support mobile app requests
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  // Create options for forwarding
  const options = {
    hostname: TARGET_HOST,
    port: TARGET_PORT,
    path: req.url,
    method: req.method,
    headers: req.headers
  };

  // Forward request to Apache
  const proxyReq = http.request(options, (proxyRes) => {
    console.log(`[PROXY RESPONSE] ${req.method} ${req.url} -> ${proxyRes.statusCode}`);
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on('error', (err) => {
    console.error(`[PROXY ERROR] Failed to connect to Apache: ${err.message}`);
    res.writeHead(502, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ 
      status: 'error', 
      message: 'Proxy failed to connect to backend Apache server', 
      error: err.message 
    }));
  });

  req.pipe(proxyReq, { end: true });
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[PROXY] Node.js firewall-bridge listening on http://0.0.0.0:${PORT} -> forwarding to http://${TARGET_HOST}:${TARGET_PORT}`);
});
