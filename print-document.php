<?php

/**
 * Shared, dependency-free PDF presentation helpers for Shoestagram documents.
 * All coordinates use PDF points and all output is intentionally ASCII-safe for
 * the built-in Helvetica fonts used by the existing project.
 */

function shoestagram_pdf_clean_text($value)
{
    $value = str_replace(
        array('₱', '–', '—', '•', '“', '”', '’', '·'),
        array('PHP ', '-', '-', '-', '"', '"', "'", '-'),
        (string) $value
    );
    $value = preg_replace('/[^\x20-\x7E]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim((string) $value);
}

function shoestagram_pdf_escape($value)
{
    return str_replace(
        array('\\', '(', ')'),
        array('\\\\', '\\(', '\\)'),
        shoestagram_pdf_clean_text($value)
    );
}

function shoestagram_pdf_palette()
{
    return array(
        'ink' => '0.14 0.15 0.13',
        'muted' => '0.39 0.40 0.36',
        'line' => '0.86 0.86 0.82',
        'paper_soft' => '0.96 0.96 0.93',
        'paper_warm' => '0.98 0.95 0.92',
        'accent' => '0.92 0.48 0.31',
        'accent_dark' => '0.67 0.26 0.14',
        'olive' => '0.28 0.32 0.24',
        'white' => '1 1 1',
        'danger' => '0.72 0.20 0.20',
        'success' => '0.13 0.50 0.32',
        'warning' => '0.68 0.43 0.07',
    );
}

function shoestagram_pdf_text(&$commands, $x, $y, $text, $size = 10, $bold = false, $color = '0.14 0.15 0.13', $align = 'left')
{
    $safe_text = shoestagram_pdf_clean_text($text);
    $estimated_width = strlen($safe_text) * ((float) $size * 0.51);

    if ($align === 'right') {
        $x -= $estimated_width;
    } elseif ($align === 'center') {
        $x -= $estimated_width / 2;
    }

    $font = $bold ? 'F2' : 'F1';
    $commands[] = $color . ' rg';
    $commands[] = 'BT /' . $font . ' ' . number_format((float) $size, 2, '.', '')
        . ' Tf 1 0 0 1 ' . number_format((float) $x, 2, '.', '') . ' '
        . number_format((float) $y, 2, '.', '') . ' Tm (' . shoestagram_pdf_escape($safe_text) . ') Tj ET';
}

function shoestagram_pdf_rect(&$commands, $x, $y, $width, $height, $fill, $stroke = '', $line_width = 1)
{
    if ($fill !== '') {
        $commands[] = $fill . ' rg';
        $commands[] = $x . ' ' . $y . ' ' . $width . ' ' . $height . ' re f';
    }

    if ($stroke !== '') {
        $commands[] = number_format((float) $line_width, 2, '.', '') . ' w';
        $commands[] = $stroke . ' RG';
        $commands[] = $x . ' ' . $y . ' ' . $width . ' ' . $height . ' re S';
    }
}

function shoestagram_pdf_line(&$commands, $x1, $y1, $x2, $y2, $color, $line_width = 1)
{
    $commands[] = number_format((float) $line_width, 2, '.', '') . ' w';
    $commands[] = $color . ' RG';
    $commands[] = $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . ' l S';
}

function shoestagram_pdf_truncate($value, $width, $size = 8)
{
    $value = shoestagram_pdf_clean_text($value);
    $max_characters = max(3, (int) floor((float) $width / max(1, (float) $size * 0.52)));

    if (strlen($value) <= $max_characters) {
        return $value;
    }

    return rtrim(substr($value, 0, max(1, $max_characters - 3))) . '...';
}

function shoestagram_pdf_wrap($value, $width, $size = 9, $max_lines = 3)
{
    $value = shoestagram_pdf_clean_text($value);
    $max_characters = max(6, (int) floor((float) $width / max(1, (float) $size * 0.52)));
    $lines = explode("\n", wordwrap($value, $max_characters, "\n", true));

    if (count($lines) > $max_lines) {
        $lines = array_slice($lines, 0, $max_lines);
        $lines[$max_lines - 1] = shoestagram_pdf_truncate($lines[$max_lines - 1], $width, $size);
    }

    return $lines;
}

function shoestagram_pdf_build($page_commands, $page_width = 595, $page_height = 842)
{
    $page_commands = array_values((array) $page_commands);
    $page_count = count($page_commands);
    $page_references = array();

    for ($index = 0; $index < $page_count; $index++) {
        $page_references[] = (5 + ($index * 2)) . ' 0 R';
    }

    $objects = array(
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [' . implode(' ', $page_references) . '] /Count ' . $page_count . ' >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    );

    foreach ($page_commands as $index => $commands) {
        $content_object = 6 + ($index * 2);
        $stream = implode("\n", (array) $commands) . "\n";
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . (int) $page_width . ' ' . (int) $page_height
            . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /ProcSet [/PDF /Text] >> /Contents '
            . $content_object . ' 0 R >>';
        $objects[] = "<< /Length " . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = array(0);

    foreach ($objects as $index => $object) {
        $object_number = $index + 1;
        $offsets[$object_number] = strlen($pdf);
        $pdf .= $object_number . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xref_offset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($index = 1; $index <= count($objects); $index++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$index]) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xref_offset . "\n%%EOF";
    return $pdf;
}

function shoestagram_pdf_output($filename, $pdf)
{
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) $filename);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen((string) $pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit();
}

function shoestagram_pdf_date_label($value, $with_time = false)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        $manila = new DateTimeZone('Asia/Manila');
        $date = new DateTimeImmutable($value, $manila);
        return $date->setTimezone($manila)->format($with_time ? 'F j, Y - g:i A' : 'F j, Y');
    } catch (Exception $exception) {
        return shoestagram_pdf_clean_text($value);
    }
}

function shoestagram_build_sales_report_pdf($options)
{
    $palette = shoestagram_pdf_palette();
    $page_width = 842;
    $page_height = 595;
    $margin_x = 40;
    $content_right = $page_width - $margin_x;
    $title = shoestagram_pdf_clean_text($options['title'] ?? 'Sales Report');
    $scope_label = shoestagram_pdf_clean_text($options['scope_label'] ?? 'Completed sales and transaction history');
    $generated_at = shoestagram_pdf_date_label($options['generated_at'] ?? 'now', true);
    $filters = array_values((array) ($options['filters'] ?? array()));
    $summary = array_values((array) ($options['summary'] ?? array()));
    $transactions = array_values((array) ($options['transactions'] ?? array()));
    $history = array_values((array) ($options['history'] ?? array()));
    $secondary_metrics = array_values((array) ($options['secondary_metrics'] ?? array()));
    $pages = array();
    $commands = array();
    $y = 0;

    $start_page = function ($continuation = false) use (&$pages, &$commands, &$y, $palette, $page_width, $page_height, $margin_x, $content_right, $title, $scope_label, $generated_at) {
        if (!empty($commands)) {
            $pages[] = $commands;
        }

        $commands = array();
        shoestagram_pdf_rect($commands, 0, $page_height - 40, $page_width, 40, $palette['olive']);
        shoestagram_pdf_rect($commands, 0, $page_height - 44, $page_width, 4, $palette['accent']);
        shoestagram_pdf_text($commands, $margin_x, $page_height - 25, 'SHOESTAGRAM', 15, true, $palette['white']);
        shoestagram_pdf_text($commands, $content_right, $page_height - 24, $continuation ? 'SALES REPORT - CONTINUED' : 'OFFICIAL SALES REPORT', 8, true, $palette['white'], 'right');

        if ($continuation) {
            shoestagram_pdf_text($commands, $margin_x, $page_height - 72, $title, 17, true, $palette['ink']);
            shoestagram_pdf_text($commands, $content_right, $page_height - 70, 'Generated ' . $generated_at, 7.5, false, $palette['muted'], 'right');
            shoestagram_pdf_line($commands, $margin_x, $page_height - 84, $content_right, $page_height - 84, $palette['line']);
            $y = $page_height - 105;
            return;
        }

        shoestagram_pdf_text($commands, $margin_x, $page_height - 80, $title, 23, true, $palette['ink']);
        shoestagram_pdf_text($commands, $margin_x, $page_height - 98, $scope_label, 8.5, false, $palette['muted']);
        shoestagram_pdf_text($commands, $content_right, $page_height - 80, 'Generated ' . $generated_at, 8, false, $palette['muted'], 'right');
        $y = $page_height - 122;
    };

    $start_page(false);

    shoestagram_pdf_text($commands, $margin_x, $y, 'REPORT SCOPE', 8, true, $palette['accent_dark']);
    $y -= 52;
    shoestagram_pdf_rect($commands, $margin_x, $y, $page_width - ($margin_x * 2), 42, $palette['paper_soft'], $palette['line']);
    $filter_count = max(1, count($filters));
    $filter_width = ($page_width - ($margin_x * 2) - 20) / $filter_count;

    foreach ($filters as $index => $filter) {
        $x = $margin_x + 10 + ($index * $filter_width);
        shoestagram_pdf_text($commands, $x, $y + 27, strtoupper(shoestagram_pdf_clean_text($filter['label'] ?? 'Filter')), 6.5, true, $palette['muted']);
        shoestagram_pdf_text($commands, $x, $y + 12, shoestagram_pdf_truncate($filter['value'] ?? 'All', $filter_width - 12, 8), 8, true, $palette['ink']);
    }

    $y -= 25;
    shoestagram_pdf_text($commands, $margin_x, $y, 'SUMMARY', 8, true, $palette['accent_dark']);
    $y -= 66;
    $summary_count = max(1, count($summary));
    $summary_gap = 9;
    $summary_width = (($page_width - ($margin_x * 2)) - ($summary_gap * ($summary_count - 1))) / $summary_count;

    foreach ($summary as $index => $item) {
        $x = $margin_x + ($index * ($summary_width + $summary_gap));
        shoestagram_pdf_rect($commands, $x, $y, $summary_width, 54, $palette['paper_warm'], $palette['line']);
        shoestagram_pdf_text($commands, $x + 10, $y + 37, strtoupper(shoestagram_pdf_clean_text($item['label'] ?? 'Metric')), 6.3, true, $palette['muted']);
        shoestagram_pdf_text($commands, $x + 10, $y + 15, shoestagram_pdf_truncate($item['value'] ?? '', $summary_width - 20, 13), 13, true, $palette['ink']);
    }

    if (!empty($secondary_metrics)) {
        $y -= 20;
        $metric_parts = array();
        foreach ($secondary_metrics as $item) {
            $metric_parts[] = shoestagram_pdf_clean_text($item['label'] ?? '') . ': ' . shoestagram_pdf_clean_text($item['value'] ?? '');
        }
        shoestagram_pdf_text($commands, $margin_x, $y, implode('   |   ', $metric_parts), 7.5, false, $palette['muted']);
    }

    $y -= 28;
    shoestagram_pdf_text($commands, $margin_x, $y, 'TRANSACTION DETAIL', 9, true, $palette['accent_dark']);
    $y -= 22;

    $columns = array(
        array('label' => 'Date', 'key' => 'date', 'width' => 60, 'align' => 'left'),
        array('label' => 'Reference', 'key' => 'reference', 'width' => 74, 'align' => 'left'),
        array('label' => 'Customer', 'key' => 'customer', 'width' => 105, 'align' => 'left'),
        array('label' => 'Product', 'key' => 'product', 'width' => 130, 'align' => 'left'),
        array('label' => 'Channel', 'key' => 'channel', 'width' => 58, 'align' => 'left'),
        array('label' => 'Qty', 'key' => 'quantity', 'width' => 30, 'align' => 'right'),
        array('label' => 'Total (PHP)', 'key' => 'amount', 'width' => 73, 'align' => 'right'),
        array('label' => 'Status', 'key' => 'status', 'width' => 67, 'align' => 'left'),
        array('label' => 'Source', 'key' => 'source', 'width' => 123, 'align' => 'left'),
    );

    $draw_transaction_header = function () use (&$commands, &$y, $columns, $margin_x, $palette) {
        $table_width = 0;
        foreach ($columns as $column) {
            $table_width += $column['width'];
        }
        shoestagram_pdf_rect($commands, $margin_x, $y - 3, $table_width, 21, $palette['paper_soft'], $palette['line']);
        $x = $margin_x;
        foreach ($columns as $column) {
            $text_x = $column['align'] === 'right' ? $x + $column['width'] - 6 : $x + 6;
            shoestagram_pdf_text($commands, $text_x, $y + 4, strtoupper($column['label']), 6.2, true, $palette['muted'], $column['align']);
            $x += $column['width'];
        }
        $y -= 24;
    };

    $draw_transaction_header();

    if (empty($transactions)) {
        shoestagram_pdf_rect($commands, $margin_x, $y - 22, 720, 32, $palette['white'], $palette['line']);
        shoestagram_pdf_text($commands, $margin_x + 10, $y - 8, 'No transactions matched the selected filters.', 8, false, $palette['muted']);
        $y -= 42;
    } else {
        foreach ($transactions as $row_index => $transaction) {
            if ($y < 62) {
                $start_page(true);
                shoestagram_pdf_text($commands, $margin_x, $y, 'TRANSACTION DETAIL', 9, true, $palette['accent_dark']);
                $y -= 22;
                $draw_transaction_header();
            }

            if ($row_index % 2 === 1) {
                shoestagram_pdf_rect($commands, $margin_x, $y - 8, 720, 23, '0.985 0.985 0.975');
            }

            $row = array(
                'date' => shoestagram_pdf_date_label($transaction['created_at'] ?? ''),
                'reference' => $transaction['reference'] ?? '',
                'customer' => $transaction['customer'] ?? '',
                'product' => $transaction['product_name'] ?? '',
                'channel' => strtoupper((string) ($transaction['transaction_type'] ?? 'ONLINE')) === 'WALK-IN' ? 'Walk-in' : 'Online',
                'quantity' => (string) ((int) ($transaction['quantity'] ?? 0)),
                'amount' => number_format((float) ($transaction['amount'] ?? 0), 2),
                'status' => $transaction['status'] ?? '',
                'source' => $transaction['source_label'] ?? '',
            );
            $x = $margin_x;
            foreach ($columns as $column) {
                $text_x = $column['align'] === 'right' ? $x + $column['width'] - 6 : $x + 6;
                $value = shoestagram_pdf_truncate($row[$column['key']] ?? '', $column['width'] - 12, 6.7);
                shoestagram_pdf_text($commands, $text_x, $y, $value, 6.7, false, $palette['ink'], $column['align']);
                $x += $column['width'];
            }
            shoestagram_pdf_line($commands, $margin_x, $y - 9, $margin_x + 720, $y - 9, $palette['line'], 0.5);
            $y -= 23;
        }
    }

    if ($y < 150) {
        $start_page(true);
    } else {
        $y -= 16;
    }

    shoestagram_pdf_text($commands, $margin_x, $y, 'MONTHLY SALES PERFORMANCE', 9, true, $palette['accent_dark']);
    $y -= 25;
    shoestagram_pdf_rect($commands, $margin_x, $y - 3, 420, 21, $palette['paper_soft'], $palette['line']);
    shoestagram_pdf_text($commands, $margin_x + 7, $y + 4, 'PERIOD', 6.5, true, $palette['muted']);
    shoestagram_pdf_text($commands, $margin_x + 268, $y + 4, 'UNITS', 6.5, true, $palette['muted'], 'right');
    shoestagram_pdf_text($commands, $margin_x + 410, $y + 4, 'REVENUE (PHP)', 6.5, true, $palette['muted'], 'right');
    $y -= 24;

    if (empty($history)) {
        shoestagram_pdf_text($commands, $margin_x + 7, $y, 'No monthly history is available for this filter.', 8, false, $palette['muted']);
    } else {
        foreach ($history as $point) {
            if ($y < 62) {
                $start_page(true);
                shoestagram_pdf_text($commands, $margin_x, $y, 'MONTHLY SALES PERFORMANCE - CONTINUED', 9, true, $palette['accent_dark']);
                $y -= 25;
            }
            shoestagram_pdf_text($commands, $margin_x + 7, $y, $point['label'] ?? '', 8, false, $palette['ink']);
            shoestagram_pdf_text($commands, $margin_x + 268, $y, (string) ((int) ($point['units'] ?? 0)), 8, false, $palette['ink'], 'right');
            shoestagram_pdf_text($commands, $margin_x + 410, $y, number_format((float) ($point['revenue'] ?? 0), 2), 8, true, $palette['ink'], 'right');
            shoestagram_pdf_line($commands, $margin_x, $y - 8, $margin_x + 420, $y - 8, $palette['line'], 0.5);
            $y -= 21;
        }
    }

    if (!empty($commands)) {
        $pages[] = $commands;
    }

    $page_count = count($pages);
    foreach ($pages as $index => &$page) {
        shoestagram_pdf_line($page, $margin_x, 34, $content_right, 34, $palette['line'], 0.6);
        shoestagram_pdf_text($page, $margin_x, 20, 'Shoestagram - Sales Forecasting and Product Recommendation System', 6.5, false, $palette['muted']);
        shoestagram_pdf_text($page, $content_right, 20, 'Page ' . ($index + 1) . ' of ' . $page_count, 6.5, false, $palette['muted'], 'right');
    }
    unset($page);

    return shoestagram_pdf_build($pages, $page_width, $page_height);
}

function shoestagram_build_reservation_receipt_pdf($order, $support_email = '')
{
    $palette = shoestagram_pdf_palette();
    $commands = array();
    $page_width = 612;
    $page_height = 792;
    $left = 44;
    $right = 568;
    $content_width = $right - $left;
    $created_at = (string) ($order['created_at'] ?? '');
    $order_id = function_exists('store_state_checkout_reference') ? store_state_checkout_reference($order) : (string) ($order['id'] ?? 'Reservation');
    $items = function_exists('store_state_checkout_items') ? store_state_checkout_items((string) ($order['id'] ?? '')) : array();
    if (!$items) {
        $items = array($order);
    }
    $grand_total = array_sum(array_map(function ($item) { return (float) ($item['subtotal'] ?? 0); }, $items));
    $status = (string) ($order['status'] ?? 'Pending');
    $payment_method = (string) ($order['payment_method'] ?? 'In-Store Payment');
    $payment_status = (string) ($order['payment_status'] ?? 'Unpaid');
    $channel_type = strtoupper((string) ($order['transaction_type'] ?? 'ONLINE')) === 'WALK-IN' ? 'Walk-in' : 'Online';
    $channel_detail = trim((string) ($order['channel'] ?? ''));
    $channel = $channel_detail !== '' && stripos($channel_detail, $channel_type) === false
        ? $channel_type . ' - ' . $channel_detail
        : ($channel_detail !== '' ? $channel_detail : $channel_type);

    shoestagram_pdf_rect($commands, 0, 720, $page_width, 72, $palette['olive']);
    shoestagram_pdf_rect($commands, 0, 714, $page_width, 6, $palette['accent']);
    shoestagram_pdf_text($commands, $left, 761, 'SHOESTAGRAM', 19, true, $palette['white']);
    shoestagram_pdf_text($commands, $left, 742, 'SNEAKERS & STREETWEAR', 7.5, true, '0.88 0.89 0.85');
    shoestagram_pdf_text($commands, $right, 760, 'RESERVATION #' . $order_id, 10, true, $palette['white'], 'right');
    shoestagram_pdf_text($commands, $right, 742, shoestagram_pdf_date_label($created_at, true), 8, false, '0.88 0.89 0.85', 'right');

    shoestagram_pdf_text($commands, $left, 681, 'Reservation Receipt', 24, true, $palette['ink']);
    shoestagram_pdf_text($commands, $left, 660, 'Confirmation and pickup record', 9, false, $palette['muted']);

    $status_color = $palette['success'];
    $status_fill = '0.92 0.97 0.93';
    if (stripos($status, 'pending') !== false) {
        $status_color = $palette['warning'];
        $status_fill = '0.98 0.95 0.87';
    } elseif (stripos($status, 'cancel') !== false) {
        $status_color = $palette['danger'];
        $status_fill = '0.99 0.92 0.92';
    } elseif (stripos($status, 'confirm') !== false) {
        $status_color = '0.16 0.38 0.62';
        $status_fill = '0.92 0.95 0.98';
    }
    shoestagram_pdf_rect($commands, 445, 651, 123, 28, $status_fill, $status_color);
    shoestagram_pdf_text($commands, 506.5, 661, strtoupper(shoestagram_pdf_truncate($status, 104, 8)), 8, true, $status_color, 'center');

    shoestagram_pdf_text($commands, $left, 627, 'RESERVATION DETAILS', 8, true, $palette['accent_dark']);
    shoestagram_pdf_line($commands, $left, 617, $right, 617, $palette['line']);

    $box_gap = 9;
    $box_width = ($content_width - ($box_gap * 2)) / 3;
    $details = array(
        array('Customer', (string) ($order['customer_name'] ?? '')),
        array('Transaction date/time', shoestagram_pdf_date_label($created_at, true)),
        array('Channel', $channel),
        array('Order status', $status),
        array('Payment method', $payment_method),
        array('Payment status', $payment_status),
    );

    foreach ($details as $index => $detail) {
        $row = intdiv($index, 3);
        $column = $index % 3;
        $x = $left + ($column * ($box_width + $box_gap));
        $box_y = 559 - ($row * 61);
        shoestagram_pdf_rect($commands, $x, $box_y, $box_width, 50, $palette['paper_soft'], $palette['line']);
        shoestagram_pdf_text($commands, $x + 10, $box_y + 33, strtoupper($detail[0]), 6.3, true, $palette['muted']);
        shoestagram_pdf_text($commands, $x + 10, $box_y + 14, shoestagram_pdf_truncate($detail[1], $box_width - 20, 8.3), 8.3, true, $palette['ink']);
    }

    shoestagram_pdf_text($commands, $left, 475, 'PRODUCT SUMMARY', 8, true, $palette['accent_dark']);
    if (trim((string) ($order['payment_reference'] ?? '')) !== '') {
        shoestagram_pdf_text($commands, $right, 475, 'Payment ref: ' . (string) $order['payment_reference'], 7, false, $palette['muted'], 'right');
    }

    $column_widths = array(205, 72, 50, 95, 102);
    $headers = array('Product', 'Size', 'Qty', 'Price (PHP)', 'Total (PHP)');
    shoestagram_pdf_rect($commands, $left, 438, $content_width, 24, $palette['paper_soft'], $palette['line']);
    $x = $left;
    foreach ($headers as $index => $header) {
        $align = $index >= 2 ? 'right' : 'left';
        $text_x = $align === 'right' ? $x + $column_widths[$index] - 8 : $x + 8;
        shoestagram_pdf_text($commands, $text_x, 446, strtoupper($header), 6.4, true, $palette['muted'], $align);
        $x += $column_widths[$index];
    }

    $pages = array();
    $remaining = array_values($items);
    $first_page = true;
    do {
        if (!$first_page) {
            $commands = array();
            shoestagram_pdf_rect($commands, 0, 720, $page_width, 72, $palette['olive']);
            shoestagram_pdf_text($commands, $left, 758, 'SHOESTAGRAM', 17, true, $palette['white']);
            shoestagram_pdf_text($commands, $right, 758, 'RESERVATION #' . $order_id, 9, true, $palette['white'], 'right');
            shoestagram_pdf_text($commands, $left, 690, 'PRODUCT SUMMARY - CONTINUED', 9, true, $palette['accent_dark']);
            shoestagram_pdf_rect($commands, $left, 652, $content_width, 24, $palette['paper_soft'], $palette['line']);
            $x = $left;
            foreach ($headers as $index => $header) {
                $align = $index >= 2 ? 'right' : 'left';
                shoestagram_pdf_text($commands, $align === 'right' ? $x + $column_widths[$index] - 8 : $x + 8, 660, strtoupper($header), 6.4, true, $palette['muted'], $align);
                $x += $column_widths[$index];
            }
        }
        $row_top = $first_page ? 438 : 652;
        $capacity = $first_page ? 7 : 16;
        foreach (array_splice($remaining, 0, $capacity) as $index => $item) {
            $row_y = $row_top - (($index + 1) * 29);
            shoestagram_pdf_rect($commands, $left, $row_y, $content_width, 29, $palette['white'], $palette['line']);
            $values = array(
                (string) ($item['product_name'] ?? 'Product'),
                function_exists('store_order_size_display') ? store_order_size_display($item) : (string) ($item['size'] ?? ''),
                (string) max(1, (int) ($item['quantity'] ?? 1)),
                number_format((float) ($item['price_each'] ?? 0), 2),
                number_format((float) ($item['subtotal'] ?? 0), 2),
            );
            $x = $left;
            foreach ($values as $value_index => $value) {
                $align = $value_index >= 2 ? 'right' : 'left';
                $text_x = $align === 'right' ? $x + $column_widths[$value_index] - 8 : $x + 8;
                shoestagram_pdf_text($commands, $text_x, $row_y + 10, shoestagram_pdf_truncate($value, $column_widths[$value_index] - 16, 8.2), 8.2, $value_index === 0 || $value_index === 4, $palette['ink'], $align);
                $x += $column_widths[$value_index];
            }
        }
        if (!$remaining) {
            shoestagram_pdf_text($commands, $left, 201, 'PICKUP INFORMATION', 8, true, $palette['accent_dark']);
            shoestagram_pdf_text($commands, $left, 184, shoestagram_pdf_truncate($order['pickup_window'] ?? 'Awaiting confirmation', 300, 8), 8, false, $palette['muted']);
            shoestagram_pdf_text($commands, 390, 175, 'TOTAL (PHP)', 10, true, $palette['accent_dark']);
            shoestagram_pdf_text($commands, $right, 175, number_format($grand_total, 2), 12, true, $palette['accent_dark'], 'right');
            shoestagram_pdf_text($commands, $left, 130, 'Bring this receipt or your reservation number when collecting your items.', 7.5, false, $palette['muted']);
            shoestagram_pdf_text($commands, $left, 113, 'Thank you for choosing Shoestagram.', 11, true, $palette['ink']);
            if ($support_email !== '') {
                shoestagram_pdf_text($commands, $left, 95, 'Support: ' . $support_email, 7.5, false, $palette['muted']);
            }
        } else {
            shoestagram_pdf_text($commands, $right, 175, 'Item list continues on next page', 8, true, $palette['accent_dark'], 'right');
        }
        shoestagram_pdf_line($commands, $left, 64, $right, 64, $palette['line']);
        shoestagram_pdf_text($commands, $left, 48, 'Computer-generated reservation receipt - no signature required.', 6.8, false, $palette['muted']);
        shoestagram_pdf_text($commands, $right, 48, 'Generated ' . shoestagram_pdf_date_label('now', true), 6.8, false, $palette['muted'], 'right');
        $pages[] = $commands;
        $first_page = false;
    } while ($remaining);

    return shoestagram_pdf_build($pages, $page_width, $page_height);
}

function shoestagram_build_text_report_pdf($title, $lines)
{
    $palette = shoestagram_pdf_palette();
    $pages = array();
    $commands = array();
    $y = 0;
    $start_page = function () use (&$pages, &$commands, &$y, $palette, $title) {
        if (!empty($commands)) {
            $pages[] = $commands;
        }
        $commands = array();
        shoestagram_pdf_rect($commands, 0, 802, 595, 40, $palette['olive']);
        shoestagram_pdf_rect($commands, 0, 798, 595, 4, $palette['accent']);
        shoestagram_pdf_text($commands, 44, 817, 'SHOESTAGRAM', 15, true, $palette['white']);
        shoestagram_pdf_text($commands, 44, 760, $title, 21, true, $palette['ink']);
        shoestagram_pdf_text($commands, 551, 760, 'Generated ' . shoestagram_pdf_date_label('now', true), 7.5, false, $palette['muted'], 'right');
        shoestagram_pdf_line($commands, 44, 744, 551, 744, $palette['line']);
        $y = 716;
    };

    $start_page();
    foreach ((array) $lines as $line) {
        $wrapped = shoestagram_pdf_wrap($line, 507, 9, 5);
        foreach ($wrapped as $wrapped_line) {
            if ($y < 62) {
                $start_page();
            }
            shoestagram_pdf_text($commands, 44, $y, $wrapped_line, 9, false, $palette['ink']);
            $y -= 16;
        }
        $y -= 4;
    }
    if (!empty($commands)) {
        $pages[] = $commands;
    }

    $count = count($pages);
    foreach ($pages as $index => &$page) {
        shoestagram_pdf_line($page, 44, 36, 551, 36, $palette['line']);
        shoestagram_pdf_text($page, 44, 21, 'Shoestagram', 7, false, $palette['muted']);
        shoestagram_pdf_text($page, 551, 21, 'Page ' . ($index + 1) . ' of ' . $count, 7, false, $palette['muted'], 'right');
    }
    unset($page);

    return shoestagram_pdf_build($pages, 595, 842);
}
