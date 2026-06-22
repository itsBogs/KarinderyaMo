<?php
// Fragment: Rider Dashboard with Dynamic Stats and Chart
require_once __DIR__ . '/db.php'; 

// FIX: Session must be started on AJAX fragments to access $_SESSION
// We use a check to avoid warnings if headers were already sent, but for AJAX this is a MUST.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

// Basic Security Check: If not logged in OR not a rider
if (!$user_id || $user_role !== 'rider') { 
    echo '<div class="container py-3"><div class="alert alert-danger">❌ Unauthorized Access. Please log in as a Rider.</div></div>';
    exit;
}

$stats = [
    'pending_today' => 0,
    'completed_today' => 0,
    'earnings_today' => 0,
    'completed_all_time' => 0,
    'cancelled_all_time' => 0,
    'total_all_time' => 0,
    'in_progress_all_time' => 0,
    'total_earnings' => 0,
];
$today = date('Y-m-d');
$weekly_earnings = [];

try {
    $pdo = getPDO();
    
    // 1. Get statistics for today
    $stmt_today = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN o.status = 'delivered' AND DATE(d.delivered_at) = ? THEN 1 ELSE 0 END) as completed_today,
            SUM(CASE WHEN o.status IN ('approved', 'preparing', 'out_for_delivery') THEN 1 ELSE 0 END) as pending_today,
            SUM(CASE WHEN o.status = 'delivered' AND DATE(d.delivered_at) = ? THEN CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END ELSE 0 END) as earnings_today
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.rider_id = ?
    ");
    $stmt_today->execute([$today, $today, $user_id]);
    $today_stats = $stmt_today->fetch();
    
    $stats['pending_today'] = $today_stats['pending_today'] ?? 0;
    $stats['completed_today'] = $today_stats['completed_today'] ?? 0;
    $stats['earnings_today'] = $today_stats['earnings_today'] ?? 0;
    
    // 2. Get all-time statistics for Pie Chart data
    $stmt_all_time = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_all_time,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_all_time,
            COUNT(*) as total_all_time
        FROM orders
        WHERE rider_id = ?
    ");
    $stmt_all_time->execute([$user_id]);
    $all_time_stats = $stmt_all_time->fetch();
    
    $stats['completed_all_time'] = $all_time_stats['completed_all_time'] ?? 0;
    $stats['cancelled_all_time'] = $all_time_stats['cancelled_all_time'] ?? 0;
    $stats['total_all_time'] = $all_time_stats['total_all_time'] ?? 0;
    
    // Calculate In-Progress/Other
    $stats['in_progress_all_time'] = $stats['total_all_time'] - $stats['completed_all_time'] - $stats['cancelled_all_time'];

    // 3. Get total earnings (all time)
    $stmt_total_earnings = $pdo->prepare("
        SELECT SUM(CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END) as total_earnings
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.rider_id = ? AND o.status = 'delivered'
    ");
    $stmt_total_earnings->execute([$user_id]);
    $total_earnings_result = $stmt_total_earnings->fetch();
    $stats['total_earnings'] = $total_earnings_result['total_earnings'] ?? 0;

    // 4. Get weekly earnings for bar chart (last 7 days)
    $stmt_weekly = $pdo->prepare("
        SELECT 
            DATE(d.delivered_at) as delivery_date,
            SUM(CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END) as daily_earnings,
            COUNT(*) as delivery_count
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.rider_id = ? 
            AND o.status = 'delivered' 
            AND d.delivered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(d.delivered_at)
        ORDER BY delivery_date ASC
    ");
    $stmt_weekly->execute([$user_id]);
    $weekly_data = $stmt_weekly->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in all 7 days (including days with no deliveries)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_name = date('D', strtotime($date));
        $weekly_earnings[$date] = [
            'date' => $date,
            'day' => $day_name,
            'earnings' => 0,
            'count' => 0
        ];
    }
    
    // Merge actual data
    foreach ($weekly_data as $row) {
        if (isset($weekly_earnings[$row['delivery_date']])) {
            $weekly_earnings[$row['delivery_date']]['earnings'] = floatval($row['daily_earnings']);
            $weekly_earnings[$row['delivery_date']]['count'] = intval($row['delivery_count']);
        }
    }

} catch (Exception $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
}

?>

<div class="container py-3">
  <h3 class="mb-4">👋 Welcome Back, Rider!</h3>
  
  <!-- Stats Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="border-left: 4px solid #FFC107; border-radius: 8px;">
        <small class="text-muted">🚴 Active Assignments</small>
        <p class="fs-3 fw-bold mb-0" style="color: #FFC107;"><?=$stats['pending_today']?></p>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="border-left: 4px solid #4CAF50; border-radius: 8px;">
        <small class="text-muted">✅ Completed Today</small>
        <p class="fs-3 fw-bold mb-0" style="color: #4CAF50;"><?=$stats['completed_today']?></p>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="border-left: 4px solid #2196F3; border-radius: 8px;">
        <small class="text-muted">💰 Earnings Today</small>
        <p class="fs-3 fw-bold mb-0" style="color: #2196F3;">₱<?=number_format($stats['earnings_today'], 2)?></p>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card shadow-sm p-3" style="border-left: 4px solid #9C27B0; border-radius: 8px;">
        <small class="text-muted">💎 Total Earnings</small>
        <p class="fs-3 fw-bold mb-0" style="color: #9C27B0;">₱<?=number_format($stats['total_earnings'], 2)?></p>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row g-3 mb-4">
    <!-- Pie Chart -->
    <div class="col-md-5">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">📊 Delivery Status Breakdown</h5>
        <div style="height: 280px; display: flex; align-items: center; justify-content: center;">
            <canvas id="deliveryChart"></canvas>
        </div>
        <div class="text-center mt-2">
          <small class="text-muted">Total Orders: <strong><?=$stats['total_all_time']?></strong></small>
        </div>
      </div>
    </div>

    <!-- Bar Chart - Weekly Earnings -->
    <div class="col-md-7">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">📈 Weekly Earnings (Last 7 Days)</h5>
        <div style="height: 280px;">
            <canvas id="weeklyEarningsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Key Metrics & Performance -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">🏆 Performance Summary</h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>📦 Total Orders Handled</span>
                <span class="badge bg-secondary rounded-pill fs-6"><?=$stats['total_all_time']?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>✅ Successful Deliveries</span>
                <span class="badge bg-success rounded-pill fs-6"><?=$stats['completed_all_time']?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>❌ Cancelled / Failed</span>
                <span class="badge bg-danger rounded-pill fs-6"><?=$stats['cancelled_all_time']?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>🔄 In Progress</span>
                <span class="badge bg-info rounded-pill fs-6"><?=$stats['in_progress_all_time']?></span>
            </li>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm p-3 h-100">
        <h5 class="mb-3">💵 Earnings Breakdown</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <tbody>
              <?php 
              $weekTotal = 0;
              foreach (array_reverse($weekly_earnings) as $day): 
                $weekTotal += $day['earnings'];
              ?>
              <tr>
                <td><strong><?=$day['day']?></strong> <small class="text-muted">(<?=date('M d', strtotime($day['date']))?>)</small></td>
                <td class="text-end">
                  <span class="badge bg-light text-dark"><?=$day['count']?> deliveries</span>
                </td>
                <td class="text-end fw-bold" style="color: #4CAF50;">₱<?=number_format($day['earnings'], 2)?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="table-warning">
                <td colspan="2"><strong>📅 This Week Total</strong></td>
                <td class="text-end fw-bold" style="color: #FF8B54; font-size: 1.1em;">₱<?=number_format($weekTotal, 2)?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

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
    }
  </style>

  <script>
  (function() {
    // Chart data from PHP
    const chartData = {
      completed: <?=$stats['completed_all_time']?>,
      cancelled: <?=$stats['cancelled_all_time']?>,
      in_progress: <?=$stats['in_progress_all_time']?>
    };

    const weeklyData = {
      labels: [<?php echo implode(',', array_map(function($d) { return "'" . $d['day'] . "'"; }, $weekly_earnings)); ?>],
      earnings: [<?php echo implode(',', array_map(function($d) { return $d['earnings']; }, $weekly_earnings)); ?>],
      counts: [<?php echo implode(',', array_map(function($d) { return $d['count']; }, $weekly_earnings)); ?>]
    };

    console.log('=== RIDER DASHBOARD CHART DEBUG ===');
    console.log('Chart.js loaded:', typeof Chart !== 'undefined');
    console.log('Chart data:', chartData);
    console.log('Weekly data:', weeklyData);

    function initRiderCharts() {
      if (typeof Chart === 'undefined') {
        console.log('⏳ Waiting for Chart.js...');
        setTimeout(initRiderCharts, 50);
        return;
      }

      console.log('✅ Chart.js ready, initializing rider charts...');

      // Destroy existing charts if they exist
      const existingPieChart = Chart.getChart('deliveryChart');
      if (existingPieChart) {
        console.log('🗑️ Destroying existing pie chart');
        existingPieChart.destroy();
      }
      const existingBarChart = Chart.getChart('weeklyEarningsChart');
      if (existingBarChart) {
        console.log('🗑️ Destroying existing bar chart');
        existingBarChart.destroy();
      }
      
      // 1. Delivery Status Pie/Doughnut Chart
      const pieCtx = document.getElementById('deliveryChart');
      if (pieCtx) {
        if (chartData.completed > 0 || chartData.cancelled > 0 || chartData.in_progress > 0) {
          console.log('📊 Creating delivery pie chart...');
          new Chart(pieCtx, {
            type: 'doughnut',
            data: {
              labels: ['✅ Completed', '❌ Cancelled', '🔄 In Progress'],
              datasets: [{
                data: [chartData.completed, chartData.cancelled, chartData.in_progress],
                backgroundColor: ['#4CAF50', '#FF5252', '#2196F3'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              cutout: '60%',
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: { padding: 15, usePointStyle: true }
                },
                tooltip: {
                  callbacks: {
                    label: function(context) {
                      const total = chartData.completed + chartData.cancelled + chartData.in_progress;
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
        } else {
          console.log('⚠️ No delivery data');
          pieCtx.parentElement.innerHTML = '<div class="text-center p-4 text-muted">📭 No delivery data yet</div>';
        }
      }
      
      // 2. Weekly Earnings Bar Chart
      const barCtx = document.getElementById('weeklyEarningsChart');
      if (barCtx) {
        console.log('📊 Creating weekly earnings bar chart...');
        new Chart(barCtx, {
          type: 'bar',
          data: {
            labels: weeklyData.labels,
            datasets: [{
              label: 'Earnings (₱)',
              data: weeklyData.earnings,
              backgroundColor: 'rgba(76, 175, 80, 0.7)',
              borderColor: '#4CAF50',
              borderWidth: 2,
              borderRadius: 6,
              barThickness: 35
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: function(value) {
                    return '₱' + value.toLocaleString();
                  }
                },
                grid: { color: 'rgba(0,0,0,0.05)' }
              },
              x: {
                grid: { display: false }
              }
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                callbacks: {
                  label: function(context) {
                    const idx = context.dataIndex;
                    return [
                      'Earnings: ₱' + context.raw.toLocaleString(),
                      'Deliveries: ' + weeklyData.counts[idx]
                    ];
                  }
                }
              }
            }
          }
        });
        console.log('✅ Bar chart created!');
      }
    }

    // Start initialization
    initRiderCharts();
  })();
  </script>