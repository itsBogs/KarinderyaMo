<?php

require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();


function lightenColor($hex, $factor = 0.85) {
  $hex = ltrim(trim($hex), '#');
  if (strlen($hex) === 3) {
    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
  }
  if (strlen($hex) !== 6) return '#ffffff';
  $r = hexdec(substr($hex,0,2));
  $g = hexdec(substr($hex,2,2));
  $b = hexdec(substr($hex,4,2));
  $blend = function($c) use ($factor) {
    return (int)round($c + (255 - $c) * $factor);
  };
  return sprintf('#%02x%02x%02x', $blend($r), $blend($g), $blend($b));
}


$canEdit = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin','owner'], true);

$pdo = getPDO();
$keys = [
  'site_name' => 'Karinderya Mo',
  'site_description' => 'Philippine Food Delivery System',
  'contact_email' => 'support@karinderya.com',
  'contact_phone' => '09123456789',
  'theme_primary_color' => '#FF8B54',
  'theme_secondary_color' => '#FF6B54',
  'theme_bg_color' => '#fff9ea',
  'theme_muted_color' => '#ffe8b4',
  'theme_text_color' => '#1d1d1d'
];


$message = '';
$errors = [];
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($keys as $k => $default) {
    if (isset($_POST[$k])) {
      $keys[$k] = trim($_POST[$k]);
    }
  }


  $primary = $keys['theme_primary_color'];
  $keys['theme_bg_color'] = lightenColor($primary, 0.92);
  $keys['theme_muted_color'] = lightenColor($primary, 0.82);

  $keys['theme_text_color'] = '#1d1d1d';
    try {
        $stmt = $pdo->prepare('INSERT INTO settings (key_name, value) VALUES (:k, :v)
                               ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($keys as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v]);
        }
        $message = 'Settings saved successfully.';
    } catch (Exception $e) {
        $errors[] = 'Could not save settings: ' . $e->getMessage();
    }


    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
         || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isAjax) {
      header('Content-Type: application/json');
      if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => $errors[0]]);
        exit();
      }


      $site_meta = [
        'site_name' => $keys['site_name'],
        'site_description' => $keys['site_description']
      ];
      $theme = [
        'primary' => $keys['theme_primary_color'],
        'secondary' => $keys['theme_secondary_color'],
        'bg' => $keys['theme_bg_color'],
        'muted' => $keys['theme_muted_color'],
        'text' => $keys['theme_text_color']
      ];

      echo json_encode(['success' => true, 'message' => $message, 'site_meta' => $site_meta, 'theme' => $theme]);
      exit();
    }
}


try {
    $stmt = $pdo->query('SELECT key_name, value FROM settings');
    foreach ($stmt->fetchAll() as $row) {
        $keys[$row['key_name']] = $row['value'];
    }
} catch (Exception $e) {
    $errors[] = 'Could not load settings: ' . $e->getMessage();
}
?>

<div class="card p-3">
  <h3>Site Settings</h3>
  <div class="small mb-2">Update site configurations such as contact info and theme colors.</div>
  <div id="settings-message" style="display:none; margin-bottom:10px;"></div>
  <noscript>
    <?php if($message): ?><div class="alert alert-success"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <?php if($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </noscript>

  <form method="post" action="site-settings.php" id="theme-form">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Site Name</label>
          <input type="text" class="form-control" name="site_name" value="<?=htmlspecialchars($keys['site_name'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Contact Email</label>
          <input type="email" class="form-control" name="contact_email" value="<?=htmlspecialchars($keys['contact_email'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Contact Phone</label>
          <input type="text" class="form-control" name="contact_phone" value="<?=htmlspecialchars($keys['contact_phone'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Tagline / Description</label>
          <input type="text" class="form-control" name="site_description" value="<?=htmlspecialchars($keys['site_description'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Theme Primary Color</label>
          <input type="color" class="form-control form-control-color" id="color-primary" name="theme_primary_color" value="<?=htmlspecialchars($keys['theme_primary_color'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Theme Secondary Color</label>
          <input type="color" class="form-control form-control-color" id="color-secondary" name="theme_secondary_color" value="<?=htmlspecialchars($keys['theme_secondary_color'])?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
    </div>

    <?php if($canEdit): ?>
      <button class="btn btn-success" type="submit">Save Changes</button>
    <?php else: ?>
      <div class="alert alert-warning mt-2 mb-0">You need admin/owner access to edit settings.</div>
    <?php endif; ?>
  </form>
</div>

<script>

(function() {
  const THEME_KEY = 'theme-update';
  const root = document.documentElement;
  const primaryInput = document.getElementById('color-primary');
  const secondaryInput = document.getElementById('color-secondary');
  
  function lightenColor(hex, factor = 0.85) {
    hex = hex.replace('#', '');
    if (hex.length === 3) {
      hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
    }
    const r = parseInt(hex.substr(0,2), 16);
    const g = parseInt(hex.substr(2,2), 16);
    const b = parseInt(hex.substr(4,2), 16);
    const blend = (c) => Math.round(c + (255 - c) * factor);
    return '#' + [blend(r), blend(g), blend(b)].map(x => x.toString(16).padStart(2,'0')).join('');
  }
  
  function applyTheme(primary, secondary) {
    const bg = lightenColor(primary, 0.92);
    const muted = lightenColor(primary, 0.82);
    const text = '#1d1d1d';
    
    root.style.setProperty('--theme-primary', primary);
    root.style.setProperty('--theme-secondary', secondary);
    root.style.setProperty('--theme-strong', primary);
    root.style.setProperty('--theme-bg', bg);
    root.style.setProperty('--theme-muted', muted);
    root.style.setProperty('--theme-text', text);
    

    const data = {
      primary: primary,
      secondary: secondary,
      bg: bg,
      muted: muted,
      text: text,
      ts: Date.now()
    };
    localStorage.setItem(THEME_KEY, JSON.stringify(data));
  }


  function applySiteMeta(meta) {
    if (!meta) return;
    try {
      const m = typeof meta === 'string' ? JSON.parse(meta) : meta;
      if (m.site_name) document.querySelectorAll('.site-name').forEach(el => el.textContent = m.site_name);
      if (m.site_description) document.querySelectorAll('.site-desc').forEach(el => el.textContent = m.site_description);
      if (m.site_name) {
        if (document.title && (document.title.indexOf('—') !== -1 || document.title.indexOf('-') !== -1)) {

          document.title = m.site_name + ' - ' + (document.title.split('-').slice(1).join('-') || '');
        } else {
          document.title = m.site_name + ' - ' + (m.site_description || '');
        }
      }
    } catch (e) {

    }
  }
  

  if (primaryInput) {
    primaryInput.addEventListener('input', function() {
      applyTheme(this.value, secondaryInput.value);
    });
  }
  if (secondaryInput) {
    secondaryInput.addEventListener('input', function() {
      applyTheme(primaryInput.value, this.value);
    });
  }

  const siteNameInput = document.querySelector('input[name="site_name"]');
  const siteDescInput = document.querySelector('input[name="site_description"]');
  function broadcastSiteMeta() {
    const meta = {
      site_name: siteNameInput ? siteNameInput.value : document.title,
      site_description: siteDescInput ? siteDescInput.value : '' ,
      ts: Date.now()
    };
    localStorage.setItem('site_meta', JSON.stringify(meta));

    applySiteMeta(meta);
  }
  if (siteNameInput) siteNameInput.addEventListener('input', broadcastSiteMeta);
  if (siteDescInput) siteDescInput.addEventListener('input', broadcastSiteMeta);
  

  const form = document.getElementById('theme-form');
    if (form) {

      form.dataset.ajaxAttached = 'true';
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const origText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

        const fd = new FormData(form);
        fetch('site-settings.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: fd,
          credentials: 'same-origin'
        }).then(r => r.json()).then(data => {
          if (!data) throw new Error('No response');
          const msgEl = document.getElementById('settings-message');
          function showMsg(text, type){
            if(!msgEl) return;
            msgEl.style.display = 'block';
            msgEl.innerHTML = '<div class="alert alert-' + (type==='success'? 'success':'danger') + '">' + text + '</div>';
            setTimeout(()=>{ if(msgEl) msgEl.style.display='none'; }, 4000);
          }

          if (data.success) {

            if (data.theme) {
              const themeData = { primary: data.theme.primary, secondary: data.theme.secondary, bg: data.theme.bg, muted: data.theme.muted, text: data.theme.text, ts: Date.now() };
              localStorage.setItem('theme-update', JSON.stringify(themeData));

              try { applyTheme(themeData.primary, themeData.secondary); } catch (e) {}
            }


            if (data.site_meta) {
              const meta = { site_name: data.site_meta.site_name, site_description: data.site_meta.site_description, ts: Date.now() };
              localStorage.setItem('site_meta', JSON.stringify(meta));
              applySiteMeta(meta);
            }

            showMsg(data.message || 'Settings saved', 'success');
          } else {
            showMsg('Error: ' + (data.message || 'Could not save'), 'error');
          }
        }).catch(err => {
          console.error(err);
          showMsg('Network error while saving settings', 'error');
        }).finally(() => {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origText; }
        });
      });
  }
})();
</script>
