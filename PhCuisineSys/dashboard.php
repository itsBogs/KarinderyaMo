<?php
// dashboard.php - Dynamic admin dashboard with database data
require_once __DIR__ . '/db.php';
// session_start() - Already started in admin_dashboard.php, don't call it again

try {
    $pdo = getPDO();
    
    // Get statistics
    $statsStmt = $pdo->query('SELECT COUNT(*) as total FROM orders');
    $totalOrders = $statsStmt->fetch()['total'];
    
    $statsStmt = $pdo->query('SELECT COUNT(*) as total FROM users');
    $totalUsers = $statsStmt->fetch()['total'];
    
    $statsStmt = $pdo->query('SELECT SUM(total_amount) as revenue FROM orders');
    $totalRevenue = $statsStmt->fetch()['revenue'] ?? 0;
    
    $statsStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
    $pendingOrders = $statsStmt->fetch()['total'];
    
    $statsStmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'");
    $completedOrders = $statsStmt->fetch()['total'];
    
    // Get order status breakdown for pie chart
    $statusStmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $ordersByStatus = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get daily revenue for last 7 days for line chart
    $revenueStmt = $pdo->query("
        SELECT DATE(created_at) as date, SUM(total_amount) as revenue 
        FROM orders 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $dailyRevenue = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent orders
    $ordersStmt = $pdo->query('
        SELECT o.id, o.order_number, u.name as customer_name, o.total_amount, o.status, o.created_at 
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ');
    $recentOrders = $ordersStmt->fetchAll();
    
} catch (Exception $e) {
    $totalOrders = 0;
    $totalUsers = 0;
    $totalRevenue = 0;
    $pendingOrders = 0;
    $completedOrders = 0;
    $ordersByStatus = [];
    $dailyRevenue = [];
    $recentOrders = [];
}
?>

<div class="container py-3">
  <div class="row g-3">
    <!-- Card 1: Total Orders -->
    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
        <h6 class="mb-2" style="opacity: 0.9;">📦 Total Orders</h6>
        <p class="fs-3 fw-bold mb-0"><?=number_format($totalOrders)?></p>
      </div>
    </div>

    <!-- Card 2: Total Users -->
    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
        <h6 class="mb-2" style="opacity: 0.9;">👥 Total Users</h6>
        <p class="fs-3 fw-bold mb-0"><?=number_format($totalUsers)?></p>
      </div>
    </div>

    <!-- Card 3: Total Revenue -->
    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
        <h6 class="mb-2" style="opacity: 0.9;">💰 Total Revenue</h6>
        <p class="fs-3 fw-bold mb-0">₱<?=number_format($totalRevenue, 2)?></p>
      </div>
    </div>

    <!-- Card 4: Completed Orders -->
    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
        <h6 class="mb-2" style="opacity: 0.9;">✅ Completed</h6>
        <p class="fs-3 fw-bold mb-0"><?=number_format($completedOrders)?></p>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="col-md-5">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">📊 Order Status Distribution</h5>
        <div style="position: relative; height: 280px;">
          <canvas id="statusPieChart"></canvas>
        </div>
      </div>
    </div>

    <div class="col-md-7">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">📈 Revenue Trend (Last 7 Days)</h5>
        <div style="position: relative; height: 280px;">
          <canvas id="revenueLineChart"></canvas>
        </div>
      </div>
    </div>
    
    <!-- Recent Orders Table -->
    <div class="col-12 mt-4">
      <div class="card shadow-sm p-3">
        <h5>Recent Orders</h5>
        <div class="table-responsive">
          <table class="table table-striped table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($recentOrders) > 0): ?>
                <?php foreach ($recentOrders as $order): ?>
                  <tr>
                    <td><strong><?=htmlspecialchars($order['order_number'])?></strong></td>
                    <td><?=htmlspecialchars($order['customer_name'])?></td>
                    <td>₱<?=number_format($order['total_amount'], 2)?></td>
                    <td>
                      <span class="badge bg-<?php 
                        echo ($order['status'] === 'delivered') ? 'success' : (($order['status'] === 'pending') ? 'warning' : 'info');
                      ?>">
                        <?=ucfirst(str_replace('_', ' ', $order['status']))?>
                      </span>
                    </td>
                    <td><?=date('M d, Y', strtotime($order['created_at']))?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted">No orders yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
  // Prepare chart data from PHP
  const ordersByStatus = <?=json_encode($ordersByStatus)?>;
  const dailyRevenue = <?=json_encode($dailyRevenue)?>;

  console.log('=== DASHBOARD CHART DEBUG ===');
  console.log('Chart.js loaded:', typeof Chart !== 'undefined');
  console.log('Orders by status:', ordersByStatus);
  console.log('Daily revenue:', dailyRevenue);

  // Wait for Chart.js to be loaded
  function initializeCharts() {
    if (typeof Chart === 'undefined') {
      console.log('⏳ Waiting for Chart.js...');
      setTimeout(initializeCharts, 50);
      return;
    }

    console.log('✅ Chart.js ready, initializing charts...');

    // Color palette for charts
    const chartColors = {
      pending: 'rgba(255, 193, 7, 0.8)',
      approved: 'rgba(13, 202, 240, 0.8)',
      preparing: 'rgba(255, 159, 64, 0.8)',
      ready: 'rgba(54, 162, 235, 0.8)',
      picked_up: 'rgba(75, 192, 192, 0.8)',
      delivered: 'rgba(40, 167, 69, 0.8)',
      cancelled: 'rgba(220, 53, 69, 0.8)',
      rejected: 'rgba(220, 53, 69, 0.8)'
    };

    // Destroy existing charts if they exist
    const existingPieChart = Chart.getChart('statusPieChart');
    if (existingPieChart) {
      console.log('🗑️ Destroying existing pie chart');
      existingPieChart.destroy();
    }
    const existingLineChart = Chart.getChart('revenueLineChart');
    if (existingLineChart) {
      console.log('🗑️ Destroying existing line chart');
      existingLineChart.destroy();
    }

    // Pie Chart - Order Status Distribution
    const pieCtx = document.getElementById('statusPieChart');
    if (pieCtx && Object.keys(ordersByStatus).length > 0) {
      console.log('📊 Creating pie chart...');
      const statusLabels = Object.keys(ordersByStatus).map(s => s.replace(/_/g, ' ').toUpperCase());
      const statusData = Object.values(ordersByStatus);
      const statusColors = Object.keys(ordersByStatus).map(status => chartColors[status] || 'rgba(153, 102, 255, 0.8)');

      new Chart(pieCtx, {
        type: 'doughnut',
        data: {
          labels: statusLabels,
          datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            borderColor: statusColors.map(c => c.replace('0.8', '1')),
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 12,
                font: { size: 11 }
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const value = context.parsed;
                  const percentage = ((value / total) * 100).toFixed(1);
                  return `${context.label}: ${value} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
      console.log('✅ Pie chart created!');
    } else if (pieCtx) {
      console.log('⚠️ No order data for pie chart');
      pieCtx.parentElement.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">No order data available</div>';
    }

    // Line Chart - Revenue Trend
    const lineCtx = document.getElementById('revenueLineChart');
    if (lineCtx && dailyRevenue.length > 0) {
      console.log('📈 Creating line chart...');
      const revenueDates = dailyRevenue.map(d => {
        const date = new Date(d.date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      });
      const revenueValues = dailyRevenue.map(d => parseFloat(d.revenue) || 0);

      const gradient = lineCtx.getContext('2d').createLinearGradient(0, 0, 0, 280);
      gradient.addColorStop(0, 'rgba(75, 192, 192, 0.5)');
      gradient.addColorStop(1, 'rgba(75, 192, 192, 0.05)');

      new Chart(lineCtx, {
        type: 'line',
        data: {
          labels: revenueDates,
          datasets: [{
            label: 'Revenue (₱)',
            data: revenueValues,
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: 'rgba(75, 192, 192, 1)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 7
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              padding: 12,
              titleFont: { size: 13 },
              bodyFont: { size: 14 },
              callbacks: {
                label: function(context) {
                  return `Revenue: ₱${context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return '₱' + value.toLocaleString();
                }
              },
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      });
      console.log('✅ Line chart created!');
    } else if (lineCtx) {
      console.log('⚠️ No revenue data for line chart');
      lineCtx.parentElement.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">No revenue data for the last 7 days</div>';
    }
  }

  // Start initialization
  initializeCharts();
})();
</script>
