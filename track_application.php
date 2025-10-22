<?php
// Simple application tracking for XAMPP

if (isset($_GET['ref'])) {
    $reference = $_GET['ref'];
    
    try {
        // XAMPP default connection (no password)
        $pdo = new PDO('mysql:host=localhost;dbname=pwd_portal', 'root', '');
        
        $sql = "SELECT * FROM applications WHERE reference_number = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reference]);
        $application = $stmt->fetch();
        
        if ($application) {
            echo json_encode([
                'found' => true,
                'data' => $application
            ]);
        } else {
            echo json_encode(['found' => false]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>