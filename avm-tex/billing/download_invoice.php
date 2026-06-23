<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/invoice_helper.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid invoice.';
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

$invoice = fetchInvoiceDetails($pdo, $id);
if (!$invoice) {
    $_SESSION['flash_error'] = 'Invoice not found.';
    header('Location: ' . APP_BASE . '/billing/index.php');
    exit;
}

// Check for Dompdf library and show a friendly error if missing.
$dompdfPath = __DIR__ . '/../lib/dompdf/autoload.inc.php';
if (!file_exists($dompdfPath)) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Install Dompdf</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:20px;">';
    echo '<h3>Dompdf library not found</h3>';
    echo '<p>Please install <strong>Dompdf</strong> into <code>/lib/dompdf</code> before using PDF export.</p>';
    echo '<p>Quick install (from project root):</p>';
    echo '<pre style="background:#f8f9fa;padding:10px;border-radius:4px;">mkdir lib\dompdf
curl -L -o lib/dompdf_2-0-3.zip https://github.com/dompdf/dompdf/releases/download/v2.0.3/dompdf_2-0-3.zip
powershell -Command "Expand-Archive lib/dompdf_2-0-3.zip -DestinationPath lib\dompdf"
</pre>';
    echo '<p>After installing, reload this page to generate the PDF.</p>';
    echo '<p><a href="' . APP_BASE . '/billing/invoice_view.php?id=' . $id . '">Back to invoice</a></p>';
    echo '</body></html>';
    exit;
}

require_once $dompdfPath;

// Fetch company settings if available
$company = [
    'company_name' => 'A.V.M TEX',
    'address' => '',
    'phone' => '',
    'email' => '',
    'gst_number' => '',
    'logo_path' => '',
];
try {
    $stmt = $pdo->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $company = array_merge($company, $row);
    }
} catch (PDOException $e) {
    // ignore
}

$logoUrl = '';
if (!empty($company['logo_path'])) {
    $logoPath = $company['logo_path'];
    if (!preg_match('/^https?:\/\//i', $logoPath)) {
        $candidate = realpath(__DIR__ . '/../' . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $logoPath), DIRECTORY_SEPARATOR));
        if ($candidate && file_exists($candidate)) {
            $logoUrl = 'file://' . str_replace('\\', '/', $candidate);
        }
    } else {
        $logoUrl = $logoPath;
    }
}

$items = $invoice['items'] ?? [];
$customerAddress = trim(implode(', ', array_filter([
    $invoice['address'] ?? '',
    $invoice['city'] ?? '',
    $invoice['state'] ?? '',
    $invoice['pincode'] ?? '',
])));

$html = '<!doctype html><html><head><meta charset="utf-8"><style>' .
    'body{font-family:Arial,Helvetica,sans-serif;color:#333;margin:0;padding:0;} ' .
    '.page{padding:24px;} ' .
    'table.layout{width:100%;border-collapse:collapse;margin-bottom:24px;} ' .
    '.company-name{font-size:22px;font-weight:700;margin:0 0 6px;} ' .
    '.company-meta{font-size:12px;line-height:1.5;margin:0;} ' .
    '.invoice-title{font-size:20px;font-weight:700;color:#1f4e79;margin:0 0 8px;} ' .
    '.invoice-meta{font-size:12px;line-height:1.6;} ' .
    '.panel{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:20px;} ' .
    '.box{background:#f7f6f2;border-radius:6px;padding:12px;font-size:12px;line-height:1.5;} ' .
    'table{width:100%;border-collapse:collapse;margin-top:10px;} ' .
    'th,td{padding:10px 8px;border:1px solid #ddd;font-size:12px;} ' .
    'th{background:#f7f7f7;font-weight:700;text-align:left;} ' .
    '.text-right{text-align:right;} ' .
    '.summary td{border:none;padding:6px 8px;} ' .
    '.summary .label{text-align:right;font-weight:700;} ' .
    '.footer{margin-top:24px;font-size:11px;color:#555;text-align:center;}' .
    '</style></head><body><div class="page">';

$html .= '<table class="layout"><tr><td style="vertical-align:top;width:70%;">';
$html .= '<div class="company-name">' . htmlspecialchars($company['company_name'] ?: 'A.V.M TEX') . '</div>';
if (!empty($company['address'])) {
    $html .= '<div class="company-meta">' . nl2br(htmlspecialchars($company['address'])) . '</div>';
}
$contactMeta = [];
if (!empty($company['phone'])) {
    $contactMeta[] = 'Phone: ' . htmlspecialchars($company['phone']);
}
if (!empty($company['email'])) {
    $contactMeta[] = 'Email: ' . htmlspecialchars($company['email']);
}
if (!empty($contactMeta)) {
    $html .= '<div class="company-meta">' . implode(' | ', $contactMeta) . '</div>';
}
if (!empty($company['gst_number'])) {
    $html .= '<div class="company-meta">GSTIN: ' . htmlspecialchars($company['gst_number']) . '</div>';
}
$html .= '</td>';
$html .= '<td style="vertical-align:top;text-align:right;">';
if (!empty($logoUrl)) {
    $html .= '<img src="' . htmlspecialchars($logoUrl) . '" style="max-width:160px;max-height:90px;" alt="Logo">';
}
$html .= '</td></tr></table>';

$html .= '<div class="invoice-title">TAX INVOICE</div>';
$html .= '<div class="invoice-meta">Invoice #: ' . htmlspecialchars($invoice['invoice_number']) . '</div>';
$html .= '<div class="invoice-meta">Date: ' . htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) . '</div>';

$html .= '<table class="layout"><tr><td style="vertical-align:top;width:50%;padding-right:8px;">';
$html .= '<div class="box"><strong>Bill To</strong><br>' . htmlspecialchars($invoice['customer_name']);
if (!empty($customerAddress)) {
    $html .= '<br>' . nl2br(htmlspecialchars($customerAddress));
}
if (!empty($invoice['phone'])) {
    $html .= '<br>Phone: ' . htmlspecialchars($invoice['phone']);
}
if (!empty($invoice['email'])) {
    $html .= '<br>Email: ' . htmlspecialchars($invoice['email']);
}
$html .= '</div></td>';
$html .= '<td style="vertical-align:top;width:50%;padding-left:8px;">';
$html .= '<div class="box"><strong>Invoice Details</strong><br>Invoice Date: ' . htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) . '<br>Invoice No: ' . htmlspecialchars($invoice['invoice_number']) . '</div>';
$html .= '</td></tr></table>';

$html .= '<div class="panel">';
$html .= '<table><thead><tr>' .
    '<th style="width:6%;">#</th>' .
    '<th style="width:44%;">Product</th>' .
    '<th style="width:10%;" class="text-right">Qty</th>' .
    '<th style="width:15%;" class="text-right">Unit Price</th>' .
    '<th style="width:10%;" class="text-right">GST %</th>' .
    '<th style="width:15%;" class="text-right">Total</th>' .
    '</tr></thead><tbody>';

foreach ($items as $index => $line) {
    $product = htmlspecialchars($line['product_name'] . (!empty($line['unit']) ? ' (' . $line['unit'] . ')' : ''));
    $html .= '<tr>' .
        '<td>' . ($index + 1) . '</td>' .
        '<td>' . $product . '</td>' .
        '<td class="text-right">' . number_format((float)$line['quantity'], 2) . '</td>' .
        '<td class="text-right">₹ ' . number_format((float)$line['price'], 2) . '</td>' .
        '<td class="text-right">' . number_format((float)$line['gst_percentage'], 2) . '%</td>' .
        '<td class="text-right">₹ ' . number_format((float)$line['total'], 2) . '</td>' .
        '</tr>';
}

$html .= '</tbody></table>';
$html .= '<table class="summary" style="margin-top:12px;width:100%;">';
$html .= '<tr><td class="label">Subtotal</td><td class="text-right">₹ ' . number_format((float)$invoice['subtotal'], 2) . '</td></tr>';
$html .= '<tr><td class="label">GST Total</td><td class="text-right">₹ ' . number_format((float)$invoice['gst_total'], 2) . '</td></tr>';
$html .= '<tr><td class="label">Discount</td><td class="text-right">- ₹ ' . number_format((float)$invoice['discount'], 2) . '</td></tr>';
$html .= '<tr><td class="label" style="font-size:14px;padding-top:12px;">Grand Total</td><td class="text-right" style="font-size:14px;padding-top:12px;">₹ ' . number_format((float)$invoice['grand_total'], 2) . '</td></tr>';
$html .= '</table>';
$html .= '</div>';

if (!empty($invoice['notes'])) {
    $html .= '<div class="panel"><strong>Notes</strong><br>' . nl2br(htmlspecialchars($invoice['notes'])) . '</div>';
}

$html .= '<div class="footer">Thank you for your business — A.V.M TEX</div>';
$html .= '</div></body></html>';

$dompdfOptions = new Dompdf\Options();
$dompdfOptions->set('defaultFont', 'Helvetica');
$dompdfOptions->set('isRemoteEnabled', true);
$dompdf = new Dompdf\Dompdf($dompdfOptions);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

$filename = 'invoice_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $invoice['invoice_number']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$dompdf->stream($filename, ['Attachment' => true]);
exit;
