<?php

function isPointInPolygon($latitude, $longitude, $polygon) {
    $x = $longitude;
    $y = $latitude;
    $inside = false;
    
    $j = count($polygon) - 1;
    for ($i = 0; $i < count($polygon); $i++) {
        $xi = $polygon[$i][0];
        $yi = $polygon[$i][1];
        $xj = $polygon[$j][0];
        $yj = $polygon[$j][1];
        
        if ((($yi > $y) != ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
        $j = $i;
    }
    
    return $inside;
}

function isPointInBarangay($latitude, $longitude, $geojson_data) {
    if (empty($geojson_data)) {
        return false;
    }
    
    $geojson = json_decode($geojson_data, true);
    
    if (!$geojson || !isset($geojson['geometry'])) {
        return false;
    }
    
    $geometry = $geojson['geometry'];
    
    switch ($geometry['type']) {
        case 'Polygon':
            return isPointInPolygon($latitude, $longitude, $geometry['coordinates'][0]);
            
        case 'MultiPolygon':
            foreach ($geometry['coordinates'] as $polygon) {
                if (isPointInPolygon($latitude, $longitude, $polygon[0])) {
                    return true;
                }
            }
            return false;
            
        default:
            return false;
    }
}

function manualCountAndAssign($pdo) {
    $results = [
        'total_checked' => 0,
        'assigned' => 0,
        'errors' => []
    ];
    
    try {
        // Get all PWD records with coordinates
        $stmt = $pdo->query("
            SELECT id, latitude, longitude, barangay_id 
            FROM pwd_records 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ");
        $pwd_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get all barangays with GeoJSON data
        $stmt = $pdo->query("
            SELECT id, name, geojson_data 
            FROM barangay_boundaries 
            WHERE geojson_data IS NOT NULL
        ");
        $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['total_checked'] = count($pwd_records);
        
        foreach ($pwd_records as $record) {
            $assigned = false;
            
            foreach ($barangays as $barangay) {
                if (isPointInBarangay($record['latitude'], $record['longitude'], $barangay['geojson_data'])) {
                    // Assign this PWD record to this barangay
                    $updateStmt = $pdo->prepare("UPDATE pwd_records SET barangay_id = ? WHERE id = ?");
                    $updateStmt->execute([$barangay['id'], $record['id']]);
                    
                    $results['assigned']++;
                    $assigned = true;
                    break;
                }
            }
            
            if (!$assigned && $record['barangay_id'] !== null) {
                // Clear assignment if no longer in any barangay
                $updateStmt = $pdo->prepare("UPDATE pwd_records SET barangay_id = NULL WHERE id = ?");
                $updateStmt->execute([$record['id']]);
            }
        }
        
        // Update PWD counts for all barangays
        $stmt = $pdo->prepare("
            UPDATE barangay_boundaries 
            SET pwd_count = (
                SELECT COUNT(*) 
                FROM pwd_records 
                WHERE barangay_id = barangay_boundaries.id
            )
        ");
        $stmt->execute();
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

function debugSpatialCalculations($pdo) {
    $debug = [];
    
    try {
        // Check database structure
        $debug['database_check'] = [];
        
        $tables = ['pwd_records', 'barangay_boundaries'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("DESCRIBE $table");
            $debug['database_check'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Sample spatial calculation
        $stmt = $pdo->query("
            SELECT p.id, p.latitude, p.longitude, p.barangay_id,
                   b.name as barangay_name
            FROM pwd_records p
            LEFT JOIN barangay_boundaries b ON p.barangay_id = b.id
            WHERE p.latitude IS NOT NULL AND p.longitude IS NOT NULL
            LIMIT 5
        ");
        $debug['sample_records'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Barangay data sample
        $stmt = $pdo->query("
            SELECT id, name, 
                   CASE 
                       WHEN geojson_data IS NOT NULL THEN 'Has GeoJSON'
                       ELSE 'No GeoJSON'
                   END as geojson_status,
                   pwd_count
            FROM barangay_boundaries
            LIMIT 5
        ");
        $debug['sample_barangays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $debug['error'] = $e->getMessage();
    }
    
    return $debug;
}

function countPWDInBarangay($pdo, $barangay_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pwd_records WHERE barangay_id = ?");
        $stmt->execute([$barangay_id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function assignPWDToBarangays($pdo) {
    return manualCountAndAssign($pdo);
}
?>
