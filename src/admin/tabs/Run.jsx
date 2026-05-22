import { useState } from 'react';
import { api } from '../api';

export function RunTab({ settings, inventory }) {
  const [phase, setPhase] = useState('idle'); // idle | running | done | error
  const [progress, setProgress] = useState({ processed: 0, total: 0 });
  const [download, setDownload] = useState(null);
  const [error, setError] = useState(null);

  const includedTypes = inventory.post_types.filter(
    (pt) => settings.post_types[pt.name]?.include
  );
  const includedTax = inventory.taxonomies.filter(
    (tx) => settings.taxonomies[tx.name]?.include
  );
  const totalEstimate = includedTypes.reduce((sum, pt) => sum + (pt.count || 0), 0);

  async function start() {
    setError(null);
    setDownload(null);
    setPhase('running');
    setProgress({ processed: 0, total: totalEstimate });
    try {
      const { run_id, total } = await api.startExport();
      setProgress({ processed: 0, total });

      let done = false;
      let processed = 0;
      while (!done) {
        const r = await api.tickExport(run_id, 20);
        processed = r.processed;
        done = !!r.done;
        setProgress({ processed: r.processed, total: r.total });
      }

      const fin = await api.finalizeExport(run_id);
      if (fin.error) throw new Error(fin.message || fin.error);

      setDownload({
        url: api.downloadUrl(run_id, fin.token),
        filename: fin.filename,
        size: fin.size,
      });
      setPhase('done');
    } catch (e) {
      setError(e.message);
      setPhase('error');
    }
  }

  const pct = progress.total > 0
    ? Math.min(100, Math.round((progress.processed / progress.total) * 100))
    : 0;

  return (
    <div>
      <div className="mdf-summary">
        <strong>Export plan</strong>
        <ul>
          <li>
            Post types ({includedTypes.length}):{' '}
            {includedTypes.map((pt) => `${pt.name}→${settings.post_types[pt.name].folder}`).join(', ') || '—'}
          </li>
          <li>
            Taxonomies ({includedTax.length}):{' '}
            {includedTax.map((tx) => `${tx.name}→${settings.taxonomies[tx.name].key}`).join(', ') || '—'}
          </li>
          <li>Mapped meta fields: {(settings.meta || []).length}</li>
          <li>Estimated posts: ~{totalEstimate}</li>
        </ul>
      </div>

      <button
        className="button button-primary"
        disabled={phase === 'running' || includedTypes.length === 0}
        onClick={start}
      >
        {phase === 'running' ? 'Exporting…' : 'Start export'}
      </button>

      {phase !== 'idle' && (
        <div className="mdf-progress" aria-label={`${pct}%`}>
          <span style={{ width: `${pct}%` }} />
        </div>
      )}
      {phase === 'running' && (
        <p className="mdf-muted">
          Processed {progress.processed} of {progress.total}…
        </p>
      )}

      {phase === 'done' && download && (
        <div className="mdf-status ok">
          <p><strong>Export ready.</strong></p>
          <p>
            <a className="button button-primary" href={download.url} download={download.filename}>
              Download {download.filename}
            </a>{' '}
            <span className="mdf-muted">({Math.round(download.size / 1024)} KB)</span>
          </p>
          <p className="mdf-muted">
            Unzip into <code>mdframework/app/site/</code>. The zip contains a
            fresh <code>config.json</code>, content folders, and per-post media.
          </p>
        </div>
      )}

      {phase === 'error' && (
        <div className="mdf-status err">
          <strong>Error:</strong> {error}
        </div>
      )}
    </div>
  );
}
