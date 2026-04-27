<?php
require_once '../config/config.php';
requireRole(['admin', 'staff']);

require_once '../includes/auth.php';
require_once '../includes/concern.php';
require_once '../includes/college.php';

$auth = new Auth();
$concern = new Concern();
$college = new College();
$db = getDB();
$user = $auth->getCurrentUser();

$error = '';
$success = '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_update_status']) && isset($_POST['concern_ids']) && !empty($_POST['concern_ids'])) {
        $concern_ids = $_POST['concern_ids'];
        $new_status = $_POST['bulk_status'] ?? '';
        $notes = $_POST['bulk_notes'] ?? null;
        
        if (empty($new_status)) {
            $error = "Please select a status to update";
        } else {
            $result = $concern->bulkUpdateStatus($concern_ids, $new_status, $_SESSION['user_id'], $notes);
            if ($result['success']) {
                $success = "Successfully updated {$result['success_count']} of {$result['total']} concerns";
                if (!empty($result['errors'])) {
                    $error = "Some updates failed: " . implode(", ", $result['errors']);
                }
            } else {
                $error = "Failed to update concerns";
            }
        }
    }
    
    if (isset($_POST['manual_route']) && isset($_POST['route_concern_id'])) {
        $result = $concern->manualRoute(
            $_POST['route_concern_id'],
            $_SESSION['user_id'],
            $_POST['route_college_id'] ?? null,
            $_POST['route_department_id'] ?? null
        );
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['reclassify']) && isset($_POST['reclassify_concern_id'])) {
        $result = $concern->reclassifyConcern($_POST['reclassify_concern_id']);
        if ($result['success']) {
            $success = "Concern reclassified successfully";
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['delete_concern']) && isset($_POST['delete_concern_id']) && getUserRole() === 'admin') {
        $result = $concern->deleteConcern($_POST['delete_concern_id']);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$college_filter = $_GET['college_id'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

$filters = [];
if ($status_filter) $filters['status'] = $status_filter;
if ($college_filter) $filters['college_id'] = $college_filter;

// If admin has department_id, filter by department (most specific)
// Otherwise, if admin has college_id, filter by college
if ($user['department_id']) {
    // Admin is assigned to a specific department - only show concerns routed to that department
    $filters['department_id'] = $user['department_id'];
} elseif ($user['college_id']) {
    // Admin is assigned to a college - show concerns from that college
    $filters['college_id'] = $user['college_id'];
}

// Get concerns with search
$concerns = $concern->getConcerns($filters);

// Apply category filter
if ($category_filter) {
    $concerns = array_filter($concerns, function($c) use ($category_filter) {
        return $c['predicted_category'] === $category_filter;
    });
}

// Apply search filter
if ($search) {
    $concerns = array_filter($concerns, function($c) use ($search) {
        return stripos($c['concern_text'], $search) !== false || 
               stripos($c['full_name'], $search) !== false ||
               stripos($c['email'], $search) !== false;
    });
}

// Get routing information for each concern to show department
foreach ($concerns as &$c) {
    $routing = $concern->getRouting($c['concern_id']);
    $c['routing'] = $routing;
    if (!empty($routing) && isset($routing[0]['department_name'])) {
        $c['department_name'] = $routing[0]['department_name'];
    }
}
unset($c);

// Get colleges - if admin is college-specific, only show their college
$colleges = [];
if ($user['college_id']) {
    $college_data = $college->getCollege($user['college_id']);
    if ($college_data) {
        $colleges = [$college_data];
    }
} else {
    $colleges = $college->getColleges();
}
$departments = $college->getDepartments();

// Get statistics (respect the same filters as the concern list)
$stats = $concern->getStatistics($filters);

// Get unique categories for filter (exclude old categories: General, Facilities, IT, Academic, Financial, Guidance, Library)
$stmt = $db->query("SELECT DISTINCT predicted_category FROM classifications WHERE predicted_category IS NOT NULL");
$all_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Only show categories that the classifier can actually produce
$valid_categories = [
    'MIS Office',
    'IMCO Office',
    'Registrar Office',
    'Internet Laboratory',
    'Cashier Office',
    'Maintenance Office',
    'ISSC Office',
    'Faculty Office',
];
$categories = array_values(array_filter($all_categories, function($cat) use ($valid_categories) {
    return in_array($cat, $valid_categories, true);
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Concerns - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .stat-box h3 {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .filter-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .table-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .bulk-actions {
            background: var(--bg-color);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            display: none;
        }
        .bulk-actions.active {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .concern-row {
            cursor: pointer;
        }
        .concern-row:hover {
            background: var(--bg-color);
        }
    </style>
</head>
<body class="dashboard-page">
    <?php include '../includes/header.php'; ?>

    <div class="layout-admin">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <div class="logo"></div>
                <div class="text">Admin Panel</div>
            </div>
            <nav class="admin-nav">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="concerns.php">📋 Manage Concerns</a>
                <a href="routing.php">📤 Routing</a>
                <a href="reports.php">📊 Reports & Analytics</a>
                <?php if (getUserRole() === 'admin'): ?>
                    <a href="users.php">👥 Users</a>
                    <a href="register_user.php">➕ Register User</a>
                <?php endif; ?>
                <a href="../notifications.php">🔔 Notifications (<?php echo $unread_count; ?>)</a>
            </nav>
            <div style="margin-top: 2rem;">
                <a href="../logout.php" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">Logout</a>
                <div style="margin-top: 0.75rem; color: rgba(4,120,87,0.9); font-size: 0.92rem; word-break: break-word;">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </div>
            </div>
        </aside>

        <main class="admin-content">
            <div class="container">
        <div class="page-header">
            <h1><?php 
                if ($user['department_name']) {
                    echo htmlspecialchars($user['department_name']) . ' - Concern Management';
                } elseif ($user['college_name']) {
                    echo htmlspecialchars($user['college_name']) . ' - Concern Management';
                } else {
                    echo 'Concern Management Dashboard';
                }
            ?></h1>
            <p>Manage and track student concerns<?php echo $user['department_name'] ? ' for ' . htmlspecialchars($user['department_name']) : ''; ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Concerns</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-box">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['by_status']['Pending'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>In Progress</h3>
                <div class="number"><?php echo $stats['by_status']['In Progress'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Resolved</h3>
                <div class="number"><?php echo $stats['by_status']['Resolved'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <h3>Today</h3>
                <div class="number"><?php echo $stats['today']; ?></div>
            </div>
            <div class="stat-box">
                <h3>This Week</h3>
                <div class="number"><?php echo $stats['this_week']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search by concern, student name, or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="concerns.php" class="btn btn-secondary">Clear All</a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <span id="selectedCount">0 selected</span>
            <select name="bulk_status" id="bulkStatus" class="form-group" style="margin: 0;">
                <option value="">Select Action</option>
                <option value="Pending">Mark as Pending</option>
                <option value="In Progress">Mark as In Progress</option>
                <option value="Resolved">Mark as Resolved</option>
            </select>
            <textarea name="bulk_notes" id="bulkNotes" placeholder="Optional notes..." rows="2" style="flex: 1; min-width: 200px;"></textarea>
            <button type="button" class="btn btn-primary" onclick="applyBulkAction()">Apply</button>
            <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
        </div>

        <!-- Concerns Table -->
        <div class="table-container">
            <form method="POST" id="bulkForm" action="">
                <input type="hidden" name="bulk_update_status" value="1">
                <table class="concerns-admin-table">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                            </th>
                            <th>ID</th>
                            <th>Student</th>
                            <th>College</th>
                            <th>Department</th>
                            <th>Concern Preview</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($concerns)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                    No concerns found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($concerns as $c): ?>
                                <tr class="concern-row">
                                    <td>
                                        <input type="checkbox" name="concern_ids[]" value="<?php echo $c['concern_id']; ?>" class="concern-checkbox" onchange="updateBulkActions()">
                                    </td>
                                    <td>#<?php echo $c['concern_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($c['full_name']); ?></strong><br>
                                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($c['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['college_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (isset($c['department_name'])): ?>
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars($c['department_name']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">Not routed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars(substr($c['concern_text'], 0, 100)); ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($c['predicted_category']): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($c['predicted_category']); ?></span>
                                            <?php if ($c['confidence_score']): ?>
                                                <br><small style="color: var(--text-secondary);"><?php echo number_format($c['confidence_score'] * 100, 1); ?>%</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $c['status'])); ?>">
                                            <?php echo $c['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($c['created_at']); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="../concern.php?id=<?php echo $c['concern_id']; ?>" class="btn btn-sm btn-primary" title="View Details">👁️</a>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="openRouteModal(<?php echo $c['concern_id']; ?>)" title="Route">📤</button>
                                            <?php if (getUserRole() === 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-secondary" onclick="reclassifyConcern(<?php echo $c['concern_id']; ?>)" title="Reclassify">🔄</button>
                                                <button type="button" class="btn btn-sm" style="background: var(--error-color); color: white;" onclick="deleteConcern(<?php echo $c['concern_id']; ?>)" title="Delete">🗑️</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <!-- Route Modal -->
    <div class="modal" id="routeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Route Concern</h2>
                <button type="button" class="close-modal" onclick="closeRouteModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="route_concern_id" id="route_concern_id">
                <div class="form-group">
                    <label for="route_college_id">Route to College (Optional)</label>
                    <select id="route_college_id" name="route_college_id">
                        <option value="">None</option>
                        <?php foreach ($colleges as $c): ?>
                            <option value="<?php echo $c['college_id']; ?>"><?php echo htmlspecialchars($c['college_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="route_department_id">Route to Department (Optional)</label>
                    <select id="route_department_id" name="route_department_id">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="manual_route" class="btn btn-primary">Route Concern</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRouteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.concern-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateBulkActions();
        }

        function updateBulkActions() {
            const checked = document.querySelectorAll('.concern-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checked.length > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = checked.length + ' selected';
            } else {
                bulkActions.classList.remove('active');
            }
        }

        function applyBulkAction() {
            const status = document.getElementById('bulkStatus').value;
            const notes = document.getElementById('bulkNotes').value;
            const checked = document.querySelectorAll('.concern-checkbox:checked');
            
            if (!status) {
                alert('Please select an action');
                return;
            }
            
            if (checked.length === 0) {
                alert('Please select at least one concern');
                return;
            }
            
            if (confirm(`Update ${checked.length} concern(s) to "${status}"?`)) {
                // Ensure all checked boxes are included in form
                const form = document.getElementById('bulkForm');
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'bulk_status';
                statusInput.value = status;
                form.appendChild(statusInput);
                
                if (notes) {
                    const notesInput = document.createElement('input');
                    notesInput.type = 'hidden';
                    notesInput.name = 'bulk_notes';
                    notesInput.value = notes;
                    form.appendChild(notesInput);
                }
                
                form.submit();
            }
        }

        function clearSelection() {
            document.querySelectorAll('.concern-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }

        function openRouteModal(concernId) {
            document.getElementById('route_concern_id').value = concernId;
            document.getElementById('routeModal').classList.add('active');
        }

        function closeRouteModal() {
            document.getElementById('routeModal').classList.remove('active');
        }

        function reclassifyConcern(concernId) {
            if (confirm('Reclassify this concern using AI? This will update the category and routing.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="reclassify_concern_id" value="${concernId}">
                    <input type="hidden" name="reclassify" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteConcern(concernId) {
            if (confirm('Are you sure you want to delete this concern? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_concern_id" value="${concernId}">
                    <input type="hidden" name="delete_concern" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal on outside click
        document.getElementById('routeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRouteModal();
            }
        });
    </script>
</body>
</html>
