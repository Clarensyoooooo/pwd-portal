<?php
date_default_timezone_set('Asia/Manila');
require_once 'config.php';
requireAdminLogin($pdo);
requirePermission($pdo, 'reports.view');

$admin = getCurrentAdmin($pdo);

// Get filter parameters
$report_type = $_GET['type'] ?? 'analytics';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$disability_filter = $_GET['disability'] ?? '';
$age_group = $_GET['age_group'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$time_period = $_GET['time_period'] ?? 'monthly';
$gender_filter = $_GET['gender'] ?? '';
$employment_filter = $_GET['employment'] ?? '';

// Get available filter options
$available_barangays = [];
$available_disabilities = [];
$available_employment = [];

try {
    $stmt = $pdo->query("SELECT DISTINCT barangay FROM pwd_records WHERE barangay IS NOT NULL ORDER BY barangay");
    $available_barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT disability_type FROM pwd_records WHERE disability_type IS NOT NULL ORDER BY disability_type");
    $available_disabilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT DISTINCT employment_status FROM pwd_records WHERE employment_status IS NOT NULL ORDER BY employment_status");
    $available_employment = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Handle error silently
}

// Generate reports based on type
$report_data = [];
switch ($report_type) {
    case 'analytics':
        // --- NEW: ADDED ALL-TIME SUMMARY QUERY ---
        // This query runs with NO filters to get the total community numbers
        try {
            $all_time_stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as total_active,
                    SUM(CASE WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as total_expired,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as total_inactive,
                    AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as total_avg_age,
                    MIN(created_at) as first_registration_date
                FROM pwd_records
            ");
            $report_data['all_time_summary'] = $all_time_stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $report_data['all_time_summary'] = [
                'total_records' => 0, 'total_active' => 0, 'total_expired' => 0, 'total_inactive' => 0, 'total_avg_age' => 0,
                'first_registration_date' => null
            ];
        }
        // --- END NEW BLOCK ---
        $report_data += generateAnalyticsReport($pdo, $date_from, $date_to, $time_period, $status_filter, $disability_filter, $gender_filter, $barangay_filter, $employment_filter);
        break;
    case 'demographics':
        $report_data = generateDemographicsReport($pdo, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter);
        break;
    case 'resources':
        $report_data = generateResourcesReport($pdo, $date_from, $date_to, $barangay_filter, $disability_filter, $status_filter, $gender_filter, $employment_filter);
        break;
}

// --- THIS IS THE FUNCTION WITH THE FIX FOR THE ID STATUS CHART ---
function generateAnalyticsReport($pdo, $date_from, $date_to, $time_period, $status_filter, $disability_filter, $gender_filter, $barangay_filter, $employment_filter) {
    $data = [];
    
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($status_filter) {
        if ($status_filter === 'expired') {
            $where_conditions[] = "(status = 'issued' AND expiry_date < CURDATE())";
        } else if ($status_filter === 'issued') {
            // 'issued' from dropdown now means 'Active'
            $where_conditions[] = "(status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()))";
        } else {
            // This handles draft, validated, inactive
            $where_conditions[] = "status = ?";
            $params[] = $status_filter;
        }
    }
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    if ($gender_filter) {
        $where_conditions[] = "gender = ?";
        $params[] = $gender_filter;
    }
    
    if ($barangay_filter) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($employment_filter) {
        $where_conditions[] = "employment_status = ?";
        $params[] = $employment_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_individuals,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_records,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated_profiles,
            SUM(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as active_ids,
            SUM(CASE WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_ids,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ids,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_coordinates,
            COUNT(CASE WHEN barangay IS NOT NULL THEN 1 END) as assigned_to_barangay
        FROM pwd_records 
        {$where_clause}
    ");
    $stmt->execute($params);
    $data['summary'] = $stmt->fetch();
    
    // --- NEW: Format summary data for ID Status Pie Chart ---
    $summary = $data['summary'];
    $id_status_data = [
        ['status' => 'Active', 'count' => (int)($summary['active_ids'] ?? 0)],
        ['status' => 'Validated (Pending ID)', 'count' => (int)($summary['validated_profiles'] ?? 0)],
        ['status' => 'Expired', 'count' => (int)($summary['expired_ids'] ?? 0)],
        ['status' => 'Inactive', 'count' => (int)($summary['inactive_ids'] ?? 0)],
        ['status' => 'Draft', 'count' => (int)($summary['draft_records'] ?? 0)],
    ];
    // Filter out zero-count entries to keep the pie chart clean
    $data['id_status_distribution'] = array_values(array_filter($id_status_data, function($row) {
        return $row['count'] > 0;
    }));
    // --- END NEW ---
    
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Children (0-17)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN 'Young Adults (18-30)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN 'Adults (31-50)'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 65 THEN 'Mature Adults (51-65)'
                ELSE 'Senior Citizens (65+)'
            END as age_group,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records
        {$where_clause}
        GROUP BY age_group
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($params, $params));
    $data['age_groups'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            gender,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records
        {$where_clause}
        GROUP BY gender
    ");
    $stmt->execute(array_merge($params, $params));
    $data['gender_distribution'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            disability_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records 
        {$where_clause}
        AND disability_type IS NOT NULL
        GROUP BY disability_type 
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($params, $params));
    $data['disability_distribution'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as active_ids,
            SUM(CASE WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_ids,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ids,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause} AND barangay IS NOT NULL), 1) as percentage
        FROM pwd_records 
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay 
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($params, $params));
    $data['barangay_distribution'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(employment_status, 'Not Specified') as employment_status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM pwd_records {$where_clause}), 1) as percentage
        FROM pwd_records 
        {$where_clause}
        GROUP BY employment_status 
        ORDER BY count DESC
    ");
    $stmt->execute(array_merge($params, $params));
    $data['employment_distribution'] = $stmt->fetchAll();
    
    // --- START: Trends Query (REVAMPED TO FIX QUARTERLY BUG) ---
    $period_select = "DATE_FORMAT(created_at, '%Y-%m')"; // Default: monthly
    $period_group_by = "period";

    if ($time_period == 'yearly') {
        $period_select = "DATE_FORMAT(created_at, '%Y')";
    } elseif ($time_period == 'quarterly') {
        // --- THIS IS THE FIX ---
        // MySQL DATE_FORMAT does not support %q.
        // We must use the QUARTER() function and CONCAT()
        $period_select = "CONCAT(YEAR(created_at), '-Q', QUARTER(created_at))";
    }
    // 'daily' is already removed

    $stmt = $pdo->prepare("
        SELECT 
            {$period_select} as period,
            COUNT(*) as registrations,
            SUM(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as active_ids,
            SUM(CASE WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_ids,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ids,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated
        FROM pwd_records 
        {$where_clause}
        GROUP BY {$period_group_by}
        ORDER BY period
    ");
    $stmt->execute($params);
    $data['trends'] = $stmt->fetchAll();
    // --- END: Trends Query ---
    
    return $data;
}

function generateDemographicsReport($pdo, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter) {
    $data = [];
    
    $where_conditions = ["created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($age_group) {
        switch ($age_group) {
            case 'children':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18";
                break;
            case 'adults':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 64";
                break;
            case 'seniors':
                $where_conditions[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 65";
                break;
        }
    }
    
    if ($barangay_filter) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($gender_filter) {
        $where_conditions[] = "gender = ?";
        $params[] = $gender_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "disability_type = ?";
        $params[] = $disability_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as total_individuals,
            COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_count,
            COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count,
            COUNT(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 END) as active_ids
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay
        ORDER BY total_individuals DESC
    ");
    $stmt->execute($params);
    $data['barangay_profiles'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as count
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, count DESC
    ");
    $stmt->execute($params);
    $data['disability_by_barangay'] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Children'
                WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 64 THEN 'Adults'
                ELSE 'Seniors'
            END as age_category,
            COUNT(*) as count
        FROM pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay, age_category
        ORDER BY barangay, count DESC
    ");
    $stmt->execute($params);
    $data['age_by_barangay'] = $stmt->fetchAll();
    
    return $data;
}

function generateResourcesReport($pdo, $date_from, $date_to, $barangay_filter, $disability_filter, $status_filter, $gender_filter, $employment_filter) {
    $data = [];
    
    // --- START: Main Filter Logic ---
    $where_conditions = ["pwd_records.created_at BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($barangay_filter) {
        $where_conditions[] = "pwd_records.barangay = ?";
        $params[] = $barangay_filter;
    }
    
    if ($disability_filter) {
        $where_conditions[] = "pwd_records.disability_type = ?";
        $params[] = $disability_filter;
    }

    if ($gender_filter) {
        $where_conditions[] = "pwd_records.gender = ?";
        $params[] = $gender_filter;
    }

    if ($employment_filter) {
        $where_conditions[] = "pwd_records.employment_status = ?";
        $params[] = $employment_filter;
    }

    if ($status_filter) {
        if ($status_filter === 'expired') {
            $where_conditions[] = "(pwd_records.status = 'issued' AND pwd_records.expiry_date < CURDATE())";
        } else if ($status_filter === 'issued') {
            // 'issued' from dropdown now means 'Active'
            $where_conditions[] = "(pwd_records.status = 'issued' AND (pwd_records.expiry_date IS NULL OR pwd_records.expiry_date >= CURDATE()))";
        } else {
            // This handles draft, validated, inactive
            $where_conditions[] = "pwd_records.status = ?";
            $params[] = $status_filter;
        }
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    // --- END: Main Filter Logic ---
    
    
    // Master service mapping by disability type
    $service_recommendations = [
        'Psychosocial Disability' => [
            'services' => [
                'Mental health counseling services',
                'Peer support group meetings',
                'Crisis intervention hotline',
                'Medication management programs',
                'Psychiatric consultation',
                'Community mental health outreach'
            ]
        ],
        'Hearing Impairment' => [
            'services' => [
                'Sign language interpretation services',
                'Hearing aid maintenance and distribution',
                'Speech therapy programs',
                'Communication assistance',
                'Visual alert system installation',
                'Deaf community social groups'
            ]
        ],
        'Visual Impairment' => [
            'services' => [
                'Braille literacy programs',
                'Orientation and mobility training',
                'Assistive technology support',
                'Screen reader software training',
                'White cane training sessions',
                'Audio book library'
            ]
        ],
        'Intellectual Disability' => [
            'services' => [
                'Inclusive education programs',
                'Skills training workshops',
                'Family counseling services',
                'Behavioral therapy programs',
                'Life skills development',
                'Community integration support'
            ]
        ],
        'Physical Disability' => [
            'services' => [
                'Mobility aids and assistive devices',
                'Rehabilitation therapy',
                'Accessibility infrastructure programs',
                'Physical therapy sessions',
                'Wheelchair maintenance',
                'Accessible transportation'
            ]
        ]
    ];
    
    // Get detailed barangay data with age groups and employment
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            disability_type,
            COUNT(*) as total_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 1 END) as children_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 64 THEN 1 END) as adult_count,
            COUNT(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 65 THEN 1 END) as senior_count,
            COUNT(CASE WHEN employment_status = 'Unemployed' THEN 1 END) as unemployed_count,
            COUNT(CASE WHEN employment_status = 'Employed' THEN 1 END) as employed_count,
            AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age
        FROM pwd_records pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        AND disability_type IS NOT NULL
        GROUP BY barangay, disability_type
        ORDER BY barangay, total_count DESC
    ");
    $stmt->execute($params);
    $barangay_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // --- START: FIX for CONCENTRATION CALCULATION ---
    
    // Create a new set of params and conditions for the *unfiltered* barangay totals.
    // We must include all filters EXCEPT the disability filter.
    
    $totals_where_conditions = ["created_at BETWEEN ? AND ?"];
    $totals_params = [$date_from, $date_to];

    if ($barangay_filter) {
        $totals_where_conditions[] = "barangay = ?";
        $totals_params[] = $barangay_filter;
    }

    if ($gender_filter) {
        $totals_where_conditions[] = "gender = ?";
        $totals_params[] = $gender_filter;
    }

    if ($employment_filter) {
        $totals_where_conditions[] = "employment_status = ?";
        $totals_params[] = $employment_filter;
    }

    if ($status_filter) {
        if ($status_filter === 'expired') {
            $totals_where_conditions[] = "(status = 'issued' AND expiry_date < CURDATE())";
        } else if ($status_filter === 'issued') {
            $totals_where_conditions[] = "(status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()))";
        } else {
            $totals_where_conditions[] = "status = ?";
            $totals_params[] = $status_filter;
        }
    }
    
    // *** We deliberately DO NOT add the $disability_filter here ***

    $totals_where_clause = "WHERE " . implode(" AND ", $totals_where_conditions);

    // Run the query to get the TRUE total PWDs for each barangay (ignoring disability filter)
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COUNT(*) as barangay_total
        FROM pwd_records
        {$totals_where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay
    ");
    $stmt->execute($totals_params);
    
    $barangay_totals_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // --- END: FIX ---
    
    // Process and organize data by barangay
    $barangay_recommendations = [];
    
    foreach ($barangay_data as $row) {
        $barangay = $row['barangay'];
        $disability = $row['disability_type'];
        $total = (int)$row['total_count'];
        $barangay_total = $barangay_totals_map[$barangay] ?? $total;
        
        $concentration = $barangay_total > 0 ? ($total / $barangay_total) * 100 : 0;
        
        $priority = 'Medium';
        if ($concentration > 50) {
            $priority = 'Critical';
        } elseif ($concentration > 30) {
            $priority = 'High';
        }
        
        $children_percentage = $total > 0 ? ((int)$row['children_count'] / $total) * 100 : 0;
        $unemployment_rate = $total > 0 ? ((int)$row['unemployed_count'] / $total) * 100 : 0;
        
        if (!isset($barangay_recommendations[$barangay])) {
            $barangay_recommendations[$barangay] = [
                'total_pwd' => 0,
                'total_children' => 0,
                'total_adults' => 0,
                'total_seniors' => 0,
                'total_unemployed' => 0,
                'disabilities' => [],
                'recommended_services' => []
            ];
        }
        
        $barangay_recommendations[$barangay]['total_pwd'] += $total;
        $barangay_recommendations[$barangay]['total_children'] += (int)$row['children_count'];
        $barangay_recommendations[$barangay]['total_adults'] += (int)$row['adult_count'];
        $barangay_recommendations[$barangay]['total_seniors'] += (int)$row['senior_count'];
        $barangay_recommendations[$barangay]['total_unemployed'] += (int)$row['unemployed_count'];
        
        $barangay_recommendations[$barangay]['disabilities'][] = [
            'type' => $disability,
            'count' => $total,
            'concentration' => round($concentration, 1),
            'children_count' => (int)$row['children_count'],
            'adult_count' => (int)$row['adult_count'],
            'unemployed_count' => (int)$row['unemployed_count'],
            'avg_age' => round((float)$row['avg_age'], 1)
        ];
        
        $matched_services = [];
        foreach ($service_recommendations as $key => $rec) {
            if (stripos($disability, $key) !== false || stripos($key, $disability) !== false) {
                $matched_services = $rec['services'];
                break;
            }
        }
        
        if (empty($matched_services)) {
            $matched_services = [
                'General accessibility improvements',
                'Community support programs',
                'Healthcare coordination',
                'Skills development training',
                'Social integration activities',
                'Transportation assistance'
            ];
        }
        
        $contextual_services = $matched_services;
        
        if ($children_percentage > 30) {
            array_unshift($contextual_services, 
                '🎓 Inclusive education programs',
                '🎨 Therapy and special programs for children'
            );
            if ($priority === 'Medium') $priority = 'High';
        }
        
        if ($unemployment_rate > 40) {
            array_push($contextual_services,
                '💼 Livelihood programs and vocational training',
                '🤝 Partnerships with local businesses'
            );
            if ($priority === 'Medium') $priority = 'High';
        }
        
        if ((int)$row['adult_count'] > $total * 0.5) {
            array_push($contextual_services,
                '🎯 Employment and skills training',
                '💪 Livelihood support programs'
            );
        }
        
        $barangay_recommendations[$barangay]['recommended_services'][] = [
            'disability_type' => $disability,
            'affected_count' => $total,
            'concentration' => round($concentration, 1),
            'priority' => $priority,
            'children_count' => (int)$row['children_count'],
            'unemployed_count' => (int)$row['unemployed_count'],
            'services' => array_unique($contextual_services),
            'factors' => [
                'high_concentration' => $concentration > 30,
                'many_children' => $children_percentage > 30,
                'high_unemployment' => $unemployment_rate > 40
            ]
        ];
    }
    
    foreach ($barangay_recommendations as $barangay => &$brgy_data) {
        usort($brgy_data['disabilities'], function($a, $b) {
            return $b['count'] - $a['count'];
        });
        usort($brgy_data['recommended_services'], function($a, $b) {
            $priority_order = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];
            $a_priority = $priority_order[$a['priority']] ?? 0;
            $b_priority = $priority_order[$b['priority']] ?? 0;
            if ($a_priority !== $b_priority) {
                return $b_priority - $a_priority;
            }
            return $b['affected_count'] - $a['affected_count'];
        });
    }
    
    uasort($barangay_recommendations, function($a, $b) {
        return ($b['total_pwd'] ?? 0) - ($a['total_pwd'] ?? 0);
    });

    $data['grand_totals'] = [
        'total_barangays' => count($barangay_recommendations),
        'total_pwd' => array_sum(array_column($barangay_recommendations, 'total_pwd')),
        'total_unemployed' => array_sum(array_column($barangay_recommendations, 'total_unemployed')),
        'total_children' => array_sum(array_column($barangay_recommendations, 'total_children'))
    ];

    if (!empty($barangay_recommendations)) {
        $all_barangays = $barangay_recommendations;
        $data['full_export_data'] = $all_barangays;
            
        $total_items = count($all_barangays);
        $items_per_page = 5; 
        $total_pages = ceil($total_items / $items_per_page);
        
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($current_page < 1) {
            $current_page = 1;
        } elseif ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
        }
        
        $offset = ($current_page - 1) * $items_per_page;
        
        $paginated_barangays = array_slice($all_barangays, $offset, $items_per_page, true);
        
        $data['barangay_recommendations'] = $paginated_barangays;
        
        $data['pagination'] = [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'items_per_page' => $items_per_page
        ];
    } else {
        $data['barangay_recommendations'] = [];
        $data['pagination'] = null;
        $data['full_export_data'] = [];
    }

    // --- START: Generate Key Insights (NEW) ---
    $insights = [];
    $full_data = $data['full_export_data'];
    
    if (!empty($full_data)) {
        // Find top priority barangay
        $top_barangay_name = key($full_data); // Get the first key (barangay name) since it's sorted by total PWD
        $top_barangay_data = $full_data[$top_barangay_name];
        $critical_count = 0;
        foreach ($top_barangay_data['recommended_services'] as $service) {
            if ($service['priority'] == 'Critical') {
                $critical_count++;
            }
        }
        if ($critical_count > 0) {
            $insights[] = "<strong>{$top_barangay_name}</strong> shows the highest need with <strong>{$critical_count}</strong> 'Critical' priority service recommendations.";
        } else {
            $insights[] = "<strong>{$top_barangay_name}</strong> has the highest PWD population (<strong>{$top_barangay_data['total_pwd']}</strong>) in this filter set.";
        }

        // Find most common service factors
        $factor_counts = ['children' => 0, 'unemployment' => 0, 'concentration' => 0];
        $priority_disabilities = [];
        foreach ($full_data as $brgy_name => $brgy_data) {
            foreach ($brgy_data['recommended_services'] as $service) {
                if ($service['factors']['many_children']) $factor_counts['children']++;
                if ($service['factors']['high_unemployment']) $factor_counts['unemployment']++;
                if ($service['factors']['high_concentration']) $factor_counts['concentration']++;
                
                if ($service['priority'] == 'Critical' || $service['priority'] == 'High') {
                    $type = $service['disability_type'];
                    $priority_disabilities[$type] = ($priority_disabilities[$type] ?? 0) + 1;
                }
            }
        }
        
        // Add factor insights
        if ($factor_counts['unemployment'] > 0 && $factor_counts['unemployment'] >= count($full_data) / 2) {
            $insights[] = "<strong>Livelihood programs</strong> are a common need, triggered by high unemployment rates in <strong>" . $factor_counts['unemployment'] . "</strong> barangay(s).";
        }
        if ($factor_counts['children'] > 0 && $factor_counts['children'] >= count($full_data) / 2) {
            $insights[] = "<strong>Child-focused services</strong> (education/therapy) are a high priority, triggered in <strong>" . $factor_counts['children'] . "</strong> barangay(s).";
        }

        // Add top disability insight
        if (!empty($priority_disabilities)) {
            arsort($priority_disabilities);
            $top_disability_type = key($priority_disabilities);
            $top_disability_count = $priority_disabilities[$top_disability_type];
            $insights[] = "<strong>{$top_disability_type}</strong> is the most common high-priority disability, appearing in <strong>{$top_disability_count}</strong> barangay(s).";
        }
    } else {
        $insights[] = "No specific insights generated due to the current filters. Broaden your search to identify key trends.";
    }
    $data['insights'] = $insights;
    // --- END: Generate Key Insights ---

    // --- START: Generate Stacked Bar Chart Data (REVISED) ---
    // This code goes INSIDE generateResourcesReport() right BEFORE 'return $data;'
    
    $chart_labels = []; // Barangays
    $chart_datasets = []; // Disabilities { label: 'Type', data: [...] }
    $all_disabilities = []; // To track unique disability types
    $pivoted_data = []; // [Barangay][Disability] => Count

    // Use the full, unpaginated data so it respects the filters
    $full_data = $data['full_export_data'] ?? []; 

    // 1. First pass: Get all unique labels (barangays) and datasets (disabilities)
    //    and populate the pivot table
    foreach ($full_data as $barangay => $brgy_data) {
        $chart_labels[] = $barangay; // Add barangay to labels
        if (!empty($brgy_data['disabilities'])) {
            foreach ($brgy_data['disabilities'] as $disability) {
                $type = $disability['type'];
                if (!in_array($type, $all_disabilities)) {
                    $all_disabilities[] = $type; // Add unique disability
                }
                $pivoted_data[$barangay][$type] = $disability['count'];
            }
        }
    }
    
    // 2. Second pass: Build the datasets for Chart.js
    foreach ($all_disabilities as $disability_type) {
        $dataset = [
            'label' => $disability_type,
            'data' => []
        ];
        
        // For each barangay, find the count for this disability
        foreach ($chart_labels as $barangay) {
            // Add the count, or 0 if it doesn't exist for this barangay
            $dataset['data'][] = $pivoted_data[$barangay][$disability_type] ?? 0;
        }
        
        $chart_datasets[] = $dataset;
    }

    // Add to the main $data array to be sent to the view
    $data['stacked_chart_data'] = [
        'labels' => $chart_labels,
        'datasets' => $chart_datasets
    ];
    // --- END: Generate Stacked Bar Chart Data ---

    // --- START: Generate Employment by Barangay Chart Data (NEW) ---
    
    // We use the main $where_clause and $params to respect all filters
    // (date, barangay, disability, status, gender, AND employment).
    $stmt = $pdo->prepare("
        SELECT 
            barangay,
            COALESCE(employment_status, 'Not Specified') as employment_status,
            COUNT(*) as count
        FROM pwd_records pwd_records
        {$where_clause}
        AND barangay IS NOT NULL
        GROUP BY barangay, employment_status
    ");
    $stmt->execute($params);
    $emp_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $emp_chart_labels = []; // Stores Barangays
    $emp_chart_datasets = []; // Stores Employment Statuses { label: 'Status', data: [...] }
    $all_employment_statuses = []; // Stores unique statuses found
    $emp_pivoted_data = []; // Format: [Barangay][EmploymentStatus] => Count

    // 1. First pass: Get all unique labels (barangays) and datasets (statuses)
    //    and populate the pivot table
    foreach ($emp_results as $row) {
        $barangay = $row['barangay'];
        $status = $row['employment_status'];
        
        if (!in_array($barangay, $emp_chart_labels)) {
            $emp_chart_labels[] = $barangay; // Add unique barangay
        }
        if (!in_array($status, $all_employment_statuses)) {
            $all_employment_statuses[] = $status; // Add unique employment status
        }
        $emp_pivoted_data[$barangay][$status] = $row['count'];
    }
    
    // Sort labels and datasets for consistency
    sort($emp_chart_labels);
    sort($all_employment_statuses);

    // 2. Second pass: Build the datasets for Chart.js
    foreach ($all_employment_statuses as $status) {
        $dataset = [
            'label' => $status,
            'data' => []
        ];
        
        // For each barangay, find the count for this status
        foreach ($emp_chart_labels as $barangay) {
            // Add the count, or 0 if it doesn't exist for this barangay
            $dataset['data'][] = $emp_pivoted_data[$barangay][$status] ?? 0;
        }
        $emp_chart_datasets[] = $dataset;
    }

    // Add to the main $data array to be sent to the view
    $data['employment_by_barangay_chart_data'] = [
        'labels' => $emp_chart_labels,
        'datasets' => $emp_chart_datasets
    ];
    // --- END: Generate Employment by Barangay Chart Data ---

    return $data;
}
// Make sure this is the end of the function


// --- START: PDF EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {

    define('PWD_PORTAL_PDF_EXPORT', true); 
    require_once __DIR__ . '/../vendor/autoload.php'; 
    require_once('reports/pdf_templates.php');      
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('PWD Support Portal Admin');
    $pdf->SetHeaderData('', 0, 'PWD Support Portal Report', "Type: " . ucfirst($report_type) . "\nPeriod: " . htmlspecialchars($date_from) . " to " . htmlspecialchars($date_to));
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->AddPage();
    
    switch ($report_type) {
        case 'analytics':
            generateAnalyticsPdf($pdf, $report_data);
            break;
        case 'demographics':
            generateDemographicsPdf($pdf, $report_data);
            break;
        case 'resources':
            generateResourcesPdf($pdf, $report_data);
            break;
    }

    // --- NEW: ADDED LOGGING ---
    $filters = compact('report_type', 'date_from', 'date_to', 'status_filter', 'disability_filter', 'age_group', 'barangay_filter', 'time_period', 'gender_filter', 'employment_filter');
    logAdminActivity($pdo, 'export', 'reports', $report_type . '_pdf', null, $filters);
    
    $pdf->Output('pwd_report_' . $report_type . '_' . date('Y-m-d') . '.pdf', 'D');
    
    exit;

// --- NEW: ADDED SERVER-SIDE CSV EXPORT LOGIC ---
} elseif (isset($_GET['export']) && $_GET['export'] === 'csv') {
    
    $filename = 'pwd_report_' . $report_type . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8 Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // --- Generate CSV based on report type ---
    switch ($report_type) {
        case 'analytics':
            fputcsv($output, ["PWD Analytics Report"]);
            fputcsv($output, ["Period:", $date_from . " to " . $date_to]);
            fputcsv($output, []); // Empty line
            
            fputcsv($output, ["Summary Statistics"]);
            fputcsv($output, ["Metric", "Value"]);
            if (!empty($report_data['summary'])) {
                $summary = $report_data['summary'];
                fputcsv($output, ["Total Individuals", $summary['total_individuals'] ?? 0]);
                fputcsv($output, ["Draft Records", $summary['draft_records'] ?? 0]);
                fputcsv($output, ["Validated Profiles", $summary['validated_profiles'] ?? 0]);
                fputcsv($output, ["Active IDs", $summary['active_ids'] ?? 0]);
                fputcsv($output, ["Expired IDs", $summary['expired_ids'] ?? 0]);
                fputcsv($output, ["Inactive IDs", $summary['inactive_ids'] ?? 0]);
                fputcsv($output, ["Average Age", round($summary['avg_age'] ?? 0, 1)]);
            }
            fputcsv($output, []); // Empty line

            fputcsv($output, ["Age Distribution"]);
            fputcsv($output, ["Age Group", "Count", "Percentage"]);
            if (!empty($report_data['age_groups'])) {
                foreach ($report_data['age_groups'] as $row) {
                    fputcsv($output, [$row['age_group'], $row['count'], $row['percentage']]);
                }
            }
            fputcsv($output, []); // Empty line
            
            fputcsv($output, ["Gender Distribution"]);
            fputcsv($output, ["Gender", "Count", "Percentage"]);
            if (!empty($report_data['gender_distribution'])) {
                foreach ($report_data['gender_distribution'] as $row) {
                    fputcsv($output, [$row['gender'], $row['count'], $row['percentage']]);
                }
            }
            fputcsv($output, []); // Empty line

            fputcsv($output, ["Disability Type Distribution"]);
            fputcsv($output, ["Disability Type", "Count", "Percentage"]);
            if (!empty($report_data['disability_distribution'])) {
                foreach ($report_data['disability_distribution'] as $row) {
                    fputcsv($output, [$row['disability_type'], $row['count'], $row['percentage']]);
                }
            }
            fputcsv($output, []); // Empty line
            
            fputcsv($output, ["Geographic Distribution Across Barangays"]);
            fputcsv($output, ["Barangay", "Total", "Active IDs", "Expired IDs", "Inactive IDs", "Validated", "Drafts", "Avg Age"]);
            if (!empty($report_data['barangay_distribution'])) {
                foreach ($report_data['barangay_distribution'] as $row) {
                    fputcsv($output, [
                        $row['barangay'], $row['count'], $row['active_ids'], $row['expired_ids'],
                        $row['inactive_ids'], $row['validated'], $row['drafts'], round($row['avg_age'], 1)
                    ]);
                }
            }
            break;
            
        case 'demographics':
            fputcsv($output, ["PWD Demographics Report"]);
            fputcsv($output, ["Period:", $date_from . " to " . $date_to]);
            fputcsv($output, []); // Empty line
            
            fputcsv($output, ["Detailed Area Profiles"]);
            fputcsv($output, ["Barangay", "Total Members", "Male", "Female", "Children (0-17)", "Avg Age", "Active IDs"]);
            if (!empty($report_data['barangay_profiles'])) {
                foreach ($report_data['barangay_profiles'] as $row) {
                    fputcsv($output, [
                        $row['barangay'], $row['total_individuals'], $row['male_count'], $row['female_count'],
                        $row['children_count'], round($row['avg_age'], 1), $row['active_ids']
                    ]);
                }
            }
            
            fputcsv($output, []); // Empty line
            fputcsv($output, ["Disability Type by Area"]);
            fputcsv($output, ["Barangay", "Disability Type", "Count"]);
            if (!empty($report_data['disability_by_barangay'])) {
                 foreach ($report_data['disability_by_barangay'] as $row) {
                    fputcsv($output, [$row['barangay'], $row['disability_type'], $row['count']]);
                }
            }
            break;
            
        case 'resources':
            fputcsv($output, ["PWD Resource Planning Report"]);
            fputcsv($output, ["Period:", $date_from . " to " . $date_to]);
            fputcsv($output, []); // Empty line

            fputcsv($output, [
                "Barangay", "Disability Type", "Affected Count", "Concentration %", "Priority", 
                "Children Count", "Unemployed Count", "Recommended Services (Sample)"
            ]);
            
            // Use the full_export_data for a complete CSV, not the paginated one
            $dataToExport = $report_data['full_export_data'] ?? $report_data['barangay_recommendations'] ?? [];

            if (!empty($dataToExport)) {
                foreach ($dataToExport as $barangay => $data) {
                    if (!empty($data['recommended_services'])) {
                        foreach ($data['recommended_services'] as $service) {
                            fputcsv($output, [
                                $barangay,
                                $service['disability_type'],
                                $service['affected_count'],
                                $service['concentration'],
                                $service['priority'],
                                $service['children_count'],
                                $service['unemployed_count'],
                                implode('; ', array_slice($service['services'], 0, 5)) // Get first 5 services
                            ]);
                        }
                    }
                }
            }
            break;
    }
    
    fclose($output);
    
    // --- NEW: ADDED LOGGING ---
    $filters = compact('report_type', 'date_from', 'date_to', 'status_filter', 'disability_filter', 'age_group', 'barangay_filter', 'time_period', 'gender_filter', 'employment_filter');
    logAdminActivity($pdo, 'export', 'reports', $report_type . '_csv', null, $filters);
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Insights - PWD Support Portal</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    
    .active-filters-bar {
    background: #fffbe6; /* Light yellow */
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.active-filter-tag {
    background: #fef3c7;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
}
.clear-all-link {
    margin-left: auto;
    color: #b91c1c;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: underline;
}

    /* --- Pagination Styles --- */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination {
    display: flex;
    list-style: none; /* This removes the bullet points */
    padding: 0;
    margin: 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden; /* This helps round the corners */
}

.page-item {
    margin: 0; /* Removes default list item margins */
}

.page-link {
    display: block;
    padding: 0.75rem 1rem;
    color: #2c5aa0;
    background-color: white;
    border-left: 1px solid #e5e7eb;
    text-decoration: none; /* Removes the underline */
    transition: background-color 0.2s ease;
}

.page-item:first-child .page-link {
    border-left: none;
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}

.page-item:last-child .page-link {
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
}

.page-link:hover {
    background-color: #f8fafc;
}

.page-item.active .page-link {
    background-color: #2c5aa0;
    color: white;
    font-weight: 600;
    pointer-events: none; /* Prevents clicking on the active page */
}

.page-item.disabled .page-link {
    color: #9ca3af;
    background-color: #f8fafc;
    pointer-events: none; /* Disables the "Previous/Next" links */
}
        .analytics-header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e40af 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .analytics-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .analytics-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .report-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
            overflow-x: auto;
        }
        
        .report-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
        }
        
        .report-tab.active {
            color: #2c5aa0;
            border-bottom-color: #2c5aa0;
        }
        
        .report-tab:hover {
            color: #2c5aa0;
            background: #f8fafc;
        }
        
        .filters-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        /* --- STYLES FOR BUTTONS --- */
        .filters-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem; /* Slightly smaller gap for buttons */
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb; /* Separator line */
        }
        
        /* Remove bottom margin from form-groups inside the new actions container */
        .filters-actions .form-group {
            margin-bottom: 0; 
        }

        /* This is the magic class. It pushes the items after it to the right */
        .filters-actions .push-left {
            margin-left: auto; 
        }
        /* --- END NEW STYLES --- */
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .analytics-card h3 {
            margin: 0 0 1rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #cbd5e1;
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c5aa0;
            margin: 0;
        }
        
        .metric-label {
            color: #64748b;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }
        
        .metric-change {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            color: #10b981;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 1rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2c5aa0, #3b82f6);
            transition: width 0.3s ease;
        }
        
        .resource-card {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        
        .resource-card h4 {
            margin: 0 0 0.5rem 0;
            color: #0c4a6e;
        }
        
        .resource-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .resource-list li {
            padding: 0.25rem 0;
            color: #0369a1;
        }
        
        .resource-list li:before {
            content: "→";
            margin-right: 0.5rem;
            color: #2c5aa0;
        }
        
        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-critical {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .priority-high {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .priority-medium {
            background: #dbeafe;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        
        .filter-clear {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .filter-clear:hover {
            background: #dc2626;
        }
        
        .factor-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }
        
        .factor-concentration {
            background: #fef3c7;
            color: #92400e;
        }
        
        .factor-children {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .factor-unemployment {
            background: #fee2e2;
            color: #991b1b;
        }
        
        @media (max-width: 768px) {
            .analytics-header h1 {
                font-size: 2rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }

            /* --- RESPONSIVE FIX FOR BUTTONS --- */
            .filters-actions {
                justify-content: flex-start; /* Stack them on the left on mobile */
            }
            .filters-actions .push-left {
                margin-left: 0; /* Remove the auto-margin */
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    
    <main class="dashboard-container">
        <div class="analytics-header">
            <h1><i class="fas fa-chart-line"></i> Community Analytics & Insights</h1>
            <p>Data-driven insights to support our community and improve services</p>
        </div>
        
        <div class="report-tabs">
            <a href="?type=analytics" class="report-tab <?php echo $report_type == 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Overview Analytics
            </a>
            <a href="?type=demographics" class="report-tab <?php echo $report_type == 'demographics' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Demographics
            </a>
            <a href="?type=resources" class="report-tab <?php echo $report_type == 'resources' ? 'active' : ''; ?>">
                <i class="fas fa-lightbulb"></i> Resource Planning
            </a>
        </div>

        <?php
// --- START: Active Filter Bar ---
// Build a fresh URL with only the report type and default dates
$clear_url = http_build_query([
    'type' => $report_type,
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-d')
]);

// Check for any non-default filters
$active_filters = [];

// --- NEW CHECKS FOR DATE AND TIME PERIOD ---
// Check if 'date_from' is NOT the default (first of the month)
if ($date_from !== date('Y-m-01')) {
    $active_filters['From Date'] = date('M j, Y', strtotime($date_from));
}

// Check if 'date_to' is NOT the default (today)
if ($date_to !== date('Y-m-d')) {
    $active_filters['To Date'] = date('M j, Y', strtotime($date_to));
}

// Check if 'time_period' is NOT the default ('monthly')
if ($report_type == 'analytics' && $time_period !== 'monthly') {
    $active_filters['Time Period'] = ucfirst($time_period); // e.g., "Quarterly"
}
// --- END NEW CHECKS ---


// --- Your existing checks ---
if (!empty($status_filter)) { 
    $status_text = $status_filter;
    if ($status_text === 'issued') $status_text = 'Active'; // Match dropdown
    $active_filters['Status'] = ucfirst($status_text);
}
if (!empty($disability_filter)) { $active_filters['Disability'] = $disability_filter; }
if (!empty($age_group)) { $active_filters['Age'] = ucfirst($age_group); }
if (!empty($barangay_filter)) { $active_filters['Barangay'] = $barangay_filter; }
if (!empty($gender_filter)) { $active_filters['Gender'] = $gender_filter; }
if (!empty($employment_filter)) { $active_filters['Employment'] = $employment_filter; }
?>

<?php if (!empty($active_filters)): ?>
    <div class="active-filters-bar">
        <strong><i class="fas fa-filter"></i> Filters Active:</strong>
        <?php foreach ($active_filters as $label => $value): ?>
            <span class="active-filter-tag">
                <?php echo htmlspecialchars($label); ?>: 
                <strong><?php echo htmlspecialchars($value); ?></strong>
            </span>
        <?php endforeach; ?>

        <a href="?<?php echo $clear_url; ?>" class="clear-all-link">Clear All</a>
    </div>
<?php endif; ?>
        
        <div class="filters-panel">
            <form method="GET" id="filtersForm">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <?php if ($report_type == 'analytics'): ?>
                    <div class="form-group">
                        <label for="time_period">Time Period</label>
                        <select name="time_period" id="time_period" class="form-control">
                            <option value="monthly" <?php echo $time_period == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo $time_period == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="yearly" <?php echo $time_period == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_filter">Status</label>
                        <select name="status" id="status_filter" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="validated" <?php echo $status_filter == 'validated' ? 'selected' : ''; ?>>Validated</option>
                            <option value="issued" <?php echo $status_filter == 'issued' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender_filter">Gender</label>
                        <select name="gender" id="gender_filter" class="form-control">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo $gender_filter == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $gender_filter == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="employment_filter">Employment</label>
                        <select name="employment" id="employment_filter" class="form-control">
                            <option value="">All Employment</option>
                            <?php foreach ($available_employment as $employment): ?>
                            <option value="<?php echo htmlspecialchars($employment); ?>" <?php echo $employment_filter == $employment ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employment); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="barangay_filter">Barangay</label>
                        <select name="barangay" id="barangay_filter" class="form-control">
                            <option value="">All Barangays</option>
                            <?php foreach ($available_barangays as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo $barangay_filter == $barangay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="disability_filter">Disability Type</label>
                        <select name="disability" id="disability_filter" class="form-control">
                            <option value="">All Disabilities</option>
                            <?php foreach ($available_disabilities as $disability): ?>
                            <option value="<?php echo htmlspecialchars($disability); ?>" <?php echo $disability_filter == $disability ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disability); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($report_type == 'demographics'): ?>

                        <div class="form-group">
                        <label for="gender_filter">Gender</label>
                        <select name="gender" id="gender_filter" class="form-control">
                            <option value="">All Genders</option>
                            <option value="Male" <?php echo $gender_filter == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $gender_filter == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="age_group">Age Group</label>
                        <select name="age_group" id="age_group" class="form-control">
                            <option value="">All Ages</option>
                            <option value="children" <?php echo $age_group == 'children' ? 'selected' : ''; ?>>Children (0-17)</option>
                            <option value="adults" <?php echo $age_group == 'adults' ? 'selected' : ''; ?>>Adults (18-64)</option>
                            <option value="seniors" <?php echo $age_group == 'seniors' ? 'selected' : ''; ?>>Seniors (65+)</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="filters-actions">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Update Report
                        </button>
                    </div>
                    
                    
                    
                    <?php
                    // Build the query string for the export links, preserving all filters
                    $query_params = $_GET;
                    unset($query_params['export']); // Remove any old export param
                    ?>
                    
                    <div class="form-group push-left">
                        <?php $csv_query_string = http_build_query($query_params) . '&export=csv'; ?>
                        <a href="?<?php echo $csv_query_string; ?>" class="btn btn-outline">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                    </div>

                    <div class="form-group">
                        <?php $pdf_query_string = http_build_query($query_params) . '&export=pdf'; ?>
                        <a href="?<?php echo $pdf_query_string; ?>" class="btn btn-primary" style="background-color: #e53935; border-color: #e53935;" target="_blank">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <div id="reportContent">
            <?php if ($report_type == 'analytics'): ?>
                <?php include 'reports/analytics.php'; ?>
            <?php elseif ($report_type == 'demographics'): ?>
                <?php include 'reports/demographics.php'; ?>
            <?php elseif ($report_type == 'resources'): ?>
                <?php include 'reports/resources.php'; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="assets/admin.js"></script>
    <script>
        // --- REMOVED old JS export functions ---
        
        function clearFilters() {
            const form = document.getElementById('filtersForm');
            const inputs = form.querySelectorAll('input[type="date"], select');
            
            inputs.forEach(input => {
                if (input.type === 'date') {
                    if (input.name === 'date_from') {
                        input.value = '<?php echo date('Y-m-01'); ?>';
                    } else if (input.name === 'date_to') {
                        input.value = '<?php echo date('Y-m-d'); ?>';
                    }
                } else if (input.name !== 'type') {
                    input.selectedIndex = 0;
                }
            });
            
            form.submit();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initializeAnalyticsCharts === 'function') {
                initializeAnalyticsCharts();
            }
        });
    </script>
</body>
</html>