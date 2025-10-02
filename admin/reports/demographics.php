Demographics Report 
<div class="analytics-grid">
     Summary Cards 
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format(array_sum(array_column($report_data['barangay_profiles'] ?? [], 'total_individuals'))); ?></div>
        <div class="metric-label">Community Members</div>
        <div class="metric-change">
            <i class="fas fa-users"></i> Across all areas
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo count($report_data['barangay_profiles'] ?? []); ?></div>
        <div class="metric-label">Geographic Areas</div>
        <div class="metric-change">
            <i class="fas fa-map"></i> Barangays represented
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value">
            <?php 
            $all_male = array_sum(array_column($report_data['barangay_profiles'] ?? [], 'male_count'));
            $all_female = array_sum(array_column($report_data['barangay_profiles'] ?? [], 'female_count'));
            $all_total = $all_male + $all_female;
            $gender_ratio = $all_total > 0 ? round(($all_male / $all_total) * 100) : 50;
            echo $gender_ratio . '%';
            ?>
        </div>
        <div class="metric-label">Male Representation</div>
        <div class="metric-change">
            <i class="fas fa-male"></i> <?php echo number_format($all_male); ?> members
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value">
            <?php 
            $female_ratio = $all_total > 0 ? round(($all_female / $all_total) * 100) : 50;
            echo $female_ratio . '%';
            ?>
        </div>
        <div class="metric-label">Female Representation</div>
        <div class="metric-change">
            <i class="fas fa-female"></i> <?php echo number_format($all_female); ?> members
        </div>
    </div>
</div>

 Narrative Insights 
<div class="analytics-card" style="grid-column: 1 / -1; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b;">
    <h3><i class="fas fa-info-circle"></i> Demographic Insights</h3>
    <div style="line-height: 1.8; color: #92400e;">
        <?php
        // Find areas with unique characteristics
        $total_across_all = array_sum(array_column($report_data['barangay_profiles'] ?? [], 'total_individuals'));
        $unique_characteristics = [];
        
        foreach ($report_data['barangay_profiles'] ?? [] as $profile) {
            $children_pct = $profile['total_individuals'] > 0 ? ($profile['children_count'] / $profile['total_individuals']) * 100 : 0;
            $coverage_rate = $profile['total_individuals'] > 0 ? ($profile['active_ids'] / $profile['total_individuals']) * 100 : 0;
            $share_of_total = $total_across_all > 0 ? ($profile['total_individuals'] / $total_across_all) * 100 : 0;
            
            if ($children_pct > 30) {
                $unique_characteristics[] = $profile['barangay'] . ' has a notable young population (' . round($children_pct) . '% children), suggesting strong need for educational and developmental support services.';
            }
            
            if ($profile['avg_age'] > 40) {
                $unique_characteristics[] = $profile['barangay'] . ' shows an older average age profile (' . round($profile['avg_age'], 1) . ' years), which may indicate different service priorities focused on mature adult needs.';
            }
            
            if ($share_of_total > 15) {
                $unique_characteristics[] = $profile['barangay'] . ' represents ' . round($share_of_total) . '% of the total registered community, making it a significant service area.';
            }
        }
        ?>
        
        <p style="margin: 0.5rem 0;">
            <strong>Geographic Distribution:</strong> 
            Community members are distributed across <?php echo count($report_data['barangay_profiles'] ?? []); ?> barangays, with each area showing unique demographic patterns that help inform localized service delivery.
        </p>
        
        <?php if (!empty($unique_characteristics)): ?>
            <?php foreach (array_slice($unique_characteristics, 0, 3) as $characteristic): ?>
                <p style="margin: 0.5rem 0;">• <?php echo $characteristic; ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <p style="margin: 0.5rem 0;">
            <strong>Gender Balance:</strong> 
            The community shows <?php echo abs($gender_ratio - 50) < 10 ? 'balanced' : 'diverse'; ?> gender representation with <?php echo $gender_ratio; ?>% male and <?php echo $female_ratio; ?>% female members across all areas.
        </p>
        
        <?php
        // Analyze disability distribution balance
        $disability_counts = [];
        foreach ($report_data['disability_by_barangay'] ?? [] as $item) {
            if (!isset($disability_counts[$item['disability_type']])) {
                $disability_counts[$item['disability_type']] = 0;
            }
            $disability_counts[$item['disability_type']]++;
        }
        $avg_barangays_per_disability = count($disability_counts) > 0 ? count($report_data['barangay_profiles'] ?? []) / count($disability_counts) : 0;
        ?>
        
        <p style="margin: 0.5rem 0;">
            <strong>Disability Distribution:</strong> 
            <?php if ($avg_barangays_per_disability > 2): ?>
                Different disability types are represented across multiple barangays, showing a balanced distribution that supports diverse service needs in various communities.
            <?php else: ?>
                Disability types show varied geographic patterns, helping identify where specialized services may be most beneficial.
            <?php endif; ?>
        </p>
    </div>
</div>

 Cross-Demographic Visualizations 
<div class="analytics-grid">
    <div class="analytics-card">
        <h3><i class="fas fa-chart-pie"></i> Gender × Barangay Distribution</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Shows community diversity across geographic areas</p>
        <div class="chart-container">
            <canvas id="genderByBarangayChart"></canvas>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-users"></i> Age Groups Across Areas</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Understanding age distribution helps tailor age-appropriate services</p>
        <div class="chart-container">
            <canvas id="ageByBarangayChart"></canvas>
        </div>
    </div>
</div>

 Barangay Profiles Table 
<?php if (!empty($report_data['barangay_profiles'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-table"></i> Detailed Area Profiles</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Comparative overview of demographic characteristics across areas</p>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Total Members</th>
                        <th>Share of Community</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Children (0-17)</th>
                        <th>Avg Age</th>
                        <th>ID Coverage Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_all = array_sum(array_column($report_data['barangay_profiles'], 'total_individuals'));
                    foreach ($report_data['barangay_profiles'] as $profile): 
                        $share_pct = $total_all > 0 ? round(($profile['total_individuals'] / $total_all) * 100, 1) : 0;
                        $children_pct = $profile['total_individuals'] > 0 ? round(($profile['children_count'] / $profile['total_individuals']) * 100) : 0;
                        $coverage_pct = $profile['total_individuals'] > 0 ? round(($profile['active_ids'] / $profile['total_individuals']) * 100) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($profile['barangay']); ?></strong></td>
                        <td><?php echo number_format($profile['total_individuals']); ?></td>
                        <td>
                            <span style="font-weight: 600; color: #2c5aa0;"><?php echo $share_pct; ?>%</span>
                        </td>
                        <td><?php echo number_format($profile['male_count']); ?></td>
                        <td><?php echo number_format($profile['female_count']); ?></td>
                        <td>
                            <?php echo number_format($profile['children_count']); ?>
                            <span style="color: #64748b; font-size: 0.85rem;">(<?php echo $children_pct; ?>%)</span>
                        </td>
                        <td><?php echo round($profile['avg_age'], 1); ?> yrs</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: <?php echo $coverage_pct; ?>%"></div>
                                </div>
                                <span style="min-width: 3rem; text-align: right; font-weight: 600; color: #10b981;">
                                    <?php echo $coverage_pct; ?>%
                                </span>
                            </div>
                            <small style="color: #64748b;"><?php echo number_format($profile['active_ids']); ?> of <?php echo number_format($profile['total_individuals']); ?> members</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

 Disability Characteristics by Area 
<?php if (!empty($report_data['disability_by_barangay'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-hands-helping"></i> Disability Type Characteristics Across Areas</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Understanding the distribution of support needs across different communities</p>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Disability Type</th>
                        <th>Count</th>
                        <th>Share in Area</th>
                        <th>Visual Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_barangay = '';
                    $barangay_totals = [];
                    
                    // Calculate totals per barangay first
                    foreach ($report_data['disability_by_barangay'] as $item) {
                        if (!isset($barangay_totals[$item['barangay']])) {
                            $barangay_totals[$item['barangay']] = 0;
                        }
                        $barangay_totals[$item['barangay']] += $item['count'];
                    }
                    
                    foreach ($report_data['disability_by_barangay'] as $item): 
                        $show_barangay = $current_barangay !== $item['barangay'];
                        $current_barangay = $item['barangay'];
                        $barangay_total = $barangay_totals[$item['barangay']] ?? 1;
                        $share_in_area = round(($item['count'] / $barangay_total) * 100, 1);
                    ?>
                    <tr>
                        <td><?php echo $show_barangay ? '<strong>' . htmlspecialchars($item['barangay']) . '</strong>' : ''; ?></td>
                        <td><?php echo htmlspecialchars($item['disability_type']); ?></td>
                        <td><?php echo number_format($item['count']); ?></td>
                        <td>
                            <span style="font-weight: 600; color: #2c5aa0;"><?php echo $share_in_area; ?>%</span>
                            <span style="color: #64748b; font-size: 0.85rem;"> of area total</span>
                        </td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $share_in_area; ?>%"></div>
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
    // Gender by Barangay Stacked Chart
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
                    label: 'Male Members',
                    data: maleData,
                    backgroundColor: 'rgba(44, 90, 160, 0.8)',
                    borderColor: '#2c5aa0',
                    borderWidth: 1
                }, {
                    label: 'Female Members',
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
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const datasetLabel = context.dataset.label;
                                const value = context.parsed.y;
                                const total = maleData[context.dataIndex] + femaleData[context.dataIndex];
                                const percentage = ((value / total) * 100).toFixed(1);
                                return datasetLabel + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        title: {
                            display: true,
                            text: 'Number of Members'
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        },
                        title: {
                            display: true,
                            text: 'Barangay'
                        }
                    }
                }
            }
        });
    }
    
    // Age Categories by Barangay
    const ageBarangayCtx = document.getElementById('ageByBarangayChart');
    if (ageBarangayCtx) {
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
                backgroundColor: colors[index] + 'CC',
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
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: false,
                        title: {
                            display: true,
                            text: 'Number of Members'
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        },
                        title: {
                            display: true,
                            text: 'Barangay'
                        }
                    }
                }
            }
        });
    }
}
</script>
