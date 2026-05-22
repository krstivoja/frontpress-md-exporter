const cfg = (typeof window !== 'undefined' && window.fpsExporter) || {
  restRoot: '/wp-json/fps-mdexp/v1/',
  nonce: '',
};

async function request(path, { method = 'GET', body } = {}) {
  const res = await fetch(cfg.restRoot + path.replace(/^\//, ''), {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': cfg.nonce,
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    let payload = null;
    try { payload = await res.json(); } catch (e) {}
    const message = (payload && payload.message) || `${res.status} ${res.statusText}`;
    throw new Error(message);
  }
  return res.json();
}

export const api = {
  inventory: () => request('inventory'),
  getSettings: () => request('settings'),
  saveSettings: (data) => request('settings', { method: 'POST', body: data }),
  startExport: () => request('export/start', { method: 'POST' }),
  tickExport: (runId, batch = 20) =>
    request(`export/tick?run_id=${encodeURIComponent(runId)}&batch=${batch}`, { method: 'POST' }),
  finalizeExport: (runId) =>
    request(`export/finalize?run_id=${encodeURIComponent(runId)}`, { method: 'POST' }),
  downloadUrl: (runId, token) =>
    `${cfg.restRoot}export/download?run_id=${encodeURIComponent(runId)}&token=${encodeURIComponent(token)}&_wpnonce=${encodeURIComponent(cfg.nonce)}`,
};
