<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

// Restrict to admin role
if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    $_SESSION['flash_error'] = 'Unauthorized: admin access required.';
    header('Location: ' . APP_BASE . '/dashboard/dashboard.php');
    exit;
}

$pageTitle = 'Settings • A.V.M TEX ERP System';
$activeMenu = 'Settings';

$flashMessage = '';
$flashError = '';

// Ensure table exists
$createTableSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `company_name` VARCHAR(191) DEFAULT NULL,
  `gst_number` VARCHAR(64) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(64) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `invoice_prefix` VARCHAR(32) DEFAULT NULL,
  `currency_symbol` VARCHAR(8) DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL;

try {
    $pdo->exec($createTableSql);
} catch (PDOException $e) {
    // non-fatal: show later
    $flashError = APP_DEBUG ? $e->getMessage() : 'Failed to ensure settings table exists.';
}

// Load existing settings (single-row table)
$settings = [
    'company_name' => '',
    'gst_number' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'invoice_prefix' => '',
    'currency_symbol' => '₹',
    'logo_path' => '',
];

try {
    $stmt = $pdo->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $settings = array_merge($settings, $row);
    }
} catch (PDOException $e) {
    $flashError = APP_DEBUG ? $e->getMessage() : 'Unable to load settings.';
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('/settings/index.php');

    $companyName = trim($_POST['company_name'] ?? '');
    $gstNumber = trim($_POST['gst_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $invoicePrefix = trim($_POST['invoice_prefix'] ?? '');
    $currencySymbol = trim($_POST['currency_symbol'] ?? '');

    // Basic validation
    if ($companyName === '') {
        $flashError = 'Company name is required.';
    }

    // Handle logo upload if provided
    $uploadedLogoPath = $settings['logo_path'] ?? '';
    if (isset($_FILES['logo']) && is_array($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['logo'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ((int)$file['size'] > 2 * 1024 * 1024) {
                $flashError = 'Logo file must be 2 MB or smaller.';
            } else {
                $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed, true)) {
                    $flashError = 'Unsupported logo file type. Use PNG, JPG, WEBP or SVG.';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $safeName = 'company_logo_' . time() . '.' . $ext;
                    $destDir = __DIR__ . '/../uploads';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    $destPath = $destDir . '/' . $safeName;
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        $flashError = 'Failed to move uploaded logo file.';
                    } else {
                        $uploadedLogoPath = APP_BASE . '/uploads/' . $safeName;
                    }
                }
            }
        } else {
            $flashError = 'Logo upload error (code: ' . (int)$file['error'] . ').';
        }
    }

    if ($flashError === '') {
        try {
            // Determine insert vs update
            $existsStmt = $pdo->query('SELECT id FROM company_settings ORDER BY id ASC LIMIT 1');
            $exists = (bool)$existsStmt->fetchColumn();

            if ($exists) {
                $updateSql = 'UPDATE company_settings SET company_name = :company_name, gst_number = :gst_number, address = :address, phone = :phone, email = :email, invoice_prefix = :invoice_prefix, currency_symbol = :currency_symbol, logo_path = :logo_path WHERE id = (SELECT id FROM (SELECT id FROM company_settings ORDER BY id ASC LIMIT 1) AS t)';
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([
                    ':company_name' => $companyName,
                    ':gst_number' => $gstNumber,
                    ':address' => $address,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':invoice_prefix' => $invoicePrefix,
                    ':currency_symbol' => $currencySymbol,
                    ':logo_path' => $uploadedLogoPath,
                ]);
            } else {
                $insertSql = 'INSERT INTO company_settings (company_name, gst_number, address, phone, email, invoice_prefix, currency_symbol, logo_path) VALUES (:company_name, :gst_number, :address, :phone, :email, :invoice_prefix, :currency_symbol, :logo_path)';
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute([
                    ':company_name' => $companyName,
                    ':gst_number' => $gstNumber,
                    ':address' => $address,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':invoice_prefix' => $invoicePrefix,
                    ':currency_symbol' => $currencySymbol,
                    ':logo_path' => $uploadedLogoPath,
                ]);
            }

            $_SESSION['flash_success'] = 'Settings saved successfully.';
            header('Location: ' . APP_BASE . '/settings/index.php');
            exit;
        } catch (PDOException $e) {
            $flashError = APP_DEBUG ? $e->getMessage() : 'Failed to save settings.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="d-none d-lg-block col-lg-3 col-xl-2 p-0" aria-hidden="true"></div>
        <main class="col-12 col-lg-9 col-xl-10 avm-content">
            <div class="d-flex align-items-start align-items-md-center justify-content-between flex-column flex-md-row gap-2 mb-3">
                <div>
                    <h2 class="mb-1 fw-bold">Settings</h2>
                    <div class="avm-muted">Update company details and invoice settings.</div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../includes/flash_messages.php'; ?>
            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="card avm-card">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrfTokenInput() ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name']) ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">GST Number</label>
                                <input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars($settings['gst_number']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($settings['address']) ?></textarea>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($settings['phone']) ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email']) ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol']) ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Company Logo</label>
                                <?php if (!empty($settings['logo_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Company Logo" style="max-height:80px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo" class="form-control">
                                <div class="form-text">PNG/JPG/WebP/SVG. Max 2MB.</div>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-avm-gold">Save Settings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
