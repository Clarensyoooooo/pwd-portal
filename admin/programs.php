<?php
require_once 'config.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requireAdminLogin($pdo);

?>

<main class="dashboard-container">
    <!-- Header -->
    <div class="content-header">
        <div class="header-top">
            <h1 class="page-title">Program Management</h1>
            <div> 
            <button class="btn btn-primary" onclick="showCreateProgramModal()">
                <i class="fas fa-plus"></i> New Program
            </button>
        </div>
    </div>
    </div>

    <div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon programs">
            <i class="fas fa-list-alt"></i>
        </div>
        <div class="stat-content">
            <h3 id="stat-total-programs">0</h3>
            <p>Total Programs</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon active-programs">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3 id="stat-active-programs">0</h3>
            <p>Active Programs</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon applications">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-content">
            <h3 id="stat-total-applications">0</h3>
            <p>Total Applications</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon pending-applications">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3 id="stat-submitted-applications">0</h3>
            <p>Submitted Applications</p>
        </div>
    </div>
</div>

    <!-- Tabs -->
    <div class="tabs-container">
        <button class="tab-button active" onclick="switchTab('programs')">
            <i class="fas fa-list"></i> Programs
        </button>
        <button class="tab-button" onclick="switchTab('applications')">
            <i class="fas fa-file-alt"></i> Applications
        </button>
    </div>

    <!-- Programs Tab -->
    <div id="programs-tab" class="tab-content active">
        <div class="filters-bar">
            <input type="text" id="programSearch" placeholder="Search programs..." class="search-input">
            <select id="programCategoryFilter" class="filter-select">
                <option value="">All Categories</option>
                <option value="Mobility">Mobility</option>
                <option value="Education">Education</option>
                <option value="Livelihood">Livelihood</option>
                <option value="Healthcare">Healthcare</option>
                <option value="Technology">Technology</option>
                <option value="Community">Community</option>
            </select>
            <select id="programStatusFilter" class="filter-select">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="archived">Archived</option>
            </select>

            <div class="export-buttons" style="margin-left: auto; display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="exportPrograms()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-secondary" onclick="exportProgramsPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="programs-grid" id="programsGrid">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading programs...
            </div>
        </div>
        <div id="programsPagination" class="pagination-controls"></div>
    </div>

    <!-- Applications Tab -->
    <div id="applications-tab" class="tab-content">
       <div class="filters-bar">
            <input type="text" id="applicationSearch" placeholder="Search by email or name..." class="search-input">
            <select id="applicationProgramFilter" class="filter-select">
                <option value="">All Programs</option>
            </select>
            <select id="applicationStatusFilter" class="filter-select">
                <option value="">All Statuses</option>
                <option value="submitted">Submitted</option>
                <option value="under_review">Under Review</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>

            <div class="export-buttons" style="margin-left: auto; display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="exportApplications()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-secondary" onclick="exportApplicationsPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>

        </div>

        <div class="table-responsive">
            <table class="data-table" id="applicationsTable">
                <thead>
                    <tr>
                        <th>Applicant Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Applied Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsTableBody">
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i> Loading applications...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="applicationsPagination" class="pagination-controls"></div>
    </div>
</div>

<!-- Create/Edit Program Modal -->
<div id="programModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="programModalTitle">Create New Program</h2>
            <span class="close" onclick="closeModal('programModal')">&times;</span>
        </div>
        <form id="programForm" onsubmit="handleSaveProgram(event)">
            <div class="form-section">
                <h3>Program Information</h3>
                <div class="form-group">
                    <label for="programTitle">Program Title *</label>
                    <input type="text" id="programTitle" name="title" required placeholder="e.g., Mobility Assistance Program">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="programCategory">Category *</label>
                        <select id="programCategory" name="category" required>
                            <option value="">Select Category</option>
                            <option value="Mobility">Mobility</option>
                            <option value="Education">Education</option>
                            <option value="Livelihood">Livelihood</option>
                            <option value="Healthcare">Healthcare</option>
                            <option value="Technology">Technology</option>
                            <option value="Community">Community</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="programIcon">Icon Class *</label>
                        <input type="text" id="programIcon" name="icon" required placeholder="e.g., fa-wheelchair" value="fa-circle">
                        <small>Enter Font Awesome icon class (without 'fas fa-')</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="programDescription">Description *</label>
                    <textarea id="programDescription" name="description" rows="4" required placeholder="Detailed program description..."></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Requirements</h3>
                <div id="requirementsContainer">
                    <div class="requirement-item">
                        <input type="text" class="requirement-input" placeholder="Enter requirement..." value="">
                        <button type="button" class="btn-remove" onclick="removeRequirement(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addRequirement()">
                    <i class="fas fa-plus"></i> Add Requirement
                </button>
            </div>

            <div class="form-section">
                <h3>Settings</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="programStatus">Status *</label>
                        <select id="programStatus" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="programMaxApplicants">Max Applicants (Optional)</label>
                        <input type="number" id="programMaxApplicants" name="max_applicants" placeholder="Leave empty for unlimited">
                    </div>
                    <div class="form-group">
                        <label for="programDeadline">Application Deadline (Optional)</label>
                        <input type="date" id="programDeadline" name="application_deadline">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('programModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Program</button>
            </div>
        </form>
    </div>
</div>

<!-- Application Review Modal -->
<div id="reviewApplicationModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2>Review Application</h2>
            <span class="close" onclick="closeModal('reviewApplicationModal')">&times;</span>
        </div>
        <div class="review-content" id="reviewContent">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<style>
    .content-header {
        padding: 20px;
        background: white;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
    }

    .page-title {
        color: #2c5aa0;
        font-size: 1.8rem;
        margin: 0;
    }

    .tabs-container {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #e2e8f0;
        background: white;
        border-radius: 8px 8px 0 0;
        overflow: hidden;
    }

    .tab-button {
        flex: 1;
        padding: 15px 20px;
        background: #f8f9fa;
        border: none;
        cursor: pointer;
        font-weight: 500;
        color: #666;
        transition: all 0.3s;
        border-bottom: 3px solid transparent;
    }

    .tab-button:hover {
        background: #f0f4f8;
        color: #2c5aa0;
    }

    .tab-button.active {
        background: white;
        color: #2c5aa0;
        border-bottom-color: #2c5aa0;
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .filters-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .search-input,
    .filter-select {
        padding: 10px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        font-size: 1rem;
        background: white;
    }

    .search-input {
        flex: 1;
        min-width: 200px;
    }

    .filter-select {
        min-width: 150px;
    }

    .programs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .program-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.3s;
        border-left: 4px solid #2c5aa0;
    }

    .program-card:hover {
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        transform: translateY(-3px);
    }

    .program-card-header {
        display: flex;
        align-items: start;
        gap: 12px;
        margin-bottom: 15px;
    }

    .program-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #2c5aa0, #1e3a8a);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .program-card-title {
        flex: 1;
    }

    .program-card h3 {
        margin: 0;
        color: #2c5aa0;
        font-size: 1.1rem;
        word-break: break-word;
    }

    .program-category {
        display: inline-block;
        background: #e0e7ff;
        color: #2c5aa0;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
        margin-top: 5px;
    }

    .program-card-body {
        margin-bottom: 15px;
    }

    .program-description {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .program-meta {
        display: flex;
        gap: 15px;
        font-size: 0.85rem;
        color: #999;
        margin-bottom: 10px;
    }

    .program-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 4px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-archived {
        background: #f3f4f6;
        color: #4b5563;
    }

    .program-card-footer {
        display: flex;
        gap: 10px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .program-card-footer button {
        flex: 1;
        padding: 8px 12px;
        font-size: 0.9rem;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.85rem;
    }

    .btn {
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: #2c5aa0;
        color: white;
    }

    .btn-primary:hover {
        background: #1e3a8a;
    }

    .btn-secondary {
        background: #f0f0f0;
        color: #333;
        border: 1px solid #ddd;
    }

    .btn-secondary:hover {
        background: #e0e0e0;
    }

    .btn-edit {
        background: #3b82f6;
        color: white;
    }

    .btn-delete {
        background: #ef4444;
        color: white;
    }

    .btn-remove {
        background: #ef4444;
        color: white;
        border: none;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
    }

    .requirement-item {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }

    .requirement-input {
        flex: 1;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        font-size: 0.95rem;
    }

    .form-section {
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #e2e8f0;
    }

    .form-section:last-child {
        border-bottom: none;
    }

    .form-section h3 {
        color: #2c5aa0;
        font-size: 1.2rem;
        margin-bottom: 15px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #333;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        font-size: 1rem;
        font-family: inherit;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-group small {
        display: block;
        color: #999;
        font-size: 0.85rem;
        margin-top: 4px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 20px; /* <-- MODIFIED THIS LINE */
    border-top: 1px solid #e2e8f0;
}

    #programForm {
    /* This adds 20px padding to the top, left, and right */
    padding: 20px 20px 0 20px;
}

    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: #f8f9fa;
        border-bottom: 2px solid #e2e8f0;
    }

    .data-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #333;
    }

    .data-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table tbody tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-submitted {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-under_review {
        background: #fef3c7;
        color: #b45309;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-view {
        background: #2c5aa0;
        color: white;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
    }

    .btn-view:hover {
        background: #1e3a8a;
    }

    .review-content {
        padding: 20px;
    }

    .review-section {
        margin-bottom: 20px;
    }

    .review-section h4 {
        color: #2c5aa0;
        margin-bottom: 12px;
        font-size: 1.05rem;
    }

    .review-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }

    .review-item {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 5px;
    }

    .review-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }

    .review-value {
        color: #333;
    }

    .review-textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 5px;
        font-family: inherit;
        min-height: 80px;
        resize: vertical;
    }

    .review-actions {
        display: flex;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        margin-top: 20px;
    }

    .btn-approve {
        flex: 1;
        background: #10b981;
        color: white;
    }

    .btn-approve:hover {
        background: #059669;
    }

    .btn-reject {
        flex: 1;
        background: #ef4444;
        color: white;
    }

    .btn-reject:hover {
        background: #dc2626;
    }

    .loading-spinner {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #999;
        font-size: 1.1rem;
    }

    .text-center {
        text-align: center;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: block;
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease;
    }

    .modal-large {
        max-width: 900px;
    }

    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        color: #2c5aa0;
        font-size: 1.5rem;
    }

    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s;
    }

    .close:hover {
        color: #2c5aa0;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .header-top {
            flex-direction: column;
            align-items: stretch;
        }

        .programs-grid {
            grid-template-columns: 1fr;
        }

        .filters-bar {
            flex-direction: column;
        }

        .search-input,
        .filter-select {
            width: 100%;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .review-grid {
            grid-template-columns: 1fr;
        }

        .program-card-footer {
            flex-direction: column;
        }
    }
    /* Stat Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: white;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 5px solid var(--primary-color);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    color: #2c5aa0;
}
.stat-content p {
    margin: 0;
    color: #666;
}
.stat-icon.programs { background: linear-gradient(135deg, #2c5aa0, #1e3a8a); border-color: #1e3a8a; }
.stat-icon.active-programs { background: linear-gradient(135deg, #10b981, #059669); border-color: #059669; }
.stat-icon.applications { background: linear-gradient(135deg, #3b82f6, #2563eb); border-color: #2563eb; }
.stat-icon.pending-applications { background: linear-gradient(135deg, #f59e0b, #d97706); border-color: #d97706; }

/* Pagination */
.pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    margin-top: 10px;
}
.pagination-info {
    color: #666;
    font-size: 0.9rem;
}
.pagination-buttons {
    display: flex;
    gap: 8px;
}
.pagination-buttons button {
    background: white;
    border: 1px solid #e2e8f0;
    color: #333;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
}
.pagination-buttons button:hover {
    background: #f8f9fa;
    color: #2c5aa0;
}
.pagination-buttons button.active {
    background: #2c5aa0;
    color: white;
    border-color: #2c5aa0;
}
.pagination-buttons button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<script>
    let currentProgramId = null;
    let allPrograms = [];
    let allApplications = [];

    // REPLACE your existing DOMContentLoaded listener with this
document.addEventListener('DOMContentLoaded', () => {
    loadStats(); // Load stats on page load
    loadPrograms(1); // Load first page of programs
    loadApplications(1); // Load first page of applications

    // Event listeners for filters
    document.getElementById('programSearch').addEventListener('input', () => loadPrograms(1));
    document.getElementById('programCategoryFilter').addEventListener('change', () => loadPrograms(1));
    document.getElementById('programStatusFilter').addEventListener('change', () => loadPrograms(1));

    document.getElementById('applicationSearch').addEventListener('input', () => loadApplications(1));
    document.getElementById('applicationProgramFilter').addEventListener('change', () => loadApplications(1));
    document.getElementById('applicationStatusFilter').addEventListener('change', () => loadApplications(1));

    // Close modals on outside click
    window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        };
    });

    // ADD THIS NEW FUNCTION
async function loadStats() {
    try {
        const response = await fetch('api/programs.php?action=get_stats');
        const result = await response.json();

        if (result.success) {
            document.getElementById('stat-total-programs').textContent = result.stats.total_programs;
            document.getElementById('stat-active-programs').textContent = result.stats.active_programs;
            document.getElementById('stat-total-applications').textContent = result.stats.total_applications;
            document.getElementById('stat-submitted-applications').textContent = result.stats.submitted_applications;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

    function switchTab(tab) {
        // Update tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tab + '-tab').classList.add('active');
    }

    // REPLACE your existing loadPrograms() function with this
async function loadPrograms(page = 1) {
    try {
        // Get filter values
        const search = document.getElementById('programSearch').value;
        const category = document.getElementById('programCategoryFilter').value;
        const status = document.getElementById('programStatusFilter').value;

        // Build URL with params
        const params = new URLSearchParams();
        params.append('action', 'get_programs');
        params.append('page', page);
        if (search) params.append('search', search);
        if (category) params.append('category', category);
        if (status) params.append('status', status);

        const response = await fetch(`api/programs.php?${params.toString()}`);
        const result = await response.json();

        if (result.success) {
            allPrograms = result.programs; // Still useful for the edit modal
            displayPrograms(result.programs);
            displayProgramPagination(result.pagination);
        } else {
            document.getElementById('programsGrid').innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">${result.error || 'Error loading programs'}</div>`;
        }
    } catch (error) {
        console.error('Error loading programs:', error);
        document.getElementById('programsGrid').innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">Failed to fetch programs</div>`;
    }
}

    // REPLACE your existing displayPrograms() function with this
function displayPrograms(programs) {
    const grid = document.getElementById('programsGrid');

    if (programs.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">No programs found</div>';
        return;
    }

    grid.innerHTML = programs.map(program => `
        
        <div class="program-card">
            <div class="program-card-header">
                <div class="program-icon">
                    <i class="fas fa-${program.icon}"></i>
                </div>
                <div class="program-card-title">
                    <h3>${program.title}</h3>
                    <span class="program-category">${program.category}</span>
                </div>
            </div>
            <div class="program-card-body">
                <p class="program-description">${program.description}</p>
                <div class="program-meta">
                    <span><i class="fas fa-calendar"></i> Created: ${formatDate(program.created_at)}</span>
                    <span class="program-status status-${program.status}">${program.status.toUpperCase()}</span>
                </div>
            </div>
            <div class="program-card-footer">
                <button class="btn btn-edit btn-sm" onclick="editProgram(${program.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-delete btn-sm" onclick="deleteProgram(${program.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    `).join('');
}

    // ADD THIS NEW FUNCTION
function displayProgramPagination(pagination) {
    const { total_records, total_pages, current_page, per_page } = pagination;
    const container = document.getElementById('programsPagination');

    if (total_pages <= 1) {
        container.innerHTML = ''; // No pagination needed
        return;
    }

    let start = (current_page - 1) * per_page + 1;
    let end = Math.min(start + per_page - 1, total_records);

    container.innerHTML = `
        <div class="pagination-info">
            Showing ${start}-${end} of ${total_records} programs
        </div>
        <div class="pagination-buttons">
            <button onclick="loadPrograms(1)" ${current_page === 1 ? 'disabled' : ''}>
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button onclick="loadPrograms(${current_page - 1})" ${current_page === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="active">Page ${current_page} of ${total_pages}</button>
            <button onclick="loadPrograms(${current_page + 1})" ${current_page === total_pages ? 'disabled' : ''}>
                <i class="fas fa-chevron-right"></i>
            </button>
            <button onclick="loadPrograms(${total_pages})" ${current_page === total_pages ? 'disabled' : ''}>
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    `;
}

    function showCreateProgramModal() {
        currentProgramId = null;
        document.getElementById('programModalTitle').textContent = 'Create New Program';
        document.getElementById('programForm').reset();
        document.getElementById('requirementsContainer').innerHTML = `
            <div class="requirement-item">
                <input type="text" class="requirement-input" placeholder="Enter requirement...">
                <button type="button" class="btn-remove" onclick="removeRequirement(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        document.getElementById('programModal').classList.add('show');
    }

    function addRequirement() {
        const container = document.getElementById('requirementsContainer');
        const div = document.createElement('div');
        div.className = 'requirement-item';
        div.innerHTML = `
            <input type="text" class="requirement-input" placeholder="Enter requirement...">
            <button type="button" class="btn-remove" onclick="removeRequirement(this)">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(div);
    }

    function removeRequirement(btn) {
        btn.parentElement.remove();
    }

    async function editProgram(id) {
        const program = allPrograms.find(p => p.id === id);
        if (!program) return;

        currentProgramId = id;
        document.getElementById('programModalTitle').textContent = 'Edit Program';
        
        document.getElementById('programTitle').value = program.title;
        document.getElementById('programCategory').value = program.category;
        document.getElementById('programIcon').value = program.icon;
        document.getElementById('programDescription').value = program.description;
        document.getElementById('programStatus').value = program.status;
        document.getElementById('programMaxApplicants').value = program.max_applicants || '';
        document.getElementById('programDeadline').value = program.application_deadline || '';

        const requirements = program.requirements.split('|');
        const container = document.getElementById('requirementsContainer');
        container.innerHTML = requirements.map(req => `
            <div class="requirement-item">
                <input type="text" class="requirement-input" placeholder="Enter requirement..." value="${req.trim()}">
                <button type="button" class="btn-remove" onclick="removeRequirement(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');

        document.getElementById('programModal').classList.add('show');
    }

    async function handleSaveProgram(event) {
        event.preventDefault();

        const requirements = Array.from(document.querySelectorAll('.requirement-input'))
            .map(input => input.value.trim())
            .filter(val => val);

        if (requirements.length === 0) {
            showNotification('Please add at least one requirement', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'save_program');
        formData.append('id', currentProgramId || '');
        formData.append('title', document.getElementById('programTitle').value);
        formData.append('category', document.getElementById('programCategory').value);
        formData.append('icon', document.getElementById('programIcon').value);
        formData.append('description', document.getElementById('programDescription').value);
        formData.append('requirements', requirements.join('|'));
        formData.append('status', document.getElementById('programStatus').value);
        formData.append('max_applicants', document.getElementById('programMaxApplicants').value);
        formData.append('application_deadline', document.getElementById('programDeadline').value);

        try {
            const response = await fetch('api/programs.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification(currentProgramId ? 'Program updated successfully' : 'Program created successfully', 'success');
                closeModal('programModal');
                loadPrograms();
            } else {
                showNotification(result.error || 'Error saving program', 'error');
            }
        } catch (error) {
            showNotification('Error saving program', 'error');
        }
    }

    async function deleteProgram(id) {
        if (!confirm('Are you sure you want to delete this program? This will also delete all applications.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_program');
            formData.append('id', id);

            const response = await fetch('api/programs.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification('Program deleted successfully', 'success');
                loadPrograms();
            } else {
                showNotification(result.error || 'Error deleting program', 'error');
            }
        } catch (error) {
            showNotification('Error deleting program', 'error');
        }
    }

    // REPLACE your existing loadApplications() function with this
async function loadApplications(page = 1) {
    try {
        // Get filter values
        const search = document.getElementById('applicationSearch').value;
        const program = document.getElementById('applicationProgramFilter').value;
        const status = document.getElementById('applicationStatusFilter').value;

        // Build URL with params
        const params = new URLSearchParams();
        params.append('action', 'get_applications');
        params.append('page', page);
        if (search) params.append('search', search);
        if (program) params.append('program', program);
        if (status) params.append('status', status);

        const response = await fetch(`api/programs.php?${params.toString()}`);
        const result = await response.json();

        if (result.success) {
            allApplications = result.applications; // Still useful for the review modal
            displayApplications(result.applications);
            displayApplicationPagination(result.pagination); // Add this call

            // Populate program filter (only if it's the first page load)
            if (page === 1 && !program && !search && !status) {
                const programs = [...new Set(allApplications.map(a => a.program_title))];
                const programFilter = document.getElementById('applicationProgramFilter');
                programFilter.innerHTML = '<option value="">All Programs</option>' +
                    programs.map(p => `<option value="${p}">${p}</option>`).join('');
            }
        } else {
             document.getElementById('applicationsTableBody').innerHTML = `<tr><td colspan="6" class="text-center">${result.error || 'Error loading applications'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading applications:', error);
        document.getElementById('applicationsTableBody').innerHTML = `<tr><td colspan="6" class="text-center">Failed to fetch applications</td></tr>`;
    }
}

    function displayApplications(applications) {
        const tbody = document.getElementById('applicationsTableBody');

        if (applications.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No applications found</td></tr>';
            return;
        }

        tbody.innerHTML = applications.map(app => `
            <tr>
                <td>${app.first_name} ${app.last_name}</td>
                <td>${app.email}</td>
                <td>${app.program_title}</td>
                <td>
                    <span class="status-badge status-${app.status}">
                        ${app.status.replace('_', ' ').toUpperCase()}
                    </span>
                </td>
                <td>${formatDate(app.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-view" onclick="viewApplication(${app.id})">
                            <i class="fas fa-eye"></i> Review
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // ADD THIS NEW FUNCTION
function displayApplicationPagination(pagination) {
    const { total_records, total_pages, current_page, per_page } = pagination;
    const container = document.getElementById('applicationsPagination');

    if (total_pages <= 1) {
        container.innerHTML = ''; // No pagination needed
        return;
    }

    let start = (current_page - 1) * per_page + 1;
    let end = Math.min(start + per_page - 1, total_records);

    container.innerHTML = `
        <div class="pagination-info">
            Showing ${start}-${end} of ${total_records} applications
        </div>
        <div class="pagination-buttons">
            <button onclick="loadApplications(1)" ${current_page === 1 ? 'disabled' : ''}>
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button onclick="loadApplications(${current_page - 1})" ${current_page === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="active">Page ${current_page} of ${total_pages}</button>
            <button onclick="loadApplications(${current_page + 1})" ${current_page === total_pages ? 'disabled' : ''}>
                <i class="fas fa-chevron-right"></i>
            </button>
            <button onclick="loadApplications(${total_pages})" ${current_page === total_pages ? 'disabled' : ''}>
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    `;
}
   

    async function viewApplication(id) {
        try {
            const response = await fetch(`api/programs.php?action=get_application&id=${id}`);
            const result = await response.json();

            if (result.success) {
                const app = result.application;
                const reviewContent = document.getElementById('reviewContent');

                reviewContent.innerHTML = `
                    <div class="review-section">
                        <h4>Applicant Information</h4>
                        <div class="review-grid">
                            <div class="review-item">
                                <div class="review-label">Name</div>
                                <div class="review-value">${app.first_name} ${app.last_name}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Email</div>
                                <div class="review-value">${app.email}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Phone</div>
                                <div class="review-value">${app.phone}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Date of Birth</div>
                                <div class="review-value">${formatDate(app.date_of_birth)}</div>
                            </div>
                            <div class="review-item" style="grid-column: 1/-1;">
                                <div class="review-label">Address</div>
                                <div class="review-value">${app.address}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Disability Type</div>
                                <div class="review-value">${app.disability_type || 'Not specified'}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Program</div>
                                <div class="review-value">${app.program_title}</div>
                            </div>
                            <div class="review-item">
                                <div class="review-label">Status</div>
                                <div class="review-value">
                                    <span class="status-badge status-${app.status}">
                                        ${app.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${app.additional_info ? `
                        <div class="review-section">
                            <h4>Additional Information</h4>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 5px; color: #333;">
                                ${app.additional_info}
                            </div>
                        </div>
                    ` : ''}

                    ${app.status !== 'approved' && app.status !== 'rejected' ? `
                        <div class="review-section">
                            <h4>Review Decision</h4>
                            <label style="display: block; margin-bottom: 10px; font-weight: 500;">
                                Review Notes (Optional)
                            </label>
                            <textarea id="reviewNotes" class="review-textarea" placeholder="Add your review notes...">${app.review_notes || ''}</textarea>
                        </div>

                        <div class="review-actions">
                            <button class="btn btn-approve" onclick="updateApplicationStatus(${app.id}, 'approved')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-reject" onclick="updateApplicationStatus(${app.id}, 'rejected')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    ` : ''}
                `;

                document.getElementById('reviewApplicationModal').classList.add('show');
            }
        } catch (error) {
            showNotification('Error loading application', 'error');
        }
    }

    async function updateApplicationStatus(id, status) {
        const reviewNotes = document.getElementById('reviewNotes')?.value || '';

        try {
            const formData = new FormData();
            formData.append('action', 'update_application_status');
            formData.append('id', id);
            formData.append('status', status);
            formData.append('review_notes', reviewNotes);

            const response = await fetch('api/programs.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showNotification(`Application ${status} successfully`, 'success');
                closeModal('reviewApplicationModal');
                loadApplications();
            } else {
                showNotification(result.error || 'Error updating application', 'error');
            }
        } catch (error) {
            showNotification('Error updating application', 'error');
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 10001;
            animation: slideIn 0.3s ease;
        `;
        
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#2c5aa0'
        };
        notification.style.backgroundColor = colors[type] || colors.info;
        notification.textContent = message;

        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }


    // ADD THIS NEW FUNCTION
function exportPrograms() {
    // 1. Get the current filter values
    const search = document.getElementById('programSearch').value;
    const category = document.getElementById('programCategoryFilter').value;
    const status = document.getElementById('programStatusFilter').value;

    // 2. Build the URL for the API
    const params = new URLSearchParams();
    params.append('action', 'export_programs'); // This is our new API action

    if (search) params.append('search', search);
    if (category) params.append('category', category);
    if (status) params.append('status', status);

    // 3. Trigger the download by pointing the browser to the API URL
    // (This assumes your API file is at 'api/programs.php')
    window.location.href = `api/programs.php?${params.toString()}`;
}

// This is your existing function for CSV
    function exportPrograms() {
        // 1. Get the current filter values
        const search = document.getElementById('programSearch').value;
        const category = document.getElementById('programCategoryFilter').value;
        const status = document.getElementById('programStatusFilter').value;

        // 2. Build the URL for the API
        const params = new URLSearchParams();
        params.append('action', 'export_programs'); // This is our new API action

        if (search) params.append('search', search);
        if (category) params.append('category', category);
        if (status) params.append('status', status);

        // 3. Trigger the download by pointing the browser to the API URL
        // (This assumes your API file is at 'api/programs.php')
        window.location.href = `api/programs.php?${params.toString()}`;
    }

    // ADD THIS NEW FUNCTION FOR PDF
    function exportProgramsPDF() {
        // 1. Get the current filter values
        const search = document.getElementById('programSearch').value;
        const category = document.getElementById('programCategoryFilter').value;
        const status = document.getElementById('programStatusFilter').value;

        // 2. Build the URL for the API
        const params = new URLSearchParams();
        params.append('action', 'export_programs_pdf'); // This is our new API action

        if (search) params.append('search', search);
        if (category) params.append('category', category);
        if (status) params.append('status', status);

        // 3. Trigger the PDF in a new tab
        window.open(`api/programs.php?${params.toString()}`, '_blank');
    }

   

// ADD THIS NEW FUNCTION
function exportApplications() {
    // 1. Get the current filter values
    const search = document.getElementById('applicationSearch').value;
    const program = document.getElementById('applicationProgramFilter').value;
    const status = document.getElementById('applicationStatusFilter').value;

    // 2. Build the URL for the API
    const params = new URLSearchParams();
    params.append('action', 'export_applications'); // This is our new API action

    if (search) params.append('search', search);
    if (program) params.append('program', program);
    if (status) params.append('status', status);

    // 3. Trigger the download
    window.location.href = `api/programs.php?${params.toString()}`;
}


// ADD THIS NEW FUNCTION
function exportApplicationsPDF() {
    // 1. Get the current filter values
    const search = document.getElementById('applicationSearch').value;
    const program = document.getElementById('applicationProgramFilter').value;
    const status = document.getElementById('applicationStatusFilter').value;

    // 2. Build the URL for the API
    const params = new URLSearchParams();
    params.append('action', 'export_applications_pdf'); // <-- This is the new action

    if (search) params.append('search', search);
    if (program) params.append('program', program);
    if (status) params.append('status', status);

    // 3. Trigger the PDF in a new tab
    // We use window.open() so it doesn't navigate away from the admin page
    window.open(`api/programs.php?${params.toString()}`, '_blank');
}


</script>

<?php require_once 'includes/footer.php'; ?>
