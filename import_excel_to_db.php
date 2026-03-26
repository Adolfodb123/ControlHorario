<?php
// import_excel_to_db.php
// Usage:
//   php import_excel_to_db.php [--truncate] [--limit=N] [--dry-run]
//
// This script reads ControlHorario.xlsx (first worksheet) and imports rows into
// the MySQL table "empleados_anual" in the local database.
// It maps specific columns from the spreadsheet into the table fields.

function e($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$options = getopt("", ["truncate", "limit:", "dry-run"]);
$doTruncate = isset($options['truncate']);
$limit = isset($options['limit']) ? intval($options['limit']) : null;
$dryRun = isset($options['dry-run']);

$xlsx = __DIR__ . '/ControlHorario.xlsx';
if (!file_exists($xlsx)) {
    fwrite(STDERR, "Error: XLSX file not found at $xlsx\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($xlsx) !== true) {
    fwrite(STDERR, "Error: Unable to open XLSX as zip archive\n");
    exit(1);
}

$sharedStrings = [];
if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
    $xml = simplexml_load_string($zip->getFromIndex($idx));
    foreach ($xml->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
            continue;
        }
        $t = '';
        foreach ($si->r as $r) {
            $t .= (string)$r->t;
        }
        $sharedStrings[] = $t;
    }
}

$sheetPath = 'xl/worksheets/sheet1.xml';
if (($idx = $zip->locateName($sheetPath)) === false) {
    fwrite(STDERR, "Error: Sheet1 not found in XLSX\n");
    exit(1);
}

$sheetXml = simplexml_load_string($zip->getFromIndex($idx));

function getCellValue($c, $sharedStrings) {
    $t = (string)$c['t'];
    if ($t === 's') {
        $v = (string)$c->v;
        $idx = intval($v);
        return $sharedStrings[$idx] ?? '';
    }
    if ($t === 'inlineStr') {
        return isset($c->is->t) ? (string)$c->is->t : '';
    }
    return isset($c->v) ? (string)$c->v : '';
}

function excelDateToMysqlDate($serial) {
    if ($serial === '' || $serial === null) {
        return null;
    }
    if (!is_numeric($serial)) {
        return null;
    }
    // Excel uses 1900 date system with leap year bug; this formula works for serial >= 1.
    $serial = floatval($serial);
    if ($serial <= 0) {
        return null;
    }
    // Convert to Unix timestamp (seconds) in UTC
    $unix = ($serial - 25569) * 86400;
    // Create date in UTC
    $dt = new DateTime('@' . round($unix));
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d');
}

function excelTimeToMysqlTime($serial) {
    if ($serial === '' || $serial === null) {
        return null;
    }
    if (!is_numeric($serial)) {
        return null;
    }
    $serial = floatval($serial);
    // Treat 0 / empty as no time set
    if ($serial <= 0.0001) {
        return null;
    }
    // Excel time is a fraction of a day.
    $seconds = round($serial * 86400);
    $seconds = $seconds % 86400;
    return gmdate('H:i', $seconds);
}

$rows = $sheetXml->sheetData->row;

// Calculate number of rows to import
$totalRows = count($rows);

// We'll iterate over rows but stop early if a limit is set.

// Connect to database
$mysqli = new mysqli('localhost', 'root', '', 'controlhorario_cmw');
if ($mysqli->connect_error) {
    fwrite(STDERR, "Error connecting to MySQL: " . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

if ($doTruncate) {
    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] Would truncate empleados_anual\n");
    } else {
        fwrite(STDOUT, "Truncating empleados_anual...\n");
        $mysqli->query('TRUNCATE TABLE empleados_anual');
    }
}

$insertSql = "INSERT INTO empleados_anual (full_name, Equipo, nombre_mes, dia_semana, Festivo, Justificado, `date`, horas, clock_in, clock_out) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";
$stmt = $mysqli->prepare($insertSql);
if (!$stmt) {
    fwrite(STDERR, "Error preparing insert statement: " . $mysqli->error . "\n");
    exit(1);
}

$rowCount = 0;
$inserted = 0;
foreach ($rows as $row) {
    $rowCount++;
    if ($limit !== null && $rowCount > $limit) {
        break;
    }

    // Skip entirely empty rows (common in XLSX)
    $cells = [];
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $cells[$ref] = getCellValue($c, $sharedStrings);
    }

    // Determine if the row contains any meaningful data
    $hasData = false;
    foreach ($cells as $v) {
        if (trim($v) !== '') {
            $hasData = true;
            break;
        }
    }
    if (!$hasData) {
        continue;
    }

    // Map columns (based on observed spreadsheet structure)
    $fullName = trim($cells['L' . $row['r']] ?? '');
    $equipo = trim($cells['N' . $row['r']] ?? '');
    if ($equipo === '') {
        // fallback to country/team column if needed
        $equipo = trim($cells['D' . $row['r']] ?? '');
    }

    $nombreMes = trim($cells['G' . $row['r']] ?? '');
    $diaSemana = trim($cells['F' . $row['r']] ?? '');
    $festivo = trim($cells['O' . $row['r']] ?? '');
    $justificado = trim($cells['U' . $row['r']] ?? '');

    $excelDate = $cells['E' . $row['r']] ?? '';
    $date = excelDateToMysqlDate($excelDate);

    $horas = str_replace(',', '.', trim($cells['X' . $row['r']] ?? ''));
    if ($horas === '' || !is_numeric($horas)) {
        $horas = 0;
    } else {
        $horas = floatval($horas);
    }

    $clockIn = excelTimeToMysqlTime($cells['H' . $row['r']] ?? '');
    $clockOut = excelTimeToMysqlTime($cells['I' . $row['r']] ?? '');

    // Skip rows which don't have a full name (likely not actual employee entries)
    $lowerName = mb_strtolower(trim($fullName));
    if ($lowerName === '' || in_array($lowerName, ['full_name', 'nombre', 'nombre completo', 'nombre_completo'])) {
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] Row {$row['r']}: full_name='{$fullName}', equipo='{$equipo}', date='{$date}', horas={$horas}\n");
        continue;
    }

    $stmt->bind_param('ssssssssss', $fullName, $equipo, $nombreMes, $diaSemana, $festivo, $justificado, $date, $horas, $clockIn, $clockOut);
    if (!$stmt->execute()) {
        fwrite(STDERR, "Error inserting row {$row['r']}: " . $stmt->error . "\n");
        continue;
    }

    $inserted++;
    if ($inserted % 500 === 0) {
        fwrite(STDOUT, "Inserted $inserted rows...\n");
    }
}

fwrite(STDOUT, "Done. Processed $rowCount rows. Inserted $inserted rows.\n");

$stmt->close();
$mysqli->close();
