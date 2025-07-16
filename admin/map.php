<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'gis.view');

$admin = getCurrentAdmin($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'import_geojson':
            handleImportGeoJSON();
            break;
        case 'export_geojson':
            handleExportGeoJSON();
            break;
        case 'update_location':
            handleUpdateLocation();
            break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get PWD records with location data
$stmt = $pdo->prepare("
    SELECT id, pwd_id_number, first_name, last_name, disability_type, 
           address_line1, city_municipality, province, latitude, longitude,
           status, created_at
    FROM pwd_records 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
    ORDER BY created_at DESC
");
$stmt->execute();
$pwd_locations = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as with_location,
        COUNT(DISTINCT city_municipality) as cities,
        COUNT(DISTINCT province) as provinces
    FROM pwd_records
";
$stats = $pdo->query($stats_query)->fetch();

// Get location distribution by city
$city_stats = $pdo->query("
    SELECT city_municipality, province, COUNT(*) as count,
           AVG(latitude) as avg_lat, AVG(longitude) as avg_lng
    FROM pwd_records 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
    GROUP BY city_municipality, province
    ORDER BY count DESC
    LIMIT 20
")->fetchAll();

function handleImportGeoJSON() {
    global $pdo;
    requirePermission($pdo, 'gis.import');
    
    if (!isset($_FILES['geojson_file'])) {
        adminJsonResponse(['error' => 'No file uploaded'], 400);
    }
    
    $file = $_FILES['geojson_file'];
    $import_type = $_POST['import_type'] ?? 'auto';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        adminJsonResponse(['error' => 'File upload failed'], 400);
    }
    
    $allowed_types = ['application/json', 'application/geo+json', 'text/plain'];
    if (!in_array($file['type'], $allowed_types)) {
        adminJsonResponse(['error' => 'Invalid file type. Please upload a GeoJSON file.'], 400);
    }
    
    try {
        $geojson_content = file_get_contents($file['tmp_name']);
        $geojson_data = json_decode($geojson_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            adminJsonResponse(['error' => 'Invalid JSON format'], 400);
        }
        
        if (!isset($geojson_data['type']) || $geojson_data['type'] !== 'FeatureCollection') {
            adminJsonResponse(['error' => 'Invalid GeoJSON format. Expected FeatureCollection.'], 400);
        }
        
        $geometry_types = [];
        foreach ($geojson_data['features'] as $feature) {
            $geom_type = $feature['geometry']['type'] ?? 'Unknown';
            $geometry_types[$geom_type] = ($geometry_types[$geom_type] ?? 0) + 1;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO gis_import_logs (filename, file_size, import_status, imported_by)
            VALUES (?, ?, 'processing', ?)
        ");
        $stmt->execute([$file['name'], $file['size'], $_SESSION['admin_user_id']]);
        $import_id = $pdo->lastInsertId();
        
        $imported_count = 0;
        $failed_count = 0;
        $errors = [];
        $import_summary = [];
        
        foreach ($geojson_data['features'] as $feature) {
            try {
                $geometry_type = $feature['geometry']['type'];
                $properties = $feature['properties'] ?? [];
                
                if ($geometry_type === 'Point') {
                    $result = importPointFeature($pdo, $feature, $properties);
                    if ($result['success']) {
                        $imported_count++;
                        $import_summary['points'] = ($import_summary['points'] ?? 0) + 1;
                    } else {
                        $failed_count++;
                        $errors[] = $result['error'];
                    }
                } elseif (in_array($geometry_type, ['Polygon', 'MultiPolygon'])) {
                    $result = importPolygonFeature($pdo, $feature, $properties);
                    if ($result['success']) {
                        $imported_count++;
                        $import_summary['polygons'] = ($import_summary['polygons'] ?? 0) + 1;
                    } else {
                        $failed_count++;
                        $errors[] = $result['error'];
                    }
                } else {
                    $failed_count++;
                    $errors[] = "Unsupported geometry type: {$geometry_type}";
                }
            } catch (Exception $e) {
                $failed_count++;
                $errors[] = $e->getMessage();
            }
        }
        
        updateBarangayPWDCounts($pdo);
        
        $stmt = $pdo->prepare("
            UPDATE gis_import_logs 
            SET records_imported = ?, records_failed = ?, import_status = 'completed', 
                error_log = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $imported_count, 
            $failed_count, 
            $errors ? json_encode($errors) : null, 
            $import_id
        ]);
        
        logAdminActivity($pdo, 'import', 'gis', 'geojson', $import_id, [
            'filename' => $file['name'],
            'geometry_types' => $geometry_types,
            'imported' => $imported_count,
            'failed' => $failed_count,
            'summary' => $import_summary
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => "Import completed! {$imported_count} features imported, {$failed_count} failed.",
            'imported' => $imported_count,
            'failed' => $failed_count,
            'geometry_types' => $geometry_types,
            'summary' => $import_summary,
            'errors' => array_slice($errors, 0, 10)
        ]);
        
    } catch (Exception $e) {
        adminJsonResponse(['error' => 'Import failed: ' . $e->getMessage()], 500);
    }
}

function importPointFeature($pdo, $feature, $properties) {
    try {
        $coordinates = $feature['geometry']['coordinates'];
        $longitude = $coordinates[0];
        $latitude = $coordinates[1];
        
        if (isset($properties['pwd_id']) || isset($properties['name'])) {
            $update_conditions = [];
            $update_params = [$latitude, $longitude];
            
            if (isset($properties['pwd_id'])) {
                $update_conditions[] = "pwd_id_number = ?";
                $update_params[] = $properties['pwd_id'];
            } elseif (isset($properties['name'])) {
                $name_parts = explode(' ', $properties['name'], 2);
                $update_conditions[] = "first_name = ? AND last_name = ?";
                $update_params[] = $name_parts[0];
                $update_params[] = $name_parts[1] ?? '';
            }
            
            $update_sql = "UPDATE pwd_records SET latitude = ?, longitude = ?, geojson_data = ? WHERE " . implode(' AND ', $update_conditions);
            $update_params[2] = json_encode($feature);
            
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute($update_params);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => "No matching PWD record found for: " . json_encode($properties)];
            }
        } else {
            return ['success' => false, 'error' => "Point feature missing required properties (pwd_id or name)"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => "Point import error: " . $e->getMessage()];
    }
}

function importPolygonFeature($pdo, $feature, $properties) {
    try {
        $barangay_name = $properties['BRGY'] ?? $properties['barangay'] ?? $properties['BARANGAY'] ?? $properties['name'] ?? $properties['NAME'] ?? '';
        $city_municipality = $properties['city'] ?? $properties['CITY'] ?? $properties['municipality'] ?? $properties['MUNICIPALITY'] ?? $properties['MUNICIPA'] ?? 'Santo Tomas City';
        $province = $properties['province'] ?? $properties['PROVINCE'] ?? $properties['PROV'] ?? 'Batangas';
        $region = $properties['region'] ?? $properties['REGION'] ?? 'Region IV-A (CALABARZON)';
        $barangay_code = $properties['code'] ?? $properties['CODE'] ?? $properties['barangay_code'] ?? $properties['OBJECTID_1'] ?? '';
        
        $area_ha = $properties['AREA_HA'] ?? $properties['area'] ?? $properties['AREA'] ?? null;
        $area_sqkm = $area_ha ? ($area_ha / 100) : null;
        
        $population = $properties['pop2007'] ?? $properties['population'] ?? $properties['POPULATION'] ?? $properties['POP'] ?? null;
        
        if (empty($barangay_name)) {
            return ['success' => false, 'error' => "Polygon feature missing barangay name (BRGY field)"];
        }
        
        $geometry_wkt = convertGeoJSONToWKT($feature['geometry']);
        
        $stmt = $pdo->prepare("
            SELECT id FROM barangay_boundaries 
            WHERE barangay_name = ? AND city_municipality = ?
        ");
        $stmt->execute([$barangay_name, $city_municipality]);
        $existing = $stmt->fetch();
        
        if ($existing) {
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
        return ['success' => false, 'error' => "Polygon import error: " . $e->getMessage()];
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

function updateBarangayPWDCounts($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE barangay_boundaries bb
            SET pwd_count = (
                SELECT COUNT(*)
                FROM pwd_records pr
                WHERE pr.latitude IS NOT NULL 
                AND pr.longitude IS NOT NULL
                AND ST_Contains(bb.geometry, ST_Point(pr.longitude, pr.latitude))
            )
        ");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to update barangay PWD counts: " . $e->getMessage());
    }
}

function handleExportGeoJSON() {
    global $pdo;
    requirePermission($pdo, 'gis.export');
    
    $include_personal_data = $_POST['include_personal_data'] ?? false;
    
    try {
        if ($include_personal_data) {
            $stmt = $pdo->prepare("
                SELECT pwd_id_number, first_name, last_name, disability_type, 
                       city_municipality, province, latitude, longitude, status
                FROM pwd_records 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT pwd_id_number, disability_type, city_municipality, province, 
                       latitude, longitude, status
                FROM pwd_records 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            ");
        }
        
        $stmt->execute();
        $records = $stmt->fetchAll();
        
        $features = [];
        foreach ($records as $record) {
            $properties = [
                'pwd_id' => $record['pwd_id_number'],
                'disability_type' => $record['disability_type'],
                'city' => $record['city_municipality'],
                'province' => $record['province'],
                'status' => $record['status']
            ];
            
            if ($include_personal_data) {
                $properties['name'] = $record['first_name'] . ' ' . $record['last_name'];
            }
            
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($record['longitude']),
                        floatval($record['latitude'])
                    ]
                ],
                'properties' => $properties
            ];
        }
        
        $geojson = [
            'type' => 'FeatureCollection',
            'features' => $features,
            'metadata' => [
                'exported_at' => date('c'),
                'exported_by' => $_SESSION['admin_username'],
                'total_features' => count($features),
                'coordinate_system' => 'EPSG:4326'
            ]
        ];
        
        logAdminActivity($pdo, 'export', 'gis', 'geojson', null, [
            'record_count' => count($features),
            'include_personal_data' => $include_personal_data
        ]);
        
        header('Content-Type: application/geo+json');
        header('Content-Disposition: attachment; filename="pwd_locations_' . date('Y-m-d_H-i-s') . '.geojson"');
        echo json_encode($geojson, JSON_PRETTY_PRINT);
        exit();
        
    } catch (Exception $e) {
        adminJsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }
}

function handleUpdateLocation() {
    global $pdo;
    requirePermission($pdo, 'records.edit');
    
    $record_id = $_POST['record_id'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    
    if (empty($record_id) || empty($latitude) || empty($longitude)) {
        adminJsonResponse(['error' => 'Record ID, latitude, and longitude are required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE pwd_records SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->execute([$latitude, $longitude, $record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found'], 404);
        }
        
        logAdminActivity($pdo, 'edit', 'gis', 'location', $record_id, [
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'Location updated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to update location: ' . $e->getMessage()], 500);
    }
}

// Get barangay boundaries with proper GeoJSON conversion
function getBarangayBoundaries() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, barangay_code, barangay_name, city_municipality, province, 
                   area_sqkm, population, pwd_count, geojson_data
            FROM barangay_boundaries
            ORDER BY barangay_name ASC
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        $boundaries = [];
        foreach ($results as $row) {
            $boundary = $row;
            
            // Parse the stored GeoJSON data
            if (!empty($row['geojson_data'])) {
                $geojson = json_decode($row['geojson_data'], true);
                if ($geojson && isset($geojson['geometry'])) {
                    $boundary['geometry'] = $geojson['geometry'];
                } else {
                    // Skip this boundary if geometry is invalid
                    continue;
                }
            } else {
                // Skip this boundary if no geometry data
                continue;
            }
            
            $boundaries[] = $boundary;
        }
        
        return $boundaries;
    } catch (Exception $e) {
        error_log("Error fetching barangay boundaries: " . $e->getMessage());
        return [];
    }
}

// Get barangay boundaries
$barangay_boundaries = getBarangayBoundaries();

// Get barangay statistics
$barangay_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_barangays,
        SUM(pwd_count) as total_pwd_in_barangays,
        AVG(pwd_count) as avg_pwd_per_barangay,
        MAX(pwd_count) as max_pwd_in_barangay
    FROM barangay_boundaries
")->fetch();

// Calculate map center based on barangay boundaries
$map_center = ['lat' => 14.1078, 'lng' => 121.1414]; // Default to Santo Tomas, Batangas
$map_zoom = 12;

if (!empty($barangay_boundaries)) {
    $lats = [];
    $lngs = [];
    
    foreach ($barangay_boundaries as $boundary) {
        if (isset($boundary['geometry']['coordinates'])) {
            $coords = $boundary['geometry']['coordinates'];
            if ($boundary['geometry']['type'] === 'Polygon') {
                foreach ($coords[0] as $point) {
                    $lngs[] = $point[0];
                    $lats[] = $point[1];
                }
            } elseif ($boundary['geometry']['type'] === 'MultiPolygon') {
                foreach ($coords as $polygon) {
                    foreach ($polygon[0] as $point) {
                        $lngs[] = $point[0];
                        $lats[] = $point[1];
                    }
                }
            }
        }
    }
    
    if (!empty($lats) && !empty($lngs)) {
        $map_center['lat'] = (min($lats) + max($lats)) / 2;
        $map_center['lng'] = (min($lngs) + max($lngs)) / 2;
        
        // Calculate appropriate zoom level based on bounds
        $lat_diff = max($lats) - min($lats);
        $lng_diff = max($lngs) - min($lngs);
        $max_diff = max($lat_diff, $lng_diff);
        
        if ($max_diff > 1) $map_zoom = 8;
        elseif ($max_diff > 0.5) $map_zoom = 10;
        elseif ($max_diff > 0.1) $map_zoom = 12;
        else $map_zoom = 14;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GIS Map - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gis-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }
        
        .map-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .map-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .map-header h3 {
            color: #2c5aa0;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .map-controls {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .layer-controls, .boundary-controls {
            display: flex;
            gap: 16px;
        }
        
        .map-filters {
            display: flex;
            gap: 8px;
        }
        
        .map-filters select {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .gis-map {
            flex: 1;
            min-height: 400px;
        }
        
        .map-legend {
            padding: 12px 20px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .map-legend h4 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: #374151;
        }
        
        .legend-items {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .legend-marker {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .legend-marker.draft { background: #f59e0b; }
        .legend-marker.validated { background: #10b981; }
        .legend-marker.issued { background: #2c5aa0; }
        .legend-marker.boundary { background: #6366f1; border-radius: 2px; }
        
        .analytics-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
        }
        
        .analytics-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .city-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .city-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .city-item:hover {
            background: #f8fafc;
        }
        
        .city-item:last-child {
            border-bottom: none;
        }
        
        .city-info strong {
            display: block;
            color: #1e293b;
            font-size: 0.9rem;
        }
        
        .city-info small {
            color: #64748b;
            font-size: 0.8rem;
        }
        
        .count-badge {
            background: #2c5aa0;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .map-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #1e293b;
            font-size: 0.9rem;
        }
        
        .map-tools {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .custom-marker .marker-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .marker-popup, .barangay-popup {
            min-width: 200px;
        }
        
        .marker-popup h4, .barangay-popup h4 {
            margin: 0 0 8px 0;
            color: #2c5aa0;
        }
        
        .marker-popup p, .barangay-popup p {
            margin: 4px 0;
            font-size: 0.9rem;
            color: #374151;
        }
        
        .popup-actions {
            margin-top: 12px;
            display: flex;
            gap: 6px;
        }
        
        .barangay-stats {
            margin: 12px 0;
            padding: 8px;
            background: #f8fafc;
            border-radius: 4px;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 4px 0;
            font-size: 0.9rem;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .stat-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .choropleth-legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }
        
        .choropleth-legend.show {
            display: block;
        }
        
        .choropleth-legend h4 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: #374151;
        }
        
        .legend-scale {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
        }
        
        .legend-color {
            width: 20px;
            height: 12px;
            border: 1px solid #ccc;
        }
        
        @media (max-width: 1024px) {
            .gis-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .map-container {
                height: 500px;
            }
            
            .map-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .map-controls {
                justify-content: space-between;
            }
        }
        
        @media (max-width: 768px) {
            .map-controls {
                flex-direction: column;
                gap: 12px;
            }
            
            .layer-controls, .boundary-controls {
                flex-direction: column;
                gap: 8px;
            }
            
            .map-filters {
                flex-direction: column;
            }
            
            .legend-items {
                flex-direction: column;
                gap: 8px;
            }
            
            .map-tools {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-map-marked-alt"></i> GIS Mapping System</h1>
                <p>Geographic Information System for PWD records - Santo Tomas, Batangas</p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission($pdo, 'gis.import')): ?>
                    <a href="gis_diagnostic.php" class="btn btn-success">
                        <i class="fas fa-tools"></i> GIS Tools
                    </a>
                    <button class="btn btn-success" onclick="showImportModal()">
                        <i class="fas fa-upload"></i> Import GeoJSON
                    </button>
                <?php endif; ?>
                <?php if (hasPermission($pdo, 'gis.export')): ?>
                    <button class="btn btn-outline" onclick="showExportModal()">
                        <i class="fas fa-download"></i> Export GeoJSON
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['with_location']); ?></h3>
                    <p>Records with Location</p>
                    <span class="stat-change">
                        <?php 
                        $percentage = $stats['total_records'] > 0 ? round(($stats['with_location'] / $stats['total_records']) * 100, 1) : 0;
                        echo $percentage . '% of total records';
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-city"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['cities']); ?></h3>
                    <p>Cities Covered</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-map"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($barangay_stats['total_barangays']); ?></h3>
                    <p>Barangay Boundaries</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_records']); ?></h3>
                    <p>Total PWD Records</p>
                </div>
            </div>
        </div>
        
        <!-- Map and Analytics Layout -->
        <div class="gis-layout">
            <!-- Map Container -->
            <div class="map-container">
                <div class="map-header">
                    <h3><i class="fas fa-globe"></i> Interactive Map - Santo Tomas, Batangas</h3>
                    <div class="map-controls">
                        <div class="layer-controls">
                            <label class="checkbox-label">
                                <input type="checkbox" id="clusterMarkers" checked onchange="toggleClustering()">
                                <span class="checkmark"></span>
                                Cluster Markers
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="showHeatmap" onchange="toggleHeatmap()">
                                <span class="checkmark"></span>
                                Heat Map
                            </label>
                        </div>
                        <div class="boundary-controls">
                            <label class="checkbox-label">
                                <input type="checkbox" id="showBoundaries" checked onchange="toggleBoundaries()">
                                <span class="checkmark"></span>
                                Show Boundaries
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="choroplethMode" onchange="toggleChoropleth()">
                                <span class="checkmark"></span>
                                Choropleth Mode
                            </label>
                        </div>
                        <div class="map-filters">
                            <select id="disabilityFilter" onchange="filterMarkers()">
                                <option value="">All Disabilities</option>
                                <option value="Physical Disability">Physical Disability</option>
                                <option value="Visual Impairment">Visual Impairment</option>
                                <option value="Hearing Impairment">Hearing Impairment</option>
                                <option value="Intellectual Disability">Intellectual Disability</option>
                                <option value="Psychosocial Disability">Psychosocial Disability</option>
                                <option value="Multiple Disabilities">Multiple Disabilities</option>
                            </select>
                            <select id="statusFilter" onchange="filterMarkers()">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="validated">Validated</option>
                                <option value="issued">Issued</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="gisMap" class="gis-map"></div>
                <div class="map-legend">
                    <h4>Legend</h4>
                    <div class="legend-items">
                        <div class="legend-item">
                            <div class="legend-marker boundary"></div>
                            <span>Barangay Boundaries</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-marker draft"></div>
                            <span>Draft Records</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-marker validated"></div>
                            <span>Validated Records</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-marker issued"></div>
                            <span>Issued IDs</span>
                        </div>
                    </div>
                </div>
                
                <!-- Choropleth Legend -->
                <div id="choroplethLegend" class="choropleth-legend">
                    <h4>PWD Density</h4>
                    <div class="legend-scale">
                        <span>Low</span>
                        <div class="legend-color" style="background: #f0f9ff;"></div>
                        <div class="legend-color" style="background: #bae6fd;"></div>
                        <div class="legend-color" style="background: #38bdf8;"></div>
                        <div class="legend-color" style="background: #0284c7;"></div>
                        <div class="legend-color" style="background: #1e40af;"></div>
                        <span>High</span>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Panel -->
            <div class="analytics-panel">
                <div class="analytics-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Location Distribution</h3>
                    </div>
                    <div class="card-content">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Barangay List</h3>
                    </div>
                    <div class="card-content">
                        <div class="city-list">
                            <?php foreach (array_slice($barangay_boundaries, 0, 15) as $barangay): ?>
                                <div class="city-item" onclick="focusOnBarangay('<?php echo htmlspecialchars($barangay['barangay_name']); ?>')">
                                    <div class="city-info">
                                        <strong><?php echo htmlspecialchars($barangay['barangay_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($barangay['city_municipality'] ?: 'Santo Tomas City'); ?></small>
                                    </div>
                                    <div class="city-count">
                                        <span class="count-badge"><?php echo $barangay['pwd_count'] ?? 0; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Map Information</h3>
                    </div>
                    <div class="card-content">
                        <div class="map-info">
                            <div class="info-item">
                                <span class="info-label">Location:</span>
                                <span class="info-value">Santo Tomas, Batangas</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Coordinate System:</span>
                                <span class="info-value">EPSG:4326 (WGS84)</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Barangay Boundaries:</span>
                                <span class="info-value"><?php echo count($barangay_boundaries); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Visible Markers:</span>
                                <span class="info-value" id="visibleMarkers"><?php echo count($pwd_locations); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-header">
                        <h3><i class="fas fa-tools"></i> Map Tools</h3>
                    </div>
                    <div class="card-content">
                        <div class="map-tools">
                            <button class="btn btn-outline btn-sm" onclick="centerMap()">
                                <i class="fas fa-crosshairs"></i> Center Map
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="fitAllBoundaries()">
                                <i class="fas fa-expand-arrows-alt"></i> Fit All
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="refreshMap()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="toggleFullscreen()">
                                <i class="fas fa-expand"></i> Fullscreen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Import GeoJSON Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import GeoJSON Data</h3>
                <button class="modal-close" onclick="closeModal('importModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="geojsonFile">Select GeoJSON File</label>
                        <input type="file" id="geojsonFile" name="geojson_file" accept=".geojson,.json" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="importType">Import Type</label>
                        <select id="importType" name="import_type">
                            <option value="auto">Auto-detect (Recommended)</option>
                            <option value="points">Points Only (PWD Records)</option>
                            <option value="polygons">Polygons Only (Barangay Boundaries)</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Import Data
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('importModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Export GeoJSON Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Export GeoJSON Data</h3>
                <button class="modal-close" onclick="closeModal('exportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="includePersonalData" name="include_personal_data">
                            <span class="checkmark"></span>
                            Include Personal Data (Names)
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('exportModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
        let map;
        let markers = [];
        let barangayLayers = [];
        let markerClusterGroup;
        let heatmapLayer;
        let allPWDLocations = <?php echo json_encode($pwd_locations); ?>;
        let barangayBoundaries = <?php echo json_encode($barangay_boundaries); ?>;
        let filteredLocations = [...allPWDLocations];
        let showBoundaries = true;
        let choroplethMode = false;
        let mapCenter = <?php echo json_encode($map_center); ?>;
        let mapZoom = <?php echo $map_zoom; ?>;

        // Initialize the map
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing map with center:', mapCenter, 'zoom:', mapZoom);
            console.log('Barangay boundaries to load:', barangayBoundaries.length);
            
            initializeMap();
            initializeCharts();
            loadMarkers();
            loadBarangayBoundaries();
        });

        function initializeMap() {
            // Center on Santo Tomas, Batangas or calculated center
            map = L.map('gisMap').setView([mapCenter.lat, mapCenter.lng], mapZoom);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Initialize marker cluster group
            markerClusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50
            });
            
            // Add click event to map
            map.on('click', function(e) {
                console.log('Clicked at:', e.latlng.lat, e.latlng.lng);
            });
            
            console.log('Map initialized successfully');
        }

        function loadBarangayBoundaries() {
            console.log('Loading barangay boundaries...', barangayBoundaries.length);
            
            // Clear existing boundary layers
            barangayLayers.forEach(layer => {
                if (map.hasLayer(layer)) {
                    map.removeLayer(layer);
                }
            });
            barangayLayers = [];
            
            if (!showBoundaries) {
                console.log('Boundaries hidden by user setting');
                return;
            }
            
            let loadedCount = 0;
            let errorCount = 0;
            
            barangayBoundaries.forEach(function(barangay, index) {
                try {
                    if (!barangay.geometry || !barangay.geometry.coordinates) {
                        console.warn('No geometry data for barangay:', barangay.barangay_name);
                        errorCount++;
                        return;
                    }
                    
                    // Create GeoJSON feature
                    const feature = {
                        type: 'Feature',
                        geometry: barangay.geometry,
                        properties: {
                            id: barangay.id,
                            name: barangay.barangay_name,
                            city: barangay.city_municipality,
                            province: barangay.province,
                            area: barangay.area_sqkm,
                            population: barangay.population,
                            pwd_count: barangay.pwd_count || 0
                        }
                    };
                    
                    // Create polygon layer with enhanced styling
                    const layer = L.geoJSON(feature, {
                        style: function(feature) {
                            return getBarangayStyle(barangay);
                        },
                        onEachFeature: function(feature, layer) {
                            // Create popup content
                            const popupContent = `
                                <div class="barangay-popup">
                                    <h4>${barangay.barangay_name}</h4>
                                    <p><strong>${barangay.city_municipality || 'Santo Tomas City'}</strong></p>
                                    <p><i class="fas fa-map-marker-alt"></i> ${barangay.province || 'Batangas'}</p>
                                    <div class="barangay-stats">
                                        <div class="stat-item">
                                            <span class="stat-label">PWD Records:</span>
                                            <span class="stat-value">${barangay.pwd_count || 0}</span>
                                        </div>
                                        ${barangay.population ? `
                                        <div class="stat-item">
                                            <span class="stat-label">Population:</span>
                                            <span class="stat-value">${parseInt(barangay.population).toLocaleString()}</span>
                                        </div>
                                        ` : ''}
                                        ${barangay.area_sqkm ? `
                                        <div class="stat-item">
                                            <span class="stat-label">Area:</span>
                                            <span class="stat-value">${parseFloat(barangay.area_sqkm).toFixed(2)} km²</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                    <div class="popup-actions">
                                        <button class="btn btn-sm btn-primary" onclick="viewBarangayDetails(${barangay.id})">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-sm btn-outline" onclick="filterByBarangay('${barangay.barangay_name}')">
                                            <i class="fas fa-filter"></i> Filter PWDs
                                        </button>
                                    </div>
                                </div>
                            `;
                            
                            layer.bindPopup(popupContent);
                            
                            // Add hover effects
                            layer.on('mouseover', function(e) {
                                this.setStyle({
                                    weight: 4,
                                    fillOpacity: 0.8,
                                    color: '#2c5aa0'
                                });
                                
                                if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                    layer.bringToFront();
                                }
                            });
                            
                            layer.on('mouseout', function(e) {
                                this.setStyle(getBarangayStyle(barangay));
                            });
                            
                            // Add click handler
                            layer.on('click', function(e) {
                                layer.openPopup();
                            });
                        }
                    });
                    
                    barangayLayers.push(layer);
                    layer.addTo(map);
                    loadedCount++;
                    
                } catch (error) {
                    console.error('Error loading barangay boundary for', barangay.barangay_name, ':', error);
                    errorCount++;
                }
            });
            
            console.log(`Successfully loaded ${loadedCount} out of ${barangayBoundaries.length} barangay boundaries`);
            if (errorCount > 0) {
                console.warn(`Failed to load ${errorCount} barangay boundaries`);
            }
            
            // Update choropleth legend visibility
            const legend = document.getElementById('choroplethLegend');
            if (legend) {
                if (choroplethMode && showBoundaries) {
                    legend.classList.add('show');
                } else {
                    legend.classList.remove('show');
                }
            }
            
            // Show success message
            if (loadedCount > 0) {
                showNotification(`Successfully loaded ${loadedCount} barangay boundaries for Santo Tomas, Batangas!`, 'success', 3000);
            }
        }

        function getBarangayStyle(barangay) {
            const pwdCount = barangay.pwd_count || 0;
            
            if (choroplethMode) {
                // Choropleth coloring based on PWD density
                const maxCount = Math.max(...barangayBoundaries.map(b => b.pwd_count || 0));
                const intensity = maxCount > 0 ? pwdCount / maxCount : 0;
                
                return {
                    fillColor: getColorForIntensity(intensity),
                    weight: 2,
                    opacity: 0.9,
                    color: '#374151',
                    fillOpacity: 0.7
                };
            } else {
                // Default styling with better visibility
                return {
                    fillColor: pwdCount > 0 ? '#6366f1' : '#e2e8f0',
                    weight: 2,
                    opacity: 0.8,
                    color: '#475569',
                    fillOpacity: pwdCount > 0 ? 0.5 : 0.3
                };
            }
        }

        function getColorForIntensity(intensity) {
            // Color scale from light blue to dark blue
            const colors = [
                '#f0f9ff', '#e0f2fe', '#bae6fd', '#7dd3fc', 
                '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#1e40af'
            ];
            const index = Math.floor(intensity * (colors.length - 1));
            return colors[index] || colors[0];
        }

        function toggleBoundaries() {
            showBoundaries = !showBoundaries;
            console.log('Toggling boundaries:', showBoundaries);
            loadBarangayBoundaries();
        }

        function toggleChoropleth() {
            choroplethMode = !choroplethMode;
            console.log('Toggling choropleth mode:', choroplethMode);
            loadBarangayBoundaries();
        }

        function viewBarangayDetails(barangayId) {
            showNotification('Barangay details functionality will be implemented', 'info');
        }

        function filterByBarangay(barangayName) {
            showNotification(`Filtering PWD records in ${barangayName}`, 'info');
        }

        function focusOnBarangay(barangayName) {
            // Find the barangay layer and zoom to it
            const barangay = barangayBoundaries.find(b => b.barangay_name === barangayName);
            if (barangay && barangay.geometry && barangay.geometry.coordinates) {
                // Calculate bounds from geometry
                let bounds = [];
                const coords = barangay.geometry.coordinates;
                
                if (barangay.geometry.type === 'Polygon') {
                    coords[0].forEach(point => {
                        bounds.push([point[1], point[0]]); // [lat, lng]
                    });
                } else if (barangay.geometry.type === 'MultiPolygon') {
                    coords[0][0].forEach(point => {
                        bounds.push([point[1], point[0]]); // [lat, lng]
                    });
                }
                
                if (bounds.length > 0) {
                    const leafletBounds = L.latLngBounds(bounds);
                    map.fitBounds(leafletBounds, { padding: [20, 20] });
                }
            }
        }
        
        function loadMarkers() {
            // Clear existing markers
            clearMarkers();
            
            filteredLocations.forEach(function(location) {
                const marker = createMarker(location);
                markers.push(marker);
                
                if (document.getElementById('clusterMarkers').checked) {
                    markerClusterGroup.addLayer(marker);
                } else {
                    marker.addTo(map);
                }
            });
            
            if (document.getElementById('clusterMarkers').checked) {
                map.addLayer(markerClusterGroup);
            }
            
            updateVisibleMarkers();
        }
        
        function createMarker(location) {
            const lat = parseFloat(location.latitude);
            const lng = parseFloat(location.longitude);
            
            // Create custom icon based on status
            const iconColor = getStatusColor(location.status);
            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div class="marker-icon ${location.status}" style="background-color: ${iconColor};">
                         <i class="fas fa-wheelchair"></i>
                       </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const marker = L.marker([lat, lng], { icon: icon });
            
            // Create popup content
            const popupContent = `
                <div class="marker-popup">
                    <h4>${location.pwd_id_number}</h4>
                    <p><strong>${location.first_name} ${location.last_name}</strong></p>
                    <p><i class="fas fa-wheelchair"></i> ${location.disability_type}</p>
                    <p><i class="fas fa-map-marker-alt"></i> ${location.city_municipality}, ${location.province}</p>
                    <p><i class="fas fa-flag"></i> Status: <span class="status-badge status-${location.status}">${location.status}</span></p>
                    <div class="popup-actions">
                        <button class="btn btn-sm btn-primary" onclick="viewRecord(${location.id})">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="updateLocation(${location.id}, ${lat}, ${lng})">
                            <i class="fas fa-edit"></i> Update Location
                        </button>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            
            return marker;
        }
        
        function getStatusColor(status) {
            const colors = {
                'draft': '#f59e0b',
                'validated': '#10b981',
                'issued': '#2c5aa0',
                'expired': '#ef4444',
                'revoked': '#6b7280'
            };
            return colors[status] || '#6b7280';
        }
        
        function clearMarkers() {
            markers.forEach(marker => {
                map.removeLayer(marker);
                markerClusterGroup.removeLayer(marker);
            });
            markers = [];
            markerClusterGroup.clearLayers();
            map.removeLayer(markerClusterGroup);
        }
        
        function filterMarkers() {
            const disabilityFilter = document.getElementById('disabilityFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            filteredLocations = allPWDLocations.filter(location => {
                const matchesDisability = !disabilityFilter || location.disability_type === disabilityFilter;
                const matchesStatus = !statusFilter || location.status === statusFilter;
                return matchesDisability && matchesStatus;
            });
            
            loadMarkers();
        }
        
        function toggleClustering() {
            loadMarkers();
        }
        
        function toggleHeatmap() {
            // Heatmap functionality would be implemented here
            showNotification('Heatmap functionality will be implemented', 'info');
        }
        
        function updateVisibleMarkers() {
            document.getElementById('visibleMarkers').textContent = filteredLocations.length;
        }
        
        function centerMap() {
            map.setView([mapCenter.lat, mapCenter.lng], mapZoom);
        }
        
        function fitAllBoundaries() {
            if (barangayLayers.length > 0) {
                const group = new L.featureGroup(barangayLayers);
                map.fitBounds(group.getBounds(), { padding: [20, 20] });
            } else {
                centerMap();
            }
        }
        
        function refreshMap() {
            location.reload();
        }
        
        function toggleFullscreen() {
            const mapContainer = document.querySelector('.map-container');
            if (!document.fullscreenElement) {
                mapContainer.requestFullscreen().then(() => {
                    setTimeout(() => map.invalidateSize(), 100);
                });
            } else {
                document.exitFullscreen().then(() => {
                    setTimeout(() => map.invalidateSize(), 100);
                });
            }
        }
        
        function viewRecord(recordId) {
            window.open(`records.php?id=${recordId}`, '_blank');
        }
        
        function updateLocation(recordId, lat, lng) {
            showNotification('Location update functionality will be implemented', 'info');
        }
        
        function initializeCharts() {
            // Location distribution chart
            const ctx = document.getElementById('locationChart').getContext('2d');
            const cityData = <?php echo json_encode($city_stats); ?>;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: cityData.map(city => city.city_municipality),
                    datasets: [{
                        data: cityData.map(city => city.count),
                        backgroundColor: [
                            '#2c5aa0', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Import/Export functionality
        function showImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }
        
        function showExportModal() {
            document.getElementById('exportModal').style.display = 'block';
        }
        
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'import_geojson');
            formData.append('geojson_file', document.getElementById('geojsonFile').files[0]);
            formData.append('import_type', document.getElementById('importType').value);
            
            showLoading('Importing GeoJSON data...');
            
            fetch('map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('importModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error || 'Import failed', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Import failed: ' + error.message, 'error');
            });
        });
        
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'export_geojson');
            formData.append('include_personal_data', document.getElementById('includePersonalData').checked);
            
            showLoading('Exporting GeoJSON data...');
            
            fetch('map.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                hideLoading();
                if (response.ok) {
                    return response.blob();
                } else {
                    throw new Error('Export failed');
                }
            })
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'pwd_locations_' + new Date().toISOString().slice(0, 10) + '.geojson';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showNotification('GeoJSON exported successfully', 'success');
                closeModal('exportModal');
            })
            .catch(error => {
                hideLoading();
                showNotification('Export failed: ' + error.message, 'error');
            });
        });
    </script>
</body>
</html>
