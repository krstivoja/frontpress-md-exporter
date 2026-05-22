export function TaxonomiesTab({ inventory, settings, onChange }) {
  function update(name, patch) {
    const next = { ...settings, taxonomies: { ...settings.taxonomies } };
    next.taxonomies[name] = { ...(next.taxonomies[name] || {}), ...patch };
    onChange(next);
  }

  return (
    <table className="mdf-table">
      <thead>
        <tr>
          <th style={{ width: '40px' }}>Include</th>
          <th>WP taxonomy</th>
          <th>Front-matter key</th>
          <th>Used by</th>
        </tr>
      </thead>
      <tbody>
        {inventory.taxonomies.map((tx) => {
          const cfg = settings.taxonomies[tx.name] || tx.default || {};
          return (
            <tr key={tx.name}>
              <td>
                <input
                  type="checkbox"
                  checked={!!cfg.include}
                  onChange={(e) => update(tx.name, { include: e.target.checked })}
                />
              </td>
              <td>
                <strong>{tx.label}</strong>
                <div className="mdf-muted">{tx.name}</div>
              </td>
              <td>
                <input
                  type="text"
                  value={cfg.key || ''}
                  onChange={(e) => update(tx.name, { key: e.target.value })}
                />
              </td>
              <td className="mdf-muted">{tx.objects.join(', ')}</td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}
