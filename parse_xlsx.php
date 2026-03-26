<?php
// Simple script to print first N rows of the first worksheet in an XLSX file.
$xlsx = __DIR__ . '/ControlHorario.xlsx';
if (!file_exists($xlsx)) {
    die("File not found: $xlsx\n");
}
$zip = new ZipArchive();
if ($zip->open($xlsx) !== TRUE) {
    die("Failed to open XLSX as zip\n");
}

// Load shared strings if present
$sharedStrings = [];
if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
    $xml = simplexml_load_string($zip->getFromIndex($idx));
    $xml->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    foreach ($xml->si as $si) {
        // shared string can have multiple t nodes
        $text = '';
        if (isset($si->t)) {
            $text = (string)$si->t;
        } else {
            // rich text
            foreach ($si->r as $r) {
                $text .= (string)$r->t;
            }
        }
        $sharedStrings[] = $text;
    }
}

// Load first worksheet (sheet1)
$sheetName = 'xl/worksheets/sheet1.xml';
if (($idx = $zip->locateName($sheetName)) === false) {
    die("Sheet1 not found in XLSX\n");
}
$sheetXml = simplexml_load_string($zip->getFromIndex($idx));
$sheetXml->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

$rows = $sheetXml->sheetData->row;
$maxRows = 10;

function cellValue($c, $sharedStrings) {
    $t = (string)$c['t'];
    if ($t === 's') {
        $v = (string)$c->v;
        $idx = intval($v);
        return $sharedStrings[$idx] ?? '';
    }
    if ($t === 'inlineStr') {
        // Inline string is stored under <is><t>..</t></is>
        return isset($c->is->t) ? (string)$c->is->t : '';
    }
    return isset($c->v) ? (string)$c->v : '';
}

foreach ($rows as $i => $row) {
    $r = (int)$row['r'];
    echo "Row $r:\n";
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $val = cellValue($c, $sharedStrings);
        echo "  $ref = $val\n";
    }
    echo "\n";
    if ($r >= $maxRows) break;
}

echo "Done\n";
