<!-- Resource Planning Report -->
<div class="analytics-grid">
    <?php if (!empty($report_data['barangay_recommendations'])): ?>
    <?php foreach ($report_data['barangay_recommendations'] as $barangay => $data): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($barangay); ?></h3>
        
        <div style="margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; margin: 0.5rem 0;">
                <span>Total PWDs:</span>
                <strong><?php echo number_format($data['total_pwd']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin: 0.5rem 0;">
                <span>Unemployed:</span>
                <strong><?php echo number_format($data['unemployed_count']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin: 0.5rem 0;">
                <span>Children:</span>
                <strong><?php echo number_format($data['children_count']); ?></strong>
            </div>
        </div>
        
        <h4 style="margin: 1rem 0 0.5rem 0; color: #2c5aa0;">Top Support Needs:</h4>
        <?php foreach (array_slice($data['disabilities'], 0, 3) as $disability): ?>
        <div style="background: #f8fafc; padding: 0.5rem; border-radius: 4px; margin: 0.5rem 0; border-left: 4px solid #2c5aa0;">
            <strong><?php echo htmlspecialchars($disability['type']); ?></strong>
            <span style="float: right; color: #64748b;"><?php echo $disability['count']; ?> individuals</span>
        </div>
        <?php endforeach; ?>
        
        <?php if (!empty($data['recommended_services'])): ?>
        <h4 style="margin: 1rem 0 0.5rem 0; color: #10b981;">Recommended Services:</h4>
        <?php foreach ($data['recommended_services'] as $service): ?>
        <div class="resource-card">
            <h4>
                <?php echo htmlspecialchars($service['disability_type']); ?>
                <span class="priority-badge priority-<?php echo strtolower($service['priority']); ?>" style="float: right;">
                    <?php echo $service['priority']; ?>
                </span>
            </h4>
            <p style="margin: 0.5rem 0; color: #64748b;">
                <strong><?php echo $service['affected_count']; ?></strong> individuals affected
            </p>
            <ul class="resource-list">
                <?php foreach (array_slice($service['services'], 0, 3) as $recommended_service): ?>
                <li><?php echo htmlspecialchars($recommended_service); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-info-circle"></i> No Resource Data Available</h3>
        <p class="text-muted">No resource planning data available for the selected period. Please adjust your date range or ensure data has been entered.</p>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($report_data['barangay_recommendations'])): ?>
<div class="analytics-grid">
    <div class="analytics-card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-lightbulb"></i> Resource Allocation Summary</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Total PWDs</th>
                        <th>Primary Need</th>
                        <th>Secondary Need</th>
                        <th>Unemployed</th>
                        <th>Children</th>
                        <th>Priority Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['barangay_recommendations'] as $barangay => $data): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($barangay); ?></strong></td>
                        <td><?php echo number_format($data['total_pwd']); ?></td>
                        <td>
                            <?php 
                            $primary = $data['disabilities'][0] ?? null;
                            echo $primary ? htmlspecialchars($primary['type']) . ' (' . $primary['count'] . ')' : 'N/A';
                            ?>
                        </td>
                        <td>
                            <?php 
                            $secondary = $data['disabilities'][1] ?? null;
                            echo $secondary ? htmlspecialchars($secondary['type']) . ' (' . $secondary['count'] . ')' : 'N/A';
                            ?>
                        </td>
                        <td><?php echo number_format($data['unemployed_count']); ?></td>
                        <td><?php echo number_format($data['children_count']); ?></td>
                        <td>
                            <?php 
                            $priority = 'Medium';
                            $priority_class = 'priority-medium';
                            
                            if ($data['total_pwd'] > 20 || $data['children_count'] > 5) {
                                $priority = 'High';
                                $priority_class = 'priority-high';
                            }
                            if ($data['total_pwd'] > 50 || $data['unemployed_count'] > 15) {
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
</div>
<?php endif; ?>
