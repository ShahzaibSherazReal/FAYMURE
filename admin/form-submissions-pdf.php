<?php
/**
 * Export form submissions (Design Your Own, Wholesale, Customizations) as PDF.
 * GET: table=custom_designs|quote_requests|product_customizations, optional id=N for single submission.
 * If id is provided, one PDF; otherwise all submissions for that table.
 */
require_once __DIR__ . '/check-auth.php';
require_once __DIR__ . '/../includes/SimplePdf.php';

$conn = getDBConnection();
$allowed_tables = ['custom_designs', 'product_customizations', 'quote_requests'];
$table = $_GET['table'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($table, $allowed_tables, true)) {
    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/admin/form-submissions');
    exit;
}

$rows = [];
if ($id > 0) {
    if ($table === 'custom_designs') {
        $stmt = $conn->prepare("SELECT cd.*, u.username FROM custom_designs cd LEFT JOIN users u ON cd.user_id = u.id WHERE cd.id = ?");
    } elseif ($table === 'quote_requests') {
        $stmt = $conn->prepare("SELECT qr.*, p.name as product_name, u.username FROM quote_requests qr LEFT JOIN products p ON qr.product_id = p.id LEFT JOIN users u ON qr.user_id = u.id WHERE qr.id = ?");
    } else {
        $stmt = $conn->prepare("SELECT pc.*, p.name as product_name, u.username FROM product_customizations pc LEFT JOIN products p ON pc.product_id = p.id LEFT JOIN users u ON pc.user_id = u.id WHERE pc.id = ?");
    }
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
} else {
    if ($table === 'custom_designs') {
        $res = $conn->query("SELECT cd.*, u.username FROM custom_designs cd LEFT JOIN users u ON cd.user_id = u.id ORDER BY cd.created_at DESC");
    } elseif ($table === 'quote_requests') {
        $res = $conn->query("SELECT qr.*, p.name as product_name, u.username FROM quote_requests qr LEFT JOIN products p ON qr.product_id = p.id LEFT JOIN users u ON qr.user_id = u.id ORDER BY qr.created_at DESC");
    } else {
        $res = $conn->query("SELECT pc.*, p.name as product_name, u.username FROM product_customizations pc LEFT JOIN products p ON pc.product_id = p.id LEFT JOIN users u ON pc.user_id = u.id ORDER BY pc.created_at DESC");
    }
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}

if (empty($rows)) {
    $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
    header('Location: ' . $base . '/admin/form-submissions?tab=' . urlencode($table));
    exit;
}

$titles = [
    'custom_designs' => 'Design Your Own Product',
    'quote_requests' => 'Wholesale / Quote Request',
    'product_customizations' => 'Customizations',
];

$pdf = new SimplePdf();
$pdf->AddPage();
$margin = 20;
$pageHeight = 842 - 2 * $margin;
$yLimit = 750;

foreach ($rows as $index => $row) {
    if ($index > 0) {
        $pdf->AddPage();
    }
    $pdf->SetFont('Helvetica', '', 14);
    $pdf->SetXY($margin, $margin);
    $pdf->Cell(0, 8, $titles[$table] . ' – Submission #' . ($row['id'] ?? $index + 1), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Ln(4);

    $label = function ($text) use ($pdf) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, $text, 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
    };
    $line = function ($key) use ($pdf, $row) {
        $v = $row[$key] ?? '';
        if ($v === '' || $v === null) $v = 'N/A';
        $pdf->MultiCell(0, 6, (string) $v, 0, 'L');
    };

    if ($table === 'custom_designs') {
        $label('Customer Name');
        $line('customer_name');
        $label('Email');
        $line('customer_email');
        $label('Phone');
        $line('customer_phone');
        $label('Product Type');
        $line('product_type');
        $label('Description');
        $line('description');
        $label('Quantity');
        $line('quantity');
        $label('Timeline');
        $line('timeline');
        $label('Budget');
        $line('budget');
        $label('Status');
        $line('status');
        $label('Created');
        $line('created_at');
    } elseif ($table === 'quote_requests') {
        $label('Product');
        $line('product_name');
        $label('Customer Name');
        $line('customer_name');
        $label('Email');
        $line('customer_email');
        $label('Phone');
        $line('customer_phone');
        $label('Message');
        $line('message');
        $label('Quantity');
        $line('quantity');
        $label('Status');
        $line('status');
        $label('Created');
        $line('created_at');
    } else {
        $label('Product');
        $line('product_name');
        $label('Customer Name');
        $line('customer_name');
        $label('Email');
        $line('customer_email');
        $label('Phone');
        $line('customer_phone');
        $label('Customizations');
        $line('customizations');
        $label('Description');
        $line('description');
        $label('Quantity');
        $line('quantity');
        $label('Status');
        $line('status');
        $label('Created');
        $line('created_at');
    }
}

$base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
$suffix = $id > 0 ? '-submission-' . $id : '-all';
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $table) . $suffix . '.pdf';
$pdf->Output('D', $filename);
