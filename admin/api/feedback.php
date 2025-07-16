<?php
require_once '../config.php';
requireAdminLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_feedback':
        getFeedback();
        break;
    case 'rating_distribution':
        getRatingDistribution();
        break;
    default:
        adminJsonResponse(['error' => 'Invalid action'], 400);
}

function getFeedback() {
    global $pdo;
    requirePermission($pdo, 'feedback.view');
    
    $feedback_id = $_GET['id'] ?? '';
    
    if (empty($feedback_id)) {
        adminJsonResponse(['error' => 'Feedback ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, u.first_name as user_first_name, u.last_name as user_last_name,
                   au.full_name as responded_by_name
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.id
            LEFT JOIN admin_users au ON f.responded_by = au.id
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
        adminJsonResponse(['error' => 'Failed to get feedback: ' . $e->getMessage()], 500);
    }
}

function getRatingDistribution() {
    global $pdo;
    requirePermission($pdo, 'feedback.view');
    
    try {
        $stmt = $pdo->query("
            SELECT rating, COUNT(*) as count
            FROM feedback 
            WHERE rating IS NOT NULL
            GROUP BY rating
            ORDER BY rating
        ");
        $results = $stmt->fetchAll();
        
        // Create array with all ratings 1-5, defaulting to 0
        $distribution = [0, 0, 0, 0, 0]; // Index 0-4 for ratings 1-5
        
        foreach ($results as $result) {
            $rating = intval($result['rating']);
            if ($rating >= 1 && $rating <= 5) {
                $distribution[$rating - 1] = intval($result['count']);
            }
        }
        
        adminJsonResponse([
            'success' => true,
            'distribution' => $distribution
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to get rating distribution: ' . $e->getMessage()], 500);
    }
}
?>
