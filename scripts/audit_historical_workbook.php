<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/xlsx_reader.php';

$path = $argv[1] ?? '';
if ($path === '') {
    fwrite(STDERR, "Usage: php scripts/audit_historical_workbook.php <workbook.xlsx>\n");
    exit(2);
}

try {
    $sheets = shoestagram_xlsx_read($path);
    foreach ($sheets as $name => $rows) {
        $headers = isset($rows[0]) ? array_values(array_filter(array_map('trim', $rows[0]), static function ($value) {
            return $value !== '';
        })) : array();
        echo "SHEET: {$name}\n";
        echo 'ROWS_EXCLUDING_HEADER: ' . max(0, count($rows) - 1) . "\n";
        echo 'HEADERS: ' . implode(' | ', $headers) . "\n";

        if ($name === 'Data Notes') {
            foreach ($rows as $rowIndex => $row) {
                echo 'NOTE_ROW_' . ($rowIndex + 1) . ': ' . json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        $records = shoestagram_xlsx_rows_as_records($rows);
        $preview = array_slice($records, 0, 2);
        foreach ($preview as $index => $record) {
            echo 'PREVIEW_' . ($index + 1) . ': ' . json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Workbook audit failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
