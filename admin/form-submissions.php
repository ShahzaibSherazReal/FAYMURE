<?php
require_once 'check-auth.php';

$conn = getDBConnection();

$success = false;
$error = '';

// Handle form updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status']);
        $table = sanitize($_POST['table']);
        
        $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to update status.";
        }
        $stmt->close();
    } elseif (isset($_POST['update_submission'])) {
        $id = intval($_POST['id']);
        $table = sanitize($_POST['table']);
        
        // Update based on table type
        if ($table == 'custom_designs') {
            $customer_name = sanitize($_POST['customer_name'] ?? '');
            $customer_email = sanitize($_POST['customer_email'] ?? '');
            $customer_phone = sanitize($_POST['customer_phone'] ?? '');
            $product_type = sanitize($_POST['product_type'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $timeline = sanitize($_POST['timeline'] ?? '');
            $budget = sanitize($_POST['budget'] ?? '');
            $status = sanitize($_POST['status'] ?? 'pending');
            
            $stmt = $conn->prepare("UPDATE custom_designs SET customer_name = ?, customer_email = ?, customer_phone = ?, product_type = ?, description = ?, quantity = ?, timeline = ?, budget = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssisssi", $customer_name, $customer_email, $customer_phone, $product_type, $description, $quantity, $timeline, $budget, $status, $id);
        } elseif ($table == 'product_customizations') {
            $customer_name = sanitize($_POST['customer_name'] ?? '');
            $customer_email = sanitize($_POST['customer_email'] ?? '');
            $customer_phone = sanitize($_POST['customer_phone'] ?? '');
            $customizations = sanitize($_POST['customizations'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $status = sanitize($_POST['status'] ?? 'pending');
            
            $stmt = $conn->prepare("UPDATE product_customizations SET customer_name = ?, customer_email = ?, customer_phone = ?, customizations = ?, description = ?, quantity = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssisi", $customer_name, $customer_email, $customer_phone, $customizations, $description, $quantity, $status, $id);
        } elseif ($table == 'quote_requests') {
            $customer_name = sanitize($_POST['customer_name'] ?? '');
            $customer_email = sanitize($_POST['customer_email'] ?? '');
            $customer_phone = sanitize($_POST['customer_phone'] ?? '');
            $message = sanitize($_POST['message'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $status = sanitize($_POST['status'] ?? 'pending');
            
            $stmt = $conn->prepare("UPDATE quote_requests SET customer_name = ?, customer_email = ?, customer_phone = ?, message = ?, quantity = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssssisi", $customer_name, $customer_email, $customer_phone, $message, $quantity, $status, $id);
        } elseif ($table == 'manufacturing_inquiries') {
            $customer_name = sanitize($_POST['customer_name'] ?? '');
            $customer_email = sanitize($_POST['customer_email'] ?? '');
            $customer_phone = sanitize($_POST['customer_phone'] ?? '');
            $product_type = sanitize($_POST['product_type'] ?? '');
            $message = sanitize($_POST['message'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 1);
            $status = sanitize($_POST['status'] ?? 'pending');
            
            $stmt = $conn->prepare("UPDATE manufacturing_inquiries SET customer_name = ?, customer_email = ?, customer_phone = ?, product_type = ?, message = ?, quantity = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssssisi", $customer_name, $customer_email, $customer_phone, $product_type, $message, $quantity, $status, $id);
        }
        
        if (isset($stmt) && $stmt->execute()) {
            $success = true;
        } else {
            $error = "Failed to update submission.";
        }
        if (isset($stmt)) $stmt->close();
    }
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'custom_designs';
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

// Build query for each table
$custom_designs = [];
$product_customizations = [];
$quote_requests = [];
$manufacturing_inquiries = [];

// Custom Designs
$custom_designs_query = "SELECT cd.*, u.username FROM custom_designs cd LEFT JOIN users u ON cd.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $custom_designs_query .= " AND cd.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $custom_designs_query .= " AND (cd.customer_name LIKE '%$search_escaped%' OR cd.customer_email LIKE '%$search_escaped%' OR cd.product_type LIKE '%$search_escaped%')";
}
$custom_designs_query .= " ORDER BY cd.created_at DESC";
$custom_designs = $conn->query($custom_designs_query)->fetch_all(MYSQLI_ASSOC);

// Product Customizations
$product_customizations_query = "SELECT pc.*, p.name as product_name, u.username FROM product_customizations pc LEFT JOIN products p ON pc.product_id = p.id LEFT JOIN users u ON pc.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $product_customizations_query .= " AND pc.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $product_customizations_query .= " AND (pc.customer_name LIKE '%$search_escaped%' OR pc.customer_email LIKE '%$search_escaped%' OR p.name LIKE '%$search_escaped%')";
}
$product_customizations_query .= " ORDER BY pc.created_at DESC";
$product_customizations = $conn->query($product_customizations_query)->fetch_all(MYSQLI_ASSOC);

// Quote Requests
$quote_requests_query = "SELECT qr.*, p.name as product_name, u.username FROM quote_requests qr LEFT JOIN products p ON qr.product_id = p.id LEFT JOIN users u ON qr.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $quote_requests_query .= " AND qr.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $quote_requests_query .= " AND (qr.customer_name LIKE '%$search_escaped%' OR qr.customer_email LIKE '%$search_escaped%' OR p.name LIKE '%$search_escaped%')";
}
$quote_requests_query .= " ORDER BY qr.created_at DESC";
$quote_requests = $conn->query($quote_requests_query)->fetch_all(MYSQLI_ASSOC);

// Manufacturing Inquiries
$manufacturing_inquiries_query = "SELECT mi.*, u.username FROM manufacturing_inquiries mi LEFT JOIN users u ON mi.user_id = u.id WHERE 1=1";
if ($status_filter) {
    $manufacturing_inquiries_query .= " AND mi.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_filter) {
    $search_escaped = $conn->real_escape_string($search_filter);
    $manufacturing_inquiries_query .= " AND (mi.customer_name LIKE '%$search_escaped%' OR mi.customer_email LIKE '%$search_escaped%' OR mi.product_type LIKE '%$search_escaped%')";
}
$manufacturing_inquiries_query .= " ORDER BY mi.created_at DESC";
$manufacturing_inquiries = $conn->query($manufacturing_inquiries_query)->fetch_all(MYSQLI_ASSOC);

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
            
            <!-- Tabs -->
            <div class="admin-tabs">
                <a href="?tab=custom_designs<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'custom_designs' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> Custom Designs
                </a>
                <a href="?tab=product_customizations<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'product_customizations' ? 'active' : ''; ?>">
                    <i class="fas fa-paint-brush"></i> Product Customizations
                </a>
                <a href="?tab=quote_requests<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'quote_requests' ? 'active' : ''; ?>">
                    <i class="fas fa-calculator"></i> Quote Requests
                </a>
                <a href="?tab=manufacturing_inquiries<?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?>" 
                   class="tab-link <?php echo $active_tab == 'manufacturing_inquiries' ? 'active' : ''; ?>">
                    <i class="fas fa-industry"></i> Manufacturing Inquiries
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
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
                        } elseif ($active_tab == 'quote_requests') {
                            $statuses = ['pending', 'quoted', 'accepted', 'rejected', 'cancelled'];
                        } elseif ($active_tab == 'manufacturing_inquiries') {
                            $statuses = ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'];
                        }
                        foreach ($statuses as $status) {
                            echo '<option value="' . $status . '"' . ($status_filter == $status ? ' selected' : '') . '>' . ucfirst($status) . '</option>';
                        }
                        ?>
                    </select>
                    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                    <a href="?tab=<?php echo htmlspecialchars($active_tab); ?>" class="btn-secondary" style="padding: 8px 20px; text-decoration: none; display: inline-block;"><i class="fas fa-times"></i> Clear</a>
                </form>
            </div>
            
            <!-- Custom Designs Tab -->
            <?php if ($active_tab == 'custom_designs'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
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
                                    <td colspan="11">No custom design submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($custom_designs as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('custom_designs', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['product_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($item['timeline'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['budget'] ?? 'N/A'); ?></td>
                                        <td><span class="status-badge status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button onclick="viewSubmission('custom_designs', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view"><i class="fas fa-eye"></i></button>
                                            <button onclick="editSubmission('custom_designs', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-edit"><i class="fas fa-edit"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <!-- Product Customizations Tab -->
            <?php elseif ($active_tab == 'product_customizations'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
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
                                    <td colspan="9">No product customization submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($product_customizations as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('product_customizations', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><span class="status-badge status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button onclick="viewSubmission('product_customizations', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view"><i class="fas fa-eye"></i></button>
                                            <button onclick="editSubmission('product_customizations', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-edit"><i class="fas fa-edit"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <!-- Quote Requests Tab -->
            <?php elseif ($active_tab == 'quote_requests'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
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
                                    <td colspan="9">No quote request submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quote_requests as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('quote_requests', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><span class="status-badge status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button onclick="viewSubmission('quote_requests', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view"><i class="fas fa-eye"></i></button>
                                            <button onclick="editSubmission('quote_requests', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-edit"><i class="fas fa-edit"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <!-- Manufacturing Inquiries Tab -->
            <?php elseif ($active_tab == 'manufacturing_inquiries'): ?>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Product Type</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($manufacturing_inquiries)): ?>
                                <tr>
                                    <td colspan="9">No manufacturing inquiry submissions found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($manufacturing_inquiries as $item): ?>
                                    <tr class="submission-row" onclick="viewSubmission('manufacturing_inquiries', <?php echo htmlspecialchars(json_encode($item)); ?>)">
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['customer_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['product_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><span class="status-badge status-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="actions" onclick="event.stopPropagation();">
                                            <button onclick="viewSubmission('manufacturing_inquiries', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-view"><i class="fas fa-eye"></i></button>
                                            <button onclick="editSubmission('manufacturing_inquiries', <?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn-edit"><i class="fas fa-edit"></i></button>
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
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Submission</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_submission" value="1">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="table" id="editTable">
                <div id="editFormBody"></div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="closeModal('editModal')" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
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
            } else if (table == 'manufacturing_inquiries') {
                html = `
                    <p><strong>Customer Name:</strong> ${data.customer_name || 'N/A'}</p>
                    <p><strong>Email:</strong> ${data.customer_email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${data.customer_phone || 'N/A'}</p>
                    <p><strong>Product Type:</strong> ${data.product_type || 'N/A'}</p>
                    <p><strong>Message:</strong><br>${(data.message || 'N/A').replace(/\n/g, '<br>')}</p>
                    <p><strong>Quantity:</strong> ${data.quantity || 1}</p>
                    <p><strong>Status:</strong> ${data.status || 'pending'}</p>
                    <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleString()}</p>
                `;
            }
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function editSubmission(table, data) {
            document.getElementById('editId').value = data.id;
            document.getElementById('editTable').value = table;
            
            let html = '';
            
            if (table == 'custom_designs') {
                const statuses = ['pending', 'processing', 'completed', 'cancelled'];
                html = `
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" value="${data.customer_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" value="${data.customer_email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="customer_phone" value="${data.customer_phone || ''}">
                    </div>
                    <div class="form-group">
                        <label>Product Type</label>
                        <input type="text" name="product_type" value="${data.product_type || ''}">
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="5" required>${data.description || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="${data.quantity || 1}" min="1">
                    </div>
                    <div class="form-group">
                        <label>Timeline</label>
                        <input type="text" name="timeline" value="${data.timeline || ''}">
                    </div>
                    <div class="form-group">
                        <label>Budget</label>
                        <input type="text" name="budget" value="${data.budget || ''}">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            ${statuses.map(s => `<option value="${s}" ${data.status == s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                `;
            } else if (table == 'product_customizations') {
                const statuses = ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'];
                html = `
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" value="${data.customer_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" value="${data.customer_email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="customer_phone" value="${data.customer_phone || ''}">
                    </div>
                    <div class="form-group">
                        <label>Customizations</label>
                        <textarea name="customizations" rows="3">${data.customizations || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="5" required>${data.description || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="${data.quantity || 1}" min="1">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            ${statuses.map(s => `<option value="${s}" ${data.status == s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                `;
            } else if (table == 'quote_requests') {
                const statuses = ['pending', 'quoted', 'accepted', 'rejected', 'cancelled'];
                html = `
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" value="${data.customer_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" value="${data.customer_email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="customer_phone" value="${data.customer_phone || ''}">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="5">${data.message || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="${data.quantity || 1}" min="1">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            ${statuses.map(s => `<option value="${s}" ${data.status == s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                `;
            } else if (table == 'manufacturing_inquiries') {
                const statuses = ['pending', 'reviewing', 'approved', 'rejected', 'cancelled'];
                html = `
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" value="${data.customer_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" value="${data.customer_email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="customer_phone" value="${data.customer_phone || ''}">
                    </div>
                    <div class="form-group">
                        <label>Product Type</label>
                        <input type="text" name="product_type" value="${data.product_type || ''}">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="5">${data.message || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="${data.quantity || 1}" min="1">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            ${statuses.map(s => `<option value="${s}" ${data.status == s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                `;
            }
            
            document.getElementById('editFormBody').innerHTML = html;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modals = ['viewModal', 'editModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

