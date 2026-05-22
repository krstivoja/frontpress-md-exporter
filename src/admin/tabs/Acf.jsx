export function AcfTab({ inventory, settings, onChange }) {
  const acf = inventory.acf || { available: false, fields: [] };

  if (!acf.available) {
    return (
      <p className="mdf-muted">
        ACF (Advanced Custom Fields) isn’t active on this site. Activate ACF
        to expose its field groups here.
      </p>
    );
  }
  if (acf.fields.length === 0) {
    return <p className="mdf-muted">ACF is active, but no field groups define any fields yet.</p>;
  }

  function update(name, patch) {
    const next = { ...settings, acf: { ...(settings.acf || {}) } };
    next.acf[name] = { ...(next.acf[name] || { include: true, target: name }), ...patch };
    onChange(next);
  }

  return (
    <div>
      <p className="mdf-muted">
        Each ACF field is included by default, mapped to a front-matter key
        with the same name. Image / file / gallery values are copied into the
        post’s media folder; relationship and taxonomy values become slug
        arrays; repeater / group / flexible-content values keep their nested
        structure.
      </p>

      <table className="mdf-table">
        <thead>
          <tr>
            <th style={{ width: '40px' }}>Include</th>
            <th>Field</th>
            <th>Type</th>
            <th>Group</th>
            <th>Post types</th>
            <th>Front-matter key</th>
          </tr>
        </thead>
        <tbody>
          {acf.fields.map((f) => {
            const cfg = (settings.acf && settings.acf[f.name]) || { include: true, target: f.name };
            return (
              <tr key={f.name}>
                <td>
                  <input
                    type="checkbox"
                    checked={!!cfg.include}
                    onChange={(e) => update(f.name, { include: e.target.checked })}
                  />
                </td>
                <td>
                  <strong>{f.label}</strong>
                  <div className="mdf-muted">{f.name}</div>
                </td>
                <td>{f.type}</td>
                <td>{f.group_title}</td>
                <td className="mdf-muted">
                  {f.post_types.includes('*') ? 'all' : f.post_types.join(', ') || '—'}
                </td>
                <td>
                  <input
                    type="text"
                    value={cfg.target || ''}
                    onChange={(e) => update(f.name, { target: e.target.value })}
                  />
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
