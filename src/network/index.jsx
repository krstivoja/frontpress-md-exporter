import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import '../admin/styles.css';

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

function App() {
  const [sites, setSites] = useState(null);
  const [selected, setSelected] = useState({});
  const [phase, setPhase] = useState('idle');
  const [progress, setProgress] = useState({ processed: 0, total: 0 });
  const [download, setDownload] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    request('network/sites')
      .then((data) => {
        setSites(data.sites);
        const pre = {};
        data.sites.forEach((s) => { pre[s.id] = true; });
        setSelected(pre);
      })
      .catch((e) => setError(e.message));
  }, []);

  function toggle(id) {
    setSelected((prev) => ({ ...prev, [id]: !prev[id] }));
  }
  function setAll(value) {
    if (!sites) return;
    const next = {};
    sites.forEach((s) => { next[s.id] = value; });
    setSelected(next);
  }

  async function start() {
    setError(null);
    setDownload(null);
    setPhase('running');
    const ids = Object.entries(selected).filter(([, v]) => v).map(([k]) => Number(k));
    if (ids.length === 0) {
      setError('Pick at least one subsite.');
      setPhase('error');
      return;
    }
    try {
      const begin = await request('network/start', { method: 'POST', body: { site_ids: ids } });
      if (begin.error) throw new Error(begin.error);
      setProgress({ processed: 0, total: begin.total });

      const runId = begin.run_id;
      let done = false;
      while (!done) {
        const r = await request(
          `network/tick?run_id=${encodeURIComponent(runId)}&batch=20`,
          { method: 'POST' }
        );
        done = !!r.done;
        setProgress({ processed: r.processed, total: r.total });
      }
      const fin = await request(
        `network/finalize?run_id=${encodeURIComponent(runId)}`,
        { method: 'POST' }
      );
      if (fin.error) throw new Error(fin.message || fin.error);

      const url = `${cfg.restRoot}network/download?run_id=${encodeURIComponent(runId)}&token=${encodeURIComponent(fin.token)}&_wpnonce=${encodeURIComponent(cfg.nonce)}`;
      setDownload({ url, filename: fin.filename, size: fin.size });
      setPhase('done');
    } catch (e) {
      setError(e.message);
      setPhase('error');
    }
  }

  if (error && !sites) return <div className="mdf-status err">{error}</div>;
  if (!sites) return <p>Loading…</p>;

  const pct = progress.total > 0
    ? Math.min(100, Math.round((progress.processed / progress.total) * 100))
    : 0;

  return (
    <div className="mdf-app">
      <p className="mdf-muted">
        Each subsite is exported into its own top-level folder under
        <code>content/&lt;subsite-slug&gt;/</code>. Per-subsite mappings (post
        types, taxonomies, ACF, meta) are read from each subsite's saved
        settings; visit a subsite first to customize them.
      </p>

      <div className="mdf-toolbar">
        <button className="button" onClick={() => setAll(true)}>Select all</button>
        <button className="button" onClick={() => setAll(false)}>Select none</button>
      </div>

      <table className="mdf-table">
        <thead>
          <tr>
            <th style={{ width: '40px' }}>Include</th>
            <th>Subsite</th>
            <th>URL</th>
            <th>Posts</th>
            <th>Output folder</th>
          </tr>
        </thead>
        <tbody>
          {sites.map((s) => (
            <tr key={s.id}>
              <td>
                <input
                  type="checkbox"
                  checked={!!selected[s.id]}
                  onChange={() => toggle(s.id)}
                />
              </td>
              <td><strong>{s.name}</strong><div className="mdf-muted">#{s.id}</div></td>
              <td className="mdf-muted">{s.url}</td>
              <td>{s.count}</td>
              <td className="mdf-muted">content/{slugForSite(s)}/</td>
            </tr>
          ))}
        </tbody>
      </table>

      <p style={{ marginTop: 16 }}>
        <button
          className="button button-primary"
          disabled={phase === 'running'}
          onClick={start}
        >
          {phase === 'running' ? 'Exporting…' : 'Start export'}
        </button>
      </p>

      {phase !== 'idle' && (
        <div className="mdf-progress" aria-label={`${pct}%`}>
          <span style={{ width: `${pct}%` }} />
        </div>
      )}
      {phase === 'running' && (
        <p className="mdf-muted">Processed {progress.processed} of {progress.total}…</p>
      )}

      {phase === 'done' && download && (
        <div className="mdf-status ok">
          <p><strong>Network export ready.</strong></p>
          <p>
            <a className="button button-primary" href={download.url} download={download.filename}>
              Download {download.filename}
            </a>{' '}
            <span className="mdf-muted">({Math.round(download.size / 1024)} KB)</span>
          </p>
        </div>
      )}

      {phase === 'error' && (
        <div className="mdf-status err"><strong>Error:</strong> {error}</div>
      )}
    </div>
  );
}

function slugForSite(s) {
  const path = (s.path || '').replace(/^\/|\/$/g, '');
  if (path) return path;
  const host = (s.url || '').replace(/^https?:\/\//, '').split('.')[0];
  return host || `site-${s.id}`;
}

const mount = document.getElementById('fps-mdexp-network-root');
if (mount) {
  createRoot(mount).render(<App />);
}
