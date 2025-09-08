<!-- Data Quality & Performance Report -->
<div class="analytics-grid">
    <?php if (!empty($report_data['completeness'])): ?>
    <!-- Data Completeness Metrics -->
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['completeness']['total_records']); ?></div>
        <div class="metric-label">Total Records</div>
        <div class="metric-change">
            <i class="fas fa-database"></i> In selected period
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round(($report_data['completeness']['has_first_name'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</div>
        <div class="metric-label">Name Completeness</div>
        <div class="metric-change">
            <i class="fas fa-user"></i> Records with names
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round(($report_data['completeness']['has_disability_type'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</div>
        <div class="metric-label">Disability Data</div>
        <div class="metric-change">
            <i class="fas fa-hands-helping"></i> Complete disability info
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round(($report_data['completeness']['has_address'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</div>
        <div class="metric-label">Address Data</div>
        <div class="metric-change">
            <i class="fas fa-map-marker-alt"></i> Location information
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round($report_data['completeness']['avg_processing_days'], 1); ?></div>
        <div class="metric-label">Avg Processing Days</div>
        <div class="metric-change">
            <i class="fas fa-clock"></i> Application to validation
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-chart-bar"></i> Data Completeness Overview</h3>
        <div style="margin-top: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Names</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_first_name'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_first_name'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Birth Dates</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_birth_date'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_birth_date'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Disability Types</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_disability_type'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_disability_type'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Addresses</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_address'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_address'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Phone Numbers</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_phone'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_phone'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span>Barangay Assignment</span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($report_data['completeness']['has_barangay'] / max(1, $report_data['completeness']['total_records'])) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo round(($report_data['completeness']['has_barangay'] / max(1, $report_data['completeness']['total_records'])) * 100, 1); ?>%</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($report_data['monthly_performance'])): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-chart-line"></i> Monthly Performance Trends</h3>
        <div class="chart-container">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($report_data['monthly_performance'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-calendar-alt"></i> Monthly Performance Details</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>New Registrations</th>
                        <th>Validated</th>
                        <th>Validation Rate</th>
                        <th>Avg Processing Days</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['monthly_performance'] as $month): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($month['month']); ?></strong></td>
                        <td><?php echo number_format($month['new_registrations']); ?></td>
                        <td><?php echo number_format($month['validated_this_month']); ?></td>
                        <td>
                            <?php 
                            $validation_rate = $month['new_registrations'] > 0 ? 
                                ($month['validated_this_month'] / $month['new_registrations']) * 100 : 0;
                            ?>
                            <?php echo round($validation_rate, 1); ?>%
                        </td>
                        <td><?php echo round($month['avg_processing_days'], 1); ?> days</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(100, $validation_rate); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function initializeAnalyticsCharts() {
    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart');
    if (performanceCtx) {
        new Chart(performanceCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($report_data['monthly_performance'] ?? [], 'month')); ?>,
                datasets: [{
                    label: 'New Registrations',
                    data: <?php echo json_encode(array_column($report_data['monthly_performance'] ?? [], 'new_registrations')); ?>,
                    borderColor: '#2c5aa0',
                    backgroundColor: 'rgba(44, 90, 160, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Validated',
                    data: <?php echo json_encode(array_column($report_data['monthly_performance'] ?? [], 'validated_this_month')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}
</script>
