<?php
// Pfad zur Test-CSV
$csv_file = __DIR__ . '/test_songs.csv';

// Minimaler CSV-Testinhalt (erstellt, falls Datei nicht existiert)
if (!file_exists($csv_file)) {
    $rows = [
        ["Rank","Song Title","Streams","Daily"],
        [1,"*Friendships (Lost My Love)",100000,5000],
        [2,"Paradise",90000,4500],
        [3,"*Remedy",85000,4000],
        [4,"Far Away From Home",80000,3500],
    ];

    $fp = fopen($csv_file,'w');
    foreach ($rows as $row) fputcsv($fp, $row);
    fclose($fp);
}

// CSV einlesen und Titel unverändert ausgeben
function readCsvKeepAsterisk($file) {
    $data = [];
    if(!file_exists($file)) return $data;

    $handle = fopen($file, 'r');
    if(!$handle) return $data;

    $header = fgetcsv($handle);
    if(!$header) { fclose($handle); return $data; }

    $colTitle = array_search('Song Title', $header);
    if($colTitle === false) $colTitle = 1;

    while(($row = fgetcsv($handle)) !== false) {
        $title = isset($row[$colTitle]) ? trim($row[$colTitle]) : '';
        if($title === '' || strtolower($title) === 'song title') continue;

        $data[] = $title; // Titel unverändert speichern
    }

    fclose($handle);
    return $data;
}

$titles = readCsvKeepAsterisk($csv_file);

echo "<h2>Schritt 1: Titel aus CSV</h2>";
echo "<ul>";
foreach($titles as $t) {
    echo "<li>" . htmlspecialchars($t) . "</li>";
}
echo "</ul>";
?>