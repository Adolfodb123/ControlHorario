<?php
require 'vendor/autoload.php';

$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load('ControlHorario.xlsx');
$sheet = $spreadsheet->getActiveSheet();

$rows = [];
foreach ($sheet->getRowIterator(1, 10) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $rowData = [];
    foreach ($cellIterator as $cell) {
        $rowData[] = $cell->getValue();
    }
    $rows[] = $rowData;
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
