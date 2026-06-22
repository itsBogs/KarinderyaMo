(function(){
  const root = document.documentElement;
  const THEME_KEY = 'theme-update';
  const SITE_META_KEY = 'site_meta';
  const APPLY = (t) => {
    if (!t) return;
    root.style.setProperty('--theme-primary', t.primary || '#ffcb45');
    root.style.setProperty('--theme-secondary', t.secondary || '#f8d477');
    root.style.setProperty('--theme-strong', t.primary || '#ffcb45');
    root.style.setProperty('--theme-bg', t.bg || '#fff9ea');
    root.style.setProperty('--theme-muted', t.muted || '#ffe8b4');
    root.style.setProperty('--theme-text', t.text || '#1d1d1d');
  };

  async function fetchTheme() {
    try {
      const res = await fetch('theme_json.php', { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      APPLY(data);

      localStorage.setItem(THEME_KEY, JSON.stringify({ ...data, ts: Date.now() }));


      if (data.site_name || data.site_description) {
        const meta = { site_name: data.site_name || '', site_description: data.site_description || '', ts: Date.now() };
        localStorage.setItem(SITE_META_KEY, JSON.stringify(meta));
        applySiteMeta(meta);
      }
    } catch (e) {

    }
  }


  window.addEventListener('storage', (e) => {
    if (e.key !== THEME_KEY || !e.newValue) return;
    try {
      const data = JSON.parse(e.newValue);
      APPLY(data);
    } catch {}
  });

  function applySiteMeta(meta) {
    if (!meta) return;
    try {
      const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
      if (m.site_name) {
        document.querySelectorAll('.site-name').forEach(el => el.textContent = m.site_name);
      }
      if (m.site_description) {
        document.querySelectorAll('.site-desc').forEach(el => el.textContent = m.site_description);
      }

      try {
        const siteName = m.site_name || '';
        const siteDesc = m.site_description || '';
        const cur = document.title || '';
        const seps = [' — ', ' - ', ' – '];
        let found = seps.find(s => cur.includes(s));
        if (found) {
          const parts = cur.split(found);

          parts[parts.length - 1] = siteName || parts[parts.length - 1];
          document.title = parts.join(found);
        } else {
          document.title = siteName + (siteDesc ? ' - ' + siteDesc : '');
        }
      } catch (e) {

      }
    } catch (e) {

    }
  }

  window.addEventListener('storage', (e) => {
    if (e.key !== SITE_META_KEY || !e.newValue) return;
    applySiteMeta(e.newValue);
  });


  fetchTheme();
  setInterval(fetchTheme, 1000);

  applySiteMeta(localStorage.getItem('site_meta'));
})();
