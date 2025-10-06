
<div class="analytics-grid">
     
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['summary']['total_individuals']); ?></div>
        <div class="metric-label">Registered Community Members</div>
        <div class="metric-change positive">
            <i class="fas fa-users"></i> Active in our community
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value">
            <?php 
            $coverage_rate = $report_data['summary']['total_individuals'] > 0 
                ? round(($report_data['summary']['active_ids'] / $report_data['summary']['total_individuals']) * 100) 
                : 0;
            echo $coverage_rate . '%';
            ?>
        </div>
        <div class="metric-label">ID Coverage Achieved</div>
        <div class="metric-change positive">
            <i class="fas fa-check-circle"></i> <?php echo number_format($report_data['summary']['active_ids']); ?> active IDs
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo round($report_data['summary']['avg_age'], 1); ?> years</div>
        <div class="metric-label">Average Age</div>
        <div class="metric-change">
            <i class="fas fa-info-circle"></i> Community age profile
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value">
            <?php 
            $validation_rate = $report_data['summary']['total_individuals'] > 0 
                ? round((($report_data['summary']['validated_profiles'] + $report_data['summary']['active_ids']) / $report_data['summary']['total_individuals']) * 100) 
                : 0;
            echo $validation_rate . '%';
            ?>
        </div>
        <div class="metric-label">Processing Progress</div>
        <div class="metric-change positive">
            <i class="fas fa-sync"></i> In active processing
        </div>
    </div>
</div>

 
<div class="analytics-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; margin-bottom: 1.5rem;">
    <h3><i class="fas fa-lightbulb"></i> Key Insights</h3>
    <div style="line-height: 1.8; color: #1e40af;">
        <?php
        // Calculate insights
        $total = $report_data['summary']['total_individuals'];
        $young_adults = 0;
        $children = 0;
        $seniors = 0;
        
        // Safety check for age_groups
        if (is_array($report_data['age_groups'])) {
            foreach ($report_data['age_groups'] as $group) {
                if (stripos($group['age_group'], 'Young Adults') !== false || stripos($group['age_group'], 'Adults (31-50)') !== false) {
                    $young_adults += $group['count'];
                } elseif (stripos($group['age_group'], 'Children') !== false) {
                    $children += $group['count'];
                } elseif (stripos($group['age_group'], 'Senior') !== false || stripos($group['age_group'], 'Mature') !== false) {
                    $seniors += $group['count'];
                }
            }
        }
        
        $male_count = 0;
        $female_count = 0;
        
        // Safety check for gender_distribution
        if (is_array($report_data['gender_distribution'])) {
            foreach ($report_data['gender_distribution'] as $gender) {
                if ($gender['gender'] == 'Male') $male_count = $gender['count'];
                if ($gender['gender'] == 'Female') $female_count = $gender['count'];
            }
        }
        
        $gender_balance = abs($male_count - $female_count) < ($total * 0.1);
        
        // Define safe percentage calculation helper
        $safe_percent = function($numerator, $denominator) {
            return $denominator > 0 ? round(($numerator / $denominator) * 100) : 0;
        };
        ?>
        
        <p style="margin: 0.5rem 0;">
            <strong>Age Distribution:</strong> 
            <?php if ($young_adults > $total * 0.4): ?>
                Most registered members are young to middle-aged adults, reflecting an active working-age community.
            <?php elseif ($children > $total * 0.3): ?>
                A significant portion (<?php echo $safe_percent($children, $total); ?>%) of registered members are children, highlighting the importance of inclusive education and developmental support.
            <?php elseif ($seniors > $total * 0.3): ?>
                The community includes a notable share (<?php echo $safe_percent($seniors, $total); ?>%) of senior members, indicating diverse service needs across age groups.
            <?php else: ?>
                The community shows a balanced age distribution across children, adults, and seniors.
            <?php endif; ?>
        </p>
        
        <p style="margin: 0.5rem 0;">
            <strong>Gender Representation:</strong> 
            <?php if ($gender_balance): ?>
                Registration shows balanced representation between male (<?php echo $safe_percent($male_count, $total); ?>%) and female (<?php echo $safe_percent($female_count, $total); ?>%) members.
            <?php else: ?>
                Gender distribution includes <?php echo $safe_percent($male_count, $total); ?>% male and <?php echo $safe_percent($female_count, $total); ?>% female members.
            <?php endif; ?>
        </p>
        
        <p style="margin: 0.5rem 0;">
            <strong>Registration Trends:</strong> 
            <?php 
            $trends = is_array($report_data['trends']) ? $report_data['trends'] : []; // Safety check
            if (count($trends) >= 2) {
                $recent = end($trends);
                // We use a safe way to get the previous element by resetting and advancing the internal pointer
                reset($trends);
                $previous = $trends[count($trends) - 2]; 
                
                $change = $recent['registrations'] - $previous['registrations'];
                $change_percent = $previous['registrations'] > 0 ? round(($change / $previous['registrations']) * 100) : 0;
                
                if ($change > 0) {
                    echo "Registrations increased by " . abs($change) . " (" . ($change_percent > 0 ? '+' : '') . $change_percent . "%) since the previous period, showing continued community engagement.";
                } elseif ($change < 0) {
                    echo "Registrations have stabilized, with steady community participation maintained.";
                } else {
                    echo "Registration numbers remain consistent, indicating stable community involvement.";
                }
            } else {
                echo "Registration tracking is ongoing to monitor community growth patterns.";
            }
            ?>
        </p>
        
        <p style="margin: 0.5rem 0;">
            <strong>Employment Participation:</strong> 
            <?php
            $employed = 0;
            $unemployed = 0;
            // Safety check for employment_distribution
            if (is_array($report_data['employment_distribution'])) {
                foreach ($report_data['employment_distribution'] as $emp) {
                    if (stripos($emp['employment_status'], 'Employed') !== false) {
                        $employed += $emp['count'];
                    } elseif (stripos($emp['employment_status'], 'Unemployed') !== false) {
                        $unemployed += $emp['count'];
                    }
                }
            }
            $employment_denominator = $employed + $unemployed;
            $employment_rate = $employment_denominator > 0 ? round(($employed / $employment_denominator) * 100) : 0;
            ?>
            Employment participation among working-age members is at <?php echo $employment_rate; ?>%, with ongoing livelihood and skills development programs supporting community members.
        </p>
    </div>
</div>

 
<div class="analytics-grid">
    <div class="analytics-card">
        <h3><i class="fas fa-birthday-cake"></i> Age Distribution Overview</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Understanding our community composition helps tailor appropriate services</p>
        <div class="chart-container">
            <canvas id="ageGroupChart"></canvas>
        </div>
        <div class="data-table-container" style="max-height: 200px; overflow-y: auto; margin-top: 1rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Age Group</th>
                        <th>Count</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Safety check for table data
                    $age_groups = is_array($report_data['age_groups']) ? $report_data['age_groups'] : [];
                    foreach ($age_groups as $group): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($group['age_group']); ?></td>
                        <td><?php echo number_format($group['count']); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: <?php echo $group['percentage']; ?>%"></div>
                                </div>
                                <span style="min-width: 3rem; text-align: right;"><?php echo $group['percentage']; ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Community representation across gender identities</p>
        <div class="chart-container">
            <canvas id="genderChart"></canvas>
        </div>
        <div style="margin-top: 1rem;">
            <?php 
            // Safety check for table data
            $gender_distribution = is_array($report_data['gender_distribution']) ? $report_data['gender_distribution'] : [];
            foreach ($gender_distribution as $gender): 
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                <span style="font-weight: 500;"><?php echo htmlspecialchars($gender['gender']); ?></span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $gender['percentage']; ?>%"></div>
                    </div>
                </div>
                <span style="min-width: 4rem; text-align: right; color: #2c5aa0; font-weight: 600;">
                    <?php echo $gender['count']; ?> (<?php echo $gender['percentage']; ?>%)
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-hands-helping"></i> Disability Type Distribution</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Services are tailored to support diverse needs across our community</p>
        <div class="chart-container">
            <canvas id="disabilityChart"></canvas>
        </div>
        <div style="margin-top: 1rem; max-height: 250px; overflow-y: auto;">
            <?php 
            // Safety check for table data
            $disability_distribution = is_array($report_data['disability_distribution']) ? $report_data['disability_distribution'] : [];
            foreach ($disability_distribution as $disability): 
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #f8fafc; border-radius: 6px;">
                <span style="font-size: 0.9rem; flex: 1;"><?php echo htmlspecialchars($disability['disability_type']); ?></span>
                <div style="flex: 1; margin: 0 1rem;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $disability['percentage']; ?>%"></div>
                    </div>
                </div>
                <span style="min-width: 4rem; text-align: right; color: #2c5aa0; font-weight: 600;">
                    <?php echo $disability['count']; ?> (<?php echo $disability['percentage']; ?>%)
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-chart-line"></i> Registration Progress Over Time</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Tracking community growth and ID issuance progress</p>
        <div class="chart-container">
            <canvas id="trendsChart"></canvas>
        </div>
        <div style="margin-top: 1rem;">
            <?php 
            $trends = is_array($report_data['trends']) ? $report_data['trends'] : []; // Safety check
            $latest_trend = end($trends);
            // Reset is necessary if you use end() and then plan to iterate or use internal pointers.
            // Since we use the array index method now, this is safer:
            $latest_trend = $trends ? $trends[count($trends) - 1] : ['registrations' => 0, 'ids_issued' => 0];

            ?>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div style="text-align: center; padding: 0.75rem; background: #f0f9ff; border-radius: 6px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #2c5aa0;"><?php echo $latest_trend['registrations']; ?></div>
                    <div style="font-size: 0.875rem; color: #64748b;">Latest Period Registrations</div>
                </div>
                <div style="text-align: center; padding: 0.75rem; background: #f0fdf4; border-radius: 6px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $latest_trend['ids_issued']; ?></div>
                    <div style="font-size: 0.875rem; color: #64748b;">IDs Issued This Period</div>
                </div>
            </div>
        </div>
    </div>
</div>
 
<div class="analytics-card" style="grid-column: 1 / -1;">
    <h3><i class="fas fa-map-marked-alt"></i> Geographic Distribution Across Barangays</h3>
    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Community members are distributed across various barangays, each with unique characteristics</p>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Registered Members</th>
                    <th>Share of Total</th>
                    <th>Active IDs</th>
                    <th>Coverage Progress</th>
                    <th>Average Age</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Safety check for table data
                $barangay_distribution = is_array($report_data['barangay_distribution']) ? $report_data['barangay_distribution'] : [];
                foreach ($barangay_distribution as $barangay): 
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($barangay['barangay']); ?></strong></td>
                    <td><?php echo number_format($barangay['count']); ?></td>
                    <td><?php echo $barangay['percentage']; ?>%</td>
                    <td><?php echo number_format($barangay['active_ids']); ?></td>
                    <td>
                        <?php $barangay_coverage = $barangay['count'] > 0 ? round(($barangay['active_ids'] / $barangay['count']) * 100) : 0; ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: <?php echo $barangay_coverage; ?>%"></div>
                            </div>
                            <span style="min-width: 3rem; text-align: right; font-weight: 600; color: #10b981;">
                                <?php echo $barangay_coverage; ?>%
                            </span>
                        </div>
                    </td>
                    <td><?php echo round($barangay['avg_age'], 1); ?> years</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function initializeAnalyticsCharts() {
    // Age Group Donut Chart
    const ageCtx = document.getElementById('ageGroupChart').getContext('2d');
    new Chart(ageCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(is_array($report_data['age_groups']) ? array_column($report_data['age_groups'], 'age_group') : []); ?>,
            datasets: [{
                data: <?php echo json_encode(is_array($report_data['age_groups']) ? array_column($report_data['age_groups'], 'count') : []); ?>,
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
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Gender Pie Chart
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'pie',
        data: {
            // FIX for line 247: Check if the array exists before calling array_column
            labels: <?php echo json_encode(is_array($report_data['gender_distribution']) ? array_column($report_data['gender_distribution'], 'gender') : []); ?>,
            datasets: [{
                data: <?php echo json_encode(is_array($report_data['gender_distribution']) ? array_column($report_data['gender_distribution'], 'count') : []); ?>,
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
                        padding: 15,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Disability Type Donut Chart
    const disabilityCtx = document.getElementById('disabilityChart').getContext('2d');
    new Chart(disabilityCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(is_array($report_data['disability_distribution']) ? array_column($report_data['disability_distribution'], 'disability_type') : []); ?>,
            datasets: [{
                data: <?php echo json_encode(is_array($report_data['disability_distribution']) ? array_column($report_data['disability_distribution'], 'count') : []); ?>,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'
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
                        padding: 10,
                        usePointStyle: true,
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Trends Line Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(is_array($report_data['trends']) ? array_column($report_data['trends'], 'period') : []); ?>,
            datasets: [{
                label: 'New Registrations',
                data: <?php echo json_encode(is_array($report_data['trends']) ? array_column($report_data['trends'], 'registrations') : []); ?>,
                borderColor: '#2c5aa0',
                backgroundColor: 'rgba(44, 90, 160, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'IDs Issued',
                data: <?php echo json_encode(is_array($report_data['trends']) ? array_column($report_data['trends'], 'ids_issued') : []); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
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
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Count'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Time Period'
                    }
                }
            }
        }
    });
}
</script>
