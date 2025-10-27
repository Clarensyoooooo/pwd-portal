<?php
/**
 * This file contains functions to generate PDF reports using TCPDF.
 * It is included by reports.php when a PDF export is requested.
 */

// This is a security check to ensure this file is not accessed directly
if (!defined('PWD_PORTAL_PDF_EXPORT')) {
    exit('This file cannot be accessed directly.');
}

// --- Helper: Section Title ---
function pdf_section_title($pdf, $title) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(44, 90, 160); // Main Blue
    $pdf->Cell(0, 10, $title, 0, 1, 'L');
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(2);
}

// --- Helper: Table Header ---
function pdf_create_table_header($pdf, $headers) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetTextColor(0);
    $w = array_column($headers, 'width');
    $labels = array_column($headers, 'label');
    $num_headers = count($labels);
    for($i = 0; $i < $num_headers; ++$i) {
        $pdf->Cell($w[$i], 7, $labels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255);
}

/**
 * ===============================================
 * ANALYTICS PDF
 * ===============================================
 */
function generateAnalyticsPdf($pdf, $report_data) {
    $summary = $report_data['summary'] ?? [];
    $total = (int)($summary['total_individuals'] ?? 0);
    
    pdf_section_title($pdf, 'Summary Statistics');
    
    // Re-create metric cards in a simple layout
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(249, 249, 249);
    
    $pdf->Cell(60, 6, 'Total Records:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($total), 'RTB', 0, 'R', 1);
    $pdf->Cell(5);
    $pdf->Cell(60, 6, 'Active IDs:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($summary['active_ids'] ?? 0), 'RTB', 1, 'R', 1);
    
    $pdf->Cell(60, 6, 'Validated (Pending ID):', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($summary['validated_profiles'] ?? 0), 'RTB', 0, 'R', 1);
    $pdf->Cell(5);
    $pdf->Cell(60, 6, 'Expired IDs:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($summary['expired_ids'] ?? 0), 'RTB', 1, 'R', 1);
    
    $pdf->Cell(60, 6, 'Inactive IDs:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($summary['inactive_ids'] ?? 0), 'RTB', 0, 'R', 1);
    $pdf->Cell(5);
    $pdf->Cell(60, 6, 'Average Age:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, round($summary['avg_age'] ?? 0, 1) . ' yrs', 'RTB', 1, 'R', 1);
    
    $pdf->Ln(8);
    
    // --- Disability Distribution ---
    pdf_section_title($pdf, 'Disability Type Distribution');
    $headers_dis = [
        ['label' => 'Disability Type', 'width' => 90],
        ['label' => 'Count', 'width' => 45],
        ['label' => 'Percentage', 'width' => 45]
    ];
    pdf_create_table_header($pdf, $headers_dis);
    $fill = 0;
    foreach ($report_data['disability_distribution'] ?? [] as $row) {
        $pdf->Cell($headers_dis[0]['width'], 6, $row['disability_type'], 1, 0, 'L', $fill);
        $pdf->Cell($headers_dis[1]['width'], 6, number_format($row['count']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_dis[2]['width'], 6, $row['percentage'] . '%', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(8);
    
    // --- Barangay Distribution ---
    pdf_section_title($pdf, 'Geographic Distribution (Barangays)');
    $headers_brgy = [
        ['label' => 'Barangay', 'width' => 50],
        ['label' => 'Total', 'width' => 20],
        ['label' => 'Active', 'width' => 20],
        ['label' => 'Expired', 'width' => 20],
        ['label' => 'Inactive', 'width' => 20],
        ['label' => 'Validated', 'width' => 20],
        ['label' => 'Coverage', 'width' => 30]
    ];
    pdf_create_table_header($pdf, $headers_brgy);
    
    $fill = 0;
    foreach ($report_data['barangay_distribution'] ?? [] as $barangay) {
        $barangay_coverage = $barangay['count'] > 0 ? round(($barangay['active_ids'] / $barangay['count']) * 100) : 0;
        $pdf->Cell($headers_brgy[0]['width'], 6, $barangay['barangay'], 1, 0, 'L', $fill);
        $pdf->Cell($headers_brgy[1]['width'], 6, number_format($barangay['count']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_brgy[2]['width'], 6, number_format($barangay['active_ids']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_brgy[3]['width'], 6, number_format($barangay['expired_ids']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_brgy[4]['width'], 6, number_format($barangay['inactive_ids']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_brgy[5]['width'], 6, number_format($barangay['validated']), 1, 0, 'C', $fill);
        $pdf->Cell($headers_brgy[6]['width'], 6, $barangay_coverage . '%', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100);
    $pdf->MultiCell(0, 5, 'Note: Charts (Age, Gender, Trends) are not included in this PDF export. Please refer to the web dashboard for visual data.', 0, 'L');
}

/**
 * ===============================================
 * DEMOGRAPHICS PDF
 * ===============================================
 */
function generateDemographicsPdf($pdf, $report_data) {
    pdf_section_title($pdf, 'Detailed Area Profiles');
    
    $headers = [
        ['label' => 'Barangay', 'width' => 40],
        ['label' => 'Total', 'width' => 20],
        ['label' => 'Male', 'width' => 20],
        ['label' => 'Female', 'width' => 20],
        ['label' => 'Children', 'width' => 25],
        ['label' => 'Avg Age', 'width' => 25],
        ['label' => 'Coverage', 'width' => 30]
    ];
    pdf_create_table_header($pdf, $headers);
    
    $fill = 0;
    foreach ($report_data['barangay_profiles'] ?? [] as $profile) {
        $coverage_pct = $profile['total_individuals'] > 0 ? round(($profile['active_ids'] / $profile['total_individuals']) * 100) : 0;
        $pdf->Cell($headers[0]['width'], 6, $profile['barangay'], 1, 0, 'L', $fill);
        $pdf->Cell($headers[1]['width'], 6, number_format($profile['total_individuals']), 1, 0, 'C', $fill);
        $pdf->Cell($headers[2]['width'], 6, number_format($profile['male_count']), 1, 0, 'C', $fill);
        $pdf->Cell($headers[3]['width'], 6, number_format($profile['female_count']), 1, 0, 'C', $fill);
        $pdf->Cell($headers[4]['width'], 6, number_format($profile['children_count']), 1, 0, 'C', $fill);
        $pdf->Cell($headers[5]['width'], 6, round($profile['avg_age'], 1) . ' yrs', 1, 0, 'C', $fill);
        $pdf->Cell($headers[6]['width'], 6, $coverage_pct . '%', 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(8);

    pdf_section_title($pdf, 'Disability by Area');
    
    $headers_dis = [
        ['label' => 'Barangay', 'width' => 60],
        ['label' => 'Disability Type', 'width' => 80],
        ['label' => 'Count', 'width' => 40]
    ];
    pdf_create_table_header($pdf, $headers_dis);
    
    $fill = 0;
    $current_barangay = '';
    foreach ($report_data['disability_by_barangay'] ?? [] as $item) {
        $show_barangay = $current_barangay !== $item['barangay'];
        $current_barangay = $item['barangay'];
        
        $pdf->Cell($headers_dis[0]['width'], 6, $show_barangay ? $item['barangay'] : '', 1, 0, 'L', $fill);
        $pdf->Cell($headers_dis[1]['width'], 6, $item['disability_type'], 1, 0, 'L', $fill);
        $pdf->Cell($headers_dis[2]['width'], 6, number_format($item['count']), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(8);
    
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100);
    $pdf->MultiCell(0, 5, 'Note: Charts (Gender/Age by Barangay) are not included in this PDF export. Please refer to the web dashboard for visual data.', 0, 'L');
}

/**
 * ===============================================
 * RESOURCES PDF
 * ===============================================
 */
function generateResourcesPdf($pdf, $report_data) {
    
    // --- Grand Totals ---
    pdf_section_title($pdf, 'Resource Planning Summary');
    $totals = $report_data['grand_totals'] ?? [];
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(249, 249, 249);
    
    $pdf->Cell(60, 6, 'Barangays Analyzed:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($totals['total_barangays'] ?? 0), 'RTB', 0, 'R', 1);
    $pdf->Cell(5);
    $pdf->Cell(60, 6, 'Total PWDs:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($totals['total_pwd'] ?? 0), 'RTB', 1, 'R', 1);
    
    $pdf->Cell(60, 6, 'Total Unemployed:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($totals['total_unemployed'] ?? 0), 'RTB', 0, 'R', 1);
    $pdf->Cell(5);
    $pdf->Cell(60, 6, 'Total Children:', 'LTB', 0, 'L', 1);
    $pdf->Cell(30, 6, number_format($totals['total_children'] ?? 0), 'RTB', 1, 'R', 1);
    
    $pdf->Ln(8);
    
    // --- Key Insights ---
    pdf_section_title($pdf, 'Key Planning Insights');
    $pdf->SetFont('helvetica', '', 10);
    $insights = $report_data['insights'] ?? [];
    if (empty($insights)) {
        $pdf->Cell(0, 6, 'No specific insights for this filter.', 0, 1);
    } else {
        foreach ($insights as $insight) {
            $pdf->writeHTML('• ' . $insight . '<br>', true, false, true, false, '');
        }
    }
    $pdf->Ln(8);
    
    // --- Barangay Recommendations ---
    pdf_section_title($pdf, 'Barangay Service Recommendations');
    
    // IMPORTANT: Use the 'full_export_data', not the paginated 'barangay_recommendations'
    $barangay_data = $report_data['full_export_data'] ?? [];
    
    if (empty($barangay_data)) {
         $pdf->SetFont('helvetica', 'I', 10);
         $pdf->Cell(0, 6, 'No barangay data available for the selected filters.', 0, 1);
         return;
    }

    foreach ($barangay_data as $barangay => $data) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(0, 9, $barangay, 1, 1, 'L', 1);
        $pdf->SetFont('helvetica', '', 9);
        
        // Sub-summary
        $summary_html = "
        <table cellpadding=\"4\" border=\"0\">
        <tr>
            <td><b>Total PWDs:</b> " . number_format($data['total_pwd']) . "</td>
            <td><b>Children:</b> " . number_format($data['total_children']) . "</td>
            <td><b>Unemployed:</b> " . number_format($data['total_unemployed']) . "</td>
        </tr>
        </table>
        ";
        $pdf->writeHTML($summary_html, true, false, true, false, '');

        // Recommended Services Table
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, 'Priority Services:', 0, 1, 'L');
        
        $headers_svc = [
            ['label' => 'Priority', 'width' => 25],
            ['label' => 'Disability', 'width' => 45],
            ['label' => 'Affected', 'width' => 20],
            ['label' => 'Concentration', 'width' => 30],
            ['label' => 'Services (Top 2)', 'width' => 60],
        ];
        pdf_create_table_header($pdf, $headers_svc);
        
        $fill = 0;
        foreach ($data['recommended_services'] ?? [] as $service) {
            $pdf->SetFont('helvetica', '', 8);
            
            // Set text color based on priority
            if ($service['priority'] == 'Critical') $pdf->SetTextColor(220, 53, 69);
            elseif ($service['priority'] == 'High') $pdf->SetTextColor(217, 119, 6);
            else $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell($headers_svc[0]['width'], 6, $service['priority'], 1, 0, 'C', $fill);
            
            $pdf->SetTextColor(0); // Reset color
            $pdf->Cell($headers_svc[1]['width'], 6, $service['disability_type'], 1, 0, 'L', $fill);
            $pdf->Cell($headers_svc[2]['width'], 6, number_format($service['affected_count']), 1, 0, 'C', $fill);
            $pdf->Cell($headers_svc[3]['width'], 6, $service['concentration'] . '%', 1, 0, 'C', $fill);
            
            // Get first 2 services and strip emojis
            $service_list = array_slice($service['services'], 0, 2);
            $service_list_clean = array_map(function($item) {
                return preg_replace('/[[:^print:]]/', '', ltrim(strstr($item, ' ')));
            }, $service_list);

            $pdf->MultiCell($headers_svc[4]['width'], 6, implode("\n", $service_list_clean), 1, 'L', $fill, 1);
            
            $fill = !$fill;
        }
        $pdf->SetTextColor(0);
        $pdf->Ln(10); // Space before next barangay
    }
}