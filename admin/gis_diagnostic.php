<?php
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'gis.import');

$admin = getCurrentAdmin($pdo);
$message = '';
$error = '';
$success = '';

// Check MySQL Spatial Support
$spatial_support = false;
try {
    $check = $pdo->query("SELECT ST_GeomFromText('POINT(0 0)')");
    $spatial_support = true;
} catch (Exception $e) {
    $error = "MySQL Spatial functions not supported: " . $e->getMessage();
}

// Check PHP upload limits
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');

// Check if barangay_boundaries table exists
$table_exists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'barangay_boundaries'");
    $table_exists = $check->rowCount() > 0;
} catch (Exception $e) {
    $error = "Error checking tables: " . $e->getMessage();
}

// Store analyzed file data in session for auto-import
if (!isset($_SESSION['analyzed_geojson'])) {
    $_SESSION['analyzed_geojson'] = null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_barangay') {
        try {
            $name = $_POST['barangay_name'];
            $city = $_POST['city_municipality'];
            $province = $_POST['province'];
            $area = $_POST['area_sqkm'];
            $population = $_POST['population'];
            
            // Create a simple polygon for visualization
            $center_lat = $_POST['center_lat'];
            $center_lng = $_POST['center_lng'];
            $size = $_POST['size'] / 111; // Convert km to degrees (approximate)
            
            // Create a square polygon around the center point
            $polygon = sprintf(
                'POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))',
                $center_lng - $size, $center_lat - $size,
                $center_lng + $size, $center_lat - $size,
                $center_lng + $size, $center_lat + $size,
                $center_lng - $size, $center_lat + $size,
                $center_lng - $size, $center_lat - $size
            );
            
            // Create GeoJSON representation
            $geojson = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [$center_lng - $size, $center_lat - $size],
                        [$center_lng + $size, $center_lat - $size],
                        [$center_lng + $size, $center_lat + $size],
                        [$center_lng - $size, $center_lat + $size],
                        [$center_lng - $size, $center_lat - $size]
                    ]]
                ],
                'properties' => [
                    'BRGY' => $name,
                    'AREA_HA' => $area * 100, // Convert to hectares
                    'pop2007' => $population
                ]
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO barangay_boundaries 
                (barangay_code, barangay_name, city_municipality, province, area_sqkm, population, geometry, geojson_data)
                VALUES (?, ?, ?, ?, ?, ?, ST_GeomFromText(?), ?)
            ");
            
            $code = strtoupper(substr($city, 0, 3) . '-' . substr($name, 0, 3) . '-' . rand(100, 999));
            
            $stmt->execute([
                $code,
                $name,
                $city,
                $province,
                $area,
                $population,
                $polygon,
                json_encode($geojson)
            ]);
            
            $success = "Barangay '{$name}' added successfully!";
            
            // Log activity
            logAdminActivity($pdo, 'create', 'gis', 'barangay', $pdo->lastInsertId(), [
                'name' => $name,
                'city' => $city,
                'province' => $province
            ]);
            
        } catch (Exception $e) {
            $error = "Error adding barangay: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'test_geojson') {
        if (!isset($_FILES['test_file'])) {
            $error = "No file uploaded";
        } else {
            $file = $_FILES['test_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "File upload failed with error code: " . $file['error'];
            } else {
                try {
                    $content = file_get_contents($file['tmp_name']);
                    $json = json_decode($content, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = "Invalid JSON format: " . json_last_error_msg();
                    } else {
                        // Store the analyzed data in session for auto-import
                        $_SESSION['analyzed_geojson'] = $json;
                        
                        // Analyze the GeoJSON structure
                        $analysis = [
                            'type' => $json['type'] ?? 'Unknown',
                            'features_count' => count($json['features'] ?? []),
                            'geometry_types' => [],
                            'properties' => []
                        ];
                        
                        // Sample the first feature
                        if (!empty($json['features'])) {
                            $sample = $json['features'][0];
                            $analysis['geometry_types'][] = $sample['geometry']['type'] ?? 'Unknown';
                            $analysis['properties'] = array_keys($sample['properties'] ?? []);
                            
                            // Check for required fields
                            $has_brgy = false;
                            foreach ($analysis['properties'] as $prop) {
                                if (in_array(strtoupper($prop), ['BRGY', 'BARANGAY', 'NAME'])) {
                                    $has_brgy = true;
                                    break;
                                }
                            }
                            
                            if (!$has_brgy) {
                                $error = "Warning: No barangay name field found in properties";
                            }
                            
                            $success = "GeoJSON analysis complete. File appears valid and ready for auto-import!";
                            $message = "File contains {$analysis['features_count']} features of type {$analysis['geometry_types'][0]}";
                            $message .= "<br>Properties found: " . implode(', ', $analysis['properties']);
                        } else {
                            $error = "No features found in GeoJSON file";
                        }
                    }
                } catch (Exception $e) {
                    $error = "Error analyzing file: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'auto_import') {
        if (!isset($_SESSION['analyzed_geojson']) || empty($_SESSION['analyzed_geojson'])) {
            $error = "No analyzed GeoJSON data found. Please analyze a file first.";
        } else {
            try {
                $geojson_data = $_SESSION['analyzed_geojson'];
                $imported_count = 0;
                $failed_count = 0;
                $errors = [];
                
                $pdo->beginTransaction();
                
                foreach ($geojson_data['features'] as $feature) {
                    try {
                        $result = importPolygonFeatureAuto($pdo, $feature);
                        if ($result['success']) {
                            $imported_count++;
                        } else {
                            $failed_count++;
                            $errors[] = $result['error'];
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                        $errors[] = $e->getMessage();
                    }
                }
                
                $pdo->commit();
                
                // Clear the session data
                $_SESSION['analyzed_geojson'] = null;
                
                // Log the import
                logAdminActivity($pdo, 'import', 'gis', 'auto_import', null, [
                    'imported' => $imported_count,
                    'failed' => $failed_count,
                    'total_features' => count($geojson_data['features'])
                ]);
                
                if ($imported_count > 0) {
                    $success = "Auto-import completed! Successfully imported {$imported_count} barangay boundaries.";
                    if ($failed_count > 0) {
                        $success .= " {$failed_count} features failed to import.";
                    }
                } else {
                    $error = "Auto-import failed. No features were imported.";
                }
                
                if (!empty($errors)) {
                    $message = "Errors encountered: " . implode(', ', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $message .= " (and " . (count($errors) - 5) . " more...)";
                    }
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Auto-import failed: " . $e->getMessage();
            }
        }
    }
}

function importPolygonFeatureAuto($pdo, $feature) {
    try {
        $properties = $feature['properties'] ?? [];
        
        // Extract barangay information from various possible field names
        $barangay_name = $properties['BRGY'] ?? $properties['barangay'] ?? $properties['BARANGAY'] ?? $properties['name'] ?? $properties['NAME'] ?? '';
        $city_municipality = $properties['city'] ?? $properties['CITY'] ?? $properties['municipality'] ?? $properties['MUNICIPALITY'] ?? $properties['MUNICIPA'] ?? 'Unknown City';
        $province = $properties['province'] ?? $properties['PROVINCE'] ?? $properties['PROV'] ?? 'Unknown Province';
        $region = $properties['region'] ?? $properties['REGION'] ?? '';
        $barangay_code = $properties['code'] ?? $properties['CODE'] ?? $properties['barangay_code'] ?? $properties['OBJECTID_1'] ?? '';
        
        // Handle area - convert hectares to square kilometers if needed
        $area_ha = $properties['AREA_HA'] ?? $properties['area'] ?? $properties['AREA'] ?? null;
        $area_sqkm = $area_ha ? ($area_ha / 100) : null; // Convert hectares to sq km
        
        // Handle population data
        $population = $properties['pop2007'] ?? $properties['population'] ?? $properties['POPULATION'] ?? $properties['POP'] ?? null;
        
        if (empty($barangay_name)) {
            return ['success' => false, 'error' => "Feature missing barangay name"];
        }
        
        // Convert GeoJSON geometry to WKT for MySQL
        $geometry_wkt = convertGeoJSONToWKT($feature['geometry']);
        
        // Check if barangay already exists
        $stmt = $pdo->prepare("
            SELECT id FROM barangay_boundaries 
            WHERE barangay_name = ? AND city_municipality = ?
        ");
        $stmt->execute([$barangay_name, $city_municipality]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing barangay
            $stmt = $pdo->prepare("
                UPDATE barangay_boundaries 
                SET geometry = ST_GeomFromText(?), geojson_data = ?, area_sqkm = ?, population = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $geometry_wkt,
                json_encode($feature),
                $area_sqkm,
                $population,
                $existing['id']
            ]);
        } else {
            // Insert new barangay
            $stmt = $pdo->prepare("
                INSERT INTO barangay_boundaries 
                (barangay_code, barangay_name, city_municipality, province, region, area_sqkm, population, geometry, geojson_data)
                VALUES (?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(?), ?)
            ");
            $stmt->execute([
                $barangay_code ?: generateBarangayCode($barangay_name, $city_municipality),
                $barangay_name,
                $city_municipality,
                $province,
                $region,
                $area_sqkm,
                $population,
                $geometry_wkt,
                json_encode($feature)
            ]);
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => "Import error for {$barangay_name}: " . $e->getMessage()];
    }
}

function convertGeoJSONToWKT($geometry) {
    $type = $geometry['type'];
    $coordinates = $geometry['coordinates'];
    
    switch ($type) {
        case 'Polygon':
            $rings = [];
            foreach ($coordinates as $ring) {
                $points = [];
                foreach ($ring as $point) {
                    $points[] = $point[0] . ' ' . $point[1];
                }
                $rings[] = '(' . implode(', ', $points) . ')';
            }
            return 'POLYGON(' . implode(', ', $rings) . ')';
            
        case 'MultiPolygon':
            $polygons = [];
            foreach ($coordinates as $polygon) {
                $rings = [];
                foreach ($polygon as $ring) {
                    $points = [];
                    foreach ($ring as $point) {
                        $points[] = $point[0] . ' ' . $point[1];
                    }
                    $rings[] = '(' . implode(', ', $points) . ')';
                }
                $polygons[] = '(' . implode(', ', $rings) . ')';
            }
            return 'MULTIPOLYGON(' . implode(', ', $polygons) . ')';
            
        default:
            throw new Exception("Unsupported geometry type for WKT conversion: {$type}");
    }
}

function generateBarangayCode($barangay_name, $city_municipality) {
    return strtoupper(substr($city_municipality, 0, 3) . '-' . substr($barangay_name, 0, 3) . '-' . rand(100, 999));
}

// Get existing barangay boundaries
$barangays = [];
try {
    $stmt = $pdo->query("
        SELECT id, barangay_name, city_municipality, province, area_sqkm, population, pwd_count
        FROM barangay_boundaries
        ORDER BY barangay_name
    ");
    $barangays = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching barangays: " . $e->getMessage();
}

// Philippines center coordinates
$ph_center_lat = 12.8797;
$ph_center_lng = 121.7740;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIS Diagnostic Tool - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .diagnostic-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            font-weight: 500;
        }
        
        .status-value {
            font-weight: 600;
        }
        
        .status-value.success {
            color: #10b981;
        }
        
        .status-value.error {
            color: #ef4444;
        }
        
        .status-value.warning {
            color: #f59e0b;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .mini-map {
            height: 300px;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .barangay-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .barangay-table th,
        .barangay-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .barangay-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
        }
        
        .barangay-table tr:hover {
            background: #f8fafc;
        }
        
        .pwd-count {
            font-weight: 600;
            color: #2c5aa0;
        }
        
        .auto-import-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .auto-import-section h2 {
            color: white;
            margin-bottom: 12px;
        }
        
        .auto-import-section p {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .import-ready-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        
        .btn-auto-import {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-auto-import:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        .btn-auto-import:disabled {
            background: #6b7280;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    
    <main class="dashboard-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-tools"></i> GIS Diagnostic Tool</h1>
                <p>Troubleshoot GIS functionality and automatically import barangay boundaries</p>
            </div>
            <div class="page-actions">
                <a href="map.php" class="btn btn-outline">
                    <i class="fas fa-map"></i> Back to Map
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
                <?php if ($message): ?>
                    <p><?php echo $message; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Auto Import Section -->
        <?php if (isset($_SESSION['analyzed_geojson']) && !empty($_SESSION['analyzed_geojson'])): ?>
            <div class="auto-import-section">
                <div class="import-ready-badge">
                    <i class="fas fa-check-circle"></i> GeoJSON File Ready for Import
                </div>
                <h2><i class="fas fa-magic"></i> Auto Import All Barangay Boundaries</h2>
                <p>Your GeoJSON file has been analyzed and is ready for automatic import. Click the button below to import all <?php echo count($_SESSION['analyzed_geojson']['features']); ?> barangay boundaries automatically!</p>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="auto_import">
                    <button type="submit" class="btn-auto-import" onclick="return confirm('This will import all barangay boundaries from your GeoJSON file. Continue?')">
                        <i class="fas fa-rocket"></i> Auto Import <?php echo count($_SESSION['analyzed_geojson']['features']); ?> Barangays
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="diagnostic-section">
            <h2><i class="fas fa-heartbeat"></i> System Status</h2>
            <div class="status-list">
                <div class="status-item">
                    <span class="status-label">MySQL Spatial Support:</span>
                    <span class="status-value <?php echo $spatial_support ? 'success' : 'error'; ?>">
                        <?php echo $spatial_support ? 'Available' : 'Not Available'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Barangay Boundaries Table:</span>
                    <span class="status-value <?php echo $table_exists ? 'success' : 'error'; ?>">
                        <?php echo $table_exists ? 'Exists' : 'Missing'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">PHP Upload Max Filesize:</span>
                    <span class="status-value <?php echo (intval($upload_max_filesize) >= 8) ? 'success' : 'warning'; ?>">
                        <?php echo $upload_max_filesize; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">PHP Post Max Size:</span>
                    <span class="status-value <?php echo (intval($post_max_size) >= 8) ? 'success' : 'warning'; ?>">
                        <?php echo $post_max_size; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Existing Barangay Boundaries:</span>
                    <span class="status-value">
                        <?php echo count($barangays); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2><i class="fas fa-file-code"></i> Test GeoJSON File</h2>
            <p>Upload your GeoJSON file to analyze its structure and prepare it for automatic import.</p>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="test_geojson">
                <div class="form-group">
                    <label for="test_file">Select GeoJSON File</label>
                    <input type="file" id="test_file" name="test_file" accept=".geojson,.json" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Analyze File for Auto Import
                    </button>
                </div>
            </form>
        </div>
        
       
        
        <div class="diagnostic-section">
            <h2><i class="fas fa-list"></i> Existing Barangay Boundaries</h2>
            
            <?php if (empty($barangays)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    No barangay boundaries found. Use the auto-import feature above to import all boundaries from your GeoJSON file.
                </div>
            <?php else: ?>
                <table class="barangay-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            
                            <th>Area (sq km)</th>
                            <th>Population</th>
                            <th>PWD Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barangays as $barangay): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($barangay['barangay_name']); ?></td>
                                
                                <td><?php echo number_format($barangay['area_sqkm'], 2); ?></td>
                                <td><?php echo number_format($barangay['population']); ?></td>
                                <td class="pwd-count"><?php echo $barangay['pwd_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            const map = L.map('locationMap').setView([<?php echo $ph_center_lat; ?>, <?php echo $ph_center_lng; ?>], 5);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Add marker for selected location
            let marker = L.marker([<?php echo $ph_center_lat; ?>, <?php echo $ph_center_lng; ?>], {
                draggable: true
            }).addTo(map);
            
            // Update hidden inputs when marker is moved
            function updateLocation(latlng) {
                document.getElementById('center_lat').value = latlng.lat.toFixed(6);
                document.getElementById('center_lng').value = latlng.lng.toFixed(6);
            }
            
            marker.on('dragend', function(e) {
                updateLocation(marker.getLatLng());
            });
            
            // Allow clicking on map to move marker
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateLocation(e.latlng);
            });
        });
    </script>
</body>
</html>
