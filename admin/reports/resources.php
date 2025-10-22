<!-- Resource Planning Report -->
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

<!-- Key Insights Alert -->
<div class="analytics-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #f59e0b; margin-bottom: 1.5rem;">
    <h3 style="color: #92400e; margin-bottom: 1rem;"><i class="fas fa-lightbulb"></i> Resource Planning Methodology</h3>
    <p style="color: #78350f; margin-bottom: 0.5rem;">This analysis considers multiple factors:</p>
    <ul style="color: #78350f; margin: 0; padding-left: 1.5rem;">
        <li><strong>Disability Type & Severity:</strong> Tailored services for each disability</li>
        <li><strong>Population Concentration:</strong> High concentration (>50%) = Critical priority</li>
        <li><strong>Age Groups:</strong> Children need education/therapy, adults need employment</li>
        <li><strong>Employment Status:</strong> High unemployment (>40%) triggers livelihood programs</li>
        <li><strong>Service Gaps:</strong> Recommendations avoid duplication of existing programs</li>
    </ul>
</div>

<!-- Resource Recommendations by Barangay -->
<?php if (!empty($report_data['barangay_recommendations'])): ?>
<div class="analytics-grid">
    <?php foreach ($report_data['barangay_recommendations'] as $barangay => $data): ?>
    <div class="analytics-card">
        <h3><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($barangay); ?></h3>
        
        <!-- Barangay Summary -->
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
        
        <!-- Top Disabilities -->
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
        
        <!-- Service Recommendations -->
        <?php if (!empty($data['recommended_services'])): ?>
        <h4 style="margin: 1.5rem 0 0.75rem 0; color: #374151; font-size: 1rem;">
            <i class="fas fa-hand-holding-heart"></i> Priority Services:
        </h4>
        <?php foreach ($data['recommended_services'] as $service): ?>
        <div class="resource-card" style="margin-bottom: 0.75rem;">
            <!-- Service Header -->
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 0.25rem 0; font-size: 0.95rem; color: #0c4a6e;">
                        <?php echo htmlspecialchars($service['disability_type']); ?>
                    </h4>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem;">
                        <span class="priority-badge priority-<?php echo strtolower($service['priority']); ?>">
                            <?php echo htmlspecialchars($service['priority']); ?> Priority
                        </span>
                        <span style="font-size: 0.75rem; color: #64748b; padding: 0.25rem 0.5rem; background: white; border-radius: 4px;">
                            <strong><?php echo $service['affected_count']; ?></strong> affected
                        </span>
                        <span style="font-size: 0.75rem; color: #64748b; padding: 0.25rem 0.5rem; background: white; border-radius: 4px;">
                            <strong><?php echo $service['concentration']; ?>%</strong> concentration
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Key Factors -->
            <?php if (!empty($service['factors']) && (
                $service['factors']['high_concentration'] || 
                $service['factors']['many_children'] || 
                $service['factors']['high_unemployment']
            )): ?>
            <div style="margin-bottom: 0.75rem; padding: 0.5rem; background: #fef3c7; border-radius: 4px; border-left: 3px solid #f59e0b;">
                <div style="font-size: 0.75rem; font-weight: 600; color: #92400e; margin-bottom: 0.25rem;">
                    📊 Key Factors:
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
            
            <!-- Service List -->
            <ul class="resource-list" style="margin: 0;">
                <?php foreach (array_slice($service['services'], 0, 6) as $serviceItem): ?>
                <li style="font-size: 0.85rem; line-height: 1.5;">
                    <?php echo htmlspecialchars($serviceItem); ?>
                </li>
                <?php endforeach; ?>
                <?php if (count($service['services']) > 6): ?>
                <li style="font-size: 0.85rem; font-style: italic; color: #6b7280;">
                    + <?php echo count($service['services']) - 6; ?> more services recommended...
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endforeach; ?>
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
// START: ADD PAGINATION CONTROLS
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

<script>
function initializeAnalyticsCharts() {
    console.log('Resource planning report loaded successfully');
}
</script>
