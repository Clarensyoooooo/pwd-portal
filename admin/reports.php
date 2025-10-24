<?php
require_once 'config.php';
requireAdminLogin();
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
        $report_data = generateAnalyticsReport($pdo, $date_from, $date_to, $time_period, $status_filter, $disability_filter, $gender_filter, $barangay_filter, $employment_filter);
        break;
    case 'demographics':
        $report_data = generateDemographicsReport($pdo, $date_from, $date_to, $age_group, $barangay_filter, $gender_filter, $disability_filter);
        break;
    case 'resources':
        $report_data = generateResourcesReport($pdo, $date_from, $date_to, $barangay_filter, $disability_filter, $status_filter, $gender_filter, $employment_filter);
        break;
}

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
    
    $date_format = $time_period == 'yearly' ? '%Y' : ($time_period == 'quarterly' ? '%Y-Q%q' : '%Y-%m');
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '{$date_format}') as period,
            COUNT(*) as registrations,
            SUM(CASE WHEN status = 'issued' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) as active_ids,
            SUM(CASE WHEN status = 'issued' AND expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_ids,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_ids,
            SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated
        FROM pwd_records 
        {$where_clause}
        GROUP BY DATE_FORMAT(created_at, '{$date_format}')
        ORDER BY period
    ");
    $stmt->execute($params);
    $data['trends'] = $stmt->fetchAll();
    
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

    return $data;
}
// Make sure this is the end of the function
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
            height: 300px;
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
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    
    <main class="main-content">
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
                        <label for="age_group">Age Group</label>
                        <select name="age_group" id="age_group" class="form-control">
                            <option value="">All Ages</option>
                            <option value="children" <?php echo $age_group == 'children' ? 'selected' : ''; ?>>Children (0-17)</option>
                            <option value="adults" <?php echo $age_group == 'adults' ? 'selected' : ''; ?>>Adults (18-64)</option>
                            <option value="seniors" <?php echo $age_group == 'seniors' ? 'selected' : ''; ?>>Seniors (65+)</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Update Report
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="clearFilters()" class="filter-clear">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" onclick="exportReport()" class="btn btn-outline">
                            <i class="fas fa-download"></i> Export Report
                        </button>
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
        // Store report data for export
        const reportData = <?php echo json_encode($report_data); ?>;
        const reportType = '<?php echo $report_type; ?>';
        const filters = {
            date_from: '<?php echo $date_from; ?>',
            date_to: '<?php echo $date_to; ?>',
            status: '<?php echo $status_filter; ?>',
            disability: '<?php echo $disability_filter; ?>',
            barangay: '<?php echo $barangay_filter; ?>',
            gender: '<?php echo $gender_filter; ?>',
            employment: '<?php echo $employment_filter; ?>',
            age_group: '<?php echo $age_group; ?>'
        };
        
        function exportReport() {
            let csvContent = "data:text/csv;charset=utf-8,\ufeff";
            
            if (reportType === 'analytics') {
                csvContent += exportAnalyticsData();
            } else if (reportType === 'demographics') {
                csvContent += exportDemographicsData();
            } else if (reportType === 'resources') {
                csvContent += exportResourcesData();
            }
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `PWD_${reportType}_report_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Report exported successfully!', 'success');
        }
        
        function exportAnalyticsData() {
            let csv = "PWD Analytics Report\n";
            csv += `Generated: ${new Date().toLocaleString()}\n`;
            csv += `Period: ${filters.date_from} to ${filters.date_to}\n\n`;
            
            csv += "Summary Statistics\n";
            csv += "Metric,Value\n";
            if (reportData.summary) {
                csv += `Total Individuals,${reportData.summary.total_individuals || 0}\n`;
                csv += `Validated Profiles,${reportData.summary.validated_profiles || 0}\n`;
                csv += `Active IDs,${reportData.summary.active_ids || 0}\n`;
                csv += `Pending Support,${reportData.summary.pending_support || 0}\n`;
                csv += `Average Age,${reportData.summary.avg_age ? Math.round(reportData.summary.avg_age) : 'N/A'}\n`;
            }
            
            csv += "\n\nDisability Type Distribution\n";
            csv += "Disability Type,Count,Percentage\n";
            if (reportData.disability_distribution) {
                reportData.disability_distribution.forEach(row => {
                    csv += `"${row.disability_type}",${row.count},${row.percentage}%\n`;
                });
            }
            
            csv += "\n\nBarangay Distribution\n";
            csv += "Barangay,Count,Active IDs,Average Age,Percentage\n";
            if (reportData.barangay_distribution) {
                reportData.barangay_distribution.forEach(row => {
                    csv += `"${row.barangay}",${row.count},${row.active_ids},${Math.round(row.avg_age)},${row.percentage}%\n`;
                });
            }
            
            return csv;
        }
        
        function exportDemographicsData() {
            let csv = "PWD Demographics Report\n";
            csv += `Generated: ${new Date().toLocaleString()}\n`;
            csv += `Period: ${filters.date_from} to ${filters.date_to}\n\n`;
            
            csv += "Barangay Summary\n";
            csv += "Barangay,Total PWDs,Male,Female,Children,Average Age,Active IDs\n";
            if (reportData.barangay_profiles) {
                reportData.barangay_profiles.forEach(row => {
                    csv += `"${row.barangay}",${row.total_individuals},${row.male_count},${row.female_count},${row.children_count},${Math.round(row.avg_age)},${row.active_ids}\n`;
                });
            }
            
            return csv;
        }
        
        function exportResourcesData() {
            let csv = "PWD Resource Planning Report\n";
            csv += `Generated: ${new Date().toLocaleString()}\n`;
            csv += `Period: ${filters.date_from} to ${filters.date_to}\n\n`;
            
            csv += "Barangay Resource Recommendations\n";
            csv += "Barangay,Disability Type,Affected Count,Concentration %,Priority,Children,Seniors,Unemployed,Key Factors,Recommended Services\n";
            
            // --- 👇 THIS IS THE MODIFIED PART 👇 ---

            // Check if the new 'full_export_data' exists,
            // otherwise, fall back to the (paginated) 'barangay_recommendations'
            const dataToExport = (reportData.full_export_data && Object.keys(reportData.full_export_data).length > 0) 
                               ? reportData.full_export_data 
                               : reportData.barangay_recommendations;
            
            if (dataToExport) {
                Object.entries(dataToExport).forEach(([barangay, data]) => {
                    if (data.recommended_services) {
                        data.recommended_services.forEach(service => {
            // --- 👆 END OF MODIFIED PART 👆 ---

                            const factors = [];
                            if (service.factors?.high_concentration) factors.push('High Concentration');
                            if (service.factors?.many_children) factors.push('Many Children');
                            if (service.factors?.high_unemployment) factors.push('High Unemployment');
                            
                            const services = service.services.slice(0, 5).join('; ');
                            
                            // Corrected to data['total_seniors']
                            csv += `"${barangay}","${service.disability_type}",${service.affected_count},${service.concentration}%,${service.priority},${service.children_count},${data['total_seniors']},${service.unemployed_count},"${factors.join(', ')}","${services}"\n`;
                        });
                    }
                });
            }
            
            return csv;
        }
        
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
