<?php

session_start();


require_once __DIR__ . '/db.php'; 

require_once __DIR__ . '/includes/settings.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rider') {
    header('Location: login.php'); 
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Rider';
$user_id = $_SESSION['user_id'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?> — Rider Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<script src="js/theme-sync.js"></script>
<style>
@import url('theme.php');

:root {
  --bg: var(--theme-bg, #fff9ea);
  --muted: var(--theme-muted, #ffe8b4);
  --card: #fff;
  --accent: var(--theme-primary, #ffcb45);
  --accent-2: var(--theme-secondary, #f8d477);
  --strong-accent: var(--theme-strong, #e9a209);
  --text: var(--theme-text, #1d1d1d);
  --radius: 12px;
}

body, html { 
  margin: 0; 
  padding: 0; 
  height: 100%; 
  font-family: 'Poppins', sans-serif;
  background-color: var(--bg);
  color: var(--text);
}
.app { 
  display: flex; 
  min-height: 100vh; 
  transition: all 0.3s; 
  position: relative; 
}

.sidebar {
  width: 250px;
  transition: all 0.3s;
  background: linear-gradient(180deg, var(--card) 0%, var(--accent-2) 100%);
  padding: 1.5rem;
  position: relative;
  z-index: 1;
  box-shadow: 2px 0 8px rgba(0,0,0,0.05);
  border-right: 2px solid var(--muted);
}

.main {
  flex: 1;
  padding: 1.5rem;
  transition: all 0.3s;
  background: var(--bg);
}

.app.collapsed .sidebar { width:0; padding:0; overflow:hidden; }
.app.collapsed .main { flex:1; width:100%; }

#toggleSidebarBtn {
  position: absolute;
  top: 20px;
  right: -23px;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--strong-accent));
  color: #fff;
  border: none;
  box-shadow: 0 4px 10px rgba(233, 162, 9, 0.3);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99;
  transition: all 0.3s;
  
  left: 260px; 
  right: auto;
}

#toggleSidebarBtn:hover { 
  transform: scale(1.05); 
  box-shadow: 0 6px 14px rgba(233, 162, 9, 0.4);
}


.app.collapsed #toggleSidebarBtn { 
    right: auto;
    left: 20px; 
}


@media (max-width: 768px) {
  .app {
    flex-direction: column;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: -270px;
    width: 250px;
    height: 100vh;
    z-index: 1000;
    overflow-y: auto;
    transition: left 0.3s ease;
  }

  .app:not(.collapsed) .sidebar {
    left: 0;
  }

  .main {
    padding: 70px 1rem 1rem;
    width: 100%;
  }

  #toggleSidebarBtn {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1001;
  }

  .app.collapsed #toggleSidebarBtn {
    left: 10px;
  }

  .app:not(.collapsed)::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }

  .app.collapsed .sidebar {
    left: -270px;
  }
}

@media (max-width: 900px) and (min-width: 769px) {
  #toggleSidebarBtn {
    position: fixed;
    top: 20px;
    left: 20px;
    right: auto;
  }
}

.menu-item { 
  padding: 0.75rem 1rem; 
  cursor: pointer; 
  border-radius: 8px;
  margin-bottom: 6px;
  font-weight: 500;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.menu-item:hover { 
  background: linear-gradient(90deg, var(--accent-2), transparent);
  border-left-color: var(--strong-accent);
  transform: translateX(4px);
}

.menu-item.active { 
  background: linear-gradient(90deg, var(--accent), var(--accent-2)); 
  color: var(--text);
  font-weight: 700;
  border-left-color: var(--strong-accent);
  box-shadow: 0 2px 6px rgba(233, 162, 9, 0.2);
}
</style>
</head>
<body>
<button id="toggleSidebarBtn">
  <i class="bi bi-list fs-5"></i>
</button>

<div class="app">
  <aside class="sidebar">
    <div class="brand d-flex align-items-center gap-2 mb-3">
      <div class="logo" style="background: linear-gradient(135deg, var(--accent), var(--strong-accent)); width: 50px; height: 50px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 18px;">KM</div>
      <div>
        <h1 class="m-0 fs-6 site-name" style="font-weight: 800; color: var(--text);"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
        <p class="m-0 small" style="color: var(--strong-accent); font-weight: 600;">Rider Panel</p>
        <p class="m-0 small" style="color: var(--text); opacity: 0.7;">User: <?=htmlspecialchars($user_name)?></p>
      </div>
    </div>

    <div class="mb-3">
      <a href="logout.php" class="btn btn-outline-danger btn-sm w-100" style="border-color: var(--strong-accent); color: var(--strong-accent); font-weight: 600;">
        <i class="bi bi-box-arrow-left"></i> Logout
      </a>
    </div>

    <nav class="menu mt-3" id="leftMenu">
      <div class="menu-item" data-section="rider_dashboard">📊 Dashboard</div>
      <div class="menu-item active" data-section="rider_deliveries">🚚 Current Deliveries</div> 
      <div class="menu-item" data-section="rider_history">📋 Delivery History</div> 
      <div class="menu-item" data-section="rider_earnings">💰 Earnings</div>
      <div class="menu-item" data-section="rider_profile">👤 Profile</div>
      <div class="menu-item" data-section="rider_help">❓ Help & Support</div>
    </nav>
  </aside>

  <main class="main" id="mainContent">
    <?php 

    include 'rider_deliveries.php'; 
    ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
const toggleBtn = document.getElementById('toggleSidebarBtn');
const app = document.querySelector('.app');
const sidebar = document.querySelector('.sidebar');


let refreshIntervalId = null; 
const REFRESH_INTERVAL_MS = 15000; 

function clearDeliveryRefresh() {
    if (refreshIntervalId !== null) {
        clearInterval(refreshIntervalId);
        refreshIntervalId = null;
        console.log('Delivery refresh stopped.');
    }
}


function loadRiderDeliveriesContent() {
    const deliveryItem = document.querySelector('.menu-item[data-section="rider_deliveries"]');
    if (deliveryItem) {

        if (deliveryItem.classList.contains('active')) {

            fetch('rider_deliveries.php')
                .then(res => res.text())
                .then(html => {
                    mainContent.innerHTML = html;

                    reExecuteScripts(mainContent);
                    console.log('Rider Deliveries refreshed automatically.');
                })
                .catch(err => { console.error('Auto-refresh failed:', err); });
        }
    }
}

function startDeliveryRefresh() {

    clearDeliveryRefresh(); 
    

    refreshIntervalId = setInterval(loadRiderDeliveriesContent, REFRESH_INTERVAL_MS);
    console.log(`Delivery refresh started (every ${REFRESH_INTERVAL_MS/1000}s).`);
}


function reExecuteScripts(targetElement) {
    const scripts = targetElement.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
        
        if (oldScript.src) {
            newScript.src = oldScript.src;
        } else {
            newScript.textContent = oldScript.textContent;
        }

        oldScript.remove();
        targetElement.appendChild(newScript);
    });
}


function updateToggleIcon() {
    const icon = toggleBtn.querySelector('i');
    if (app.classList.contains('collapsed')) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x'); 
    } else {
        icon.classList.remove('bi-x');
        icon.classList.add('bi-list');
    }
}


if (window.innerWidth <= 768) {
    app.classList.add('collapsed');
}

toggleBtn.addEventListener('click', () => { 
    app.classList.toggle('collapsed'); 
    updateToggleIcon();
});


if (window.innerWidth <= 768) {
    app.addEventListener('click', (e) => {
        if (!app.classList.contains('collapsed') && 
            !e.target.closest('.sidebar') && 
            !e.target.closest('#toggleSidebarBtn')) {
            app.classList.add('collapsed');
        }
    });
}

updateToggleIcon();


const menuItems = document.querySelectorAll('.menu-item');
const mainContent = document.getElementById('mainContent');

menuItems.forEach(item => {
  item.addEventListener('click', function() {
    menuItems.forEach(m => m.classList.remove('active'));
    this.classList.add('active');   

    let section = this.dataset.section;
    let url = section + '.php';


    clearDeliveryRefresh();
    

    mainContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading content for ' + section + '...</p></div>';

    fetch(url)
      .then(res => {
          if (!res.ok) {
              throw new Error('Network response was not ok: ' + res.statusText);
          }
          return res.text();
      })
      .then(html => {

        mainContent.innerHTML = html;


        reExecuteScripts(mainContent);







        if(section === 'rider_earnings' && typeof initEarningsChart === 'function') {
            initEarningsChart();
        }
        
      })
      .catch(err => {
          console.error('AJAX Load Error:', err);
          mainContent.innerHTML = `<div class="alert alert-danger">Failed to load ${section}.php. Check if the file exists and has no PHP errors.</div>`;
      });
  });
});


document.addEventListener('DOMContentLoaded', function(){
    const initialSection = 'rider_deliveries';
    const initialLi = document.querySelector(`.menu-item[data-section="${initialSection}"]`);
    if (initialLi) {
        menuItems.forEach(item => item.classList.remove('active'));
        initialLi.classList.add('active');
        


    }
});


let earningsChartInstance = null;
function initEarningsChart(){




}

</script>
</body>
</html>