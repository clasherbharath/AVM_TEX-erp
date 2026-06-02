<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/format_currency.php';

$reportType = trim((string)($_GET['type'] ?? 'sales'));
$month = trim((string)($_GET['month'] ?? date('Y-m')));
$year = trim((string)($_GET['year'] ?? date('Y')));

$html = '<!DOCTYPE html>';
$html .= '<html lang="en">';
$html .= '<head>';
$html .= '<meta charset="UTF-8">';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
$html .= '<title>AVM TEX Report</title>';
$html .= '<style>';
$html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
$html .= 'h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }';
$html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
$html .= 'th { background-color: #007bff; color: white; padding: 10px; text-align: left; }';
$html .= 'td { padding: 8px; border-bottom: 1px solid #ddd; }';
$html .= 'tr:hover { background-color: #f5f5f5; }';
$html .= '.summary { margin: 20px 0; }';
$html .= '.summary div { padding: 10px; background-color: #f9f9f9; margin: 5px 0; border-left: 3px solid #007bff; }';
$html .= '</style>';
$html .= '</head>';
$html .= '<body>';
$html .= '<h1>AVM TEX ERP - Report Export</h1>';
$html .= '<p>Generated: ' . date('d M Y, H:i:s') . '</p>';

try {
    if ($reportType === 'sales') {
        $html .= '<h2>Sales Report - Year ' . $year . '</h2>';
        $html .= '<table><thead><tr><th>Month</th><th>Invoices</th><th>Revenue</th></tr></thead><tbody>';

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
             FROM invoices WHERE status = 'paid' AND YEAR(invoice_date) = :year
             GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month DESC"
        );
        $stmt->execute([':year' => (int)$year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr><td>' . htmlspecialchars($row['month']) . '</td>';
            $html .= '<td>' . (int)$row['invoice_count'] . '</td>';
            $html .= '<td>₹ ' . number_format((float)$row['revenue'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    } elseif ($reportType === 'transactions') {
        $html .= '<h2>Transaction Report</h2>';
        $html .= '<table><thead><tr><th>Payment Method</th><th>Count</th><th>Total Amount</th></tr></thead><tbody>';

        $stmt = $pdo->query(
            "SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
             FROM transactions GROUP BY payment_method ORDER BY total DESC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr><td>' . htmlspecialchars($row['payment_method']) . '</td>';
            $html .= '<td>' . (int)$row['count'] . '</td>';
            $html .= '<td>₹ ' . number_format((float)$row['total'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    } elseif ($reportType === 'inventory') {
        $html .= '<h2>Inventory Report</h2>';
        $html .= '<table><thead><tr><th>Product</th><th>Category</th><th>Quantity</th><th>Unit</th><th>Stock Value</th></tr></thead><tbody>';

        $stmt = $pdo->query(
            "SELECT product_name, category, quantity, unit,
                    (quantity * purchase_price) AS stock_value
             FROM inventory ORDER BY product_name ASC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr><td>' . htmlspecialchars($row['product_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['category']) . '</td>';
            $html .= '<td>' . number_format((float)$row['quantity'], 2) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['unit']) . '</td>';
            $html .= '<td>₹ ' . number_format((float)$row['stock_value'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    } elseif ($reportType === 'customers') {
        $html .= '<h2>Customer Report</h2>';
        $html .= '<table><thead><tr><th>Customer</th><th>Phone</th><th>Email</th><th>Invoices</th><th>Total Purchase</th></tr></thead><tbody>';

        $stmt = $pdo->query(
            "SELECT c.customer_name, c.phone, c.email,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.grand_total), 0) AS total_purchase
             FROM customers c
             LEFT JOIN invoices i ON c.id = i.customer_id
             GROUP BY c.id ORDER BY total_purchase DESC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $html .= '<tr><td>' . htmlspecialchars($row['customer_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['phone'] ?? '—') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['email'] ?? '—') . '</td>';
            $html .= '<td>' . (int)$row['invoice_count'] . '</td>';
            $html .= '<td>₹ ' . number_format((float)$row['total_purchase'], 2) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
} catch (PDOException $e) {
    $html .= '<p style="color: red;">Error: ' . (APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Could not export data') . '</p>';
}

$html .= '</body></html>';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="report-' . date('Y-m-d-His') . '.html"');
echo $html;
exit;
