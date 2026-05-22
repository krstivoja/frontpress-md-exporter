export function PostTypesTab({ inventory, settings, onChange }) {
  function update(name, patch) {
    const next = { ...settings, post_types: { ...settings.post_types } };
    next.post_types[name] = { ...(next.post_types[name] || {}), ...patch };
    onChange(next);
  }

  return (
    <table className="mdf-table">
      <thead>
        <tr>
          <th style={{ width: '40px' }}>Include</th>
          <th>WP type</th>
          <th>Count</th>
          <th>mdframework folder</th>
          <th>Body conversion</th>
        </tr>
      </thead>
      <tbody>
        {inventory.post_types.map((pt) => {
          const cfg = settings.post_types[pt.name] || pt.default || {};
          return (
            <tr key={pt.name}>
              <td>
                <input
                  type="checkbox"
                  checked={!!cfg.include}
                  onChange={(e) => update(pt.name, { include: e.target.checked })}
                />
              </td>
              <td>
                <strong>{pt.label}</strong>
                <div className="mdf-muted">{pt.name}</div>
              </td>
              <td>{pt.count}</td>
              <td>
                <input
                  type="text"
                  value={cfg.folder || ''}
                  onChange={(e) => update(pt.name, { folder: e.target.value })}
                />
              </td>
              <td>
                <select
                  value={cfg.body_mode || 'markdown'}
                  onChange={(e) => update(pt.name, { body_mode: e.target.value })}
                >
                  <option value="markdown">HTML → Markdown</option>
                  <option value="html">Keep raw HTML</option>
                </select>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
