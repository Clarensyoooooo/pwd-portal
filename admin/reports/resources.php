<!-- Resource Planning Report -->
<div class="analytics-grid">
    <!-- Summary Cards -->
    <div class="metric-card">
        <div class="metric-value"><?php echo count($report_data['barangay_recommendations'] ?? []); ?></div>
        <div class="metric-label">Barangays Analyzed</div>
        <div class="metric-change">
            <i class="fas fa-map"></i> Resource planning areas
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo array_sum(array_column($report_data['barangay_recommendations'] ?? [], 'total_pwd')); ?></div>
        <div class="metric-label">Total PWDs</div>
        <div class="metric-change">
            <i class="fas fa-users"></i> Requiring services
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo array_sum(array_column($report_data['barangay_recommendations'] ?? [], 'unemployed_count')); ?></div>
        <div class="metric-label">Unemployed</div>
        <div class="metric-change">
            <i class="fas fa-briefcase"></i> Need employment support
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo array_sum(array_column($report_data['barangay_recommendations'] ?? [], 'children_count')); ?></div>
        <div class="metric-label">Children</div>
        <div class="metric-change">
            <i class="fas fa-child"></i> Need special programs
        </div>
    </div>
</div>

<!-- Resource Recommendations by Barangay -->
<?php if (!empty($report_data['barangay_recommendations'])): ?>
<div class="analytics-grid">
    <?php foreach ($report_data['barangay_recommendations'] as $barangay => $data): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($barangay); ?></h3>
        
        <div style="margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Total PWDs:</span>
                <strong><?php echo number_format($data['total_pwd']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Unemployed:</span>
                <strong><?php echo number_format($data['unemployed_count']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <span>Children:</span>
                <strong><?php echo number_format($data['children_count']); ?></strong>
            </div>
        </div>
        
        <h4 style="margin: 1rem 0 0.5rem 0; color: #374151;">Top Disabilities:</h4>
        <?php foreach (array_slice($data['disabilities'], 0, 3) as $disability): ?>
        <div style="margin-bottom: 0.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($disability['type']); ?></span>
                <span style="font-weight: 600;"><?php echo $disability['count']; ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($disability['count'] / max(1, $data['total_pwd'])) * 100; ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <h4 style="margin: 1rem 0 0.5rem 0; color: #374151;">Recommended Services:</h4>
        <?php foreach ($data['recommended_services'] as $service): ?>
        <div class="resource-card">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 0.5rem;">
                <h4><?php echo htmlspecialchars($service['disability_type']); ?></h4>
                <span class="priority-badge priority-<?php echo strtolower($service['priority']); ?>">
                    <?php echo $service['priority']; ?> Priority
                </span>
            </div>
            <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #6b7280;">
                Affects <?php echo $service['affected_count']; ?> individuals in this barangay
            </p>
            <ul class="resource-list">
                <?php foreach (array_slice($service['services'], 0, 3) as $serviceItem): ?>
                <li><?php echo htmlspecialchars($serviceItem); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="analytics-card">
    <p style="text-align: center; color: #6b7280; padding: 2rem;">
        No resource planning data available for the selected filters.
    </p>
</div>
<?php endif; ?>

<script>
function initializeAnalyticsCharts() {
    // Resource planning doesn't need charts, but we keep the function for consistency
    console.log('Resource planning report loaded');
}
</script>
