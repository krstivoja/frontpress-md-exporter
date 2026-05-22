import { useMemo, useState } from 'react';

export function MetaTab({ inventory, settings, onChange }) {
  const [filter, setFilter] = useState('');

  const mappedSources = useMemo(() => {
    const set = new Set();
    (settings.meta || []).forEach((r) => set.add(r.source));
    return set;
  }, [settings.meta]);

  function setRow(idx, patch) {
    const meta = [...(settings.meta || [])];
    meta[idx] = { ...meta[idx], ...patch };
    onChange({ ...settings, meta });
  }
  function addKey(key) {
    if (mappedSources.has(key)) return;
    onChange({ ...settings, meta: [...(settings.meta || []), { source: key, target: key }] });
  }
  function removeRow(idx) {
    const meta = [...(settings.meta || [])];
    meta.splice(idx, 1);
    onChange({ ...settings, meta });
  }
  function toggleUnmapped(e) {
    onChange({ ...settings, include_unmapped_meta: e.target.checked });
  }

  const available = inventory.meta_keys.filter(
    (k) => !mappedSources.has(k) && k.toLowerCase().includes(filter.toLowerCase())
  );

  return (
    <div>
      <p className="mdf-muted">
        Map WordPress meta keys to mdframework front-matter keys. Built-in fields
        (featured image → <code>image</code>, excerpt → <code>excerpt</code>,
        non-published → <code>draft</code>) are handled automatically.
      </p>

      <h3>Mapped fields</h3>
      {(settings.meta || []).length === 0 && (
        <p className="mdf-muted">None yet — pick a key below to map.</p>
      )}
      {(settings.meta || []).map((row, idx) => (
        <div key={idx} className="mdf-meta-row">
          <input
            type="text"
            value={row.source || ''}
            onChange={(e) => setRow(idx, { source: e.target.value })}
            placeholder="WP meta_key"
          />
          <span>→</span>
          <input
            type="text"
            value={row.target || ''}
            onChange={(e) => setRow(idx, { target: e.target.value })}
            placeholder="front-matter key"
          />
          <button className="button" onClick={() => removeRow(idx)}>Remove</button>
        </div>
      ))}

      <h3>Available meta keys ({inventory.meta_keys.length})</h3>
      <div className="mdf-toolbar">
        <input
          type="search"
          placeholder="Filter…"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
        />
      </div>
      <ul style={{ columns: 3 }}>
        {available.map((k) => (
          <li key={k}>
            <button className="button-link" onClick={() => addKey(k)}>+ {k}</button>
          </li>
        ))}
      </ul>

      <p>
        <label>
          <input
            type="checkbox"
            checked={!!settings.include_unmapped_meta}
            onChange={toggleUnmapped}
          />{' '}
          Also include all unmapped (non-private) meta verbatim in front matter
        </label>
      </p>
    </div>
  );
}
