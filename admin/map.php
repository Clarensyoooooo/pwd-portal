<?php
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'gis.view');
require_once 'spatial_functions.php';

$admin = getCurrentAdmin($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'export_geojson':
                handleExportGeoJSON();
                break;
            case 'update_location':
                handleUpdateLocation();
                break;
            case 'quick_refresh':
                handleQuickRefresh();
                break;
            case 'get_barangay_records':
                handleGetBarangayRecords();
                break;
            case 'get_import_status':
                handleGetImportStatus();
                break;
            case 'get_detailed_stats':
                handleGetDetailedStats();
                break;
            case 'export_map_report':
                handleExportMapReport();
                break;
            default:
                adminJsonResponse(['error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        error_log("Map.php error: " . $e->getMessage());
        adminJsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

function handleQuickRefresh() {
    global $pdo;
    
    try {
        requirePermission($pdo, 'gis.import');
        
        // --- START of logic copied from debug_spatial.php ---
        
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
        
        // --- END of logic from debug_spatial.php ---

        // Store results for the AJAX response
        $results = [
            'processed' => $total_processed,
            'assigned' => $assigned_count
        ];
        
        logAdminActivity($pdo, 'update', 'gis', 'quick_refresh', null, $results);
        
        adminJsonResponse([
            'success' => true,
            'message' => "Map refreshed successfully! {$results['assigned']} PWD records assigned to barangays.",
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        error_log("Quick refresh error: " . $e->getMessage());
        adminJsonResponse(['error' => 'Quick refresh failed: ' . $e->getMessage()], 500);
    }
}

function handleGetImportStatus() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT filename, completed_at, records_imported, records_failed
            FROM gis_import_logs 
            WHERE import_status = 'completed'
            ORDER BY completed_at DESC 
            LIMIT 1
        ");
        $lastImport = $stmt->fetch();
        
        adminJsonResponse([
            'success' => true,
            'last_import' => $lastImport
        ]);
        
    } catch (Exception $e) {
        error_log("Import status error: " . $e->getMessage());
        adminJsonResponse(['error' => 'Failed to get import status'], 500);
    }
}

function handleGetDetailedStats() {
    global $pdo;
    
    try {
        // Disability type distribution
        $disabilityStats = $pdo->query("
            SELECT disability_type, COUNT(*) as count
            FROM pwd_records 
            WHERE disability_type IS NOT NULL AND disability_type != ''
            GROUP BY disability_type
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Status distribution
        $statusStats = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM pwd_records 
            GROUP BY status
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Top 10 barangays by PWD count
        $barangayStats = $pdo->query("
            SELECT b.barangay_name, b.city_municipality, COUNT(p.id) as count
            FROM barangay_boundaries b
            LEFT JOIN pwd_records p ON b.id = p.barangay_id
            GROUP BY b.id, b.barangay_name, b.city_municipality
            HAVING count > 0
            ORDER BY count DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly registration trends (last 12 months)
        $monthlyStats = $pdo->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM pwd_records 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Age distribution
        $ageStats = $pdo->query("
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
                    ELSE 'Over 65'
                END as age_group,
                COUNT(*) as count
            FROM pwd_records 
            WHERE date_of_birth IS NOT NULL
            GROUP BY age_group
            ORDER BY 
                CASE age_group
                    WHEN 'Under 18' THEN 1
                    WHEN '18-30' THEN 2
                    WHEN '31-50' THEN 3
                    WHEN '51-65' THEN 4
                    WHEN 'Over 65' THEN 5
                END
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Gender distribution
        $genderStats = $pdo->query("
            SELECT gender, COUNT(*) as count
            FROM pwd_records 
            WHERE gender IS NOT NULL AND gender != ''
            GROUP BY gender
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Location coverage
        $locationStats = $pdo->query("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as with_location,
                SUM(CASE WHEN barangay_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_to_barangay,
                COUNT(DISTINCT city_municipality) as cities_covered,
                COUNT(DISTINCT province) as provinces_covered
            FROM pwd_records
        ")->fetch(PDO::FETCH_ASSOC);
        
        // Barangay coverage
        $barangayCoverage = $pdo->query("
            SELECT 
                COUNT(*) as total_barangays,
                SUM(CASE WHEN pwd_count > 0 THEN 1 ELSE 0 END) as barangays_with_pwd,
                AVG(pwd_count) as avg_pwd_per_barangay,
                MAX(pwd_count) as max_pwd_in_barangay
            FROM barangay_boundaries
        ")->fetch(PDO::FETCH_ASSOC);
        
        adminJsonResponse([
            'success' => true,
            'disability_stats' => $disabilityStats ?: [],
            'status_stats' => $statusStats ?: [],
            'barangay_stats' => $barangayStats ?: [],
            'monthly_stats' => $monthlyStats ?: [],
            'age_stats' => $ageStats ?: [],
            'gender_stats' => $genderStats ?: [],
            'location_stats' => $locationStats ?: [
                'total_records' => 0,
                'with_location' => 0,
                'assigned_to_barangay' => 0,
                'cities_covered' => 0,
                'provinces_covered' => 0
            ],
            'barangay_coverage' => $barangayCoverage ?: [
                'total_barangays' => 0,
                'barangays_with_pwd' => 0,
                'avg_pwd_per_barangay' => 0,
                'max_pwd_in_barangay' => 0
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Detailed stats error: " . $e->getMessage());
        adminJsonResponse(['error' => 'Failed to get detailed stats: ' . $e->getMessage()], 500);
    }
}

function handleExportMapReport() {
    global $pdo;
    ob_start(); // Start output buffering immediately

    try {
        requirePermission($pdo, 'reports.export');
        
        $filters = $_POST['filters'] ?? [];
        
        // --- DATA PREPARATION ---
        
        // Build query based on filters
        $where_clauses = ['1=1'];
        $params = [];
        
        // Read filters from the POST data
        $disability_filter = $filters['disability_type'] ?? '';
        $status_filter = $filters['status'] ?? '';
        $barangay_id_filter = $filters['barangay_id'] ?? '';
        
        if (!empty($disability_filter)) {
            $where_clauses[] = 'p.disability_type = ?';
            $params[] = $disability_filter;
        }
        
        if (!empty($status_filter)) {
            if ($status_filter === 'expired') {
                $where_clauses[] = "(p.status = 'issued' AND p.expiry_date < CURDATE())";
            } else if ($status_filter === 'issued') {
                $where_clauses[] = "(p.status = 'issued' AND (p.expiry_date IS NULL OR p.expiry_date >= CURDATE()))";
            } else {
                $where_clauses[] = 'p.status = ?';
                $params[] = $status_filter;
            }
        }
        
        if (!empty($barangay_id_filter)) {
            $where_clauses[] = 'p.barangay_id = ?';
            $params[] = $barangay_id_filter;
        }
        
        // Ensure we only get geolocated records for a *map* report
        $where_clauses[] = "p.latitude IS NOT NULL AND p.longitude IS NOT NULL";
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Get detailed data
        $stmt = $pdo->prepare("
            SELECT 
                p.pwd_id_number, p.first_name, p.last_name, p.disability_type,
                p.address_line1, p.barangay, p.city_municipality, p.province,
                p.status, p.created_at, p.expiry_date
            FROM pwd_records p 
            WHERE $where_sql
            ORDER BY p.barangay, p.last_name, p.first_name
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // --- PREPARE STATS QUERIES ---
        // We use the exact same $where_sql and $params for all stats
        
        // Get Barangay summary
        $stmt = $pdo->prepare("
            SELECT b.barangay_name, b.city_municipality, COUNT(p.id) as count
            FROM barangay_boundaries b
            JOIN pwd_records p ON b.id = p.barangay_id
            WHERE $where_sql
            GROUP BY b.id, b.barangay_name, b.city_municipality
            HAVING count > 0
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $barangay_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Disability Type stats
        $stmt = $pdo->prepare("
            SELECT p.disability_type, COUNT(*) as count
            FROM pwd_records p
            WHERE $where_sql 
              AND p.disability_type IS NOT NULL AND p.disability_type != ''
            GROUP BY p.disability_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $disabilityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get Age Distribution stats
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 18 THEN 'Under 18'
                    WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                    WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
                    WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN '51-65'
                    ELSE 'Over 65'
                END as age_group,
                COUNT(*) as count
            FROM pwd_records p
            WHERE $where_sql AND p.date_of_birth IS NOT NULL
            GROUP BY age_group
            ORDER BY 
                CASE age_group
                    WHEN 'Under 18' THEN 1
                    WHEN '18-30' THEN 2
                    WHEN '31-50' THEN 3
                    WHEN '51-65' THEN 4
                    WHEN 'Over 65' THEN 5
                END
        ");
        $stmt->execute($params);
        $ageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        

        // --- PDF GENERATION ---
        
        require_once '../vendor/autoload.php'; // Corrected path
        
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('PWD Portal');
        $pdf->SetAuthor($_SESSION['admin_username']);
        $pdf->SetTitle('PWD Map Report - ' . date('Y-m-d'));
        $pdf->SetSubject('Community Presence Report');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        $pdf->AddPage();
        
        // Title
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 10, 'PWD Community Presence Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Santo Tomas, Batangas', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // Summary section
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Overview', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(90, 6, 'Total Community Members (Geolocated):', 0, 0, 'L');
        $pdf->Cell(0, 6, count($records), 0, 1, 'L');
        
        $pdf->Cell(90, 6, 'Barangays Covered (in this filter):', 0, 0, 'L');
        $pdf->Cell(0, 6, count($barangay_summary), 0, 1, 'L');
        
        if (!empty($disability_filter)) {
            $pdf->Cell(90, 6, 'Disability Type Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, $disability_filter, 0, 1, 'L');
        }
        
        if (!empty($status_filter)) {
            $pdf->Cell(90, 6, 'Status Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, ucfirst($status_filter), 0, 1, 'L');
        }
        
        if (!empty($barangay_id_filter)) {
            $brgy_name_stmt = $pdo->prepare("SELECT barangay_name FROM barangay_boundaries WHERE id = ?");
            $brgy_name_stmt->execute([$barangay_id_filter]);
            $brgy_name = $brgy_name_stmt->fetchColumn();
            
            $pdf->Cell(90, 6, 'Barangay Filter:', 0, 0, 'L');
            $pdf->Cell(0, 6, $brgy_name ?: "ID $barangay_id_filter", 0, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // --- SERVICE-DRIVEN SUMMARY SECTION ---
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Service-Driven Summary', 0, 1, 'L');
        
        // Disability Type Table
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Summary by Disability Type', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240); // Light gray header
        $pdf->SetTextColor(0);
        $pdf->Cell(120, 7, 'Disability Type', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Total Members', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;
        if (!empty($disabilityStats)) {
            foreach ($disabilityStats as $row) {
                $pdf->Cell(120, 6, $row['disability_type'], 1, 0, 'L', $fill);
                $pdf->Cell(60, 6, $row['count'], 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        } else {
            $pdf->Cell(180, 6, 'No disability data available for this selection', 1, 1, 'C', $fill);
        }
        $pdf->Ln(5);
        
        // Age Group Table
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Summary by Age Group', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(120, 7, 'Age Group', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Total Members', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;
        if (!empty($ageStats)) {
            foreach ($ageStats as $row) {
                $pdf->Cell(120, 6, $row['age_group'], 1, 0, 'L', $fill);
                $pdf->Cell(60, 6, $row['count'], 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        } else {
            $pdf->Cell(180, 6, 'No age data available for this selection', 1, 1, 'C', $fill);
        }
        $pdf->Ln(5);

        // --- END OF NEW SECTION ---

        // Barangay summary (Original Table)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Community Presence by Barangay', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(44, 90, 160);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(120, 7, 'Barangay', 1, 0, 'L', true);
        $pdf->Cell(60, 7, 'Registered Members', 1, 1, 'C', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(248, 250, 252);
        $fill = false;
        
        if (!empty($barangay_summary)) {
            foreach ($barangay_summary as $row) {
                $pdf->Cell(120, 6, $row['barangay_name'], 1, 0, 'L', $fill);
                $pdf->Cell(60, 6, $row['count'], 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        } else {
             $pdf->Cell(180, 6, 'No members found in any barangay for this selection', 1, 1, 'C', $fill);
        }
        
        // Log the export
        logAdminActivity($pdo, 'export', 'gis', 'map_report', null, [
            'record_count' => count($records),
            'barangay_count' => count($barangay_summary),
            'filters' => $filters
        ]);
        
        ob_end_clean(); 
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="pwd_map_report_' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        $pdf->Output('pwd_map_report_' . date('Y-m-d') . '.pdf', 'D');
        exit();
        
    } catch (Exception $e) {
        ob_end_clean(); 
        error_log("Map report export error: " . $e->getMessage());
        // Return a JSON error instead of dying
        adminJsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }
}

function handleGetBarangayRecords() {
    global $pdo;
    requirePermission($pdo, 'records.view');
    
    $barangay_id = intval($_POST['barangay_id'] ?? 0);
    
    if ($barangay_id <= 0) {
        adminJsonResponse(['error' => 'Invalid barangay ID'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, pwd_id_number, first_name, last_name, disability_type, status
            FROM pwd_records 
            WHERE barangay_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$barangay_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        adminJsonResponse([
            'success' => true,
            'records' => $records,
            'count' => count($records)
        ]);
        
    } catch (Exception $e) {
        error_log("Barangay records error: " . $e->getMessage());
        adminJsonResponse(['error' => 'Failed to get barangay records: ' . $e->getMessage()], 500);
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

// Get PWD records with location data
$stmt = $pdo->prepare("
    SELECT id, pwd_id_number, first_name, last_name, disability_type, 
           address_line1, city_municipality, province, latitude, longitude,
           status, created_at, barangay_id, expiry_date
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
            
            if (!empty($row['geojson_data'])) {
                $geojson = json_decode($row['geojson_data'], true);
                if ($geojson && isset($geojson['geometry'])) {
                    $boundary['geometry'] = $geojson['geometry'];
                } else {
                    continue;
                }
            } else {
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
        
        $lat_diff = max($lats) - min($lats);
        $lng_diff = max($lngs) - min($lngs);
        $max_diff = max($lat_diff, $lng_diff);
        
        if ($max_diff > 1) $map_zoom = 8;
        elseif ($max_diff > 0.5) $map_zoom = 10;
        elseif ($max_diff > 0.1) $map_zoom = 12;
        else $map_zoom = 14;
    }
}

// Get last import info
$last_import = $pdo->query("
    SELECT filename, completed_at, records_imported, records_failed
    FROM gis_import_logs 
    WHERE import_status = 'completed'
    ORDER BY completed_at DESC 
    LIMIT 1
")->fetch();
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
        /* ... existing styles ... */
        
        /* Modal Footer Fix */
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end; /* This moves the button to the right */
            background-color: #f8fafc;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        
        /* Make table scrollable */
        .modal-body .table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }

        .pagination-controls .btn[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            background: #e2e8f0;
        }

        .gis-container {
            position: relative;
            height: calc(100vh - 140px);
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .map-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .map-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .map-title h3 {
            color: #2c5aa0;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .import-status {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }
        
        .map-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .gis-map {
            width: 100%;
            height: 100%;
            padding-top: 60px;
        }
        
        /* Floating Stats Cards */
        .floating-stats {
            position: absolute;
            top: 80px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .floating-stats.minimized {
            transform: scale(0.8);
            opacity: 0.8;
        }
        
        .stat-card-mini {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(226, 232, 240, 0.5);
            min-width: 160px;
        }
        
        .stat-card-mini .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c5aa0;
            margin: 0;
        }
        
        .stat-card-mini .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            margin: 0;
        }
        
        /* Collapsible Sidebar */
        .map-sidebar {
            position: absolute;
            top: 60px;
            right: 20px;
            z-index: 1000;
            width: 320px;
            max-height: calc(100vh - 200px);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid rgba(226, 232, 240, 0.5);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        
        .map-sidebar.show {
            transform: translateX(0);
        }
        
        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-content {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .filter-section {
            margin-bottom: 20px;
        }
        
        .filter-section h4 {
            margin: 0 0 12px 0;
            font-size: 0.9rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group {
            margin-bottom: 12px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 4px;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .filter-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
            background: white;
        }
        
        .layer-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #374151;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }
        
        /* Barangay highlight styles */
        .barangay-highlight {
            animation: highlightPulse 2s ease-in-out infinite;
        }
        
        @keyframes highlightPulse {
            0%, 100% {
                fill-opacity: 0.7;
            }
            50% {
                fill-opacity: 0.9;
            }
        }
        
        /* Map Legend */
        .map-legend {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(226, 232, 240, 0.5);
        }
        
        .legend-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 8px 0;
        }
        
        .legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
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
            border: 2px solid white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .legend-marker.draft { background: #f59e0b; }
        .legend-marker.validated { background: #10b981; }
        .legend-marker.issued { background: #2c5aa0; }
        .legend-marker.boundary { background: #6366f1; border-radius: 2px; }
        
        /* Fullscreen Toggle */
        .fullscreen-toggle {
            position: absolute;
            top: 80px;
            right: 20px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.5);
            border-radius: 6px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .fullscreen-toggle:hover {
            background: rgba(44, 90, 160, 0.1);
            color: #2c5aa0;
        }
        
        /* Choropleth Legend */
        .choropleth-legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(226, 232, 240, 0.5);
            display: none;
        }
        
        .choropleth-legend.show {
            display: block;
        }
        
        .legend-scale {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 12px;
            border: 1px solid #ccc;
        }
        
        /* Enhanced Chart Modal */
        .chart-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .chart-modal.show {
            display: flex;
        }
        
        .chart-content {
            background: white;
            border-radius: 12px;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 24px 0 24px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 0;
        }
        
        .chart-header h3 {
            margin: 0;
            color: #2c5aa0;
        }
        
        .chart-tabs {
            display: flex;
            gap: 4px;
            margin: 0 24px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .chart-tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            color: #64748b;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .chart-tab.active {
            color: #2c5aa0;
            border-bottom-color: #2c5aa0;
            font-weight: 600;
        }
        
        .chart-tab:hover {
            color: #2c5aa0;
            background: rgba(44, 90, 160, 0.05);
        }
        
        .chart-body {
            padding: 24px;
            flex: 1;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .chart-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .chart-card h4 {
            margin: 0 0 16px 0;
            color: #374151;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-canvas {
            height: 300px;
            position: relative;
        }
        
        .chart-canvas.large {
            height: 400px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .summary-card .value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .summary-card .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Loading States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #2c5aa0;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: #64748b;
            font-weight: 500;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .map-sidebar {
                width: 280px;
            }
            
            .floating-stats {
                position: relative;
                top: 0;
                left: 0;
                flex-direction: row;
                flex-wrap: wrap;
                margin: 10px;
            }
            
            .gis-map {
                padding-top: 120px;
            }
            
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .map-header {
                flex-direction: column;
                gap: 8px;
                padding: 12px 16px;
            }
            
            .map-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .map-sidebar {
                width: 100%;
                right: 0;
                top: 0;
                max-height: 100vh;
                border-radius: 0;
            }
            
            .floating-stats {
                position: static;
                margin: 10px;
            }
            
            .stat-card-mini {
                min-width: auto;
                flex: 1;
            }
            
            .map-legend {
                position: relative;
                bottom: auto;
                left: auto;
                margin: 10px;
            }
            
            .gis-map {
                padding-top: 140px;
            }
            
            .chart-content {
                width: 100%;
                height: 100vh;
                border-radius: 0;
            }
            
            .stats-summary {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Tooltip Styles */
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            z-index: 10000;
            pointer-events: none;
            white-space: nowrap;
        }
        
        /* Success/Error Messages */
        .toast {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 10000;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            max-width: 400px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #10b981;
        }
        
        .toast.error {
            background: #ef4444;
        }
        
        .toast.info {
            background: #2c5aa0;
        }
        
        .toast.warning {
            background: #f59e0b;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
   
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-map-marked-alt"></i> GIS Mapping System</h1>
                <p>Interactive map for PWD records in Santo Tomas, Batangas</p>
            </div>
        </div>
        
        <!-- GIS Container -->
        <div class="gis-container">
            <!-- Map Header -->
            <div class="map-header">
                <div class="map-title">
                    <h3><i class="fas fa-globe"></i> Santo Tomas, Batangas</h3>
                    <div class="import-status">
                        <div class="status-indicator"></div>
                        <span id="lastUpdateText">
                            <?php if ($last_import): ?>
                                Last updated: <?php echo date('M j, Y \a\t g:i A', strtotime($last_import['completed_at'])); ?>
                            <?php else: ?>
                                No imports yet
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="map-actions">
                    <a href="gis_diagnostic.php" class="btn btn-success btn-sm" data-tooltip="Import GeoJSON, refresh data, and manage map">
                        <i class="fas fa-cogs"></i> Manage Map Data
                    </a>
                    <button class="btn btn-outline btn-sm" onclick="quickRefresh()" data-tooltip="Quickly refresh counts and assignments">
                        <i class="fas fa-sync-alt"></i> Quick Refresh
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="exportMapReport()" data-tooltip="Export map data as PDF report">
                        <i class="fas fa-file-export"></i> Export Report
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="toggleMapFilterSidebar()" data-tooltip="Show/hide filters and controls">
                        <i class="fas fa-sliders-h"></i> Filters
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="showStatsModal()" data-tooltip="View detailed statistics and analytics">
                        <i class="fas fa-chart-pie"></i> Analytics
                    </button>
                </div>
            </div>
            
            <!-- Floating Stats Cards -->
            <div class="floating-stats" id="floatingStats">
                <div class="stat-card-mini">
                    <div class="stat-value"><?php echo number_format($stats['with_location']); ?></div>
                    <div class="stat-label">PWD Records</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-value"><?php echo number_format($barangay_stats['total_barangays']); ?></div>
                    <div class="stat-label">Barangays</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-value"><?php echo number_format($barangay_stats['total_pwd_in_barangays'] ?? 0); ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-card-mini">
                    <div class="stat-value" id="visibleMarkers"><?php echo count($pwd_locations); ?></div>
                    <div class="stat-label">Visible</div>
                </div>
            </div>
            
            <!-- Fullscreen Toggle -->
            <button class="fullscreen-toggle" onclick="toggleFullscreen()" data-tooltip="Toggle fullscreen mode">
                <i class="fas fa-expand"></i>
            </button>
            
            <!-- Map Sidebar -->
            <div class="map-sidebar" id="mapSidebar">
                <div class="sidebar-header">
                    <h4><i class="fas fa-filter"></i> Map Controls</h4>
                    <button class="btn btn-sm" onclick="toggleSidebar()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="sidebar-content">
                    <div class="filter-section">
                        <h4><i class="fas fa-map-marker-alt"></i> Location Filter</h4>
                        <div class="filter-group">
                            <label>Select Barangay</label>
                            <select id="barangayFilter" onchange="filterByBarangay()">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangay_boundaries as $barangay): ?>
                                    <option value="<?php echo $barangay['id']; ?>">
                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                        <?php if ($barangay['pwd_count']): ?>
                                            (<?php echo $barangay['pwd_count']; ?> PWDs)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <h4><i class="fas fa-search"></i> Record Filters</h4>
                        <div class="filter-group">
                            <label>Disability Type</label>
                            <select id="disabilityFilter" onchange="filterMarkers()">
                                <option value="">All Disabilities</option>
                                <option value="Physical Disability">Physical Disability</option>
                                <option value="Visual Impairment">Visual Impairment</option>
                                <option value="Hearing Impairment">Hearing Impairment</option>
                                <option value="Intellectual Disability">Intellectual Disability</option>
                                <option value="Psychosocial Disability">Psychosocial Disability</option>
                                <option value="Multiple Disabilities">Multiple Disabilities</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select id="statusFilter" onchange="filterMarkers()">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="validated">Validated</option>
                                <option value="issued">Active</option>
                                <option value="expired">Expired</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <h4><i class="fas fa-layer-group"></i> Map Layers</h4>
                        <div class="layer-controls">
                            
                            <label class="checkbox-label">
                                <input type="checkbox" id="showBoundaries" checked onchange="toggleBoundaries()">
                                Barangay Areas
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" id="choroplethMode" onchange="toggleChoropleth()">
                                Community Highlights
                            </label>
                        </div>
                    </div>
                    
                    <div class="filter-section">
                        <h4><i class="fas fa-tools"></i> Quick Actions</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <button class="btn btn-outline btn-sm" onclick="centerMap()">
                                <i class="fas fa-crosshairs"></i> Center
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="fitAllBoundaries()">
                                <i class="fas fa-expand-arrows-alt"></i> Fit All
                            </button>
                            <button class="btn btn-outline btn-sm" onclick="clearBarangaySelection()" id="clearBarangayBtn" style="display: none; grid-column: 1 / -1;">
                                <i class="fas fa-times-circle"></i> Clear Selection
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Map -->
            <div id="gisMap" class="gis-map"></div>
            
            <!-- Map Legend -->
            <div class="map-legend">
                <div class="legend-title">Map Legend</div>
                <div class="legend-items">
                    <div class="legend-item">
                        <div class="legend-marker boundary"></div>
                        <span>Barangay Areas</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker draft"></div>
                        <span>Draft</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker validated"></div>
                        <span>Validated</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker issued"></div>
                        <span>Active</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker" style="background-color: #ef4444;"></div>
                        <span>Expired</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-marker" style="background-color: #6b7280;"></div>
                        <span>Inactive</span>
                    </div>
                </div>
            </div>
            
            <!-- Choropleth Legend -->
            <div id="choroplethLegend" class="choropleth-legend">
                <div class="legend-title">Community Presence</div>
                <div class="legend-scale">
                    <span style="font-size: 0.75rem;">Fewer</span>
                    <div class="legend-color" style="background: #d1fae5;"></div>
                    <div class="legend-color" style="background: #a7f3d0;"></div>
                    <div class="legend-color" style="background: #6ee7b7;"></div>
                    <div class="legend-color" style="background: #34d399;"></div>
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span style="font-size: 0.75rem;">More</span>
                </div>
                <p style="font-size: 0.7rem; color: #64748b; margin: 6px 0 0 0; text-align: center;">Registered Community Members</p>
            </div>
            
            <!-- Loading Overlay -->
            <div class="loading-overlay" id="loadingOverlay">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <div class="loading-text" id="loadingText">Loading...</div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Enhanced Analytics Modal -->
    <div id="statsModal" class="chart-modal">
        <div class="chart-content">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line"></i> PWD Analytics Dashboard</h3>
                <button class="btn btn-outline btn-sm" onclick="closeStatsModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            
            <div class="chart-tabs">
                <button class="chart-tab active" onclick="switchTab(event, 'overview')">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </button>
                <button class="chart-tab" onclick="switchTab(event, 'demographics')">
                    <i class="fas fa-users"></i> Demographics
                </button>
                <button class="chart-tab" onclick="switchTab(event, 'location')">
                    <i class="fas fa-map-marker-alt"></i> Location
                </button>
                <button class="chart-tab" onclick="switchTab(event, 'trends')">
                    <i class="fas fa-chart-line"></i> Trends
                </button>
            </div>
            
            <div class="chart-body">
                <!-- Overview Tab -->
                <div id="overviewTab" class="tab-content">
                    <div class="stats-summary" id="statsSummary">
                        <!-- Dynamic summary cards will be loaded here -->
                    </div>
                    
                    <div class="chart-grid">
                        <div class="chart-card">
                            <h4><i class="fas fa-wheelchair"></i> Disability Types</h4>
                            <div class="chart-canvas">
                                <canvas id="disabilityChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h4><i class="fas fa-flag"></i> Record Status</h4>
                            <div class="chart-canvas">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Demographics Tab -->
                <div id="demographicsTab" class="tab-content" style="display: none;">
                    <div class="chart-grid">
                        <div class="chart-card">
                            <h4><i class="fas fa-birthday-cake"></i> Age Distribution</h4>
                            <div class="chart-canvas">
                                <canvas id="ageChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <h4><i class="fas fa-venus-mars"></i> Gender Distribution</h4>
                            <div class="chart-canvas">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Location Tab -->
                <div id="locationTab" class="tab-content" style="display: none;">
                    <div class="chart-card">
                        <h4><i class="fas fa-map"></i> Community Presence by Barangay</h4>
                        <div class="chart-canvas large">
                            <canvas id="barangayChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Trends Tab -->
                <div id="trendsTab" class="tab-content" style="display: none;">
                    <div class="chart-card">
                        <h4><i class="fas fa-chart-line"></i> Monthly Registration Trends</h4>
                        <div class="chart-canvas large">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
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
        let sidebarVisible = false;
        let isFullscreen = false;
        let currentTab = 'overview';
        let detailedStats = null;
        let chartInstances = {}; // Store chart instances for proper cleanup
        let selectedBarangayId = null;
        let highlightedBarangayLayer = null;

        // Initialize the map
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing enhanced GIS map...');
            
            initializeMap();
            initializeTooltips();
            loadMarkers();
            loadBarangayBoundaries();
            
            // Auto-refresh import status every 30 seconds
            setInterval(updateImportStatus, 30000);
        });

        function initializeMap() {
            map = L.map('gisMap').setView([mapCenter.lat, mapCenter.lng], mapZoom);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            markerClusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 50
            });
            
            // Handle fullscreen changes
            document.addEventListener('fullscreenchange', handleFullscreenChange);
            
            // Handle map events for floating stats
            map.on('zoomstart movestart', function() {
                document.getElementById('floatingStats').classList.add('minimized');
            });
            
            map.on('zoomend moveend', function() {
                document.getElementById('floatingStats').classList.remove('minimized');
            });
            
            console.log('Enhanced map initialized successfully');
        }

        // Replace the loadBarangayBoundaries function with this corrected version:

function loadBarangayBoundaries() {
    console.log('Loading barangay boundaries...', barangayBoundaries.length);
    
    // Clear existing layers
    barangayLayers.forEach(layer => {
        if (map.hasLayer(layer)) {
            map.removeLayer(layer);
        }
    });
    barangayLayers = [];
    
    if (!showBoundaries) {
        return;
    }
    
    let loadedCount = 0;
    
    barangayBoundaries.forEach(function(barangay) {
        try {
            if (!barangay.geometry || !barangay.geometry.coordinates) {
                return;
            }
            
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
            
            const geoJsonLayer = L.geoJSON(feature, {
                style: function(feature) {
                    return getBarangayStyle(barangay);
                },
                onEachFeature: function(feature, layer) {
                    const popupContent = `
                        <div class="barangay-popup">
                            <h4>${barangay.barangay_name}</h4>
                            <p><strong>${'City of Sto. Tomas'}</strong></p>
                            <p><i class="fas fa-map-marker-alt"></i> ${ 'Batangas'}</p>
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
                                <button class="btn btn-sm btn-primary" onclick="viewBarangayRecords(${barangay.id}, '${barangay.barangay_name}')">
                                    <i class="fas fa-users"></i> View PWDs (${barangay.pwd_count || 0})
                                </button>
                            </div>
                        </div>
                    `;
                    
                    layer.bindPopup(popupContent);
                    
                    layer.on('mouseover', function(e) {
                        if (selectedBarangayId !== barangay.id) {
                            this.setStyle({
                                weight: 4,
                                fillOpacity: 0.8,
                                color: '#2c5aa0'
                            });
                        }
                        
                        if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                            layer.bringToFront();
                        }
                    });
                    
                    layer.on('mouseout', function(e) {
                        if (selectedBarangayId !== barangay.id) {
                            this.setStyle(getBarangayStyle(barangay));
                        }
                    });
                    
                    layer.on('click', function(e) {
                        layer.openPopup();
                    });
                }
            });
            
            // IMPORTANT: Store the barangay ID on the geoJsonLayer object, not the feature layer
            geoJsonLayer.barangayId = barangay.id;
            
            barangayLayers.push(geoJsonLayer);
            geoJsonLayer.addTo(map);
            loadedCount++;
            
        } catch (error) {
            console.error('Error loading barangay boundary for', barangay.barangay_name, ':', error);
        }
    });
    
    console.log(`Successfully loaded ${loadedCount} barangay boundaries`);
    
    const legend = document.getElementById('choroplethLegend');
    if (legend) {
        if (choroplethMode && showBoundaries) {
            legend.classList.add('show');
        } else {
            legend.classList.remove('show');
        }
    }
    
    if (loadedCount > 0) {
        showToast(`Successfully loaded ${loadedCount} barangay boundaries!`, 'success', 3000);
    }
}

        function getBarangayStyle(barangay) {
            const pwdCount = barangay.pwd_count || 0;
            const isSelected = selectedBarangayId === barangay.id;
            
            if (isSelected) {
                return {
                    fillColor: '#fbbf24',
                    weight: 4,
                    opacity: 1,
                    color: '#f59e0b',
                    fillOpacity: 0.7,
                    className: 'barangay-highlight'
                };
            }
            
            if (choroplethMode) {
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
            const colors = [
                '#f0fdf4', '#dcfce7', '#bbf7d0', '#86efac', 
                '#4ade80', '#22c55e', '#16a34a', '#15803d', '#14532d'
            ];
            const index = Math.floor(intensity * (colors.length - 1));
            return colors[index] || colors[0];
        }

        function filterByBarangay() {
            const barangayId = document.getElementById('barangayFilter').value;
            
            if (!barangayId) {
                clearBarangaySelection(); // This will call filterMarkers()
                return;
            }
            
            selectedBarangayId = parseInt(barangayId);
            
            // Find the selected barangay
            const selectedBarangay = barangayBoundaries.find(b => b.id === selectedBarangayId);
            
            if (!selectedBarangay) {
                showToast('Barangay not found', 'error');
                return;
            }
            
            // Highlight the selected barangay
            highlightBarangay(selectedBarangayId);
            
            // Filter PWD locations (this will now respect the other filters)
            filterMarkers(); 
            
            // Zoom to the barangay bounds
            zoomToBarangay(selectedBarangayId);
            
            // Show clear button
            document.getElementById('clearBarangayBtn').style.display = 'block';
            
            showToast(`Viewing ${selectedBarangay.barangay_name}`, 'info', 3000);
        }

        function highlightBarangay(barangayId) {
    console.log('Highlighting barangay:', barangayId);
    
    // Reset all barangay styles first
    barangayLayers.forEach(geoJsonLayer => {
        const barangay = barangayBoundaries.find(b => b.id === geoJsonLayer.barangayId);
        if (barangay) {
            // Apply style to all feature layers within the GeoJSON layer
            geoJsonLayer.eachLayer(function(layer) {
                if (layer.setStyle) {
                    layer.setStyle(getBarangayStyle(barangay));
                }
            });
        }
    });
    
    // Highlight the selected barangay
    const selectedLayer = barangayLayers.find(layer => layer.barangayId === barangayId);
    
    if (selectedLayer) {
        const barangay = barangayBoundaries.find(b => b.id === barangayId);
        if (barangay) {
            // Apply highlight style to all feature layers within the GeoJSON layer
            selectedLayer.eachLayer(function(layer) {
                if (layer.setStyle) {
                    layer.setStyle(getBarangayStyle(barangay));
                    layer.bringToFront();
                }
            });
            highlightedBarangayLayer = selectedLayer;
            console.log('Barangay highlighted successfully');
        }
    } else {
        console.warn('No layer found to highlight for barangay ID:', barangayId);
    }
}
       // Also update the zoomToBarangay function to work with GeoJSON layers properly:

function zoomToBarangay(barangayId) {
    const selectedLayer = barangayLayers.find(layer => layer.barangayId === barangayId);
    
    if (selectedLayer) {
        try {
            const bounds = selectedLayer.getBounds();
            map.fitBounds(bounds, { 
                padding: [50, 50],
                maxZoom: 15
            });
            console.log('Zoomed to barangay:', barangayId);
        } catch (error) {
            console.error('Error zooming to barangay:', error);
            // Fallback: try to get bounds from the first layer in the GeoJSON
            selectedLayer.eachLayer(function(layer) {
                if (layer.getBounds) {
                    const bounds = layer.getBounds();
                    map.fitBounds(bounds, { 
                        padding: [50, 50],
                        maxZoom: 15
                    });
                    return false; // Stop after first layer
                }
            });
        }
    } else {
        console.warn('No layer found for barangay ID:', barangayId);
    }
}

       function clearBarangaySelection() {
            console.log('Clearing barangay selection');
            
            selectedBarangayId = null;
            highlightedBarangayLayer = null;
            
            // Reset the dropdown
            document.getElementById('barangayFilter').value = '';
            
            // Reset all barangay styles
            barangayLayers.forEach(geoJsonLayer => {
                const barangay = barangayBoundaries.find(b => b.id === geoJsonLayer.barangayId);
                if (barangay) {
                    geoJsonLayer.eachLayer(function(layer) {
                        if (layer.setStyle) {
                            layer.setStyle(getBarangayStyle(barangay));
                        }
                    });
                }
            });
            
            // Reset filters and reload all markers (respecting other filters)
            filterMarkers(); 
            
            // Reset map view
            centerMap();
            
            // Hide clear button
            document.getElementById('clearBarangayBtn').style.display = 'none';
            
            showToast('Selection cleared', 'info', 2000);
        }

        function loadMarkers() {
            clearMarkers();
            
            filteredLocations.forEach(function(location) {
                const marker = createMarker(location);
                markers.push(marker);
                // Always add to the cluster group
                markerClusterGroup.addLayer(marker);
            });
            
            // Always add the cluster group to the map
            map.addLayer(markerClusterGroup);
            
            updateVisibleMarkers();
        }
        
        function createMarker(location) {
            const lat = parseFloat(location.latitude);
            const lng = parseFloat(location.longitude);
            
            // Determine true status
            let displayStatus = location.status;
            const isExpired = location.status === 'issued' && location.expiry_date && new Date(location.expiry_date) < new Date();
            if (isExpired) {
                displayStatus = 'expired';
            }

            const iconColor = getStatusColor(displayStatus);
            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    width: 24px; 
                    height: 24px; 
                    background-color: ${iconColor}; 
                    border: 2px solid white; 
                    border-radius: 50%; 
                    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    color: white;
                ">
                    <i class="fas fa-user" style="font-size: 10px;"></i>
                </div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            
            const marker = L.marker([lat, lng], { icon: icon });
            
            const popupContent = `
                <div class="marker-popup">
                    <h4>${location.pwd_id_number}</h4>
                    <p><strong>${location.first_name} ${location.last_name}</strong></p>
                    <p><i class="fas fa-info-circle"></i> ${location.disability_type}</p>
                    
                    <p><i class="fas fa-flag"></i> Status: <span class="status-badge status-${displayStatus}">${getStatusLabel(displayStatus)}</span></p>
                    <div class="popup-actions">
                        <button class="btn btn-sm btn-primary" onclick="viewRecord(${location.id})">
                            <i class="fas fa-eye"></i> View Details
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
                'issued': '#2c5aa0',      // Active
                'expired': '#ef4444',     // Expired
                'inactive': '#6b7280'    // Inactive
            };
            return colors[status] || '#6b7280';
        }

        function getStatusLabel(status) {
            const labels = {
                'draft': 'Pending Review',
                'validated': 'Verified',
                'issued': 'Active',
                'expired': 'Expired',
                'inactive': 'Inactive'
            };
            return labels[status] || status;
        }
        
        function clearMarkers() {
            // We just need to clear the cluster group and remove it
            markerClusterGroup.clearLayers();
            map.removeLayer(markerClusterGroup);
            markers = [];
        }
        
        function filterMarkers() {
            // Read ALL filters
            const disabilityFilter = document.getElementById('disabilityFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const barangayIdFilter = selectedBarangayId; // Use the global variable
            
            filteredLocations = allPWDLocations.filter(location => {
                // Check Disability
                const matchesDisability = !disabilityFilter || location.disability_type === disabilityFilter;
                
                // Check Status
                const isExpired = location.status === 'issued' && location.expiry_date && new Date(location.expiry_date) < new Date();
                let matchesStatus = true;
                if (statusFilter) {
                    if (statusFilter === 'expired') {
                        matchesStatus = isExpired;
                    } else if (statusFilter === 'issued') {
                        // "Active" means status is 'issued' but NOT expired
                        matchesStatus = location.status === 'issued' && !isExpired;
                    } else {
                        // This handles 'draft', 'validated', and 'inactive'
                        matchesStatus = location.status === statusFilter;
                    }
                }
                
                // Check Barangay
                const matchesBarangay = !barangayIdFilter || location.barangay_id === barangayIdFilter;
                
                // Only include if it matches ALL filters
                return matchesDisability && matchesStatus && matchesBarangay;
            });
            
            // Reload markers with the fully filtered list
            loadMarkers();
            
            // Update the "Visible" count
            updateVisibleMarkers();
        }
        
        
        
        function toggleBoundaries() {
            showBoundaries = !showBoundaries;
            loadBarangayBoundaries();
        }

        function toggleChoropleth() {
            choroplethMode = !choroplethMode;
            loadBarangayBoundaries();
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
        
        function toggleFullscreen() {
            const container = document.querySelector('.gis-container');
            
            if (!isFullscreen) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                } else if (container.msRequestFullscreen) {
                    container.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
        
        function handleFullscreenChange() {
            isFullscreen = !!document.fullscreenElement;
            const icon = document.querySelector('.fullscreen-toggle i');
            
            if (isFullscreen) {
                icon.className = 'fas fa-compress';
                document.getElementById('floatingStats').style.display = 'none';
            } else {
                icon.className = 'fas fa-expand';
                document.getElementById('floatingStats').style.display = 'flex';
            }
            
            setTimeout(() => map.invalidateSize(), 100);
        }
        
        function toggleMapFilterSidebar() {
            sidebarVisible = !sidebarVisible;
            const sidebar = document.getElementById('mapSidebar');
            
            if (sidebarVisible) {
                sidebar.classList.add('show');
            } else {
                sidebar.classList.remove('show');
            }
        }
        
        function quickRefresh() {
            showLoading('Refreshing map data like spatial debug...');
            
            fetch('map.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=quick_refresh'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.error || 'Refresh failed', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Quick refresh error:', error);
                showToast('Refresh failed: ' + error.message, 'error');
            });
        }
        
        function showStatsModal() {
            document.getElementById('statsModal').classList.add('show');
            loadDetailedStats();
        }
        
        function closeStatsModal() {
            document.getElementById('statsModal').classList.remove('show');
            // Destroy all chart instances when closing
            Object.keys(chartInstances).forEach(key => {
                if (chartInstances[key]) {
                    chartInstances[key].destroy();
                    delete chartInstances[key];
                }
            });
        }
        
        function switchTab(event, tabName) {
            // Update tab buttons
            document.querySelectorAll('.chart-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(tabName + 'Tab').style.display = 'block';
            
            currentTab = tabName;
            
            // Load charts for the active tab
            if (detailedStats) {
                renderChartsForTab(tabName);
            }
        }
        
        function loadDetailedStats() {
            showLoading('Loading detailed analytics...');
            
            fetch('map.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_detailed_stats'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    detailedStats = data;
                    renderStatsSummary(data);
                    renderChartsForTab(currentTab);
                } else {
                    showToast(data.error || 'Failed to load stats', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Stats loading error:', error);
                showToast('Failed to load stats: ' + error.message, 'error');
            });
        }
        
        function renderStatsSummary(data) {
            const summaryContainer = document.getElementById('statsSummary');
            const locationStats = data.location_stats;
            const barangayCoverage = data.barangay_coverage;
            
            const coveragePercentage = locationStats.total_records > 0 ? 
                Math.round((locationStats.with_location / locationStats.total_records) * 100) : 0;
            
            const assignmentPercentage = locationStats.total_records > 0 ? 
                Math.round((locationStats.assigned_to_barangay / locationStats.total_records) * 100) : 0;
            
            summaryContainer.innerHTML = `
                <div class="summary-card">
                    <div class="value">${locationStats.total_records.toLocaleString()}</div>
                    <div class="label">Total PWD Records</div>
                </div>
                <div class="summary-card">
                    <div class="value">${coveragePercentage}%</div>
                    <div class="label">Location Coverage</div>
                </div>
                <div class="summary-card">
                    <div class="value">${assignmentPercentage}%</div>
                    <div class="label">Barangay Assignment</div>
                </div>
                <div class="summary-card">
                    <div class="value">${barangayCoverage.barangays_with_pwd}</div>
                    <div class="label">Active Barangays</div>
                </div>
                
                <div class="summary-card">
                    <div class="value">${Math.round(barangayCoverage.avg_pwd_per_barangay || 0)}</div>
                    <div class="label">Avg PWD/Barangay</div>
                </div>
            `;
        }
        
        function renderChartsForTab(tabName) {
            if (!detailedStats) return;
            
            switch (tabName) {
                case 'overview':
                    renderDisabilityChart();
                    renderStatusChart();
                    break;
                case 'demographics':
                    renderAgeChart();
                    renderGenderChart();
                    break;
                case 'location':
                    renderBarangayChart();
                    break;
                case 'trends':
                    renderTrendsChart();
                    break;
            }
        }
        
        function destroyChart(chartId) {
            if (chartInstances[chartId]) {
                chartInstances[chartId].destroy();
                delete chartInstances[chartId];
            }
        }
        
        function renderDisabilityChart() {
            destroyChart('disabilityChart');
            
            const ctx = document.getElementById('disabilityChart').getContext('2d');
            const data = detailedStats.disability_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No disability data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['disabilityChart'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => item.disability_type),
                    datasets: [{
                        data: data.map(item => item.count),
                        backgroundColor: [
                            '#2c5aa0', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                            '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
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
                                font: { size: 11 },
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderStatusChart() {
            destroyChart('statusChart');
            
            const ctx = document.getElementById('statusChart').getContext('2d');
            const data = detailedStats.status_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No status data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['statusChart'] = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                    datasets: [{
                        data: data.map(item => item.count),
                        backgroundColor: ['#f59e0b', '#10b981', '#2c5aa0', '#ef4444', '#6b7280'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
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
                                font: { size: 11 },
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderAgeChart() {
            destroyChart('ageChart');
            
            const ctx = document.getElementById('ageChart').getContext('2d');
            const data = detailedStats.age_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No age data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['ageChart'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.age_group),
                    datasets: [{
                        label: 'PWD Count',
                        data: data.map(item => item.count),
                        backgroundColor: '#2c5aa0',
                        borderColor: '#1e3a8a',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.y} PWDs`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderGenderChart() {
            destroyChart('genderChart');
            
            const ctx = document.getElementById('genderChart').getContext('2d');
            const data = detailedStats.gender_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No gender data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['genderChart'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => item.gender),
                    datasets: [{
                        data: data.map(item => item.count),
                        backgroundColor: ['#2c5aa0', '#ec4899', '#10b981'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
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
                                font: { size: 11 },
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderBarangayChart() {
            destroyChart('barangayChart');
            
            const ctx = document.getElementById('barangayChart').getContext('2d');
            const data = detailedStats.barangay_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No barangay data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['barangayChart'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.barangay_name),
                    datasets: [{
                        label: 'PWD Count',
                        data: data.map(item => item.count),
                        backgroundColor: '#2c5aa0',
                        borderColor: '#1e3a8a',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.x} PWDs`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderTrendsChart() {
            destroyChart('trendsChart');
            
            const ctx = document.getElementById('trendsChart').getContext('2d');
            const data = detailedStats.monthly_stats;
            
            if (!data || data.length === 0) {
                ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                ctx.font = '16px Arial';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No trend data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            chartInstances['trendsChart'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'New Registrations',
                        data: data.map(item => item.count),
                        borderColor: '#2c5aa0',
                        backgroundColor: 'rgba(44, 90, 160, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2c5aa0',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed.y} new registrations`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function updateImportStatus() {
            fetch('map.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_import_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.last_import) {
                    const lastUpdate = new Date(data.last_import.completed_at);
                    document.getElementById('lastUpdateText').textContent = 
                        `Last updated: ${lastUpdate.toLocaleDateString()} at ${lastUpdate.toLocaleTimeString()}`;
                }
            })
            .catch(error => {
                console.error('Failed to update import status:', error);
            });
        }
        
        function viewRecord(recordId) {
            window.open(`records.php?id=${recordId}`, '_blank');
        }
        
        function viewBarangayRecords(barangayId, barangayName) {
            showLoading('Loading PWD records...');
            
            fetch('map.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_barangay_records&barangay_id=${barangayId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showBarangayRecordsModal(barangayName, data.records);
                } else {
                    showToast(data.error || 'Failed to load records', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Failed to load records: ' + error.message, 'error');
            });
        }
        
        function showBarangayRecordsModal(barangayName, records) {
            // Create the modal element
            const modal = document.createElement('div');
            modal.className = 'modal show';
            
            // Store records and name on the modal element itself for pagination
            modal.dataset.records = JSON.stringify(records);
            modal.dataset.barangayName = barangayName;

            modal.innerHTML = `
                <div class="modal-content" style="max-width: 800px;">
                    <div class="modal-header">
                        <h3><i class="fas fa-users"></i> PWD Records in ${barangayName}</h3>
                        <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>PWD ID</th>
                                        <th>Name</th>
                                        <th>Disability Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="barangay-records-body">
                                    </tbody>
                            </table>
                        </div>
                        
                        <div id="barangay-pagination-container" class="pagination-container">
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline" onclick="this.closest('.modal').remove()">Close</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Initial render of the first page
            renderBarangayRecordsPage(modal, 1);
        }
        
        function renderBarangayRecordsPage(modal, page) {
            const records = JSON.parse(modal.dataset.records);
            const recordsPerPage = 10; // You can change this number
            
            modal.dataset.currentPage = page;
            
            const totalRecords = records.length;
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            
            // Ensure page is within bounds
            page = Math.max(1, Math.min(page, totalPages));
            
            const startIndex = (page - 1) * recordsPerPage;
            const endIndex = startIndex + recordsPerPage;
            const pageRecords = records.slice(startIndex, endIndex);
            
            const tableBody = modal.querySelector('#barangay-records-body');
            const paginationContainer = modal.querySelector('#barangay-pagination-container');
            
            // 1. Render Table Rows
            if (pageRecords.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No PWD records found in this barangay.</td></tr>';
            } else {
                tableBody.innerHTML = pageRecords.map(record => `
                    <tr>
                        <td>${record.pwd_id_number}</td>
                        <td>${record.first_name} ${record.last_name}</td>
                        <td>${record.disability_type}</td>
                        <td><span class="status-badge status-${record.status}">${record.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewRecord(${record.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
            
            // 2. Render Pagination Controls
            if (totalPages <= 1) {
                paginationContainer.innerHTML = ''; // No pagination needed
                return;
            }
            
            paginationContainer.innerHTML = `
                <div class="pagination-info">
                    Showing ${startIndex + 1} to ${Math.min(endIndex, totalRecords)} of ${totalRecords} records
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-sm btn-outline" onclick="changeBarangayRecordsPage(this, -1)" ${page === 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i> Prev
                    </button>
                    <span style="align-self: center; font-size: 0.9rem; color: #64748b;">
                        Page ${page} of ${totalPages}
                    </span>
                    <button class="btn btn-sm btn-outline" onclick="changeBarangayRecordsPage(this, 1)" ${page === totalPages ? 'disabled' : ''}>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            `;
        }

        function changeBarangayRecordsPage(buttonElement, delta) {
            const modal = buttonElement.closest('.modal');
            const currentPage = parseInt(modal.dataset.currentPage || '1');
            const newPage = currentPage + delta;
            
            renderBarangayRecordsPage(modal, newPage);
        }
        
        function showLoading(message = 'Loading...') {
            const overlay = document.getElementById('loadingOverlay');
            const text = document.getElementById('loadingText');
            text.textContent = message;
            overlay.classList.add('show');
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }
        
        function showToast(message, type = 'info', duration = 5000) {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-${getToastIcon(type)}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; margin-left: auto; cursor: pointer;">×</button>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }
        
        function getToastIcon(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            return icons[type] || 'bell';
        }
        
        function initializeTooltips() {
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', showTooltip);
                element.addEventListener('mouseleave', hideTooltip);
            });
        }
        
        function showTooltip(e) {
            const element = e.target;
            const tooltipText = element.getAttribute('data-tooltip');
            
            if (tooltipText) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = tooltipText;
                
                document.body.appendChild(tooltip);
                
                const rect = element.getBoundingClientRect();
                tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
                
                element._tooltip = tooltip;
            }
        }
        
        function hideTooltip(e) {
            const element = e.target;
            if (element._tooltip) {
                element._tooltip.remove();
                delete element._tooltip;
            }
        }
        
        // Export form handling
        function showExportModal() {
            document.getElementById('exportModal').classList.add('show');
        }
        
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
                
                showToast('GeoJSON exported successfully', 'success');
                closeModal('exportModal');
            })
            .catch(error => {
                hideLoading();
                showToast('Export failed: ' + error.message, 'error');
            });
        });
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') || e.target.classList.contains('chart-modal')) {
                e.target.classList.remove('show');
            }
        });
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function exportMapReport() {
            showLoading('Preparing map report for export...');
            
            const formData = new FormData();
            formData.append('action', 'export_map_report');
            
            // Send ALL filters to the server
            formData.append('filters[disability_type]', document.getElementById('disabilityFilter').value);
            formData.append('filters[status]', document.getElementById('statusFilter').value);
            formData.append('filters[barangay_id]', document.getElementById('barangayFilter').value); // <-- ADDED THIS

            // We no longer send 'visible_locations[]'. 
            // This solves the max_input_vars error and the inconsistency.
            
            fetch('map.php', {
                method: 'POST',
                body: formData 
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        let errorMsg = `HTTP ${response.status}: ${response.statusText}`;
                        try {
                            const errData = JSON.parse(text);
                            if (errData.error) errorMsg = errData.error;
                        } catch (e) {
                            const htmlErrorMatch = text.match(/<b>(Warning|Error|Notice)<\/b>:\s*(.*?)\s*in/i);
                            if (htmlErrorMatch && htmlErrorMatch[2]) {
                                errorMsg = htmlErrorMatch[2];
                            } else {
                                errorMsg = 'Unknown error. Check server logs.';
                            }
                        }
                        throw new Error(errorMsg);
                    });
                }

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/pdf")) {
                    return response.text().then(text => {
                        let errorMsg = 'Export failed: Server did not return a PDF.';
                        try {
                            const errData = JSON.parse(text);
                            if (errData.error) errorMsg = errData.error;
                        } catch(e) {
                             errorMsg = 'Unknown error. Check server logs.';
                        }
                        throw new Error(errorMsg);
                    });
                }
                
                return response.blob();
            })
            .then(blob => {
                hideLoading();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `pwd_map_report_${new Date().toISOString().slice(0, 10)}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showToast('Map report exported successfully', 'success');
            })
            .catch(error => {
                hideLoading();
                console.error('Export error:', error);
                showToast('Export failed: ' + error.message, 'error', 8000); 
            });
        }
    </script>
</body>
</html>
