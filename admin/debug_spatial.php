<?php
require_once 'config.php';
requireAdminLogin($pdo);
require_once 'spatial_functions.php';

$admin = getCurrentAdmin($pdo);

// Handle debug actions
$debug_results = [];
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'debug_spatial':
            try {
                // Get barangay boundaries count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM barangay_boundaries WHERE geojson_data IS NOT NULL");
                $barangay_count = $stmt->fetch()['count'];
                $debug_results[] = "Total barangays with GeoJSON data: " . $barangay_count;
                
                // Get PWD records with coordinates
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
                $pwd_count = $stmt->fetch()['count'];
                $debug_results[] = "Total PWD records with coordinates: " . $pwd_count;
                
                // Check sample coordinates
                $stmt = $pdo->query("SELECT id, first_name, last_name, latitude, longitude, barangay_id FROM pwd_records WHERE latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 5");
                $sample_records = $stmt->fetchAll();
                $debug_results[] = "Sample PWD records:";
                foreach ($sample_records as $record) {
                    $debug_results[] = "  - ID: {$record['id']}, Name: {$record['first_name']} {$record['last_name']}, Coords: ({$record['latitude']}, {$record['longitude']}), Barangay ID: " . ($record['barangay_id'] ?? 'NULL');
                }
                
                // Check barangay boundaries
                $stmt = $pdo->query("SELECT id, barangay_name, pwd_count FROM barangay_boundaries LIMIT 5");
                $sample_barangays = $stmt->fetchAll();
                $debug_results[] = "Sample barangays:";
                foreach ($sample_barangays as $barangay) {
                    $debug_results[] = "  - ID: {$barangay['id']}, Name: {$barangay['barangay_name']}, PWD Count: " . ($barangay['pwd_count'] ?? 'NULL');
                }
                
                // Test spatial function
                if (!empty($sample_records) && !empty($sample_barangays)) {
                    $test_record = $sample_records[0];
                    $test_barangay = $sample_barangays[0];
                    
                    $stmt = $pdo->prepare("SELECT geojson_data FROM barangay_boundaries WHERE id = ?");
                    $stmt->execute([$test_barangay['id']]);
                    $geojson_data = $stmt->fetch()['geojson_data'];
                    
                    if ($geojson_data) {
                        $is_inside = isPointInBarangay($test_record['latitude'], $test_record['longitude'], $geojson_data);
                        $debug_results[] = "Test: PWD {$test_record['first_name']} {$test_record['last_name']} is " . ($is_inside ? "INSIDE" : "OUTSIDE") . " barangay {$test_barangay['barangay_name']}";
                    }
                }
                
            } catch (Exception $e) {
                $debug_results[] = "Error: " . $e->getMessage();
            }
            break;
            
        case 'manual_count':
            try {
                // Get all PWD records with coordinates
                $stmt = $pdo->query("SELECT id, latitude, longitude FROM pwd_records WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
                $pwd_records = $stmt->fetchAll();
                
                // Get all barangay boundaries
                $stmt = $pdo->query("SELECT id, barangay_name, geojson_data FROM barangay_boundaries WHERE geojson_data IS NOT NULL");
                $barangays = $stmt->fetchAll();
                
                $assigned_count = 0;
                $total_processed = 0;
                
                // Reset all barangay assignments
                $pdo->exec("UPDATE pwd_records SET barangay_id = NULL");
                $pdo->exec("UPDATE barangay_boundaries SET pwd_count = 0");
                
                foreach ($pwd_records as $record) {
                    $total_processed++;
                    $assigned_barangay = null;
                    
                    foreach ($barangays as $barangay) {
                        if (isPointInBarangay($record['latitude'], $record['longitude'], $barangay['geojson_data'])) {
                            $assigned_barangay = $barangay['id'];
                            break;
                        }
                    }
                    
                    // Update the PWD record with barangay assignment
                    if ($assigned_barangay) {
                        $update_stmt = $pdo->prepare("UPDATE pwd_records SET barangay_id = ? WHERE id = ?");
                        $update_stmt->execute([$assigned_barangay, $record['id']]);
                        $assigned_count++;
                    }
                }
                
                // Update PWD counts for all barangays
                foreach ($barangays as $barangay) {
                    $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pwd_records WHERE barangay_id = ?");
                    $count_stmt->execute([$barangay['id']]);
                    $count = $count_stmt->fetch()['count'];
                    
                    $update_stmt = $pdo->prepare("UPDATE barangay_boundaries SET pwd_count = ? WHERE id = ?");
                    $update_stmt->execute([$count, $barangay['id']]);
                }
                
                $action_message = "Manual count completed! Processed {$total_processed} PWD records, assigned {$assigned_count} to barangays.";
                
                logAdminActivity($pdo, 'update', 'gis', 'manual_count', null, [
                    'processed' => $total_processed,
                    'assigned' => $assigned_count
                ]);
                
            } catch (Exception $e) {
                $action_message = "Error during manual count: " . $e->getMessage();
            }
            break;
            
        case 'generate_sample':
            try {
                // Santo Tomas, Batangas approximate bounds
                $min_lat = 14.0800;
                $max_lat = 14.1200;
                $min_lng = 121.1200;
                $max_lng = 121.1600;
                
                $sample_names = [
                    ['Juan', 'Dela Cruz'], ['Maria', 'Santos'], ['Jose', 'Garcia'],
                    ['Ana', 'Reyes'], ['Pedro', 'Gonzales'], ['Rosa', 'Martinez'],
                    ['Carlos', 'Lopez'], ['Elena', 'Hernandez'], ['Miguel', 'Torres'],
                    ['Carmen', 'Flores']
                ];
                
                $disabilities = ['Visual Impairment', 'Hearing Impairment', 'Physical Disability', 'Intellectual Disability', 'Psychosocial Disability'];
                $statuses = ['draft', 'validated', 'issued'];
                
                $generated = 0;
                for ($i = 0; $i < 10; $i++) {
                    $name = $sample_names[array_rand($sample_names)];
                    $lat = $min_lat + (mt_rand() / mt_getrandmax()) * ($max_lat - $min_lat);
                    $lng = $min_lng + (mt_rand() / mt_getrandmax()) * ($max_lng - $min_lng);
                    
                    $pwd_id = 'PWD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO pwd_records 
                        (pwd_id_number, first_name, last_name, disability_type, status, 
                         address_line1, city_municipality, province, latitude, longitude, 
                         created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $pwd_id,
                        $name[0],
                        $name[1],
                        $disabilities[array_rand($disabilities)],
                        $statuses[array_rand($statuses)],
                        'Sample Address ' . ($i + 1),
                        'Santo Tomas City',
                        'Batangas',
                        $lat,
                        $lng,
                        $_SESSION['admin_user_id']
                    ]);
                    $generated++;
                }
                
                $action_message = "Generated {$generated} sample PWD records with coordinates in Santo Tomas, Batangas.";
                
                logAdminActivity($pdo, 'create', 'records', 'sample_data', null, [
                    'generated_count' => $generated
                ]);
                
            } catch (Exception $e) {
                $action_message = "Error generating sample data: " . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spatial Debug Tool - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-actions {
            margin-bottom: 20px;
        }
        
        .debug-actions form {
            display: inline-block;
            margin-right: 10px;
        }
        
        .debug-output {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            border: 1px solid #dee2e6;
        }
        
        .debug-line {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-info {
            color: #31708f;
            background-color: #d9edf7;
            border-color: #bce8f1;
        }
        
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-bug"></i> Spatial Debug Tool</h1>
                <p>Debug and fix spatial calculation issues for PWD records and barangay boundaries</p>
            </div>
            <div class="page-actions">
                <a href="map.php" class="btn btn-outline">
                    <i class="fas fa-map"></i> Back to Map
                </a>
            </div>
        </div>

        <?php if ($action_message): ?>
            <div class="alert alert-<?php echo strpos($action_message, 'Error') !== false ? 'danger' : 'success'; ?>">
                <i class="fas fa-<?php echo strpos($action_message, 'Error') !== false ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tools"></i> Debug Actions</h3>
            </div>
            <div class="card-content">
                <div class="debug-actions">
                    <form method="POST">
                        <input type="hidden" name="action" value="debug_spatial">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-search"></i> Debug Spatial Calculations
                        </button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="action" value="manual_count">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('This will recalculate all PWD assignments. Continue?')">
                            <i class="fas fa-calculator"></i> Manual Count & Assign
                        </button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="action" value="generate_sample">
                        <button type="submit" class="btn btn-success" onclick="return confirm('This will generate 10 sample PWD records. Continue?')">
                            <i class="fas fa-plus"></i> Generate Sample Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!empty($debug_results)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Debug Results</h3>
                </div>
                <div class="card-content">
                    <div class="debug-output">
                        <?php foreach ($debug_results as $result): ?>
                            <div class="debug-line"><?php echo htmlspecialchars($result); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-database"></i> Current Database Status</h3>
            </div>
            <div class="card-content">
                <?php
                try {
                    // Get current statistics
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM barangay_boundaries");
                    $total_barangays = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM barangay_boundaries WHERE geojson_data IS NOT NULL");
                    $barangays_with_data = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records");
                    $total_pwd = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
                    $pwd_with_coords = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pwd_records WHERE barangay_id IS NOT NULL");
                    $pwd_assigned = $stmt->fetch()['count'];
                    
                    $stmt = $pdo->query("SELECT SUM(pwd_count) as total FROM barangay_boundaries WHERE pwd_count IS NOT NULL");
                    $total_counted = $stmt->fetch()['total'] ?? 0;
                ?>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_barangays; ?></div>
                            <div class="stat-label">Total Barangays</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $barangays_with_data; ?></div>
                            <div class="stat-label">Barangays with GeoJSON</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_pwd; ?></div>
                            <div class="stat-label">Total PWD Records</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $pwd_with_coords; ?></div>
                            <div class="stat-label">PWD with Coordinates</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $pwd_assigned; ?></div>
                            <div class="stat-label">PWD Assigned to Barangays</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_counted; ?></div>
                            <div class="stat-label">Total PWD Counted</div>
                        </div>
                    </div>
                <?php
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Error getting statistics: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> How to Use This Tool</h3>
            </div>
            <div class="card-content">
                <ol>
                    <li><strong>Debug Spatial Calculations:</strong> Shows current database status and tests the spatial functions</li>
                    <li><strong>Generate Sample Data:</strong> Creates 10 test PWD records with coordinates in Santo Tomas, Batangas</li>
                    <li><strong>Manual Count & Assign:</strong> Forces the system to recalculate all PWD assignments to barangays</li>
                </ol>
                <p><strong>Recommended workflow:</strong></p>
                <ol>
                    <li>First, click "Debug Spatial Calculations" to see current status</li>
                    <li>If you need test data, click "Generate Sample Data"</li>
                    <li>Click "Manual Count & Assign" to fix any assignment issues</li>
                    <li>Go back to the map to see the updated counts</li>
                </ol>
            </div>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
</body>
</html>
