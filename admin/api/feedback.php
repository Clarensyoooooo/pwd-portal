<?php
require_once '../config.php';
requireAdminLogin($pdo);

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_feedback':
        handleGetFeedback();
        break;
    case 'rating_distribution':
        handleRatingDistribution();
        break;
    case 'export':
        handleExport();
        break;
    default:
        adminJsonResponse(['error' => 'Invalid action'], 400);
}

function handleGetFeedback() {
    global $pdo;
    requirePermission($pdo, 'feedback.view');
    
    $feedback_id = $_GET['id'] ?? '';
    
    if (empty($feedback_id)) {
        adminJsonResponse(['error' => 'Feedback ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, u.first_name as user_first_name, u.last_name as user_last_name
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$feedback_id]);
        $feedback = $stmt->fetch();
        
        if (!$feedback) {
            adminJsonResponse(['error' => 'Feedback not found'], 404);
        }
        
        adminJsonResponse([
            'success' => true,
            'feedback' => $feedback
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to fetch feedback: ' . $e->getMessage()], 500);
    }
}

function handleRatingDistribution() {
    global $pdo;
    requirePermission($pdo, 'feedback.view');
    
    try {
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5
            FROM feedback 
            WHERE rating IS NOT NULL
        ");
        $result = $stmt->fetch();
        
        $distribution = [
            intval($result['rating_1']),
            intval($result['rating_2']),
            intval($result['rating_3']),
            intval($result['rating_4']),
            intval($result['rating_5'])
        ];
        
        adminJsonResponse([
            'success' => true,
            'distribution' => $distribution
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to fetch rating distribution: ' . $e->getMessage()], 500);
    }
}

function handleExport() {
    global $pdo;
    requirePermission($pdo, 'feedback.view');
    
    try {
        // Get filters from request
        $status_filter = $_GET['status'] ?? '';
        $rating_filter = $_GET['rating'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if ($status_filter) {
            $where_conditions[] = "f.status = ?";
            $params[] = $status_filter;
        }
        
        if ($rating_filter) {
            $where_conditions[] = "f.rating = ?";
            $params[] = $rating_filter;
        }
        
        if ($search) {
            $where_conditions[] = "(f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.message LIKE ?)";
            $search_param = "%{$search}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $stmt = $pdo->prepare("
            SELECT f.name, f.email, f.subject, f.message, f.rating, f.status, f.created_at,
                   u.first_name as user_first_name, u.last_name as user_last_name
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            {$where_clause}
            ORDER BY f.created_at DESC
        ");
        $stmt->execute($params);
        $feedback_list = $stmt->fetchAll();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="feedback_export_' . date('Y-m-d') . '.csv"');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Name',
            'Email', 
            'Subject',
            'Message',
            'Rating',
            'Status',
            'Date',
            'User Type'
        ]);
        
        // CSV data
        foreach ($feedback_list as $feedback) {
            fputcsv($output, [
                $feedback['name'],
                $feedback['email'],
                $feedback['subject'],
                $feedback['message'],
                $feedback['rating'] ?: 'N/A',
                ucfirst($feedback['status']),
                date('M j, Y g:i A', strtotime($feedback['created_at'])),
                $feedback['user_first_name'] ? 'Registered User' : 'Guest'
            ]);
        }
        
        fclose($output);
        exit;
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to export feedback: ' . $e->getMessage()], 500);
    }
}
?>
