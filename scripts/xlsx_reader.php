<?php

/**
 * Minimal read-only XLSX reader for trusted Shoestagram import workbooks.
 * It intentionally supports values/formula caches only; it never executes macros.
 */
function shoestagram_xlsx_read($path)
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive support is required to read XLSX files.');
    }

    $realPath = realpath($path);
    if ($realPath === false || !is_file($realPath)) {
        throw new RuntimeException('Workbook not found.');
    }

    $zip = new ZipArchive();
    if ($zip->open($realPath) !== true) {
        throw new RuntimeException('Workbook could not be opened as an XLSX archive.');
    }

    try {
        $sharedStrings = shoestagram_xlsx_shared_strings($zip);
        $workbookXml = shoestagram_xlsx_xml($zip, 'xl/workbook.xml');
        $relationshipsXml = shoestagram_xlsx_xml($zip, 'xl/_rels/workbook.xml.rels');
        $relationships = array();

        $relationshipsXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relationshipsXml->xpath('//r:Relationship') ?: array() as $relationship) {
            $attributes = $relationship->attributes();
            $relationships[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $workbookXml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = array();

        foreach ($workbookXml->xpath('//m:sheets/m:sheet') ?: array() as $sheetNode) {
            $attributes = $sheetNode->attributes();
            $relationshipAttributes = $sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) $relationshipAttributes['id'];
            $target = $relationships[$relationshipId] ?? '';
            if ($target === '') {
                throw new RuntimeException('A workbook sheet relationship is missing.');
            }

            $entry = shoestagram_xlsx_normalize_entry($target);
            $sheets[(string) $attributes['name']] = shoestagram_xlsx_read_sheet($zip, $entry, $sharedStrings);
        }

        return $sheets;
    } finally {
        $zip->close();
    }
}

function shoestagram_xlsx_xml($zip, $entry)
{
    $contents = $zip->getFromName($entry);
    if ($contents === false) {
        throw new RuntimeException('Required XLSX entry is missing: ' . $entry);
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$xml instanceof SimpleXMLElement) {
        throw new RuntimeException('Invalid XML in XLSX entry: ' . $entry);
    }

    return $xml;
}

function shoestagram_xlsx_normalize_entry($target)
{
    $target = str_replace('\\', '/', trim((string) $target));
    if (strpos($target, '/') === 0) {
        return ltrim($target, '/');
    }

    $segments = explode('/', 'xl/' . $target);
    $normalized = array();
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($normalized);
            continue;
        }
        $normalized[] = $segment;
    }

    return implode('/', $normalized);
}

function shoestagram_xlsx_shared_strings($zip)
{
    if ($zip->locateName('xl/sharedStrings.xml') === false) {
        return array();
    }

    $xml = shoestagram_xlsx_xml($zip, 'xl/sharedStrings.xml');
    $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $strings = array();
    foreach ($xml->xpath('//m:si') ?: array() as $item) {
        $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = array();
        foreach ($item->xpath('.//m:t') ?: array() as $textNode) {
            $parts[] = (string) $textNode;
        }
        $strings[] = implode('', $parts);
    }

    return $strings;
}

function shoestagram_xlsx_read_sheet($zip, $entry, $sharedStrings)
{
    $xml = shoestagram_xlsx_xml($zip, $entry);
    $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = array();

    foreach ($xml->xpath('//m:sheetData/m:row') ?: array() as $rowNode) {
        $rowNode->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $row = array();
        foreach ($rowNode->xpath('./m:c') ?: array() as $cellNode) {
            $cellNode->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $attributes = $cellNode->attributes();
            $reference = (string) $attributes['r'];
            $type = (string) $attributes['t'];
            $index = shoestagram_xlsx_column_index($reference);
            $value = '';

            if ($type === 'inlineStr') {
                $parts = array();
                foreach ($cellNode->xpath('./m:is//m:t') ?: array() as $textNode) {
                    $parts[] = (string) $textNode;
                }
                $value = implode('', $parts);
            } else {
                $valueNodes = $cellNode->xpath('./m:v') ?: array();
                $raw = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $raw] ?? '';
                } elseif ($type === 'b') {
                    $value = $raw === '1' ? 'TRUE' : 'FALSE';
                } else {
                    $value = $raw;
                }
            }

            $row[$index] = $value;
        }

        if ($row) {
            ksort($row);
            $lastIndex = (int) max(array_keys($row));
            $denseRow = array_fill(0, $lastIndex + 1, '');
            foreach ($row as $index => $value) {
                $denseRow[$index] = $value;
            }
            $rows[] = $denseRow;
        }
    }

    return $rows;
}

function shoestagram_xlsx_column_index($reference)
{
    if (!preg_match('/^([A-Z]+)/i', (string) $reference, $matches)) {
        return 0;
    }

    $letters = strtoupper($matches[1]);
    $index = 0;
    for ($position = 0, $length = strlen($letters); $position < $length; $position++) {
        $index = ($index * 26) + (ord($letters[$position]) - 64);
    }

    return $index - 1;
}

function shoestagram_xlsx_rows_as_records($rows)
{
    if (!$rows) {
        return array();
    }

    $headers = array_map(static function ($value) {
        return trim((string) $value);
    }, array_shift($rows));
    $records = array();

    foreach ($rows as $row) {
        $record = array();
        $hasValue = false;
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $value = trim((string) ($row[$index] ?? ''));
            $record[$header] = $value;
            $hasValue = $hasValue || $value !== '';
        }
        if ($hasValue) {
            $records[] = $record;
        }
    }

    return $records;
}
