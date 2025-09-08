<!-- Community Demographics Report -->
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-map-marked-alt"></i> Barangay Community Profiles</h3>
        <?php if (!empty($report_data['barangay_profiles'])): ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Total PWDs</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Children</th>
                        <th>Avg Age</th>
                        <th>Active IDs</th>
                        <th>Coverage</th>
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
                        <td><?php echo round($profile['avg_age'], 1); ?> years</td>
                        <td><?php echo number_format($profile['active_ids']); ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($profile['active_ids'] / max(1, $profile['total_individuals'])) * 100; ?>%"></div>
                            </div>
                            <small><?php echo round(($profile['active_ids'] / max(1, $profile['total_individuals'])) * 100, 1); ?>% active</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No barangay profile data available for the selected period.</p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($report_data['disability_by_barangay'])): ?>
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-fire"></i> Support Needs by Barangay</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Disability Type</th>
                        <th>Count</th>
                        <th>Priority</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['disability_by_barangay'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['barangay']); ?></td>
                        <td><?php echo htmlspecialchars($item['disability_type']); ?></td>
                        <td><?php echo number_format($item['count']); ?></td>
                        <td>
                            <?php 
                            $priority = 'Medium';
                            $priority_class = 'priority-medium';
                            
                            if ($item['count'] > 10) {
                                $priority = 'High';
                                $priority_class = 'priority-high';
                            }
                            if ($item['count'] > 20) {
                                $priority = 'Critical';
                                $priority_class = 'priority-critical';
                            }
                            ?>
                            <span class="priority-badge <?php echo $priority_class; ?>"><?php echo $priority; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
