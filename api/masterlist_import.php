<?php

require_once 'db_connect.php';
require_once 'masterlist_common.php';

requireAdminMasterlistAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    masterlistJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!isset($_FILES['masterlist']) || $_FILES['masterlist']['error'] !== UPLOAD_ERR_OK) {
    masterlistJsonResponse(['success' => false, 'message' => 'Please choose a CSV file.'], 400);
}

$file = $_FILES['masterlist'];
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
    masterlistJsonResponse(['success' => false, 'message' => 'Only CSV files are accepted.'], 400);
}

$handle = fopen($file['tmp_name'], 'rb');
if ($handle === false) {
    masterlistJsonResponse(['success' => false, 'message' => 'Unable to read the uploaded CSV file.'], 400);
}

function normalizeCsvHeader(string $header): string
{
    $header = normalizeMasterlistValue($header);
    return preg_replace('/[^a-z0-9]+/', '_', $header);
}

function parseMasterlistCsvRow(array $row, array $headers, int $line): array
{
    $data = [];
    foreach ($headers as $index => $header) {
        $data[$header] = trim((string)($row[$index] ?? ''));
    }

    $required = ['voter_id', 'first_name', 'last_name', 'birthday', 'age', 'sex', 'address', 'purok_or_barangay'];
    $errors = [];
    foreach ($required as $field) {
        if ($data[$field] === '') {
            $errors[] = str_replace('_', ' ', $field) . ' is required';
        }
    }
    if ($data['birthday'] !== '' && !DateTime::createFromFormat('!Y-m-d', $data['birthday'])) {
        $errors[] = 'birthday must use YYYY-MM-DD';
    }
    if ($data['age'] !== '' && (!ctype_digit($data['age']) || (int)$data['age'] > 120)) {
        $errors[] = 'age must be a number from 0 to 120';
    }

    return [
        'line' => $line,
        'data' => [
            'voter_id' => normalizeMasterlistValue($data['voter_id']),
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'middle_name' => $data['middle_name'] !== '' ? trim($data['middle_name']) : null,
            'birthday' => $data['birthday'],
            'age' => (int)$data['age'],
            'sex' => trim($data['sex']),
            'address' => trim($data['address']),
            'purok_or_barangay' => trim($data['purok_or_barangay'])
        ],
        'errors' => $errors
    ];
}

$headerRow = fgetcsv($handle);
if (!$headerRow) {
    fclose($handle);
    masterlistJsonResponse(['success' => false, 'message' => 'The CSV file is empty.'], 400);
}

$headers = array_map('normalizeCsvHeader', $headerRow);
$requiredHeaders = ['voter_id', 'first_name', 'last_name', 'birthday', 'age', 'sex', 'address', 'purok_or_barangay'];
$missingHeaders = array_values(array_diff($requiredHeaders, $headers));
if ($missingHeaders) {
    fclose($handle);
    masterlistJsonResponse([
        'success' => false,
        'message' => 'Missing required CSV columns: ' . implode(', ', $missingHeaders)
    ], 400);
}

$rows = [];
$seen = [];
$line = 1;
while (($csvRow = fgetcsv($handle)) !== false) {
    $line++;
    if (count($csvRow) === 1 && trim((string)$csvRow[0]) === '') {
        continue;
    }
    $parsed = parseMasterlistCsvRow($csvRow, $headers, $line);
    $voterId = $parsed['data']['voter_id'];
    if ($voterId !== '' && isset($seen[$voterId])) {
        $parsed['errors'][] = 'duplicate voter_id in this file';
    }
    if ($voterId !== '') {
        $seen[$voterId] = true;
    }
    $rows[] = $parsed;
}
fclose($handle);

$existing = [];
if ($seen) {
    $placeholders = implode(',', array_fill(0, count($seen), '?'));
    $stmt = $pdo->prepare("SELECT LOWER(voter_id) FROM kk_masterlist WHERE LOWER(voter_id) IN ($placeholders)");
    $stmt->execute(array_keys($seen));
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
foreach ($rows as &$row) {
    if (in_array($row['data']['voter_id'], $existing, true)) {
        $row['errors'][] = 'voter_id already exists in the masterlist';
    }
}
unset($row);

$validRows = array_values(array_filter($rows, static fn(array $row): bool => count($row['errors']) === 0));
$errors = array_values(array_filter($rows, static fn(array $row): bool => count($row['errors']) > 0));
$mode = ($_POST['mode'] ?? 'preview') === 'import' ? 'import' : 'preview';

if ($mode === 'import' && $validRows) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO kk_masterlist
             (voter_id, first_name, last_name, middle_name, birthday, age, sex, address, purok_or_barangay)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($validRows as $row) {
            $data = $row['data'];
            $stmt->execute([
                $data['voter_id'], $data['first_name'], $data['last_name'], $data['middle_name'],
                $data['birthday'], $data['age'], $data['sex'], $data['address'], $data['purok_or_barangay']
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Masterlist Import] ' . $exception->getMessage());
        masterlistJsonResponse(['success' => false, 'message' => 'The masterlist import failed.'], 500);
    }
}

masterlistJsonResponse([
    'success' => true,
    'mode' => $mode,
    'imported' => $mode === 'import' ? count($validRows) : 0,
    'valid' => count($validRows),
    'errors' => $errors
]);
