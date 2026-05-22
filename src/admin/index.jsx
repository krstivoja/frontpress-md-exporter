import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import './styles.css';
import { api } from './api';
import { PostTypesTab } from './tabs/PostTypes';
import { TaxonomiesTab } from './tabs/Taxonomies';
import { MetaTab } from './tabs/Meta';
import { AcfTab } from './tabs/Acf';
import { RunTab } from './tabs/Run';

const TABS = [
  { id: 'post_types', label: 'Post Types' },
  { id: 'taxonomies', label: 'Taxonomies' },
  { id: 'acf', label: 'ACF' },
  { id: 'meta', label: 'Meta / Fields' },
  { id: 'run', label: 'Run Export' },
];

function App() {
  const [tab, setTab] = useState('post_types');
  const [inv, setInv] = useState(null);
  const [settings, setSettings] = useState(null);
  const [savedAt, setSavedAt] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    Promise.all([api.inventory(), api.getSettings()])
      .then(([inv, settings]) => { setInv(inv); setSettings(settings); })
      .catch((e) => setError(e.message));
  }, []);

  async function persist(next) {
    setSettings(next);
    try {
      const saved = await api.saveSettings(next);
      setSettings(saved);
      setSavedAt(new Date());
    } catch (e) {
      setError(e.message);
    }
  }

  if (error) return <div className="mdf-status err">{error}</div>;
  if (!inv || !settings) return <p>Loading…</p>;

  return (
    <div className="mdf-app">
      <div className="mdf-tabs">
        {TABS.map((t) => (
          <button
            key={t.id}
            className={`mdf-tab${tab === t.id ? ' active' : ''}`}
            onClick={() => setTab(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'post_types' && (
        <PostTypesTab inventory={inv} settings={settings} onChange={persist} />
      )}
      {tab === 'taxonomies' && (
        <TaxonomiesTab inventory={inv} settings={settings} onChange={persist} />
      )}
      {tab === 'acf' && (
        <AcfTab inventory={inv} settings={settings} onChange={persist} />
      )}
      {tab === 'meta' && (
        <MetaTab inventory={inv} settings={settings} onChange={persist} />
      )}
      {tab === 'run' && (
        <RunTab settings={settings} inventory={inv} />
      )}

      {savedAt && (
        <p className="mdf-muted">Saved at {savedAt.toLocaleTimeString()}</p>
      )}
    </div>
  );
}

const mount = document.getElementById('fps-mdexp-root');
if (mount) {
  createRoot(mount).render(<App />);
}
