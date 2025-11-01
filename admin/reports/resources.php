<div class="analytics-grid">
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['grand_totals']['total_barangays'] ?? 0); ?></div>
        <div class="metric-label">Barangays Analyzed</div>
        <div class="metric-change">
            <i class="fas fa-map"></i> Resource planning areas
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['grand_totals']['total_pwd'] ?? 0); ?></div>
        <div class="metric-label">Total PWDs</div>
        <div class="metric-change">
            <i class="fas fa-users"></i> Requiring services
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['grand_totals']['total_unemployed'] ?? 0); ?></div>
        <div class="metric-label">Unemployed</div>
        <div class="metric-change">
            <i class="fas fa-briefcase"></i> Need livelihood programs
        </div>
    </div>
    
    <div class="metric-card">
        <div class="metric-value"><?php echo number_format($report_data['grand_totals']['total_children'] ?? 0); ?></div>
        <div class="metric-label">Children</div>
        <div class="metric-change">
            <i class="fas fa-child"></i> Need special education
        </div>
    </div>
</div>

<div class="analytics-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px solid #0ea5e9; margin-bottom: 1.5rem;">
    <h3 style="color: #0c4a6e; margin-bottom: 1rem;"><i class="fas fa-lightbulb"></i> Key Planning Insights</h3>
    <?php if (!empty($report_data['insights'])): ?>
        <ul style="color: #0369a1; margin: 0; padding-left: 1.5rem; line-height: 1.8;">
            <?php foreach ($report_data['insights'] as $insight): ?>
                <li><?php echo $insight; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p style="color: #0369a1; margin: 0;">No specific insights for this filter. Try a broader date range.</p>
    <?php endif; ?>
</div>

<?php if (!empty($report_data['barangay_recommendations'])): ?>
<div class="analytics-grid">
    <?php foreach ($report_data['barangay_recommendations'] as $barangay => $data): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($barangay); ?></h3>
        
        <div style="margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                <div>
                    <span style="color: #6b7280; font-size: 0.85rem;">Total PWDs:</span>
                    <strong style="display: block; color: #1f2937; font-size: 1.25rem;"><?php echo number_format($data['total_pwd']); ?></strong>
                </div>
                <div>
                    <span style="color: #6b7280; font-size: 0.85rem;">Children:</span>
                    <strong style="display: block; color: #2563eb; font-size: 1.25rem;"><?php echo number_format($data['total_children']); ?></strong>
                </div>
                <div>
                    <span style="color: #6b7280; font-size: 0.85rem;">Adults:</span>
                    <strong style="display: block; color: #059669; font-size: 1.25rem;"><?php echo number_format($data['total_adults']); ?></strong>
                </div>
                
                <div>
                    <span style="color: #6b7280; font-size: 0.85rem;">Seniors:</span>
                    <strong style="display: block; color: #d97706; font-size: 1.25rem;"><?php echo number_format($data['total_seniors']); ?></strong>
                </div>
                
                <div>
                    <span style="color: #6b7280; font-size: 0.85rem;">Unemployed:</span>
                    <strong style="display: block; color: #dc2626; font-size: 1.25rem;"><?php echo number_format($data['total_unemployed']); ?></strong>
                </div>
            </div>
        </div>
        
        <h4 style="margin: 1rem 0 0.5rem 0; color: #374151; font-size: 1rem;">Disability Distribution:</h4>
        <?php foreach (array_slice($data['disabilities'], 0, 3) as $disability): ?>
        <div style="margin-bottom: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                <span style="font-size: 0.875rem; color: #4b5563; font-weight: 500;">
                    <?php echo htmlspecialchars($disability['type']); ?>
                </span>
                <div style="text-align: right;">
                    <span style="font-weight: 700; color: #1f2937; font-size: 1.1rem;"><?php echo $disability['count']; ?></span>
                    <span style="color: #6b7280; font-size: 0.75rem; margin-left: 0.25rem;">
                        (<?php echo $disability['concentration']; ?>%)
                    </span>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min(100, $disability['concentration']); ?>%"></div>
            </div>
            <?php if ($disability['children_count'] > 0 || $disability['unemployed_count'] > 0): ?>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                <?php if ($disability['children_count'] > 0): ?>
                    <span style="margin-right: 0.75rem;">👶 <?php echo $disability['children_count']; ?> children</span>
                <?php endif; ?>
                <?php if ($disability['unemployed_count'] > 0): ?>
                    <span>💼 <?php echo $disability['unemployed_count']; ?> unemployed</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if (!empty($data['recommended_services'])): ?>
        <h4 style="margin: 1.5rem 0 0.75rem 0; color: #374151; font-size: 1rem;">
            <i class="fas fa-hand-holding-heart"></i> Priority Services:
        </h4>
        <div class="service-recommendation-list">
            <?php foreach ($data['recommended_services'] as $service): ?>
            <details class="resource-card-details">
                <summary class="resource-card-summary">
                    <span class="priority-badge priority-<?php echo strtolower($service['priority']); ?>">
                        <?php echo htmlspecialchars($service['priority']); ?>
                    </span>
                    <span class="summary-title">
                        <?php echo htmlspecialchars($service['disability_type']); ?>
                        <span class="summary-subtitle">
                            (<?php echo $service['affected_count']; ?> affected, <?php echo $service['concentration']; ?>% concentration)
                        </span>
                    </span>
                    <div class="summary-chevron"><i class="fas fa-chevron-down"></i></div>
                </summary>
                <div class="resource-card-content">
                    <?php if (!empty($service['factors']) && ($service['factors']['high_concentration'] || $service['factors']['many_children'] || $service['factors']['high_unemployment'])): ?>
                    <div class="key-factors-box">
                        <div style="font-size: 0.75rem; font-weight: 600; color: #92400e; margin-bottom: 0.25rem;">
                            📊 Key Factors Triggering This Priority:
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                            <?php if ($service['factors']['high_concentration']): ?>
                                <span class="factor-badge factor-concentration">
                                    <i class="fas fa-exclamation-circle"></i> High Concentration
                                </span>
                            <?php endif; ?>
                            <?php if ($service['factors']['many_children']): ?>
                                <span class="factor-badge factor-children">
                                    <i class="fas fa-child"></i> Many Children (<?php echo $service['children_count']; ?>)
                                </span>
                            <?php endif; ?>
                            <?php if ($service['factors']['high_unemployment']): ?>
                                <span class="factor-badge factor-unemployment">
                                    <i class="fas fa-briefcase"></i> High Unemployment (<?php echo $service['unemployed_count']; ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <ul class="resource-list" style="margin: 0;">
                        <?php foreach (array_slice($service['services'], 0, 6) as $serviceItem): ?>
                        <li style="font-size: 0.85rem; line-height: 1.5;">
                            <?php echo htmlspecialchars($serviceItem); ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if (count($service['services']) > 6): ?>
                        <li style="font-size: 0.85rem; font-style: italic; color: #6b7280;">
                            + <?php echo count($service['services']) - 6; ?> more (see export for full list)
                        <?php endif; ?>
                    </ul>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; font-size: 0.875rem; color: #92400e;">
            <i class="fas fa-info-circle"></i> Insufficient data for service recommendations in this barangay.
        </p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="analytics-card" style="text-align: center; padding: 3rem;">
    <i class="fas fa-chart-bar" style="font-size: 4rem; color: #d1d5db; margin-bottom: 1rem;"></i>
    <h3 style="color: #6b7280; margin-bottom: 0.5rem;">No Data Available</h3>
    <p style="color: #9ca3af; margin: 0;">
        No resource planning data available for the selected date range and filters.<br>
        Try adjusting your filter criteria or selecting a different time period.
    </p>
</div>
<?php endif; ?>

<?php 
// START: PAGINATION CONTROLS
// Check if pagination data exists and if there is more than one page
if (isset($report_data['pagination']) && $report_data['pagination']['total_pages'] > 1): 
    $pagination = $report_data['pagination'];
    $current_page = $pagination['current_page'];
    $total_pages = $pagination['total_pages'];

    // Preserve existing filters in the URL
    $query_params = $_GET;
?>
<div class="pagination-container">
    <nav aria-label="Page navigation">
        <ul class="pagination">
            
            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <?php 
                    $query_params['page'] = $current_page - 1;
                ?>
                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Previous</a>
            </li>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                    <?php 
                        $query_params['page'] = $i;
                    ?>
                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <?php 
                    $query_params['page'] = $current_page + 1;
                ?>
                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Next</a>
            </li>

        </ul>
    </nav>
</div>
<?php endif; 
// END: PAGINATION CONTROLS
?>

<style>
.service-recommendation-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.resource-card-details {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    background: white; /* Set base background to white */
}
.resource-card-summary {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    cursor: pointer;
    background: white;
    list-style: none; /* Remove default list-item marker */
    transition: background-color 0.2s;
}
.resource-card-details[open] .resource-card-summary {
    background-color: #f8fafc; /* Light gray when open */
    border-bottom: 1px solid #e5e7eb;
}
.resource-card-summary:hover {
    background-color: #f0f9ff; /* Light blue on hover */
}
.resource-card-summary::-webkit-details-marker {
    display: none; /* Hide default arrow */
}
.summary-title {
    font-weight: 600;
    color: #1f2937;
    flex-grow: 1;
}
.summary-subtitle {
    font-weight: 400;
    color: #64748b;
    font-size: 0.8rem;
    margin-left: 8px;
}
.summary-chevron {
    margin-left: auto;
    transition: transform 0.2s;
    color: #9ca3af;
}
.resource-card-details[open] .summary-chevron {
    transform: rotate(180deg);
}
.resource-card-content {
    padding: 16px;
    background: #f8fafc; /* Content area has a light gray background */
}
.key-factors-box {
    margin-bottom: 0.75rem; 
    padding: 0.75rem; 
    background: #fefce8; 
    border-radius: 6px; 
    border-left: 3px solid #facc15;
}
.resource-list li::before {
    content: "✓";
    color: #10b981;
    font-weight: 600;
    margin-right: 0.5rem;
}
</style>

<script>
function initializeAnalyticsCharts() {
    // This function is just a placeholder to be consistent
    // No charts are on this specific page.
    console.log('Resource planning report loaded successfully');
}
</script>