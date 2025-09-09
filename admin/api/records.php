<?php
require_once 'config.php';
requireAdminLogin();
requirePermission($pdo, 'records.view');

$admin = getCurrentAdmin($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'validate_record':
            handleValidateRecord();
            break;
        case 'issue_id':
            handleIssueID();
            break;
        case 'update_record':
            handleUpdateRecord();
            break;
        case 'delete_record':
            handleDeleteRecord();
            break;
        case 'get_record_details':
            handleGetRecordDetails();
            break;
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get records with enhanced filters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$disability_filter = $_GET['disability_type'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$age_group_filter = $_GET['age_group'] ?? '';
$employment_filter = $_GET['employment_status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}

if ($barangay_filter) {
    $where_conditions[] = "pr.barangay = ?";
    $params[] = $barangay_filter;
}

if ($disability_filter) {
    $where_conditions[] = "pr.disability_type = ?";
    $params[] = $disability_filter;
}

if ($gender_filter) {
    $where_conditions[] = "pr.gender = ?";
    $params[] = $gender_filter;
}

if ($employment_filter) {
    $where_conditions[] = "pr.employment_status = ?";
    $params[] = $employment_filter;
}

if ($age_group_filter) {
    switch ($age_group_filter) {
        case 'children':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) < 18";
            break;
        case 'adults':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) BETWEEN 18 AND 59";
            break;
        case 'seniors':
            $where_conditions[] = "TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) >= 60";
            break;
    }
}

if ($search) {
    $where_conditions[] = "(pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.pwd_id_number LIKE ? OR pr.email_address LIKE ? OR pr.phone_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pwd_records pr {$where_clause}");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get records
$stmt = $pdo->prepare("
    SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name,
           TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age
    FROM pwd_records pr
    LEFT JOIN admin_users au1 ON pr.created_by = au1.id
    LEFT JOIN admin_users au2 ON pr.validated_by = au2.id
    LEFT JOIN admin_users au3 ON pr.issued_by = au3.id
    {$where_clause}
    ORDER BY pr.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get filter options from database
$barangays_stmt = $pdo->query("SELECT DISTINCT barangay FROM pwd_records WHERE barangay IS NOT NULL ORDER BY barangay");
$barangays = $barangays_stmt->fetchAll(PDO::FETCH_COLUMN);

$disabilities_stmt = $pdo->query("SELECT DISTINCT disability_type FROM pwd_records WHERE disability_type IS NOT NULL ORDER BY disability_type");
$disabilities = $disabilities_stmt->fetchAll(PDO::FETCH_COLUMN);

$employment_stmt = $pdo->query("SELECT DISTINCT employment_status FROM pwd_records WHERE employment_status IS NOT NULL ORDER BY employment_status");
$employment_statuses = $employment_stmt->fetchAll(PDO::FETCH_COLUMN);

function handleValidateRecord() {
    global $pdo;
    requirePermission($pdo, 'records.validate');
    
    $record_id = $_POST['record_id'] ?? '';
    $validation_notes = $_POST['validation_notes'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'validated', validation_date = NOW(), validated_by = ?
            WHERE id = ? AND status = 'draft'
        ");
        
        $stmt->execute([$_SESSION['admin_user_id'], $record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or already validated'], 400);
        }
        
        logAdminActivity($pdo, 'validate', 'records', 'pwd_record', $record_id, [
            'validation_notes' => $validation_notes
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record validated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to validate record: ' . $e->getMessage()], 500);
    }
}

function handleIssueID() {
    global $pdo;
    requirePermission($pdo, 'records.issue');
    
    $record_id = $_POST['record_id'] ?? '';
    $expiry_years = intval($_POST['expiry_years'] ?? 5);
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $expiry_date = date('Y-m-d', strtotime("+{$expiry_years} years"));
        
        $stmt = $pdo->prepare("
            UPDATE pwd_records 
            SET status = 'issued', issue_date = NOW(), expiry_date = ?, issued_by = ?
            WHERE id = ? AND status = 'validated'
        ");
        
        $stmt->execute([$expiry_date, $_SESSION['admin_user_id'], $record_id]);
        
        if ($stmt->rowCount() === 0) {
            adminJsonResponse(['error' => 'Record not found or not validated'], 400);
        }
        
        logAdminActivity($pdo, 'issue', 'records', 'pwd_record', $record_id, [
            'expiry_date' => $expiry_date
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD ID issued successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to issue ID: ' . $e->getMessage()], 500);
    }
}

function handleUpdateRecord() {
    global $pdo;
    requirePermission($pdo, 'records.edit');
    
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        // Build update query dynamically based on provided fields
        $update_fields = [];
        $params = [];
        
        $allowed_fields = [
            'first_name', 'middle_name', 'last_name', 'suffix',
            'phone_number', 'email_address', 'address_line1', 'address_line2',
            'barangay', 'city_municipality', 'province', 'postal_code',
            'disability_type', 'disability_cause', 'disability_description',
            'medical_condition', 'medication', 'attending_physician',
            'employment_status', 'occupation', 'employer_name', 'monthly_income'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $update_fields[] = "{$field} = ?";
                $params[] = $_POST[$field];
            }
        }
        
        if (empty($update_fields)) {
            adminJsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $update_fields[] = "updated_at = NOW()";
        $params[] = $record_id;
        
        $sql = "UPDATE pwd_records SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logAdminActivity($pdo, 'edit', 'records', 'pwd_record', $record_id);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record updated successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to update record: ' . $e->getMessage()], 500);
    }
}

function handleDeleteRecord() {
    global $pdo;
    requirePermission($pdo, 'records.delete');
    
    $record_id = $_POST['record_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        // Get record details before deletion
        $stmt = $pdo->prepare("SELECT pwd_id_number, first_name, last_name FROM pwd_records WHERE id = ?");
        $stmt->execute([$record_id]);
        $record = $stmt->fetch();
        
        if (!$record) {
            adminJsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM pwd_records WHERE id = ?");
        $stmt->execute([$record_id]);
        
        logAdminActivity($pdo, 'delete', 'records', 'pwd_record', $record_id, [
            'pwd_id' => $record['pwd_id_number'],
            'name' => $record['first_name'] . ' ' . $record['last_name'],
            'reason' => $reason
        ]);
        
        adminJsonResponse([
            'success' => true,
            'message' => 'PWD record deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to delete record: ' . $e->getMessage()], 500);
    }
}

function handleGetRecordDetails() {
    global $pdo;
    
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        adminJsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name,
                   TIMESTAMPDIFF(YEAR, pr.date_of_birth, CURDATE()) as age
            FROM pwd_records pr
            LEFT JOIN admin_users au1 ON pr.created_by = au1.id
            LEFT JOIN admin_users au2 ON pr.validated_by = au2.id
            LEFT JOIN admin_users au3 ON pr.issued_by = au3.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$record_id]);
        $record = $stmt->fetch();
        
        if (!$record) {
            adminJsonResponse(['error' => 'Record not found'], 404);
        }
        
        adminJsonResponse([
            'success' => true,
            'record' => $record
        ]);
        
    } catch (PDOException $e) {
        adminJsonResponse(['error' => 'Failed to get record details: ' . $e->getMessage()], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWD Records - PWD Portal Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-id-card"></i> PWD Records</h1>
                <p>Manage PWD records and ID issuance</p>
            </div>
            <div class="page-actions">
                <?php if (hasPermission($pdo, 'records.create')): ?>
                    <a href="interview.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Record
                    </a>
                <?php endif; ?>
                <button class="btn btn-outline" onclick="exportRecords()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            $stats_query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                    SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued,
                    SUM(CASE WHEN expiry_date < CURDATE() AND status = 'issued' THEN 1 ELSE 0 END) as expired
                FROM pwd_records
            ";
            $stats_result = $pdo->query($stats_query)->fetch();
            ?>
            
            <div class="stat-card">
                <div class="stat-icon records">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['total']); ?></h3>
                    <p>Total Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['draft']); ?></h3>
                    <p>Draft Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon validated">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['validated']); ?></h3>
                    <p>Validated Records</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats_result['issued']); ?></h3>
                    <p>IDs Issued</p>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="validated" <?php echo $status_filter === 'validated' ? 'selected' : ''; ?>>Validated</option>
                        <option value="issued" <?php echo $status_filter === 'issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="revoked" <?php echo $status_filter === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="barangay">Barangay</label>
                    <select name="barangay" id="barangay">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangays as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" 
                                    <?php echo $barangay_filter === $barangay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="disability_type">Disability Type</label>
                    <select name="disability_type" id="disability_type">
                        <option value="">All Disabilities</option>
                        <?php foreach ($disabilities as $disability): ?>
                            <option value="<?php echo htmlspecialchars($disability); ?>" 
                                    <?php echo $disability_filter === $disability ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disability); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="gender">Gender</label>
                    <select name="gender" id="gender">
                        <option value="">All Genders</option>
                        <option value="Male" <?php echo $gender_filter === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $gender_filter === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $gender_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="age_group">Age Group</label>
                    <select name="age_group" id="age_group">
                        <option value="">All Ages</option>
                        <option value="children" <?php echo $age_group_filter === 'children' ? 'selected' : ''; ?>>Children (0-17)</option>
                        <option value="adults" <?php echo $age_group_filter === 'adults' ? 'selected' : ''; ?>>Adults (18-59)</option>
                        <option value="seniors" <?php echo $age_group_filter === 'seniors' ? 'selected' : ''; ?>>Seniors (60+)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="employment_status">Employment</label>
                    <select name="employment_status" id="employment_status">
                        <option value="">All Employment</option>
                        <?php foreach ($employment_statuses as $employment): ?>
                            <option value="<?php echo htmlspecialchars($employment); ?>" 
                                    <?php echo $employment_filter === $employment ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employment); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, PWD ID, email, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="records.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Records Table -->
        <div class="data-card">
            <div class="card-header">
                <h3>PWD Records</h3>
                <span class="record-count"><?php echo number_format($total_records); ?> records</span>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PWD ID</th>
                            <th>Name & Age</th>
                            <th>Contact</th>
                            <th>Disability</th>
                            <th>Location</th>
                            <th>Employment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['pwd_id_number']); ?></strong>
                                    <?php if ($record['issue_date']): ?>
                                        <br><small class="text-muted">
                                            Issued: <?php echo date('M j, Y', strtotime($record['issue_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($record['expiry_date'] && $record['status'] === 'issued'): ?>
                                        <br><small class="text-muted">
                                            Expires: <?php echo date('M j, Y', strtotime($record['expiry_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                    <?php if ($record['middle_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['middle_name']); ?></small>
                                    <?php endif; ?>
                                    <br><small class="text-muted">
                                        <?php echo $record['gender']; ?>, Age <?php echo $record['age']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($record['phone_number']); ?>
                                    <?php if ($record['email_address']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['email_address']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge"><?php echo htmlspecialchars($record['disability_type']); ?></span>
                                    <?php if ($record['disability_cause']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['disability_cause']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['barangay']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($record['city_municipality']); ?></small>
                                </td>
                                <td>
                                    <span class="employment-badge employment-<?php echo strtolower(str_replace(' ', '-', $record['employment_status'])); ?>">
                                        <?php echo htmlspecialchars($record['employment_status']); ?>
                                    </span>
                                    <?php if ($record['occupation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['occupation']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                    <br><small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewRecord(<?php echo $record['id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission($pdo, 'records.edit')): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.validate') && $record['status'] === 'draft'): ?>
                                            <button class="btn btn-sm btn-success" onclick="validateRecord(<?php echo $record['id']; ?>)" title="Validate">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.issue') && $record['status'] === 'validated'): ?>
                                            <button class="btn btn-sm btn-info" onclick="issueID(<?php echo $record['id']; ?>)" title="Issue ID">
                                                <i class="fas fa-id-badge"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($record['status'] === 'issued'): ?>
                                            <button class="btn btn-sm btn-secondary" onclick="printID(<?php echo $record['id']; ?>)" title="Print ID">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['pwd_id_number']); ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    <i class="fas fa-id-card"></i>
                                    <p>No PWD records found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_records); ?> total records)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>" class="btn btn-outline btn-sm">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Record Details Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3 id="recordModalTitle">PWD Record Details</h3>
                <button class="modal-close" onclick="closeModal('recordModal')">&times;</button>
            </div>
            <div class="modal-body" id="recordModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    
    <!-- Edit Record Modal -->
    <div id="editRecordModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3>Edit PWD Record</h3>
                <button class="modal-close" onclick="closeModal('editRecordModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editRecordForm">
                    <input type="hidden" id="editRecordId" name="record_id">
                    
                    <div class="form-section">
                        <h4><i class="fas fa-user"></i> Personal Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editFirstName">First Name</label>
                                <input type="text" id="editFirstName" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="editMiddleName">Middle Name</label>
                                <input type="text" id="editMiddleName" name="middle_name">
                            </div>
                            <div class="form-group">
                                <label for="editLastName">Last Name</label>
                                <input type="text" id="editLastName" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label for="editSuffix">Suffix</label>
                                <input type="text" id="editSuffix" name="suffix">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-phone"></i> Contact Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editPhone">Phone Number</label>
                                <input type="tel" id="editPhone" name="phone_number" required>
                            </div>
                            <div class="form-group">
                                <label for="editEmail">Email Address</label>
                                <input type="email" id="editEmail" name="email_address">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="editAddress1">Address Line 1</label>
                                <input type="text" id="editAddress1" name="address_line1" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="editAddress2">Address Line 2</label>
                                <input type="text" id="editAddress2" name="address_line2">
                            </div>
                            <div class="form-group">
                                <label for="editBarangay">Barangay</label>
                                <input type="text" id="editBarangay" name="barangay" required>
                            </div>
                            <div class="form-group">
                                <label for="editCity">City/Municipality</label>
                                <input type="text" id="editCity" name="city_municipality" required>
                            </div>
                            <div class="form-group">
                                <label for="editProvince">Province</label>
                                <input type="text" id="editProvince" name="province" required>
                            </div>
                            <div class="form-group">
                                <label for="editPostal">Postal Code</label>
                                <input type="text" id="editPostal" name="postal_code">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editDisabilityType">Disability Type</label>
                                <select id="editDisabilityType" name="disability_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Physical Disability">Physical Disability</option>
                                    <option value="Visual Impairment">Visual Impairment</option>
                                    <option value="Hearing Impairment">Hearing Impairment</option>
                                    <option value="Intellectual Disability">Intellectual Disability</option>
                                    <option value="Psychosocial Disability">Psychosocial Disability</option>
                                    <option value="Multiple Disabilities">Multiple Disabilities</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="editDisabilityCause">Disability Cause</label>
                                <select id="editDisabilityCause" name="disability_cause">
                                    <option value="">Select Cause</option>
                                    <option value="Congenital">Congenital</option>
                                    <option value="Accident">Accident</option>
                                    <option value="Illness">Illness</option>
                                    <option value="Injury">Injury</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label for="editDisabilityDescription">Disability Description</label>
                                <textarea id="editDisabilityDescription" name="disability_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editEmploymentStatus">Employment Status</label>
                                <select id="editEmploymentStatus" name="employment_status">
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Employed">Employed</option>
                                    <option value="Self-employed">Self-employed</option>
                                    <option value="Student">Student</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="editOccupation">Occupation</label>
                                <input type="text" id="editOccupation" name="occupation">
                            </div>
                            <div class="form-group">
                                <label for="editEmployer">Employer Name</label>
                                <input type="text" id="editEmployer" name="employer_name">
                            </div>
                            <div class="form-group">
                                <label for="editIncome">Monthly Income</label>
                                <input type="number" id="editIncome" name="monthly_income" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Record
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('editRecordModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Validation Modal -->
    <div id="validationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Validate PWD Record</h3>
                <button class="modal-close" onclick="closeModal('validationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="validationForm">
                    <input type="hidden" id="validateRecordId" name="record_id">
                    <div class="form-group">
                        <label for="validationNotes">Validation Notes (Optional)</label>
                        <textarea id="validationNotes" name="validation_notes" rows="4" 
                                  placeholder="Add any notes about the validation process..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Validate Record
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('validationModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Issue ID Modal -->
    <div id="issueModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Issue PWD ID</h3>
                <button class="modal-close" onclick="closeModal('issueModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="issueForm">
                    <input type="hidden" id="issueRecordId" name="record_id">
                    <div class="form-group">
                        <label for="expiryYears">ID Validity Period</label>
                        <select id="expiryYears" name="expiry_years" required>
                            <option value="5">5 Years</option>
                            <option value="3">3 Years</option>
                            <option value="1">1 Year</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This will mark the record as "Issued" and set the expiry date. The PWD ID can then be printed and given to the applicant.
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-id-badge"></i> Issue PWD ID
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('issueModal')">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script>
        // View record details
        function viewRecord(recordId) {
            fetch('records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_record_details&record_id=${recordId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRecordDetails(data.record);
                    showModal('recordModal');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load record details', 'error');
            });
        }
        
        function displayRecordDetails(record) {
            const modalBody = document.getElementById('recordModalBody');
            const modalTitle = document.getElementById('recordModalTitle');
            
            modalTitle.textContent = `PWD Record: ${record.pwd_id_number}`;
            
            modalBody.innerHTML = `
                <div class="record-details">
                    <div class="details-grid">
                        <div class="detail-section">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Full Name:</span>
                                    <span class="value">${record.first_name} ${record.middle_name || ''} ${record.last_name} ${record.suffix || ''}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Date of Birth:</span>
                                    <span class="value">${formatDate(record.date_of_birth)}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Age:</span>
                                    <span class="value">${record.age} years old</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Gender:</span>
                                    <span class="value">${record.gender}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Civil Status:</span>
                                    <span class="value">${record.civil_status}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-map-marker-alt"></i> Address Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Address:</span>
                                    <span class="value">${record.address_line1}${record.address_line2 ? ', ' + record.address_line2 : ''}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Barangay:</span>
                                    <span class="value">${record.barangay}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">City/Municipality:</span>
                                    <span class="value">${record.city_municipality}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Province:</span>
                                    <span class="value">${record.province}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Postal Code:</span>
                                    <span class="value">${record.postal_code || 'Not specified'}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-wheelchair"></i> Disability Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Type:</span>
                                    <span class="value">${record.disability_type}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Cause:</span>
                                    <span class="value">${record.disability_cause || 'Not specified'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Description:</span>
                                    <span class="value">${record.disability_description || 'Not provided'}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Assistive Device:</span>
                                    <span class="value">${record.assistive_device || 'None specified'}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-phone"></i> Contact Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Phone:</span>
                                    <span class="value">${record.phone_number}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Email:</span>
                                    <span class="value">${record.email_address || 'Not provided'}</span>
                                </div>
                                ${record.emergency_contact_name ? `
                                <div class="detail-row">
                                    <span class="label">Emergency Contact:</span>
                                    <span class="value">${record.emergency_contact_name} (${record.emergency_contact_relationship || 'Relationship not specified'})</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Emergency Phone:</span>
                                    <span class="value">${record.emergency_contact_phone || 'Not provided'}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value">${record.employment_status}</span>
                                </div>
                                ${record.occupation ? `
                                <div class="detail-row">
                                    <span class="label">Occupation:</span>
                                    <span class="value">${record.occupation}</span>
                                </div>
                                ` : ''}
                                ${record.employer_name ? `
                                <div class="detail-row">
                                    <span class="label">Employer:</span>
                                    <span class="value">${record.employer_name}</span>
                                </div>
                                ` : ''}
                                ${record.monthly_income ? `
                                <div class="detail-row">
                                    <span class="label">Monthly Income:</span>
                                    <span class="value">₱${parseFloat(record.monthly_income).toLocaleString()}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-flag"></i> Record Status</h4>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <span class="label">Status:</span>
                                    <span class="value"><span class="status-badge status-${record.status}">${record.status.charAt(0).toUpperCase() + record.status.slice(1)}</span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Created:</span>
                                    <span class="value">${formatDateTime(record.created_at)} by ${record.created_by_name}</span>
                                </div>
                                ${record.validation_date ? `
                                <div class="detail-row">
                                    <span class="label">Validated:</span>
                                    <span class="value">${formatDateTime(record.validation_date)} by ${record.validated_by_name}</span>
                                </div>
                                ` : ''}
                                ${record.issue_date ? `
                                <div class="detail-row">
                                    <span class="label">Issued:</span>
                                    <span class="value">${formatDateTime(record.issue_date)} by ${record.issued_by_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Expires:</span>
                                    <span class="value">${formatDate(record.expiry_date)}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Edit record
        function editRecord(recordId) {
            fetch('records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_record_details&record_id=${recordId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateEditForm(data.record);
                    showModal('editRecordModal');
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load record details', 'error');
            });
        }
        
        function populateEditForm(record) {
            document.getElementById('editRecordId').value = record.id;
            document.getElementById('editFirstName').value = record.first_name || '';
            document.getElementById('editMiddleName').value = record.middle_name || '';
            document.getElementById('editLastName').value = record.last_name || '';
            document.getElementById('editSuffix').value = record.suffix || '';
            document.getElementById('editPhone').value = record.phone_number || '';
            document.getElementById('editEmail').value = record.email_address || '';
            document.getElementById('editAddress1').value = record.address_line1 || '';
            document.getElementById('editAddress2').value = record.address_line2 || '';
            document.getElementById('editBarangay').value = record.barangay || '';
            document.getElementById('editCity').value = record.city_municipality || '';
            document.getElementById('editProvince').value = record.province || '';
            document.getElementById('editPostal').value = record.postal_code || '';
            document.getElementById('editDisabilityType').value = record.disability_type || '';
            document.getElementById('editDisabilityCause').value = record.disability_cause || '';
            document.getElementById('editDisabilityDescription').value = record.disability_description || '';
            document.getElementById('editEmploymentStatus').value = record.employment_status || '';
            document.getElementById('editOccupation').value = record.occupation || '';
            document.getElementById('editEmployer').value = record.employer_name || '';
            document.getElementById('editIncome').value = record.monthly_income || '';
        }
        
        // Validate record
        function validateRecord(recordId) {
            document.getElementById('validateRecordId').value = recordId;
            showModal('validationModal');
        }
        
        // Issue ID
        function issueID(recordId) {
            document.getElementById('issueRecordId').value = recordId;
            showModal('issueModal');
        }
        
        // Print ID
        function printID(recordId) {
            window.open(`print_id.php?id=${recordId}`, '_blank');
        }
        
        // Delete record
        function deleteRecord(recordId, pwdId) {
            const reason = prompt(`Please provide a reason for deleting PWD record ${pwdId}:`);
            if (reason) {
                if (confirm(`Are you sure you want to delete PWD record ${pwdId}? This action cannot be undone.`)) {
                    fetch('records.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_record&record_id=${recordId}&reason=${encodeURIComponent(reason)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification(data.error, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Failed to delete record', 'error');
                    });
                }
            }
        }
        
        // Export records
        function exportRecords() {
            const params = new URLSearchParams(window.location.search);
            params.append('export', '1');
            window.open(`api/export_report.php?type=records&${params.toString()}`, '_blank');
        }
        
        // Form submissions
        document.getElementById('editRecordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_record');
            
            fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('editRecordModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to update record', 'error');
            });
        });
        
        document.getElementById('validationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'validate_record');
            
            fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('validationModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to validate record', 'error');
            });
        });
        
        document.getElementById('issueForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'issue_id');
            
            fetch('records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeModal('issueModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to issue ID', 'error');
            });
        });
    </script>
    
    <style>
        .employment-badge {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .employment-badge.employment-employed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .employment-badge.employment-unemployed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .employment-badge.employment-self-employed {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .employment-badge.employment-student {
            background: #fef3c7;
            color: #92400e;
        }
        
        .employment-badge.employment-retired {
            background: #f3e8ff;
            color: #7c3aed;
        }
        
        .large-modal .modal-content {
            max-width: 900px;
        }
        
        .record-details {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .detail-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .detail-section h4 {
            color: #2c5aa0;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .detail-rows {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row .label {
            font-weight: 500;
            color: #64748b;
            min-width: 120px;
            flex-shrink: 0;
        }
        
        .detail-row .value {
            color: #1e293b;
            text-align: right;
            flex: 1;
            word-break: break-word;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h4 {
            color: #2c5aa0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .detail-row .value {
                text-align: left;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</body>
</html>
