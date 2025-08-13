<!-- Overview Report Template -->
<div class="report-section">
    <h2>Overview Report</h2>
    <p class="report-period">Period: <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?></p>
    
    <!-- Summary Statistics -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon records">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($report_data['totals']['total_records']); ?></h3>
                <p>Total PWD Records</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon validated">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($report_data['totals']['validated_records']); ?></h3>
                <p>Validated Records</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon issued">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($report_data['totals']['issued_records']); ?></h3>
                <p>Issued IDs</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon appointments">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($report_data['appointments']['total_appointments']); ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Disability Type Distribution</h3>
            </div>
            <div class="card-content">
                <canvas id="disabilityChart"></canvas>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Monthly Registration Trends</h3>
            </div>
            <div class="card-content">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
function initializeReportCharts() {
    // Disability type chart
    const disabilityCtx = document.getElementById('disabilityChart').getContext('2d');
    new Chart(disabilityCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($report_data['disability_types'], 'disability_type')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($report_data['disability_types'], 'count')); ?>,
                backgroundColor: ['#2c5aa0', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Monthly trends chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($report_data['monthly_trends'], 'month')); ?>,
            datasets: [{
                label: 'Registrations',
                data: <?php echo json_encode(array_column($report_data['monthly_trends'], 'count')); ?>,
                borderColor: '#2c5aa0',
                backgroundColor: 'rgba(44, 90, 160, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>
