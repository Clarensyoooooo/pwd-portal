<!-- Demographics Report -->
<div class="analytics-grid">
    <!-- Summary Cards -->
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format(array_sum(array_column($report_data['barangay_profiles'] ?? [], 'total_individuals'))); ?></div>
        <div class="metric-label">Total Individuals</div>
        <div class="metric-change">
            <i class="fas fa-users"></i> In selected demographics
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo count($report_data['barangay_profiles'] ?? []); ?></div>
        <div class="metric-label">Barangays Covered</div>
        <div class="metric-change">
            <i class="fas fa-map"></i> Geographic areas
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo array_sum(array_column($report_data['barangay_profiles'] ?? [], 'male_count')); ?></div>
        <div class="metric-label">Male</div>
        <div class="metric-change">
            <i class="fas fa-male"></i> Gender distribution
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo array_sum(array_column($report_data['barangay_profiles'] ?? [], 'female_count')); ?></div>
        <div class="metric-label">Female</div>
        <div class="metric-change">
            <i class="fas fa-female"></i> Gender distribution
        </div>
    </div>
</div>

<!-- Demographics Charts -->
<div class="analytics-grid">
    <div class="analytics-card">
        <h3><i class="fas fa-chart-pie"></i> Gender Distribution by Barangay</h3>
        <div class="chart-container">
            <canvas id="genderByBarangayChart"></canvas>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-users"></i> Age Categories by Barangay</h3>
        <div class="chart-container">
            <canvas id="ageByBarangayChart"></canvas>
        </div>
    </div>
</div>

<!-- Barangay Profiles Table -->
<?php if (!empty($report_data['barangay_profiles'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-table"></i> Detailed Barangay Demographics</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Total</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Children</th>
                        <th>Avg Age</th>
                        <th>Active IDs</th>
                        <th>Coverage Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['barangay_profiles'] as $profile): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($profile['barangay']); ?></strong></td>
                        <td><?php echo number_format($profile['total_individuals']); ?></td>
                        <td><?php echo number_format($profile['male_count']); ?></td>
                        <td><?php echo number_format($profile['female_count']); ?></td>
                        <td><?php echo number_format($profile['children_count']); ?></td>
                        <td><?php echo round($profile['avg_age'], 1); ?> yrs</td>
                        <td><?php echo number_format($profile['active_ids']); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($profile['active_ids'] / max(1, $profile['total_individuals'])) * 100; ?>%"></div>
                            </div>
                            <small><?php echo round(($profile['active_ids'] / max(1, $profile['total_individuals'])) * 100, 1); ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Disability Distribution by Barangay -->
<?php if (!empty($report_data['disability_by_barangay'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-hands-helping"></i> Disability Types by Barangay</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Disability Type</th>
                        <th>Count</th>
                        <th>Visual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_barangay = '';
                    foreach ($report_data['disability_by_barangay'] as $item): 
                        $show_barangay = $current_barangay !== $item['barangay'];
                        $current_barangay = $item['barangay'];
                    ?>
                    <tr>
                        <td><?php echo $show_barangay ? '<strong>' . htmlspecialchars($item['barangay']) . '</strong>' : ''; ?></td>
                        <td><?php echo htmlspecialchars($item['disability_type']); ?></td>
                        <td><?php echo number_format($item['count']); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min(100, ($item['count'] / 10) * 100); ?>%"></div>
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
    // Gender by Barangay Chart
    const genderBarangayCtx = document.getElementById('genderByBarangayChart');
    if (genderBarangayCtx) {
        const barangays = <?php echo json_encode(array_column($report_data['barangay_profiles'] ?? [], 'barangay')); ?>;
        const maleData = <?php echo json_encode(array_column($report_data['barangay_profiles'] ?? [], 'male_count')); ?>;
        const femaleData = <?php echo json_encode(array_column($report_data['barangay_profiles'] ?? [], 'female_count')); ?>;
        
        new Chart(genderBarangayCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: barangays,
                datasets: [{
                    label: 'Male',
                    data: maleData,
                    backgroundColor: 'rgba(44, 90, 160, 0.8)',
                    borderColor: '#2c5aa0',
                    borderWidth: 1
                }, {
                    label: 'Female',
                    data: femaleData,
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
    
    // Age Categories by Barangay Chart
    const ageBarangayCtx = document.getElementById('ageByBarangayChart');
    if (ageBarangayCtx) {
        // Process age by barangay data
        const ageData = <?php echo json_encode($report_data['age_by_barangay'] ?? []); ?>;
        const barangays = [...new Set(ageData.map(item => item.barangay))];
        const ageCategories = ['Children', 'Adults', 'Seniors'];
        
        const datasets = ageCategories.map((category, index) => {
            const colors = ['#3b82f6', '#10b981', '#f59e0b'];
            return {
                label: category,
                data: barangays.map(barangay => {
                    const item = ageData.find(d => d.barangay === barangay && d.age_category === category);
                    return item ? item.count : 0;
                }),
                backgroundColor: colors[index] + '80',
                borderColor: colors[index],
                borderWidth: 1
            };
        });
        
        new Chart(ageBarangayCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: barangays,
                datasets: datasets
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
