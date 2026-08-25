<?php
$pageTitle = "Excel Database Manager";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

$dataDir = ExcelDatabase::getDataDir();
$tables = ExcelDatabase::getTables();

// ─── Handle Direct Download Request ─────────────────────────────────────────
if (isset($_GET['download'])) {
    $target = sanitize($_GET['download']);
    if ($target === 'master_xml') {
        $xmlFile = $dataDir . DIRECTORY_SEPARATOR . 'EWU_University_Portal_Database.xml';
        if (!file_exists($xmlFile)) {
            ExcelDatabase::saveAllToExcel();
        }
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="EWU_University_Portal_Database.xml"');
        header('Content-Length: ' . filesize($xmlFile));
        readfile($xmlFile);
        exit();
    } elseif (in_array($target, $tables)) {
        $csvFile = $dataDir . DIRECTORY_SEPARATOR . $target . '.csv';
        if (!file_exists($csvFile)) {
            ExcelDatabase::saveTableToCsv($pdo, $target);
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $target . '.csv"');
        header('Content-Length: ' . filesize($csvFile));
        readfile($csvFile);
        exit();
    }
}

// ─── Handle Force Re-Sync / Export ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_export_all'])) {
    try {
        ExcelDatabase::saveAllToExcel();
        $msg = "All 14 database tables and the Master Excel Workbook have been synchronized and updated on disk.";
        $msgType = "success";
    } catch (Exception $e) {
        $msg = "Error exporting tables: " . $e->getMessage();
        $msgType = "danger";
    }
}

// ─── Handle Force Reload from Excel CSV ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reload_all'])) {
    try {
        ExcelDatabase::loadAllFromCsv($pdo);
        ExcelDatabase::generateMasterExcelXml($pdo);
        $msg = "All tables successfully reloaded from the CSV/Excel files into active system memory.";
        $msgType = "success";
    } catch (Exception $e) {
        $msg = "Error reloading tables: " . $e->getMessage();
        $msgType = "danger";
    }
}

// ─── Handle CSV File Upload & Import ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload_csv'])) {
    $selectedTable = sanitize($_POST['upload_table'] ?? '');
    if (in_array($selectedTable, $tables) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $destFile = $dataDir . DIRECTORY_SEPARATOR . $selectedTable . '.csv';

        if (move_uploaded_file($tmpName, $destFile)) {
            try {
                ExcelDatabase::loadTableFromCsv($pdo, $selectedTable);
                ExcelDatabase::generateMasterExcelXml($pdo);
                $msg = "Excel file for table <strong>$selectedTable</strong> imported and synced successfully!";
                $msgType = "success";
            } catch (Exception $e) {
                $msg = "File uploaded, but error parsing data: " . $e->getMessage();
                $msgType = "danger";
            }
        } else {
            $msg = "Failed to upload file.";
            $msgType = "danger";
        }
    } else {
        $msg = "Please select a valid table and choose a valid CSV file.";
        $msgType = "danger";
    }
}

// ─── Collect Statistics ─────────────────────────────────────────────────────
$tableStats = [];
$totalRows = 0;
$totalBytes = 0;

foreach ($tables as $t) {
    $rowCount = $pdo->query("SELECT COUNT(*) FROM " . $t)->fetchColumn();
    $totalRows += $rowCount;

    $csvFile = $dataDir . DIRECTORY_SEPARATOR . $t . '.csv';
    $fileSize = file_exists($csvFile) ? filesize($csvFile) : 0;
    $fileTime = file_exists($csvFile) ? filemtime($csvFile) : 0;
    $totalBytes += $fileSize;

    $tableStats[] = [
        'name' => $t,
        'rows' => $rowCount,
        'size' => $fileSize,
        'mtime' => $fileTime,
        'exists' => file_exists($csvFile)
    ];
}

$xmlFile = $dataDir . DIRECTORY_SEPARATOR . 'EWU_University_Portal_Database.xml';
$masterXmlSize = file_exists($xmlFile) ? filesize($xmlFile) : 0;
$masterXmlMtime = file_exists($xmlFile) ? filemtime($xmlFile) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Excel Database Management</h1>
        <p class="page-subtitle">Zero-SQL Excel-based persistent database engine with real-time two-way synchronization.</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="?download=master_xml" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download Master Excel (.xml)
        </a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 24px;">
        <?= $msg ?>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff; color:#2563eb;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <div>
            <div class="stat-label">Active Tables</div>
            <div class="stat-value"><?= count($tables) ?> Tables</div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">100% Excel Persistent</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#ecfdf5; color:#059669;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7c0-2-1-3-3-3H7C5 4 4 5 4 7z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-6-8h6"></path></svg>
        </div>
        <div>
            <div class="stat-label">Total Records</div>
            <div class="stat-value"><?= number_format($totalRows) ?></div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Across all sheets</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7; color:#d97706;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <div class="stat-label">Database Storage</div>
            <div class="stat-value" style="font-size:1.15rem; color:#0f172a;">Excel Files</div>
            <div style="font-size:0.75rem; color:#10b981; font-weight:600; margin-top:4px;">Zero SQL Server Needed</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background:#f1f5f9; color:#475569;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
        </div>
        <div>
            <div class="stat-label">Master Workbook Size</div>
            <div class="stat-value"><?= round($masterXmlSize / 1024, 1) ?> KB</div>
            <div style="font-size:0.75rem; color:#64748b; margin-top:4px;">Updated: <?= $masterXmlMtime ? date('h:i A', $masterXmlMtime) : 'N/A' ?></div>
        </div>
    </div>
</div>

<!-- Quick Controls & Upload -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:28px;">
    <!-- Manual Sync Tools -->
    <div class="card" style="padding:20px;">
        <h3 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Synchronization Tools
        </h3>
        <p style="font-size:0.875rem; color:#64748b; margin-bottom:16px;">
            The portal automatically syncs in real time whenever data changes. You can also manually trigger a full disk flush or reload from Excel files here.
        </p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action_export_all" value="1">
                <button type="submit" class="btn btn-outline" style="font-size:0.85rem;">
                    Sync All Memory to Excel Files
                </button>
            </form>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Reloading will replace active memory with current Excel file contents. Continue?');">
                <input type="hidden" name="action_reload_all" value="1">
                <button type="submit" class="btn btn-secondary" style="font-size:0.85rem;">
                    Reload Memory from Excel Files
                </button>
            </form>
        </div>
    </div>

    <!-- Upload & Replace a Table -->
    <div class="card" style="padding:20px;">
        <h3 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            Import / Replace Excel Spreadsheet
        </h3>
        <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:12px;">
            <input type="hidden" name="action_upload_csv" value="1">
            <div style="display:flex; gap:10px;">
                <select name="upload_table" class="form-control" style="flex:1;" required>
                    <option value="">-- Choose Target Table --</option>
                    <?php foreach ($tables as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?> (<?= $t ?>.csv)</option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" style="flex:1;" required>
            </div>
            <button type="submit" class="btn btn-primary" style="align-self:flex-start; font-size:0.85rem;">
                Upload & Replace Table
            </button>
        </form>
    </div>
</div>

<!-- Table List -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Database Spreadsheets Directory</h3>
        <span style="font-size:0.8rem; color:#64748b;">Directory: <code><?= htmlspecialchars($dataDir) ?></code></span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Records (Rows)</th>
                    <th>Excel CSV File</th>
                    <th>File Size</th>
                    <th>Last Modified</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableStats as $stat): ?>
                    <tr>
                        <td>
                            <strong style="color:#0f172a; text-transform:capitalize; font-size:0.95rem;">
                                <?= htmlspecialchars(str_replace('_', ' ', $stat['name'])) ?>
                            </strong>
                            <div style="font-size:0.75rem; color:#64748b; font-family:monospace;">table: <?= $stat['name'] ?></div>
                        </td>
                        <td>
                            <span class="badge badge-blue" style="font-size:0.85rem; padding:4px 10px;">
                                <?= number_format($stat['rows']) ?> rows
                            </span>
                        </td>
                        <td>
                            <code><?= $stat['name'] ?>.csv</code>
                        </td>
                        <td>
                            <?= round($stat['size'] / 1024, 2) ?> KB
                        </td>
                        <td style="font-size:0.85rem; color:#475569;">
                            <?= $stat['mtime'] ? date('M d, Y h:i A', $stat['mtime']) : 'Not created' ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="?download=<?= urlencode($stat['name']) ?>" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download Excel CSV
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
