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
        default:
            adminJsonResponse(['error' => 'Invalid action'], 400);
    }
}

// Get records with filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR pwd_id_number LIKE ? OR email_address LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pwd_records {$where_clause}");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get records
$stmt = $pdo->prepare("
    SELECT pr.*, au1.full_name as created_by_name, au2.full_name as validated_by_name, au3.full_name as issued_by_name
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
                    SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued
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
        
        <!-- Filters -->
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
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Name, PWD ID, or email..." 
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
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Disability</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Created</th>
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
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                    <?php if ($record['middle_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['middle_name']); ?></small>
                                    <?php endif; ?>
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
                                    <?php echo htmlspecialchars($record['city_municipality'] . ', ' . $record['province']); ?>
                                    <?php if ($record['latitude'] && $record['longitude']): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo number_format($record['latitude'], 4) . ', ' . number_format($record['longitude'], 4); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                    <?php if ($record['expiry_date'] && $record['status'] === 'issued'): ?>
                                        <br><small class="text-muted">
                                            Expires: <?php echo date('M j, Y', strtotime($record['expiry_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                    <br><small class="text-muted">by <?php echo htmlspecialchars($record['created_by_name']); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewRecord(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission($pdo, 'records.edit')): ?>
                                            <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.validate') && $record['status'] === 'draft'): ?>
                                            <button class="btn btn-sm btn-success" onclick="validateRecord(<?php echo $record['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.issue') && $record['status'] === 'validated'): ?>
                                            <button class="btn btn-sm btn-info" onclick="issueID(<?php echo $record['id']; ?>)">
                                                <i class="fas fa-id-badge"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($record['latitude'] && $record['longitude']): ?>
                                            <button class="btn btn-sm btn-secondary" onclick="showOnMap(<?php echo $record['latitude']; ?>, <?php echo $record['longitude']; ?>)">
                                                <i class="fas fa-map"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission($pdo, 'records.delete')): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['pwd_id_number']); ?>')">
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
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_records); ?> total records)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline btn-sm">
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
    
    <!-- Map Modal -->
    <div id="mapModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3>Record Location</h3>
                <button class="modal-close" onclick="closeModal('mapModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="recordLocationMap" style="height: 400px;"></div>
            </div>
        </div>
    </div>
    
    <script src="assets/admin.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <script>
        let recordMap;
        
        // View record details
        function viewRecord(recordId) {
            fetch(`api/records.php?action=get_record&id=${recordId}`)
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
                                    <span class="label">Gender:</span>
                                    <span class="value">${record.gender}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Civil Status:</span>
                                    <span class="value">${record.civil_status}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Place of Birth:</span>
                                    <span class="value">${record.place_of_birth || 'Not specified'}</span>
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
                                ${record.latitude && record.longitude ? `
                                <div class="detail-row">
                                    <span class="label">Coordinates:</span>
                                    <span class="value">${parseFloat(record.latitude).toFixed(6)}, ${parseFloat(record.longitude).toFixed(6)}</span>
                                </div>
                                ` : ''}
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
            // Implementation for editing record
            showNotification('Edit functionality will be implemented', 'info');
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
        
        // Show on map
        function showOnMap(lat, lng) {
            showModal('mapModal');
            
            setTimeout(() => {
                if (recordMap) {
                    recordMap.remove();
                }
                
                recordMap = L.map('recordLocationMap').setView([lat, lng], 15);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(recordMap);
                
                L.marker([lat, lng]).addTo(recordMap)
                    .bindPopup(`Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`)
                    .openPopup();
            }, 300);
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
            const status = document.getElementById('status').value;
            const search = document.getElementById('search').value;
            
            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (search) params.append('search', search);
            params.append('export', '1');
            
            window.open(`api/records.php?${params.toString()}`, '_blank');
        }
        
        // Form submissions
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
            
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</body>
</html>
