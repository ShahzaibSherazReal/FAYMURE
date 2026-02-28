<?php
require_once 'check-auth.php';

$conn = getDBConnection();

$success = false;
$error = '';
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success = true;
}

$allowed_tables = ['custom_designs', 'product_customizations', 'quote_requests'];

// Handle form updates and clear/delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status']);
        $table = $_POST['table'] ?? '';
        if (in_array($table, $allowed_tables)) {
            $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Failed to update status.";
            }
            $stmt->close();
        }
        // Redirect back to same tab (and filters) so list stays in context
        $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
        $query = 'tab=' . rawurlencode($table);
        if (!empty($_POST['status_filter'])) {
            $query .= '&status=' . rawurlencode($_POST['status_filter']);
        }
        if (!empty($_POST['search_filter'])) {
            $query .= '&search=' . rawurlencode($_POST['search_filter']);
        }
        if ($success) {
            $query .= '&updated=1';
        }
        header('Location: ' . $base . '/admin/form-submissions?' . $query);
        exit;
    } elseif (isset($_POST['clear_one']) && isset($_POST['table']) && isset($_POST['id'])) {
        $table = $_POST['table'];
        $id = intval($_POST['id']);
        if (in_array($table, $allowed_tables) && $id > 0) {
            $conn->query("DELETE FROM $table WHERE id = $id");
            $success = true;
        }
    } elseif (isset($_POST['clear_selected']) && isset($_POST['table']) && !empty($_POST['ids']) && is_array($_POST['ids'])) {
        $table = $_POST['table'];
        if (in_array($table, $allowed_tables)) {
            $ids = array_map('intval', $_POST['ids']);
            $ids = array_filter($ids, function ($i) { return $i > 0; });
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $stmt->close();
                $success = true;
            }
        }
    } elseif (isset($_POST['clear_all']) && isset($_POST['table'])) {
        $table = $_POST['table'];
        if (in_array($table, $allowed_tables)) {
            $conn->query("DELETE FROM $table");
            $success = true;
        }
    }
}

// Get active tab (only 3 sections)
$active_tab = $_GET['tab'] ?? 'custom_designs';
if (!in_array($active_tab, $allowed_tables)) {
    $active_tab = 'custom_designs';
}
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

$custom_designs = [];
$product_customizations = [];
$quote_requests = [];

// 1. Design Your Own Product (custom_designs)
$custom_designs_query = "SELECT cd.*, u.username FROM custom_designs cd LEFT JOIN users u ON cd.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $custom_designs_query .= " AND cd.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $custom_designs_query .= " AND (cd.customer_name LIKE '%$search_escaped%' OR cd.customer_email LIKE '%$search_escaped%' OR cd.product_type LIKE '%$search_escaped%')";
}
$custom_designs_query .= " ORDER BY cd.created_at DESC";
$res = $conn->query($custom_designs_query);
$custom_designs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 2. Wholesale / Quote Requests (quote_requests)
$quote_requests_query = "SELECT qr.*, p.name as product_name, u.username FROM quote_requests qr LEFT JOIN products p ON qr.product_id = p.id LEFT JOIN users u ON qr.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $quote_requests_query .= " AND qr.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $quote_requests_query .= " AND (qr.customer_name LIKE '%$search_escaped%' OR qr.customer_email LIKE '%$search_escaped%' OR p.name LIKE '%$search_escaped%')";
}
$quote_requests_query .= " ORDER BY qr.created_at DESC";
$res = $conn->query($quote_requests_query);
$quote_requests = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 3. Customizations (product_customizations)
$product_customizations_query = "SELECT pc.*, p.name as product_name, u.username FROM product_customizations pc LEFT JOIN products p ON pc.product_id = p.id LEFT JOIN users u ON pc.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $product_customizations_query .= " AND pc.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $product_customizations_query .= " AND (pc.customer_name LIKE '%$search_escaped%' OR pc.customer_email LIKE '%$search_escaped%' OR p.name LIKE '%$search_escaped%')";
}
$product_customizations_query .= " ORDER BY pc.created_at DESC";
$res = $conn->query($product_customizations_query);
$product_customizations = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';

// Status options per table (for dropdown)
$status_options = [
    'custom_designs' => ['pending', 'processing', 'completed', 'cancelled'],
    'quote_requests' => ['pending', 'quoted', 'accepted', 'rejected', 'cancelled'],
    'product_customizations' => ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'],
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Submissions - Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-section input, .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-section button {
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-section button:hover {
            background: var(--dark-color);
        }
        .submission-row {
            cursor: pointer;
        }
        .submission-row:hover {
            background: #f5f5f5;
        }
        .images-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .images-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .actions .btn-delete {
            background: #c00;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .actions .btn-delete:hover {
            background: #a00;
        }
        /* Form submissions tabs as buttons */
        .admin-tabs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .admin-tabs .tab-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f0f0f0;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 6px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .admin-tabs .tab-link:hover {
            background: #e5e5e5;
            border-color: var(--primary-color, #c9a962);
            color: var(--primary-color, #c9a962);
        }
        .admin-tabs .tab-link.active {
            background: var(--primary-color, #c9a962);
            border-color: var(--primary-color, #c9a962);
            color: #fff;
        }
        .admin-tabs .tab-link.active:hover {
            background: var(--dark-color, #1a1a1a);
            border-color: var(--dark-color, #1a1a1a);
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1>Form Submissions</h1>
            </div>
            
            <?php if ($success): ?>
                <div class="success-notification">
                    <i class="fas fa-check-circle"></i> Changes saved successfully!
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-notification">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs: 3 sections -->
            <div class="admin-tabs">
                <a href="?tab=custom_designs<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'custom_designs' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> Design Your Own Product
                </a>
                <a href="?tab=quote_requests<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'quote_requests' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i> Wholesale
                </a>
                <a href="?tab=product_customizations<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'product_customizations' ? 'active' : ''; ?>">
                    <i class="fas fa-paint-brush"></i> Customizations
                </a>
            </div>
            
            <!-- Filters and PDF export -->
            <div class="filter-section">
                <a href="<?php echo htmlspecialchars($base); ?>/admin/form-submissions-pdf?table=<?php echo urlencode($active_tab); ?>" class="btn-primary" style="margin-right: 15px;"><i class="fas fa-file-pdf"></i> Download all as PDF</a>
                <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_filter); ?>" style="min-width: 200px;">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = [];
                        if ($active_tab == 'custom_designs') {
                            $statuses = ['pending', 'processing', 'completed', 'cancelled'];
                        } elseif ($active_tab == 'product_customizations') {
                            $statuses = ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'];
                        } else {
                            $statuses = ['pending', 'quoted', 'accepted', 'rejected', 'cancelled'];
                        }
                        foreach ($statuses as $status) {
                            echo '<option value="' . htmlspecialchars($status) . '"' . ($status_filter == $status ? ' selected' : '') . '>' . ucfirst($status) . '</option>';
                        }
                        ?>
                    </select>
                    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                    <a href="?tab=<?php echo htmlspecialchars($active_tab); ?>" class="btn-secondary" style="padding: 8px 20px; text-decoration: none; display: inline-block;"><i class="fas fa-times"></i> Clear filters</a>
                </form>
            </div>
            
            <!-- Bulk clear actions -->
            <div class="filter-section" style="margin-bottom: 15px;">
                <form method="POST" id="bulkClearForm" onsubmit="var n = document.querySelectorAll('.row-check:checked').length; if (!n) { alert('Select at least one submission.'); return false; } return confirm('Remove ' + n + ' selected submission(s)? This cannot be undone.');" style="display: inline;">
                    <input type="hidden" name="clear_selected" value="1">
                    <input type="hidden" name="table" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <div id="bulkIdsContainer"></div>
                    <button type="submit" class="btn-secondary" id="btnClearSelected" style="display: none;"><i class="fas fa-trash"></i> Clear selected</button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove ALL submissions in this section? This cannot be undone.');">
                    <input type="hidden" name="clear_all" value="1">
                    <input type="hidden" name="table" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <button type="submit" class="btn-secondary" style="color: #c00;"><i class="fas fa-trash-alt"></i> Clear all</button>
                </form>
            </div>
            
            <!-- Design Your Own Product Tab -->
            <?php if ($active_tab == 'custom_designs'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllDesign" title="Select all"></th>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Product Type</th>
                                <th>Quantity</th>
                                <th>Timeline</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($custom_designs)): ?>
                                <tr>
                                    <td colspan="12">No design-your-own submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($custom_designs as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('custom_designs', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td onclick="event.stopPropagation();"><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int)$item['id']; ?>" form="bulkClearForm"></td>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['product_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($item['timeline'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['budget'] ?? 'N/A'); ?></td>
                                        <td onclick="event.stopPropagation();">
                                            <form method="POST" class="status-dropdown-form" style="display:inline;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <input type="hidden" name="table" value="custom_designs">
                                                <?php if ($status_filter !== ''): ?><input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                                                <?php if ($search_filter !== ''): ?><input type="hidden" name="search_filter" value="<?php echo htmlspecialchars($search_filter); ?>"><?php endif; ?>
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <?php foreach ($status_options['custom_designs'] as $opt): ?>
                                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($item['status'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button type="button" onclick="viewSubmission('custom_designs', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view" title="View"><i class="fas fa-eye"></i></button>
                                            <a href="<?php echo $base; ?>/admin/form-submissions-pdf?table=custom_designs&amp;id=<?php echo (int)$item['id']; ?>" class="btn-view" title="Download PDF" style="text-decoration:none; padding: 6px 10px;"><i class="fas fa-file-pdf"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this submission?');">
                                                <input type="hidden" name="clear_one" value="1"><input type="hidden" name="table" value="custom_designs"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Clear"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <!-- Wholesale Tab -->
            <?php elseif ($active_tab == 'quote_requests'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllQuote" title="Select all"></th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quote_requests)): ?>
                                <tr>
                                    <td colspan="10">No wholesale / quote submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quote_requests as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('quote_requests', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td onclick="event.stopPropagation();"><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int)$item['id']; ?>" form="bulkClearForm"></td>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td onclick="event.stopPropagation();">
                                            <form method="POST" class="status-dropdown-form" style="display:inline;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <input type="hidden" name="table" value="quote_requests">
                                                <?php if ($status_filter !== ''): ?><input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                                                <?php if ($search_filter !== ''): ?><input type="hidden" name="search_filter" value="<?php echo htmlspecialchars($search_filter); ?>"><?php endif; ?>
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <?php foreach ($status_options['quote_requests'] as $opt): ?>
                                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($item['status'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button type="button" onclick="viewSubmission('quote_requests', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view" title="View"><i class="fas fa-eye"></i></button>
                                            <a href="<?php echo $base; ?>/admin/form-submissions-pdf?table=quote_requests&amp;id=<?php echo (int)$item['id']; ?>" class="btn-view" title="Download PDF" style="text-decoration:none; padding: 6px 10px;"><i class="fas fa-file-pdf"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this submission?');">
                                                <input type="hidden" name="clear_one" value="1"><input type="hidden" name="table" value="quote_requests"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Clear"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <!-- Customizations Tab -->
            <?php elseif ($active_tab == 'product_customizations'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCustom" title="Select all"></th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($product_customizations)): ?>
                                <tr>
                                    <td colspan="10">No customization submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($product_customizations as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('product_customizations', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td onclick="event.stopPropagation();"><input type="checkbox" class="row-check" name="ids[]" value="<?php echo (int)$item['id']; ?>" form="bulkClearForm"></td>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td onclick="event.stopPropagation();">
                                            <form method="POST" class="status-dropdown-form" style="display:inline;">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <input type="hidden" name="table" value="product_customizations">
                                                <?php if ($status_filter !== ''): ?><input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>"><?php endif; ?>
                                                <?php if ($search_filter !== ''): ?><input type="hidden" name="search_filter" value="<?php echo htmlspecialchars($search_filter); ?>"><?php endif; ?>
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <?php foreach ($status_options['product_customizations'] as $opt): ?>
                                                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($item['status'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo ucfirst($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button type="button" onclick="viewSubmission('product_customizations', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view" title="View"><i class="fas fa-eye"></i></button>
                                            <a href="<?php echo $base; ?>/admin/form-submissions-pdf?table=product_customizations&amp;id=<?php echo (int)$item['id']; ?>" class="btn-view" title="Download PDF" style="text-decoration:none; padding: 6px 10px;"><i class="fas fa-file-pdf"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this submission?');">
                                                <input type="hidden" name="clear_one" value="1"><input type="hidden" name="table" value="product_customizations"><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                <button type="submit" class="btn-delete" title="Clear"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php endif; ?>
        </div>
    </main>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="closeModal('viewModal')">&times;</span>
            <h2 id="modalTitle">Submission Details</h2>
            <div id="modalBody"></div>
        </div>
    </div>
    
    <script>
        function viewSubmission(table, data) {
            let html = '';
            
            if (table == 'custom_designs') {
                html = `
                    <p><strong>Customer Name:</strong> ${data.customer_name || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.customer_email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${data.customer_phone || 'N/A'}</p>
                    <p><strong>Product Type:</strong> ${data.product_type || 'N/A'}</p>
                    <p><strong>Description:</strong><br>${(data.description || 'N/A').replace(/\n/g, '<br>')}</p>
                    <p><strong>Quantity:</strong> ${data.quantity || 1}</p>
                    <p><strong>Timeline:</strong> ${data.timeline || 'N/A'}</p>
                    <p><strong>Budget:</strong> ${data.budget || 'N/A'}</p>
                    ${data.images ? '<p><strong>Images:</strong></p><div class="images-preview">' + JSON.parse(data.images).map(img => `<img src="../${img}" alt="Design Image">`).join('') + '</div>' : ''}
                    <p><strong>Status:</strong> ${data.status || 'pending'}</p>
                    <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleString()}</p>
                `;
            } else if (table == 'product_customizations') {
                html = `
                    <p><strong>Product:</strong> ${data.product_name || 'N/A'}</p>
                    <p><strong>Customer Name:</strong> ${data.customer_name || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.customer_email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${data.customer_phone || 'N/A'}</p>
                    <p><strong>Customizations:</strong><br>${(data.customizations || 'N/A').replace(/\n/g, '<br>')}</p>
                    <p><strong>Description:</strong><br>${(data.description || 'N/A').replace(/\n/g, '<br>')}</p>
                    <p><strong>Quantity:</strong> ${data.quantity || 1}</p>
                    <p><strong>Status:</strong> ${data.status || 'pending'}</p>
                    <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleString()}</p>
                `;
            } else if (table == 'quote_requests') {
                html = `
                    <p><strong>Product:</strong> ${data.product_name || 'N/A'}</p>
                    <p><strong>Customer Name:</strong> ${data.customer_name || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.customer_email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${data.customer_phone || 'N/A'}</p>
                    <p><strong>Message:</strong><br>${(data.message || 'N/A').replace(/\n/g, '<br>')}</p>
                    <p><strong>Quantity:</strong> ${data.quantity || 1}</p>
                    <p><strong>Status:</strong> ${data.status || 'pending'}</p>
                    <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleString()}</p>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Select-all and bulk Clear selected visibility
        (function() {
            var selectAllDesign = document.getElementById('selectAllDesign');
            var selectAllQuote = document.getElementById('selectAllQuote');
            var selectAllCustom = document.getElementById('selectAllCustom');
            var rowChecks = document.querySelectorAll('.row-check');
            var btnClearSelected = document.getElementById('btnClearSelected');

            function toggleBulkButton() {
                var any = document.querySelectorAll('.row-check:checked').length > 0;
                if (btnClearSelected) btnClearSelected.style.display = any ? 'inline-block' : 'none';
            }

            [selectAllDesign, selectAllQuote, selectAllCustom].forEach(function(header) {
                if (!header) return;
                header.onclick = function() {
                    var t = header.closest('table');
                    var cbs = t ? t.querySelectorAll('.row-check') : [];
                    cbs.forEach(function(cb) { cb.checked = header.checked; });
                    toggleBulkButton();
                };
            });
            rowChecks.forEach(function(cb) {
                cb.addEventListener('change', toggleBulkButton);
            });
            toggleBulkButton();
        })();
    </script>
</body>
</html>

