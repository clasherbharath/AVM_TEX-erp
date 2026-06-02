<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

$reportType = trim((string)($_GET['type'] ?? 'sales'));
$month = trim((string)($_GET['month'] ?? date('Y-m')));
$year = trim((string)($_GET['year'] ?? date('Y')));

$filename = 'avm-tex-export-' . date('Y-m-d-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fputcsv($output, ['AVM TEX ERP Export', date('Y-m-d H:i:s')]);
fputcsv($output, []);

try {
    if ($reportType === 'sales') {
        fputcsv($output, ['Sales Report', "Year: $year"]);
        fputcsv($output, ['Month', 'Invoice Count', 'Revenue']);

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month, COUNT(*) AS invoice_count,
                    COALESCE(SUM(grand_total), 0) AS revenue
             FROM invoices WHERE status = 'paid' AND YEAR(invoice_date) = :year
             GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month DESC"
        );
        $stmt->execute([':year' => (int)$year]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['month'],
                $row['invoice_count'],
                $row['revenue'],
            ]);
        }
    } elseif ($reportType === 'transactions') {
        fputcsv($output, ['Transaction Report']);
        fputcsv($output, ['Payment Method', 'Count', 'Total Amount']);

        $stmt = $pdo->query(
            "SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total
             FROM transactions GROUP BY payment_method ORDER BY total DESC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['payment_method'],
                $row['count'],
                $row['total'],
            ]);
        }
    } elseif ($reportType === 'inventory') {
        fputcsv($output, ['Inventory Report']);
        fputcsv($output, ['Product', 'Category', 'Quantity', 'Unit', 'Purchase Price', 'Stock Value']);

        $stmt = $pdo->query(
            "SELECT product_name, category, quantity, unit, purchase_price,
                    (quantity * purchase_price) AS stock_value
             FROM inventory ORDER BY product_name ASC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['product_name'],
                $row['category'],
                $row['quantity'],
                $row['unit'],
                $row['purchase_price'],
                $row['stock_value'],
            ]);
        }
    } elseif ($reportType === 'customers') {
        fputcsv($output, ['Customer Report']);
        fputcsv($output, ['Customer', 'Phone', 'Email', 'Invoices', 'Total Purchase', 'Last Purchase']);

        $stmt = $pdo->query(
            "SELECT c.customer_name, c.phone, c.email,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(i.grand_total), 0) AS total_purchase,
                    MAX(i.invoice_date) AS last_purchase
             FROM customers c
             LEFT JOIN invoices i ON c.id = i.customer_id
             GROUP BY c.id ORDER BY total_purchase DESC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['customer_name'],
                $row['phone'],
                $row['email'],
                $row['invoice_count'],
                $row['total_purchase'],
                $row['last_purchase'],
            ]);
        }
    }
} catch (PDOException $e) {
    fputcsv($output, ['Error: ' . (APP_DEBUG ? $e->getMessage() : 'Could not export data')]);
}

fclose($output);
exit;
