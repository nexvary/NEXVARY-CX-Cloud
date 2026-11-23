/**
 * NEXVARY CX Cloud — WebRTC Bridge
 * Provides WebSocket-based SIP/WebRTC signaling between browser and FreeSWITCH.
 * Uses SIP over WebSocket (RFC 7118) + ICE/STUN/TURN configuration.
 */

'use strict';

const WebSocket = require('ws');
const http = require('http');
const https = require('https');
const fs = require('fs');
const crypto = require('crypto');

// ── Configuration ─────────────────────────────────────────────────────────

const config = {
  port: parseInt(process.env.RTC_BRIDGE_PORT ?? '3000', 10),
  token: process.env.RTC_BRIDGE_TOKEN ?? '',
  freeswitch: {
    host: process.env.FREESWITCH_HOST ?? '127.0.0.1',
    wsPort: parseInt(process.env.FREESWITCH_WS_PORT ?? '5066', 10),
    tlsPort: parseInt(process.env.FREESWITCH_WSS_PORT ?? '7443', 10),
  },
  stun: process.env.STUN_SERVER ?? 'stun:stun.l.google.com:19302',
  turn: {
    server: process.env.TURN_SERVER ?? null,
    user: process.env.TURN_USER ?? null,
    credential: process.env.TURN_CREDENTIAL ?? null,
  },
  tls: {
    enabled: process.env.RTC_TLS === 'true',
    cert: process.env.RTC_TLS_CERT ?? '/etc/nexvary-cx/rtc/cert.pem',
    key: process.env.RTC_TLS_KEY ?? '/etc/nexvary-cx/rtc/key.pem',
  },
};

// ── Logging ───────────────────────────────────────────────────────────────

const log = (level, msg, meta) => {
  const entry = JSON.stringify({
    ts: new Date().toISOString(),
    level,
    msg,
    ...(meta ?? {}),
  });
  if (level === 'error') {
    process.stderr.write(entry + '\n');
  } else {
    process.stdout.write(entry + '\n');
  }
};

// ── HTTP Server (health endpoint) ─────────────────────────────────────────

const handleHttp = (req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status: 'ok',
      service: 'nexvary-rtc-bridge',
      clients: wss?.clients?.size ?? 0,
      uptime: process.uptime(),
    }));
    return;
  }
  if (req.url === '/ice-config') {
    // ICE server configuration for browsers
    const iceServers = [{ urls: config.stun }];
    if (config.turn.server) {
      iceServers.push({
        urls: config.turn.server,
        username: config.turn.user,
        credential: config.turn.credential,
      });
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ iceServers }));
    return;
  }
  res.writeHead(404);
  res.end();
};

let server;
if (config.tls.enabled && fs.existsSync(config.tls.cert)) {
  server = https.createServer({
    cert: fs.readFileSync(config.tls.cert),
    key: fs.readFileSync(config.tls.key),
  }, handleHttp);
} else {
  server = http.createServer(handleHttp);
}

// ── WebSocket Server ──────────────────────────────────────────────────────

const wss = new WebSocket.Server({ server, path: '/ws' });

// Connected sessions: Map<clientId, { browser: WebSocket, freeswitch: WebSocket | null, auth: boolean }>
const sessions = new Map();

const authenticateToken = (token) => {
  // Fail closed: if no token is configured, reject all connections.
  // An empty RTC_BRIDGE_TOKEN is a misconfiguration, not "open access".
  if (!config.token) {
    log('error', 'RTC_BRIDGE_TOKEN is not set — all connections rejected');
    return false;
  }
  const provided = Buffer.from(token ?? '', 'utf8');
  const expected = Buffer.from(config.token, 'utf8');
  // timingSafeEqual requires equal-length buffers; mismatched length is instant reject.
  if (provided.length !== expected.length) return false;
  return crypto.timingSafeEqual(provided, expected);
};

wss.on('connection', (browserWs, req) => {
  const clientId = crypto.randomUUID();
  const clientIp = req.headers['x-forwarded-for']?.split(',')[0].trim() ?? req.socket.remoteAddress;

  log('info', 'WebRTC client connected', { clientId, ip: clientIp });

  sessions.set(clientId, {
    browser: browserWs,
    freeswitch: null,
    authenticated: false,
    extension: null,
    callId: null,
  });

  // ── Message handler ───────────────────────────────────────────────────

  browserWs.on('message', (raw) => {
    let msg;
    try {
      msg = JSON.parse(raw);
    } catch {
      browserWs.send(JSON.stringify({ type: 'error', message: 'invalid_json' }));
      return;
    }

    const session = sessions.get(clientId);
    if (!session) return;

    switch (msg.type) {

      // Browser authenticates with app-issued token
      case 'auth': {
        if (authenticateToken(msg.token)) {
          session.authenticated = true;
          session.extension = msg.extension;
          browserWs.send(JSON.stringify({
            type: 'auth_ok',
            iceServers: buildIceServers(),
          }));
          log('info', 'WebRTC auth ok', { clientId, extension: msg.extension });
        } else {
          browserWs.send(JSON.stringify({ type: 'auth_fail' }));
          browserWs.close(4001, 'Authentication failed');
        }
        break;
      }

      // Browser initiates call — open WS tunnel to FreeSWITCH
      case 'register':
      case 'invite':
      case 'sip': {
        if (!session.authenticated) {
          browserWs.send(JSON.stringify({ type: 'error', message: 'not_authenticated' }));
          return;
        }
        // Proxy SIP message to FreeSWITCH via WebSocket
        ensureFreeSwitchConnection(clientId, session, (fsWs) => {
          if (fsWs.readyState === WebSocket.OPEN) {
            fsWs.send(JSON.stringify(msg));
          }
        });
        break;
      }

      // ICE candidate from browser
      case 'ice_candidate': {
        if (session.freeswitch?.readyState === WebSocket.OPEN) {
          session.freeswitch.send(JSON.stringify(msg));
        }
        break;
      }

      // Browser-initiated hang up
      case 'hangup': {
        if (session.freeswitch?.readyState === WebSocket.OPEN) {
          session.freeswitch.send(JSON.stringify({ type: 'hangup', callId: session.callId }));
        }
        break;
      }

      default:
        log('warn', 'Unknown message type', { clientId, type: msg.type });
    }
  });

  browserWs.on('close', (code, reason) => {
    const session = sessions.get(clientId);
    if (session?.freeswitch) {
      session.freeswitch.close();
    }
    sessions.delete(clientId);
    log('info', 'WebRTC client disconnected', { clientId, code });
  });

  browserWs.on('error', (err) => {
    log('error', 'Browser WebSocket error', { clientId, error: err.message });
  });
});

// ── FreeSWITCH WebSocket Tunnel ───────────────────────────────────────────

function ensureFreeSwitchConnection(clientId, session, callback) {
  if (session.freeswitch?.readyState === WebSocket.OPEN) {
    callback(session.freeswitch);
    return;
  }

  const fsUrl = `ws://${config.freeswitch.host}:${config.freeswitch.wsPort}`;
  log('info', 'Opening FreeSWITCH tunnel', { clientId, url: fsUrl });

  const fsWs = new WebSocket(fsUrl, ['sip']);
  session.freeswitch = fsWs;

  fsWs.on('open', () => {
    log('info', 'FreeSWITCH tunnel open', { clientId });
    callback(fsWs);
  });

  fsWs.on('message', (data) => {
    // Relay FreeSWITCH messages back to browser
    if (session.browser?.readyState === WebSocket.OPEN) {
      session.browser.send(data);
    }
  });

  fsWs.on('close', (code) => {
    log('info', 'FreeSWITCH tunnel closed', { clientId, code });
    session.freeswitch = null;
    if (session.browser?.readyState === WebSocket.OPEN) {
      session.browser.send(JSON.stringify({ type: 'pbx_disconnected', code }));
    }
  });

  fsWs.on('error', (err) => {
    log('error', 'FreeSWITCH tunnel error', { clientId, error: err.message });
    if (session.browser?.readyState === WebSocket.OPEN) {
      session.browser.send(JSON.stringify({ type: 'pbx_error', message: err.message }));
    }
  });
}

function buildIceServers() {
  const servers = [{ urls: config.stun }];
  if (config.turn.server) {
    servers.push({
      urls: config.turn.server,
      username: config.turn.user,
      credential: config.turn.credential,
    });
  }
  return servers;
}

// ── Start Server ──────────────────────────────────────────────────────────

// In a Docker container the bind address must be 0.0.0.0 so the container's
// published port is reachable from the host/nginx proxy.  Defaults to 127.0.0.1
// for standalone (non-containerised) deployments.
const bindAddress = process.env.RTC_BIND_ADDRESS ?? '127.0.0.1';
server.listen(config.port, bindAddress, () => {
  log('info', 'NEXVARY WebRTC Bridge started', {
    port: config.port,
    bind: bindAddress,
    tls: config.tls.enabled,
    freeswitch: `${config.freeswitch.host}:${config.freeswitch.wsPort}`,
  });
});

// ── Graceful Shutdown ─────────────────────────────────────────────────────

const shutdown = (signal) => {
  log('info', `Shutting down on ${signal}`);
  wss.clients.forEach(ws => ws.close(1001, 'Server shutting down'));
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 5000);
};

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));
