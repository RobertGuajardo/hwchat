<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../lib/regions.php';
requireAuth();
if (!canAccessAnalytics()) { header('Location: index.php'); exit; }

$db = Database::db();
$isSuper = isSuperAdmin();

// ─── Period filter ───
$period = $_GET['period'] ?? 'month';
$after  = null;
$before = null;

if ($period === 'custom' && !empty($_GET['after'])) {
    $after  = $_GET['after'];
    $before = $_GET['before'] ?? date('Y-m-d');
} else {
    $today = date('Y-m-d');
    switch ($period) {
        case 'today':   $after = $today; break;
        case 'week':    $after = date('Y-m-d', strtotime('monday this week')); break;
        case 'month':   $after = date('Y-m-01'); break;
        case 'quarter':
            $q = (int)ceil(date('n') / 3);
            $after = date('Y') . '-' . str_pad(($q - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01';
            break;
        case 'year':    $after = date('Y-01-01'); break;
        case 'all':     $after = null; break;
        default:        $after = date('Y-m-01'); $period = 'month'; break;
    }
}

// ─── Tenant filter (scope-aware for superadmin) ───
if ($isSuper) {
    $scopeType = $_SESSION['scope_type'] ?? 'all';
    $tenantId  = $scopeType === 'tenant' ? ($_SESSION['scope_value'] ?? null) : null;
} else {
    $tenantId = getTenantId();
}

// Build query string for links
$qs = http_build_query(array_filter([
    'period' => $period,
    'after'  => $period === 'custom' ? $after : null,
    'before' => $period === 'custom' ? $before : null,
]));

// ─── Summary data ───
$summary = Database::getAnalyticsSummary($tenantId, $after, $before);

// Format duration
$durMin = (int)floor(($summary['avg_duration_sec'] ?? 0) / 60);
$durSec = ($summary['avg_duration_sec'] ?? 0) % 60;
$durationFormatted = $durMin > 0 ? "{$durMin}m {$durSec}s" : "{$durSec}s";

renderHead('Analytics');
renderNav('analytics');
?>
    <main class="container">

        <!-- ═══ Filters ═══ -->
        <form method="GET" class="filter-bar" style="margin-bottom:16px;">
            <label class="filter-label">PERIOD</label>
            <div class="filter-pills">
                <?php foreach (['today' => 'TODAY', 'week' => 'WEEK', 'month' => 'MONTH', 'quarter' => 'QTR', 'year' => 'YEAR', 'all' => 'ALL'] as $val => $label): ?>
                    <button type="submit" name="period" value="<?php echo $val; ?>" class="pill <?php echo $period === $val ? 'active' : ''; ?>"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="period" value="<?php echo e($period); ?>">
        </form>

        <!-- Custom date range (shown when custom or as a toggle) -->
        <form method="GET" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:24px;flex-wrap:wrap;">
            <div>
                <label class="form-label">FROM</label>
                <input type="date" name="after" class="form-input" style="width:160px;padding:6px 10px;font-size:12px;" value="<?php echo e($period === 'custom' ? ($after ?? '') : ''); ?>">
            </div>
            <div>
                <label class="form-label">TO</label>
                <input type="date" name="before" class="form-input" style="width:160px;padding:6px 10px;font-size:12px;" value="<?php echo e($period === 'custom' ? ($before ?? '') : ''); ?>">
            </div>
            <input type="hidden" name="period" value="custom">
            <button type="submit" class="btn btn-sm">APPLY</button>
            <div style="margin-left:auto;">
                <a href="export-analytics.php?<?php echo e($qs); ?>" class="btn btn-sm">EXPORT CSV</a>
            </div>
        </form>

        <!-- ═══ Summary Cards ═══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($summary['total_conversations']); ?></span>
                <span class="stat-label">CONVERSATIONS <span class="info-tip" data-tip="Total chatbot conversations with at least two messages in the selected period.">&#8505;</span></span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($summary['total_leads']); ?></span>
                <span class="stat-label">LEADS CAPTURED <span class="info-tip" data-tip="Conversations where the visitor submitted their contact information.">&#8505;</span></span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo number_format($summary['total_tours']); ?></span>
                <span class="stat-label">TOURS BOOKED <span class="info-tip" data-tip="Conversations where the visitor scheduled a tour through the chatbot.">&#8505;</span></span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $durationFormatted; ?></span>
                <span class="stat-label">AVG DURATION <span class="info-tip" data-tip="Average time from first message to last message per conversation.">&#8505;</span></span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $summary['lead_capture_rate']; ?>%</span>
                <span class="stat-label">LEAD RATE <span class="info-tip" data-tip="Percentage of conversations that resulted in a captured lead.">&#8505;</span></span>
            </div>
        </div>

        <!-- ═══ Charts ═══ -->
        <style>
        .info-tip {
            display: inline;
            cursor: help;
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-left: 4px;
            position: relative;
        }
        .info-tip:hover::after {
            content: attr(data-tip);
            position: absolute;
            left: 50%;
            bottom: calc(100% + 6px);
            transform: translateX(-50%);
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 400;
            letter-spacing: normal;
            text-transform: none;
            max-width: 280px;
            width: max-content;
            white-space: normal;
            line-height: 1.4;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            pointer-events: none;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
        }
        .chart-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 20px;
        }
        .chart-card h3 {
            font-size: 12px;
            color: var(--text-muted);
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }
        .chart-card canvas {
            width: 100% !important;
            max-height: 280px;
        }
        .chart-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 48px 16px;
            font-size: 13px;
        }
        .chart-card-wide {
            grid-column: 1 / -1;
        }
        @media (max-width: 768px) {
            .chart-grid { grid-template-columns: 1fr; }
        }
        </style>

        <div class="chart-grid">
            <div class="chart-card chart-card-wide">
                <h3>CONVERSATIONS OVER TIME <span class="info-tip" data-tip="Daily conversation volume. Spikes often correlate with marketing campaigns or events.">&#8505;</span></h3>
                <canvas id="chart-conversations"></canvas>
            </div>
            <div class="chart-card">
                <h3>TOPICS <span class="info-tip" data-tip="What visitors asked about most. Each conversation can have multiple topics.">&#8505;</span></h3>
                <canvas id="chart-topics"></canvas>
            </div>
            <div class="chart-card">
                <h3>BUYER INTENT <span class="info-tip" data-tip="How serious visitors appeared: browsing, interested, or ready to buy.">&#8505;</span></h3>
                <canvas id="chart-intent"></canvas>
            </div>
            <div class="chart-card">
                <h3>SENTIMENT <span class="info-tip" data-tip="Emotional tone of conversations: positive, neutral, or negative.">&#8505;</span></h3>
                <canvas id="chart-sentiment"></canvas>
            </div>
            <div class="chart-card">
                <h3>PRICE RANGES <span class="info-tip" data-tip="Price ranges visitors searched for or asked about.">&#8505;</span></h3>
                <canvas id="chart-price-ranges"></canvas>
            </div>
            <div class="chart-card">
                <h3>OBJECTIONS <span class="info-tip" data-tip="Common concerns or pushback raised by visitors.">&#8505;</span></h3>
                <canvas id="chart-objections"></canvas>
            </div>
            <div class="chart-card">
                <h3>BUILDERS MENTIONED <span class="info-tip" data-tip="Which builders visitors asked about most frequently.">&#8505;</span></h3>
                <canvas id="chart-builders"></canvas>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function() {
            // ─── Theme-aware defaults ───
            var cs = getComputedStyle(document.documentElement);
            Chart.defaults.color = cs.getPropertyValue('--text-muted').trim() || '#6B7A94';
            Chart.defaults.borderColor = cs.getPropertyValue('--border').trim() || 'rgba(255,255,255,0.06)';

            var filters = {
                tenant: <?php echo json_encode($tenantId); ?>,
                after:  <?php echo json_encode($after); ?>,
                before: <?php echo json_encode($before); ?>
            };

            var charts = {};

            // ─── Color palettes ───
            var barColors = ['#3B7DD8','#5B9BE6','#4A8C5C','#68A97A','#C9A96E','#A78BFA','#f87171',
                             '#fbbf24','#34d399','#818cf8','#fb923c','#e879f9','#22d3ee','#a3e635','#f472b6'];
            var intentColors  = { browsing: '#6B7A94', interested: '#3B7DD8', ready_to_buy: '#4A8C5C' };
            var sentimentColors = { positive: '#4A8C5C', neutral: '#6B7A94', negative: '#C85555' };

            function buildUrl(chartType) {
                var params = 'chart=' + encodeURIComponent(chartType);
                if (filters.tenant) params += '&tenant=' + encodeURIComponent(filters.tenant);
                if (filters.after)  params += '&after='  + encodeURIComponent(filters.after);
                if (filters.before) params += '&before=' + encodeURIComponent(filters.before);
                return 'api-analytics.php?' + params;
            }

            function showEmpty(canvasId) {
                var canvas = document.getElementById(canvasId);
                var div = document.createElement('div');
                div.className = 'chart-empty';
                div.textContent = 'No data for this period';
                canvas.parentNode.replaceChild(div, canvas);
            }

            function hasData(d) {
                return d && d.labels && d.labels.length > 0;
            }

            function loadChart(canvasId, chartType, builder) {
                fetch(buildUrl(chartType))
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (!resp.success || !hasData(resp.data)) {
                            showEmpty(canvasId);
                            return;
                        }
                        if (charts[canvasId]) charts[canvasId].destroy();
                        charts[canvasId] = builder(canvasId, resp.data);
                    })
                    .catch(function() { showEmpty(canvasId); });
            }

            // ─── Chart builders ───

            loadChart('chart-conversations', 'conversations_over_time', function(id, data) {
                return new Chart(document.getElementById(id), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Conversations',
                            data: data.datasets[0].data,
                            borderColor: '#3B7DD8',
                            backgroundColor: 'rgba(59,125,216,0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: '#3B7DD8'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            });

            loadChart('chart-topics', 'topics', function(id, data) {
                return new Chart(document.getElementById(id), {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.datasets[0].data,
                            backgroundColor: barColors.slice(0, data.labels.length)
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            });

            loadChart('chart-intent', 'intent', function(id, data) {
                var colors = data.labels.map(function(l) { return intentColors[l] || '#6B7A94'; });
                return new Chart(document.getElementById(id), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{ data: data.datasets[0].data, backgroundColor: colors, borderWidth: 0 }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle' } }
                        }
                    }
                });
            });

            loadChart('chart-sentiment', 'sentiment', function(id, data) {
                var colors = data.labels.map(function(l) { return sentimentColors[l] || '#6B7A94'; });
                return new Chart(document.getElementById(id), {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{ data: data.datasets[0].data, backgroundColor: colors, borderWidth: 0 }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle' } }
                        }
                    }
                });
            });

            loadChart('chart-price-ranges', 'price_ranges', function(id, data) {
                return new Chart(document.getElementById(id), {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.datasets[0].data,
                            backgroundColor: ['#3B7DD8','#5B9BE6','#4A8C5C','#C9A96E']
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            });

            loadChart('chart-objections', 'objections', function(id, data) {
                return new Chart(document.getElementById(id), {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.datasets[0].data,
                            backgroundColor: '#C85555'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            });

            loadChart('chart-builders', 'builders', function(id, data) {
                return new Chart(document.getElementById(id), {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.datasets[0].data,
                            backgroundColor: barColors.slice(0, data.labels.length)
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            });

        })();
        </script>

    </main>
<?php renderFooter(); ?>
