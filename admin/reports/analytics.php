<?php
// This ensures $report_data exists even if no results are found
$summary = $report_data['summary'] ?? [
    'total_individuals' => 0,
    'active_ids' => 0,
    'validated_profiles' => 0,
    'expired_ids' => 0,
    'inactive_ids' => 0,
    'avg_age' => 0,
    'draft_records' => 0
];

// --- NEW: Get the All-Time Summary data ---
$all_time_summary = $report_data['all_time_summary'] ?? [
    'total_records' => 0,
    'total_active' => 0,
    'total_expired' => 0,
    'total_inactive' => 0,
    'total_avg_age' => 0
];
// --- END NEW ---

$age_groups = $report_data['age_groups'] ?? [];
$gender_distribution = $report_data['gender_distribution'] ?? [];
$disability_distribution = $report_data['disability_distribution'] ?? [];
$barangay_distribution = $report_data['barangay_distribution'] ?? [];
$trends = $report_data['trends'] ?? [];

// --- NEW: Get data for new charts ---
$id_status_distribution = $report_data['id_status_distribution'] ?? [];
$employment_distribution = $report_data['employment_distribution'] ?? [];


$total = (int)($summary['total_individuals'] ?? 0);

// Helper function
$safe_percent = function($numerator, $denominator) {
    return $denominator > 0 ? round(($numerator / $denominator) * 100) : 0;
};

// Prepare the 'since' date for the all-time summary
$all_time_date_string = "";
if (!empty($all_time_summary['first_registration_date'])) {
    // New format: [Start Date] - Present
    $start_date = date('M j, Y', strtotime($all_time_summary['first_registration_date']));
    $all_time_date_string = "({$start_date} - Present)";
}
?>

<h3 style="font-size: 1.5rem; color: #1f2937; margin-bottom: 1rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem;">
    <i class="fas fa-globe-asia"></i> All-Time Community Summary
    <span style="font-size: 1rem; color: #64748b; font-weight: 400; margin-left: 10px;">
        <?php echo $all_time_date_string; ?>
    </span>
</h3>
<div class="analytics-grid" style="margin-bottom: 2.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #0ea5e9;">
        <div class="metric-value" style="color: #0c4a6e;"><?php echo number_format($all_time_summary['total_records']); ?></div>
        <div class="metric-label">Total Community Members</div>
        <div class="metric-change" style="color: #0c4a6e;"><i class="fas fa-users"></i> All-time registered</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #22c55e;">
        <div class="metric-value" style="color: #15803d;"><?php echo number_format($all_time_summary['total_active']); ?></div>
        <div class="metric-label">Total Active IDs</div>
        <div class="metric-change" style="color: #15803d;"><i class="fas fa-check-circle"></i> Currently valid IDs</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%); border-color: #eab308;">
        <div class="metric-value" style="color: #a16207;"><?php echo number_format($all_time_summary['total_expired']); ?></div>
        <div class="metric-label">Total Expired IDs</div>
        <div class="metric-change" style="color: #a16207;"><i class="fas fa-hourglass-end"></i> Needs renewal</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-color: #64748b;">
        <div class="metric-value" style="color: #475569;"><?php echo number_format($all_time_summary['total_inactive']); ?></div>
        <div class="metric-label">Total Inactive IDs</div>
        <div class="metric-change" style="color: #475569;"><i class="fas fa-ban"></i> Marked inactive</div>
    </div>
</div>
<h3 style="font-size: 1.5rem; color: #1f2937; margin-bottom: 1rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem;">
    <i class="fas fa-filter"></i> Filtered Period Summary
    <span style="font-size: 1rem; color: #64748b; font-weight: 400; margin-left: 10px;">
        (<?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?>)
    </span>
</h3>
<div class="analytics-grid">
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($total); ?></div>
        <div class="metric-label">Records in Period</div> 
        <div class="metric-change"><i class="fas fa-users"></i> New or updated in period</div>
    </div>
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($summary['active_ids']); ?></div>
         <div class="metric-label">Active (in Period)</div>
        <div class="metric-change positive"><i class="fas fa-check-circle"></i> <?php echo $safe_percent($summary['active_ids'], $total); ?>% of total</div>
    </div>
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($summary['validated_profiles']); ?></div>
         <div class="metric-label">Validated (in Period)</div>
        <div class="metric-change" style="color: #0ea5e9;"><i class="fas fa-file-signature"></i> <?php echo $safe_percent($summary['validated_profiles'], $total); ?>% of total</div>
    </div>
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($summary['expired_ids']); ?></div>
         <div class="metric-label">Expired (in Period)</div>
        <div class="metric-change" style="color: #f59e0b;"><i class="fas fa-hourglass-end"></i> <?php echo $safe_percent($summary['expired_ids'], $total); ?>% of total</div>
    </div>
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($summary['inactive_ids']); ?></div>
         <div class="metric-label">Inactive (in Period)</div>
        <div class="metric-change" style="color: #64748b;"><i class="fas fa-ban"></i> <?php echo $safe_percent($summary['inactive_ids'], $total); ?>% of total</div>
    </div>
    <div class="metric-card">
        <div class="metric-value"><?php echo round($summary['avg_age'], 1); ?> yrs</div>
         <div class="metric-label">Average Age (in Period)</div>
       <div class="metric-change"><i class="fas fa-birthday-cake"></i> Of members in this group</div>
    </div>
</div>

<div class="analytics-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
    <h3><i class="fas fa-lightbulb"></i> Key Insights for Filtered Period</h3>
    <div style="line-height: 1.8; color: #0c4a6e;">
        <p style="margin: 0.5rem 0;">
            Based on <strong><?php echo number_format($total); ?></strong> total records in this period:
        </p>
        <ul style="padding-left: 20px; margin: 0;">
            <?php
            // Insight 1: ID Status
            $active_percent = $safe_percent($summary['active_ids'], $total);
            if ($total > 0 && $active_percent > 75) {
                echo "<li><strong>ID Coverage:</strong> Excellent. <strong>{$active_percent}%</strong> of all records have an active ID.</li>";
            } elseif ($total > 0) {
                $unprocessed_count = $summary['draft_records'] + $summary['validated_profiles'];
                $unprocessed_percent = $safe_percent($unprocessed_count, $total);
                if ($unprocessed_percent > 30) {
                    echo "<li><strong>Action Item:</strong> <strong>{$unprocessed_percent}%</strong> of records are unprocessed (Draft or Validated). Focus on ID issuance.</li>";
                } else {
                    echo "<li><strong>ID Coverage:</strong> <strong>{$active_percent}%</strong> of records are active.</li>";
                }
            }

            // Insight 2: Renewals
            $renewal_needed_count = $summary['expired_ids'] + $summary['inactive_ids'];
            $renewal_percent = $safe_percent($renewal_needed_count, $total);
            if ($renewal_percent > 10) {
                echo "<li><strong>Renewals:</strong> <strong>{$renewal_percent}%</strong> of records are Expired or Inactive (<strong>{$renewal_needed_count}</strong> members). This suggests a need for a renewal campaign.</li>";
            }

            // Insight 3: Age
            $children = 0;
            $seniors = 0;
            foreach ($age_groups as $group) {
                if (stripos($group['age_group'], 'Children') !== false) $children += $group['count'];
                if (stripos($group['age_group'], 'Senior') !== false) $seniors += $group['count'];
            }
            $children_percent = $safe_percent($children, $total);
            if ($children_percent > 25) {
                echo "<li><strong>Age Profile:</strong> A significant <strong>{$children_percent}%</strong> of members are children, indicating a high demand for educational and developmental support.</li>";
            }
            $senior_percent = $safe_percent($seniors, $total);
            if ($senior_percent > 20) {
                echo "<li><strong>Age Profile:</strong> <strong>{$senior_percent}%</strong> of members are seniors, highlighting a need for healthcare and mobility services.</li>";
            }

            // Insight 4: Top Disability
            if (!empty($disability_distribution)) {
                $top_disability = $disability_distribution[0];
                echo "<li><strong></strong> <strong>{$top_disability['disability_type']}</strong> is the most common disability, representing <strong>{$top_disability['percentage']}%</strong> of the community.</li>";
            }

            // Insight 5: Top Barangay
            if (!empty($barangay_distribution)) {
                $top_barangay = $barangay_distribution[0];
                $top_brgy_percent = $safe_percent($top_barangay['count'], $total);
                if ($top_brgy_percent > 10) {
                    echo "<li><strong></strong> <strong>{$top_barangay['barangay']}</strong> has the highest concentration of members, accounting for <strong>{$top_brgy_percent}%</strong> of all records.</li>";
                }
            }
            
            if ($total == 0) {
                echo "<li>No records found for this period. Clear filters or expand the date range to generate insights.</li>";
            }
            ?>
        </ul>
    </div>
</div>
 
<div class="analytics-grid">
    <div class="analytics-card">
        <h3><i class="fas fa-birthday-cake"></i> Age Distribution</h3>
        <div class="chart-container">
            <canvas id="ageGroupChart"></canvas>
        </div>
        </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-venus-mars"></i> Gender Distribution</h3>
        <div class="chart-container">
            <canvas id="genderChart"></canvas>
        </div>
        <div class="chart-legend" id="genderLegend"></div>
    </div>
    
    <div class="analytics-card">
        <h3><i class="fas fa-hands-helping"></i> Disability Type Distribution</h3>
        <div class="chart-container">
            <canvas id="disabilityChart"></canvas>
        </div>
        </div>

    <div class="analytics-card">
        <h3><i class="fas fa-id-card"></i> ID Status Distribution</h3>
        <div class="chart-container">
            <canvas id="idStatusChart"></canvas>
        </div>
        <div class="chart-legend" id="idStatusLegend"></div>
    </div>

    <div class="analytics-card">
        <h3><i class="fas fa-briefcase"></i> Employment Status</h3>
        <div class="chart-container">
            <canvas id="employmentChart"></canvas>
        </div>
        </div>
    
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-chart-line"></i> Status Trends Over Time</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Tracking community growth and ID status over time</p>
        <div class="chart-container">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>
</div>
 
<div class="analytics-card" style="grid-column: 1 / -1;">
    <h3><i class="fas fa-map-marked-alt"></i> Geographic Distribution Across Barangays</h3>
    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Community members are distributed across various barangays</p>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total</th>
                    <th>Active IDs</th>
                    <th>Expired IDs</th>
                    <th>Inactive IDs</th>
                    <th>Validated</th>
                    <th>Drafts</th>
                    <th>Active Coverage</th>
                    <th>Avg Age</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($barangay_distribution as $barangay): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($barangay['barangay']); ?></strong></td>
                    <td><?php echo number_format($barangay['count']); ?></td>
                    <td><?php echo number_format($barangay['active_ids']); ?></td>
                    <td><?php echo number_format($barangay['expired_ids']); ?></td>
                    <td><?php echo number_format($barangay['inactive_ids']); ?></td>
                    <td><?php echo number_format($barangay['validated']); ?></td>
                    <td><?php echo number_format($barangay['drafts']); ?></td>
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
                    <td><?php echo round($barangay['avg_age'], 1); ?> yrs</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.chart-legend {
    margin-top: 1rem;
    max-height: 200px;
    overflow-y: auto;
}
.chart-legend ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.chart-legend li {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    color: #374151;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background-color 0.2s;
}
.chart-legend li:hover {
    background-color: #f8fafc;
}
.chart-legend .legend-color-box {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    margin-right: 10px;
    flex-shrink: 0;
}
.chart-legend .legend-label {
    flex-grow: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chart-legend .legend-value {
    font-weight: 600;
    color: #1f2937;
    margin-left: 10px;
}
.chart-legend .legend-percentage {
    font-weight: 500;
    color: #64748b;
    margin-left: 8px;
    font-size: 0.8rem;
}
.chart-legend li.hidden {
    text-decoration: line-through;
    color: #9ca3af;
}
.chart-legend li.hidden .legend-color-box {
    background-color: #e5e7eb !important;
}
</style>

<script>
// This function should already exist in your main reports.php, but we re-declare it
// here to ensure it's up-to-date with the new legend plugin.
function initializeAnalyticsCharts() {
    
    // Store chart instances
    const chartInstances = {};
    function destroyChart(chartId) {
        if (chartInstances[chartId]) {
            chartInstances[chartId].destroy();
            delete chartInstances[chartId];
        }
    }

    // --- NEW: Custom HTML Legend Plugin ---
    const htmlLegendPlugin = {
        id: 'htmlLegend',
        afterUpdate(chart, args, options) {
            const ul = getOrCreateLegendList(chart, options.containerID);
            
            // Clear existing legend items
            ul.innerHTML = '';
            
            const items = chart.options.plugins.legend.labels.generateLabels(chart);
            const data = chart.data.datasets[0].data;
            const total = data.reduce((a, b) => a + b, 0);

            items.forEach((item, index) => {
                const li = document.createElement('li');
                li.style.textDecoration = item.hidden ? 'line-through' : '';
                li.style.color = item.hidden ? '#9ca3af' : '#374151';
                li.onclick = () => {
                    chart.toggleDataVisibility(item.index);
                    chart.update();
                };

                const value = data[index];
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;

                li.innerHTML = `
                    <div class="legend-color-box" style="background-color:${item.fillStyle}; border: 1px solid ${item.strokeStyle}"></div>
                    <span class="legend-label">${item.text}</span>
                    <span class="legend-value">${value.toLocaleString()}</span>
                    <span class="legend-percentage">(${percentage}%)</span>
                `;
                
                ul.appendChild(li);
            });
        }
    };
    
    const getOrCreateLegendList = (chart, id) => {
        const legendContainer = document.getElementById(id);
        let listContainer = legendContainer.querySelector('ul');

        if (!listContainer) {
            listContainer = document.createElement('ul');
            legendContainer.appendChild(listContainer);
        }
        return listContainer;
    };
    // --- END: Custom HTML Legend Plugin ---


    // --- CHANGED: Age Group Chart (to Bar) ---
    destroyChart('ageGroupChart');
    const ageCtx = document.getElementById('ageGroupChart')?.getContext('2d');
    if (ageCtx) {
        chartInstances['ageGroupChart'] = new Chart(ageCtx, {
            type: 'bar', // CHANGED
            data: {
                labels: <?php echo json_encode(array_column($age_groups, 'age_group')); ?>,
                datasets: [{
                    label: 'Count', // Added label for tooltip
                    data: <?php echo json_encode(array_column($age_groups, 'count')); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                    borderWidth: 0 // No border needed for bars
                }]
            },
            options: {
                indexAxis: 'y', // CHANGED: Makes it horizontal
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    // htmlLegend: { containerID: 'ageGroupLegend' }, // REMOVED
                    legend: { display: false }, // Hide legend, Y-axis is clear
                    // tooltip: { ... } // REMOVED custom tooltip
                },
                scales: { // ADDED
                    x: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Count' }
                    },
                    y: { 
                        beginAtZero: true 
                    }
                }
            }
        });
    }
    
    // --- UNCHANGED: Gender Pie Chart ---
    destroyChart('genderChart');
    const genderCtx = document.getElementById('genderChart')?.getContext('2d');
    if (genderCtx) {
        chartInstances['genderChart'] = new Chart(genderCtx, {
            type: 'pie',
            plugins: [htmlLegendPlugin], // Add plugin
            data: {
                labels: <?php echo json_encode(array_column($gender_distribution, 'gender')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($gender_distribution, 'count')); ?>,
                    backgroundColor: ['#2c5aa0', '#ec4899', '#10b981'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    htmlLegend: { containerID: 'genderLegend' }, // Link to placeholder
                    legend: { display: false }, // Hide default legend
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
    }
    
    // --- CHANGED: Disability Type Chart (to Bar) ---
    destroyChart('disabilityChart');
    const disabilityCtx = document.getElementById('disabilityChart')?.getContext('2d');
    if (disabilityCtx) {
        chartInstances['disabilityChart'] = new Chart(disabilityCtx, {
            type: 'bar', // CHANGED
            data: {
                labels: <?php echo json_encode(array_column($disability_distribution, 'disability_type')); ?>,
                datasets: [{
                    label: 'Count', // Added label for tooltip
                    data: <?php echo json_encode(array_column($disability_distribution, 'count')); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6'],
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y', // CHANGED: Makes it horizontal
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    // htmlLegend: { containerID: 'disabilityLegend' }, // REMOVED
                    legend: { display: false }, // Hide legend
                    // tooltip: { ... } // REMOVED custom tooltip
                },
                scales: { // ADDED
                    x: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Count' }
                    },
                    y: { 
                        beginAtZero: true 
                    }
                }
            }
        });
    }

    // --- UNCHANGED: ID Status Pie Chart ---
    destroyChart('idStatusChart');
    const idStatusCtx = document.getElementById('idStatusChart')?.getContext('2d');
    if (idStatusCtx) {
        chartInstances['idStatusChart'] = new Chart(idStatusCtx, {
            type: 'pie',
            plugins: [htmlLegendPlugin],
            data: {
                labels: <?php echo json_encode(array_column($id_status_distribution, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($id_status_distribution, 'count')); ?>,
                    backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b', '#64748b', '#cbd5e1'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    htmlLegend: { containerID: 'idStatusLegend' },
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // --- CHANGED: Employment Status Chart (to Bar) ---
    destroyChart('employmentChart');
    const employmentCtx = document.getElementById('employmentChart')?.getContext('2d');
    if (employmentCtx) {
        chartInstances['employmentChart'] = new Chart(employmentCtx, {
            type: 'bar', // CHANGED
            data: {
                labels: <?php echo json_encode(array_column($employment_distribution, 'employment_status')); ?>,
                datasets: [{
                    label: 'Count', // Added label for tooltip
                    data: <?php echo json_encode(array_column($employment_distribution, 'count')); ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'],
                    borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y', // CHANGED: Makes it horizontal
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    // htmlLegend: { containerID: 'employmentLegend' }, // REMOVED
                    legend: { display: false }, // Hide legend
                    // tooltip: { ... } // REMOVED custom tooltip
                },
                scales: { // ADDED
                    x: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Count' }
                    },
                    y: { 
                        beginAtZero: true 
                    }
                }
            }
        });
    }
    
    // Trends Line Chart (Updated with new data fields)
    destroyChart('trendsChart');
    const trendsCtx = document.getElementById('trendsChart')?.getContext('2d');
    if (trendsCtx) {
        chartInstances['trendsChart'] = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trends, 'period')); ?>,
                datasets: [{
                    label: 'New Registrations',
                    data: <?php echo json_encode(array_column($trends, 'registrations')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Active IDs',
                    data: <?php echo json_encode(array_column($trends, 'active_ids')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Expired IDs',
                    data: <?php echo json_encode(array_column($trends, 'expired_ids')); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Inactive IDs',
                    data: <?php echo json_encode(array_column($trends, 'inactive_ids')); ?>,
                    borderColor: '#6b7280',
                    backgroundColor: 'rgba(107, 114, 128, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Count' } },
                    x: { title: { display: true, text: 'Time Period' } }
                }
            }
        });
    }
}
</script>