<!-- Services & Support by Area Report -->
<div class="analytics-grid">
    <?php if (!empty($report_data['service_utilization'])): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-chart-pie"></i> Service Distribution</h3>
        <div class="chart-container">
            <canvas id="serviceDistributionChart"></canvas>
        </div>
        <div style="margin-top: 1rem;">
            <?php foreach ($report_data['service_utilization'] as $service): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; font-size: 0.9rem;">
                <span><?php echo htmlspecialchars($service['disability_type']); ?></span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($service['total_individuals'] / max(1, max(array_column($report_data['service_utilization'], 'total_individuals')))) * 100; ?>%"></div>
                    </div>
                </div>
                <span><?php echo $service['total_individuals']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-briefcase"></i> Employment Outcomes</h3>
        <div class="chart-container">
            <canvas id="employmentChart"></canvas>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-child"></i> Children & Youth Services</h3>
        <div style="margin-top: 1rem;">
            <?php 
            $total_children = array_sum(array_column($report_data['service_utilization'], 'children_count'));
            $total_individuals = array_sum(array_column($report_data['service_utilization'], 'total_individuals'));
            $children_percentage = $total_individuals > 0 ? ($total_children / $total_individuals) * 100 : 0;
            ?>
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; font-weight: bold; color: #f59e0b;"><?php echo number_format($total_children); ?></div>
                <div style="color: #64748b;">Children & Youth Served</div>
                <div style="font-size: 1.2rem; color: #d97706; margin-top: 0.5rem;">
                    <?php echo round($children_percentage, 1); ?>% of total beneficiaries
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($report_data['barangay_services'])): ?>
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-hands-helping"></i> Service Utilization by Barangay</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Disability Type</th>
                        <th>Individuals Served</th>
                        <th>Active Beneficiaries</th>
                        <th>Employed</th>
                        <th>Employment Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['barangay_services'] as $service): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($service['barangay']); ?></strong></td>
                        <td><?php echo htmlspecialchars($service['disability_type']); ?></td>
                        <td><?php echo number_format($service['individuals_served']); ?></td>
                        <td><?php echo number_format($service['active_beneficiaries']); ?></td>
                        <td><?php echo number_format($service['employed_count']); ?></td>
                        <td>
                            <?php 
                            $employment_rate = $service['individuals_served'] > 0 ? 
                                ($service['employed_count'] / $service['individuals_served']) * 100 : 0;
                            ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $employment_rate; ?>%"></div>
                            </div>
                            <small><?php echo round($employment_rate, 1); ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function initializeAnalyticsCharts() {
    // Service Distribution Chart
    const serviceCtx = document.getElementById('serviceDistributionChart');
    if (serviceCtx) {
        new Chart(serviceCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($report_data['service_utilization'] ?? [], 'disability_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($report_data['service_utilization'] ?? [], 'total_individuals')); ?>,
                    backgroundColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Employment Chart
    const employmentCtx = document.getElementById('employmentChart');
    if (employmentCtx) {
        new Chart(employmentCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($report_data['service_utilization'] ?? [], 'disability_type')); ?>,
                datasets: [{
                    label: 'Total Individuals',
                    data: <?php echo json_encode(array_column($report_data['service_utilization'] ?? [], 'total_individuals')); ?>,
                    backgroundColor: 'rgba(44, 90, 160, 0.3)',
                    borderColor: '#2c5aa0',
                    borderWidth: 1
                }, {
                    label: 'Employed',
                    data: <?php echo json_encode(array_column($report_data['service_utilization'] ?? [], 'employed_count')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
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
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            }
        });
    }
}
</script>
