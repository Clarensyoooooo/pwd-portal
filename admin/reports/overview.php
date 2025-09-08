<!-- Analytics Overview Report -->
<div class="analytics-grid">
    <!-- Key Metrics -->
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['summary']['total_individuals']); ?></div>
        <div class="metric-label">Individuals Registered</div>
        <div class="metric-change positive">
            <i class="fas fa-arrow-up"></i> Active in our community
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['summary']['active_ids']); ?></div>
        <div class="metric-label">Active PWD IDs</div>
        <div class="metric-change positive">
            <i class="fas fa-id-card"></i> Currently supported
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round($report_data['summary']['avg_age'], 1); ?></div>
        <div class="metric-label">Average Age</div>
        <div class="metric-change">
            <i class="fas fa-users"></i> Community profile
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['summary']['pending_support']); ?></div>
        <div class="metric-label">Awaiting Support</div>
        <div class="metric-change">
            <i class="fas fa-clock"></i> Processing applications
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="analytics-grid">
    <div class="analytics-card">
        <h3><i class="fas fa-birthday-cake"></i> Age Group Distribution</h3>
        <div class="chart-container">
            <canvas id="ageGroupChart"></canvas>
        </div>
        <div class="data-table-container" style="max-height: 200px; overflow-y: auto; margin-top: 1rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Age Group</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['age_groups'] as $group): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($group['age_group']); ?></td>
                        <td><?php echo number_format($group['count']); ?></td>
                        <td><?php echo $group['percentage']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3>
        <div class="chart-container">
            <canvas id="genderChart"></canvas>
        </div>
        <div style="margin-top: 1rem;">
            <?php foreach ($report_data['gender_distribution'] as $gender): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span><?php echo htmlspecialchars($gender['gender']); ?></span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $gender['percentage']; ?>%"></div>
                    </div>
                </div>
                <span><?php echo $gender['percentage']; ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-hands-helping"></i> Support Categories</h3>
        <div class="chart-container">
            <canvas id="supportChart"></canvas>
        </div>
        <div style="margin-top: 1rem;">
            <?php foreach (array_slice($report_data['support_categories'], 0, 5) as $category): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($category['support_category']); ?></span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $category['percentage']; ?>%"></div>
                    </div>
                </div>
                <span><?php echo $category['count']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-chart-line"></i> Registration Trends</h3>
        <div class="chart-container">
            <canvas id="trendsChart"></canvas>
        </div>
        <div style="margin-top: 1rem; font-size: 0.9rem; color: #64748b;">
            <p><i class="fas fa-info-circle"></i> Tracking community growth and service delivery over time</p>
        </div>
    </div>
</div>

<script>
function initializeAnalyticsCharts() {
    // Age Group Chart
    const ageCtx = document.getElementById('ageGroupChart').getContext('2d');
    new Chart(ageCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($report_data['age_groups'], 'age_group')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($report_data['age_groups'], 'count')); ?>,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'
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
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    
    // Gender Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($report_data['gender_distribution'], 'gender')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($report_data['gender_distribution'], 'count')); ?>,
                backgroundColor: ['#2c5aa0', '#10b981', '#f59e0b'],
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
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
    
    // Support Categories Chart
    const supportCtx = document.getElementById('supportChart').getContext('2d');
    new Chart(supportCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($report_data['support_categories'], 'support_category')); ?>,
            datasets: [{
                label: 'Individuals',
                data: <?php echo json_encode(array_column($report_data['support_categories'], 'count')); ?>,
                backgroundColor: 'rgba(44, 90, 160, 0.8)',
                borderColor: '#2c5aa0',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
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
    
    // Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['trends'], 'period')); ?>,
            datasets: [{
                label: 'New Registrations',
                data: <?php echo json_encode(array_column($report_data['trends'], 'registrations')); ?>,
                borderColor: '#2c5aa0',
                backgroundColor: 'rgba(44, 90, 160, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'IDs Issued',
                data: <?php echo json_encode(array_column($report_data['trends'], 'ids_issued')); ?>,
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
</script>
