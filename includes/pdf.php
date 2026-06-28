<?php

declare(strict_types=1);

function simple_pdf(string $title, array $lines): string
{
    $safeLines = [];
    $safeLines[] = 'BT';
    $safeLines[] = '/F1 18 Tf 50 800 Td (' . pdf_escape($title) . ') Tj';
    $safeLines[] = '/F1 11 Tf';
    $y = 775;
    foreach ($lines as $line) {
        if ($y < 60) {
            break;
        }
        $safeLines[] = sprintf('1 0 0 1 50 %d Tm (%s) Tj', $y, pdf_escape($line));
        $y -= 16;
    }
    $safeLines[] = 'ET';
    $content = implode("\n", $safeLines);

    $objects = [];
    $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
    $objects[] = '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj';
    $objects[] = '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj';
    $objects[] = '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj';
    $objects[] = '5 0 obj << /Length ' . strlen($content) . ' >> stream' . "\n" . $content . "\nendstream endobj";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= 'xref' . "\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }
    $pdf .= 'trailer << /Size 6 /Root 1 0 R >>' . "\nstartxref\n" . $xrefPosition . "\n%%EOF";

    return $pdf;
}

function pdf_escape(string $value): string
{
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
}
