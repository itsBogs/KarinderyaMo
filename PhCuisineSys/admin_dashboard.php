<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin','owner'], true)) {
  header('Location: main/login.html');
  exit;
}
$isOwner = ($role === 'owner');
require_once __DIR__ . '/includes/settings.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?> — Admin Panel</title>
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
  width: 220px;
  transition: all 0.3s;
  background: linear-gradient(180deg, var(--bg) 0%, var(--accent-2) 100%);
  padding: 1.25rem 1rem;
  position: relative;
  z-index: 1;
  box-shadow: 2px 0 12px rgba(0,0,0,0.08);
  border-right: 2px solid #f4c46e;
  font-family: 'Poppins', sans-serif;
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
  left: 260px;
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
}

#toggleSidebarBtn:hover { 
  transform: scale(1.05); 
  box-shadow: 0 6px 14px rgba(233, 162, 9, 0.4);
}


.app.collapsed #toggleSidebarBtn { left: 20px; }


@media (max-width: 768px) {
  .app {
    flex-direction: column;
  }

  .sidebar {
    position: fixed;
    top: 0;
    left: -250px;
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
    left: -250px;
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
  padding: 0.65rem 0.75rem; 
  cursor: pointer; 
  border-radius: 10px;
  margin-bottom: 8px;
  font-weight: 600;
  transition: all 0.18s ease;
  border: 1px solid transparent;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #4d3a15;
  background: #fff8e8;
  font-size: 14px;
}

.menu-item i { color: #d48a1f; }

.menu-item:hover { 
  background: #ffe1ae;
  border-color: #f8c76a;
  transform: translateX(3px);
}

.menu-item.active { 
  background: linear-gradient(90deg, var(--accent), var(--accent-2)); 
  color: #2b1c06;
  font-weight: 800;
  border-color: #f0ae3c;
  box-shadow: 0 2px 9px rgba(233, 162, 9, 0.28);
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
      <div class="logo" style="background: linear-gradient(135deg, var(--accent), var(--strong-accent)); width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 16px;">KM</div>
      <div>
        <h1 class="m-0 fs-6 site-name" style="font-weight: 800; color: var(--text);"><?php echo htmlspecialchars(get_setting('site_name', 'Karinderya Mo')); ?></h1>
        <p class="m-0 small" style="color: var(--strong-accent); font-weight: 600;">Admin Panel</p>
        <p class="m-0 small" style="color: var(--text); opacity: 0.7;">User: <?=htmlspecialchars($_SESSION['user_name'] ?? 'Admin')?> </p>
      </div>
    </div>

    <div class="mb-3" style="text-align:center;">
      <a href="api/logout.php" class="btn btn-outline-danger btn-sm w-100" style="border-color: #d48a1f; color: #d48a1f; font-weight: 700; border-width: 2px; border-radius: 10px;">
        <i class="bi bi-box-arrow-left"></i> Logout
      </a>
    </div>

    <nav class="menu mt-3" id="leftMenu">
      <a href="#" class="menu-item active" data-page="dashboard.php">
        <span style="font-size: 16px;">📊</span>
        <span>Dashboard</span>
      </a>
      <?php if (!$isOwner): ?>
        <a href="#" class="menu-item" data-page="orders.php">
          <span style="font-size: 16px;">🧾</span>
          <span>Orders</span>
        </a>
        <a href="#" class="menu-item" data-page="delivery.php">
          <span style="font-size: 16px;">🚚</span>
          <span>Deliveries</span>
        </a>
        <a href="#" class="menu-item" data-page="users.php">
          <span style="font-size: 16px;">👥</span>
          <span>Users</span>
        </a>
      <?php endif; ?>
      <a href="#" class="menu-item" data-page="admin_customers_section.php">
        <span style="font-size: 16px;">🧑‍🍳</span>
        <span>Customers</span>
      </a>
      <?php if (!$isOwner): ?>
        <a href="#" class="menu-item" data-page="menu.php">
          <span style="font-size: 16px;">📜</span>
          <span>Menu</span>
        </a>
      <?php endif; ?>
      <a href="#" class="menu-item" data-page="sales-reports.php">
        <span style="font-size: 16px;">📈</span>
        <span>Sales & Reports</span>
      </a>
      <?php if (!$isOwner): ?>
        <a href="#" class="menu-item" data-page="site-settings.php">
          <span style="font-size: 16px;">⚙️</span>
          <span>Site Settings</span>
        </a>
        <a href="#" class="menu-item" data-page="bank-info.php">
          <span style="font-size: 16px;">🏦</span>
          <span>Bank Info</span>
        </a>
      <?php endif; ?>
    </nav>
  </aside>

  
  <main class="main" id="mainContent">
    <?php include 'dashboard.php'; ?>
  </main>
</div>


<div id="admin-toast" style="position:fixed;top:18px;right:18px;z-index:3000;display:none;background:#1f2937;color:#f9fafb;padding:12px 16px;border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,0.3);font-size:13px;font-weight:500;min-width:250px;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>

const userRole = '<?=$role?>';
const ownerAllowedPages = ['dashboard.php','admin_customers_section.php','sales-reports.php'];
let currentPage = 'dashboard.php';
let ordersPollId = null;
let isOrdersRefreshing = false;


function showToast(message, type = 'success') {
    const toast = document.getElementById('admin-toast');
    if (!toast) return;
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    toast.style.borderLeft = `4px solid ${colors[type] || colors.success}`;
    toast.textContent = message;
    toast.style.display = 'block';
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}


const toggleBtn = document.getElementById('toggleSidebarBtn');
const app = document.querySelector('.app');
if (toggleBtn && app) {

    if (window.innerWidth <= 768) {
        app.classList.add('collapsed');
    }
    
    toggleBtn.addEventListener('click', () => {
        app.classList.toggle('collapsed');
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
}


function loadPage(page) {
    if (!page) return;

  const basePage = page.split('?')[0];
  if (userRole === 'owner' && !ownerAllowedPages.includes(basePage)) {
    const mainContent = document.getElementById('mainContent');
    mainContent.innerHTML = '<div class="alert alert-warning">Access denied for this section.</div>';
    return;
  }
    
    currentPage = page;
    const mainContent = document.getElementById('mainContent');
    mainContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

    fetch(page)
        .then(res => {
            if (!res.ok) throw new Error(`Failed to load ${page}`);
            return res.text();
        })
        .then(html => {
          mainContent.innerHTML = html;

          const scripts = mainContent.querySelectorAll('script');
          scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
              newScript.src = oldScript.src;
            } else {
              newScript.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(newScript, oldScript);
          });

          attachFormHandlers();
        })
        .catch(err => {
            mainContent.innerHTML = `<div class="alert alert-danger">Error loading page: ${err.message}</div>`;
            console.error('Page load error:', err);
        });


      if (page === 'orders.php' && userRole !== 'owner') {
        startOrdersPolling();
      } else {
        stopOrdersPolling();
      }
}


function attachFormHandlers() {
    const mainContent = document.getElementById('mainContent');
    const forms = mainContent.querySelectorAll('form');
    
    forms.forEach(form => {
        if (form.dataset.ajaxAttached) return;
        form.dataset.ajaxAttached = 'true';

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(form);
            
            fetch(currentPage, {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(html => {
                mainContent.innerHTML = html;
                attachFormHandlers();
            })
            .catch(err => {
                console.error('Form error:', err);
                alert('Error submitting form. Please try again.');
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
        });
    });
}

function startOrdersPolling() {
  if (ordersPollId !== null) return;
  ordersPollId = setInterval(() => {
    if (currentPage !== 'orders.php' || isOrdersRefreshing) return;
    isOrdersRefreshing = true;
    fetch('orders.php')
      .then(res => res.text())
      .then(html => {
        const mainContent = document.getElementById('mainContent');
        if (currentPage === 'orders.php') {
          mainContent.innerHTML = html;

          const scripts = mainContent.querySelectorAll('script');
          scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
              newScript.src = oldScript.src;
            } else {
              newScript.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(newScript, oldScript);
          });
          attachFormHandlers();
        }
      })
      .catch(err => console.error('Orders auto-refresh error:', err))
      .finally(() => { isOrdersRefreshing = false; });
  }, 1000);
}

function stopOrdersPolling() {
  if (ordersPollId !== null) {
    clearInterval(ordersPollId);
    ordersPollId = null;
  }
  isOrdersRefreshing = false;
}


function reloadCustomerSection(customerId) {
  const url = customerId ? `admin_customers_section.php?customer_id=${customerId}` : 'admin_customers_section.php';
  loadPage(url);
}


document.addEventListener('DOMContentLoaded', function() {
    const menuItems = document.querySelectorAll('.menu-item');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            const page = this.dataset.page;
            if (!page) return;
            
            menuItems.forEach(m => m.classList.remove('active'));
            this.classList.add('active');
            
            loadPage(page);
        });
    });


    loadPage('dashboard.php');
});


function handleOrderAction(orderId, action) {
    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('action', action);


    const row = document.getElementById('order-row-' + orderId);
    if (row) row.style.opacity = '0.6';

    fetch('orders.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || `Order ${action}d successfully`, 'success');

            loadPage('orders.php');
        } else {
            showToast(data.message || 'Action failed', 'error');
            if (row) row.style.opacity = '1';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
        if (row) row.style.opacity = '1';
    });
}


function assignRider(orderId, buttonElement) {
    const select = document.getElementById('rider_' + orderId);
    const riderId = select ? select.value : null;
    
    if (!riderId) {
        showToast('Please select a rider', 'warning');
        return;
    }
    

    if (buttonElement) {
        buttonElement.disabled = true;
        buttonElement.textContent = '⏳ Assigning...';
    }
    
    const formData = new FormData();
    formData.append('action', 'assign');
    formData.append('order_id', orderId);
    formData.append('rider_id', riderId);
    
    fetch('delivery.php', { method: 'POST', body: formData })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                showToast('Rider assigned successfully', 'success');
                loadPage('delivery.php');
            } else {
                throw new Error(data.message || 'Failed to assign rider');
            }
        })
        .catch(err => {
            showToast('Error: ' + err.message, 'error');
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.textContent = 'Assign';
            }
        });
}
</script>
</body>
</html>
