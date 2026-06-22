<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';

if (!$user_id || $role !== 'rider') {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

$weekData = [];
$weekTotal = 0;
$dailyAvg = 0;
$perOrder = 0;
$totalOrders = 0;

try {
    $pdo = getPDO();


    $stmt = $pdo->prepare("
        SELECT DATE(d.delivered_at) AS day,
               SUM(CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END) AS earnings,
               COUNT(*) AS deliveries
        FROM orders o
        JOIN deliveries d ON d.order_id = o.id
        WHERE o.rider_id = ? AND o.status = 'delivered' AND d.delivered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(d.delivered_at)
    ");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $weekData[$date] = [
            'label' => date('D', strtotime($date)),
            'earnings' => 0,
            'deliveries' => 0,
        ];
    }

    foreach ($rows as $r) {
        $day = $r['day'];
        if (isset($weekData[$day])) {
            $weekData[$day]['earnings'] = floatval($r['earnings']);
            $weekData[$day]['deliveries'] = intval($r['deliveries']);
        }
    }


    foreach ($weekData as $d) {
        $weekTotal += $d['earnings'];
    }
    $dailyAvg = $weekTotal / 7;


    $stmtTotal = $pdo->prepare("
        SELECT 
            COUNT(*) AS delivered_count,
            SUM(CASE WHEN d.amount IS NULL OR d.amount = 0 THEN o.shipping_fee ELSE d.amount END) AS total_earnings
        FROM orders o
        LEFT JOIN deliveries d ON d.order_id = o.id
        WHERE o.rider_id = ? AND o.status = 'delivered'
    ");
    $stmtTotal->execute([$user_id]);
    $tot = $stmtTotal->fetch(PDO::FETCH_ASSOC) ?: ['delivered_count' => 0, 'total_earnings' => 0];
    $totalOrders = intval($tot['delivered_count']);
    $perOrder = $totalOrders > 0 ? floatval($tot['total_earnings']) / $totalOrders : 0;

} catch (Exception $e) {
    error_log('Rider earnings error: ' . $e->getMessage());
}


$chartLabels = [];
$chartValues = [];
foreach ($weekData as $date => $d) {
    $chartLabels[] = $d['label'] . "\n" . date('M d', strtotime($date));
    $chartValues[] = round($d['earnings'], 2);
}
?>

<div class="container-fluid">
  <div class="mb-4">
    <h3 class="mb-0">Earnings</h3>
    <p class="text-muted">Track your income and performance</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm">
        <small class="text-muted">This Week</small>
        <h4 class="mt-2" id="weekTotal">₱<?=number_format($weekTotal, 2)?></h4>
        <div class="float-end bg-light rounded p-2"><i class="bi bi-cash-coin"></i></div>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm">
        <small class="text-muted">Daily Average</small>
        <h4 class="mt-2" id="dailyAvg">₱<?=number_format($dailyAvg, 2)?></h4>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm">
        <small class="text-muted">Per Order</small>
        <h4 class="mt-2" id="perOrder">₱<?=number_format($perOrder, 2)?></h4>
      </div>
    </div>
    <div class="col-sm-6 col-md-3">
      <div class="card p-3 shadow-sm">
        <small class="text-muted">Total Delivered</small>
        <h4 class="mt-2" id="totalOrders"><?=number_format($totalOrders)?></h4>
      </div>
    </div>
  </div>

  <div class="card mb-4 p-3">
    <div class="mt-2">
      <h6 class="mb-3">Daily Earnings Trend (7 days)</h6>
      <div class="card p-3 shadow-sm">
        <canvas id="earningsChart" style="width:100%;height:260px"></canvas>
      </div>
    </div>
  </div>

</div>

<script>
(function() {
  const labels = <?=json_encode(array_values($chartLabels))?>;
  const data = <?=json_encode(array_values($chartValues))?>;
  
  console.log('=== RIDER EARNINGS CHART DEBUG ===');
  console.log('Chart.js loaded:', typeof Chart !== 'undefined');
  console.log('Labels:', labels);
  console.log('Data:', data);

  function initEarningsChart() {
    if (typeof Chart === 'undefined') {
      console.log('⏳ Waiting for Chart.js...');
      setTimeout(initEarningsChart, 50);
      return;
    }

    console.log('✅ Chart.js ready, initializing earnings chart...');


    const existingChart = Chart.getChart('earningsChart');
    if (existingChart) {
      console.log('🗑️ Destroying existing earnings chart');
      existingChart.destroy();
    }

    const ctx = document.getElementById('earningsChart');
    if (ctx) {
      console.log('📊 Creating earnings bar chart...');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Daily Earnings',
            data: data,
            backgroundColor: 'rgba(76, 175, 80, 0.7)',
            borderColor: '#4CAF50',
            borderWidth: 2,
            borderRadius: 6,
            barThickness: 40
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
              titleFont: { size: 13 },
              bodyFont: { size: 14 },
              callbacks: {
                label: function(context) {
                  return 'Earnings: ₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
              }
            }
          }
        }
      });
      console.log('✅ Earnings chart created!');
    } else {
      console.error('❌ Canvas element #earningsChart not found');
    }
  }


  initEarningsChart();
})();
</script>
