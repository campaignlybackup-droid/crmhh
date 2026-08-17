<?php
require_once 'functions.php';
requireSuperAdmin(); // Reports are for SuperAdmin only

// ============================================
// 1. REVENUE KPI & CHART DATA
// ============================================

// A. Overview Cards
$totalRevenueStmt = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'Paid'");
$totalRevenue = $totalRevenueStmt->fetchColumn() ?: 0;

$pendingRevenueStmt = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status != 'Paid'");
$pendingRevenue = $pendingRevenueStmt->fetchColumn() ?: 0;

// B. Monthly Revenue Chart (Using payment_date)
// We get the last 6 months of revenue based on payment_date
$monthlyRevenueQuery = "
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month, 
        SUM(amount) as total 
    FROM invoices 
    WHERE status = 'Paid' AND payment_date IS NOT NULL 
        AND payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month ASC
";
$monthlyRevenueData = $pdo->query($monthlyRevenueQuery)->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$labels = [];
$dataPoints = [];

// Fill in missing months to ensure a continuous 6-month line/bar
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $labels[] = date('M Y', strtotime("-$i months")); // e.g., "Aug 2026"
    
    // Find revenue for this month
    $val = 0;
    foreach ($monthlyRevenueData as $row) {
        if ($row['month'] === $m) {
            $val = $row['total'];
            break;
        }
    }
    $dataPoints[] = $val;
}

// ============================================
// 2. LEAD CONVERSION RATE
// ============================================
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$wonLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'Won'")->fetchColumn();
$conversionRate = ($totalLeads > 0) ? round(($wonLeads / $totalLeads) * 100, 1) : 0;

// ============================================
// 3. TEAM PERFORMANCE (Tasks & Converted Leads)
// ============================================
$teamQuery = "
    SELECT 
        u.id,
        u.username, 
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) as total_tasks,
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'Done') as completed_tasks,
        (SELECT COUNT(*) FROM leads l WHERE l.assigned_to = u.id AND l.status = 'Won' AND l.deleted_at IS NULL) as won_leads,
        (SELECT COALESCE(SUM(deal_value), 0) FROM leads l WHERE l.assigned_to = u.id AND l.status = 'Won' AND l.deleted_at IS NULL) as won_value,
        (SELECT COUNT(*) FROM clients c WHERE c.assigned_to = u.id AND c.deleted_at IS NULL) as assigned_clients
    FROM users u 
    WHERE u.deleted_at IS NULL
    ORDER BY won_value DESC, completed_tasks DESC
";
$teamPerformance = $pdo->query($teamQuery)->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Global Analytics</h3>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-success text-success me-3">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold">$<?= number_format($totalRevenue, 2) ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Total Revenue</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-warning text-warning me-3">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold">$<?= number_format($pendingRevenue, 2) ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Pending Payments</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-primary text-primary me-3">
                    <i class="bi bi-funnel-fill"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $conversionRate ?>%</h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Lead Conversion Rate</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card widget-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="widget-icon bg-soft-info text-info me-3">
                    <i class="bi bi-check2-all"></i>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?= $totalLeads ?></h3>
                    <p class="text-muted small mb-0 fw-semibold text-uppercase">Total Leads Generated</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light border-0">
                <h6 class="fw-bold mb-0">Monthly Revenue (6 Months)</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Team Performance Leaderboard -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Team Leaderboard</h6>
                <a href="team_dashboard.php" class="text-decoration-none small">Full View <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Employee</th>
                                <th class="text-center">Leads Converted</th>
                                <th class="text-end pe-3">Tasks Done</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($teamPerformance)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No team performance data.</td></tr>
                            <?php else: ?>
                                <?php foreach($teamPerformance as $member): 
                                    $pct = ($member['total_tasks'] > 0) ? round(($member['completed_tasks'] / $member['total_tasks']) * 100) : 0;
                                    $bg = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold">
                                        <a href="team_dashboard.php?user_id=<?= $member['id'] ?>" class="text-decoration-none text-dark"><?= h($member['username']) ?></a>
                                        <div class="small text-muted fw-normal"><?= $member['assigned_clients'] ?> Clients</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-success text-success fw-bold"><?= $member['won_leads'] ?> Won</span>
                                        <div class="small text-muted" style="font-size:0.75rem;">AED <?= number_format($member['won_value']) ?></div>
                                    </td>
                                    <td class="text-end pe-3">
                                        <span class="fw-bold text-dark"><?= $member['completed_tasks'] ?>/<?= $member['total_tasks'] ?></span>
                                        <div class="progress mt-1 ms-auto" style="height: 5px; width: 60px;">
                                            <div class="progress-bar <?= $bg ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    // Theme Colors
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const textColor = isDark ? '#adb5bd' : '#6c757d';
    const gridColor = isDark ? '#343a40' : '#e9ecef';
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?= json_encode($dataPoints) ?>,
                backgroundColor: 'rgba(67, 97, 238, 0.8)',
                borderColor: 'rgba(67, 97, 238, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed.y;
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: { color: textColor, callback: function(value) { return '$' + value.toLocaleString(); } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: textColor }
                }
            }
        }
    });
});
</script>

<?php include 'footer.php'; ?>
