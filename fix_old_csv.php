<?php
set_time_limit(0); // Für viele Dateien

$folder = 'C:/xampp/htdocs/SpotifyStreams/'; // Pfad zu deinen CSVs
$files = glob($folder . '*.csv'); // Alle CSV-Dateien

// Funktion: Titel normalisieren und * behalten
function normalizeTitle($title) {
    $title = trim($title);

    // * am Anfang merken
    $star = '';
    if (str_starts_with($title, '*')) {
        $star = '*';
        $title = ltrim($title, '* ');
    }

    if(class_exists('Normalizer')) $title = Normalizer::normalize($title, Normalizer::FORM_C);

    $title = str_replace(["'", "\u{2019}", "`"], "'", $title);
    $title = preg_replace('/[\p{Cc}\p{Cf}]+/u','',$title);
    $title = preg_replace('/\s+/u',' ',$title);

    return $star . trim($title);
}

// Funktion: CSV einlesen
function readCsv($file){
    $data = [];
    if(!file_exists($file)) return $data;

    $handle = fopen($file,'r');
    if(!$handle) return $data;

    $header = fgetcsv($handle);
    if(!$header){ fclose($handle); return $data; }

    // Spalten bestimmen
    $colRank    = array_search('Rank', $header);
    $colTitle   = array_search('Song Title', $header);
    $colStreams = array_search('Streams', $header);
    $colDaily   = array_search('Daily', $header);

    $rank_counter = 1;
    $hasRankColumn = $colRank !== false;

    if($colTitle === false) $colTitle = 1;
    if($colStreams === false) $colStreams = 2;
    if($colDaily === false) $colDaily = 3;

    while(($row=fgetcsv($handle)) !== false){
        $title = isset($row[$colTitle]) ? trim($row[$colTitle]) : '';
        if($title === '' || strtolower($title) === 'song title') continue;

        $key = normalizeTitle($title);

        $rank = $hasRankColumn && isset($row[$colRank]) && is_numeric($row[$colRank])
                ? (int)$row[$colRank]
                : $rank_counter;

        $streams = isset($row[$colStreams]) ? (int) preg_replace('/[^0-9]/','',$row[$colStreams]) : 0;
        $daily   = isset($row[$colDaily])   ? (int) preg_replace('/[^0-9]/','',$row[$colDaily])   : 0;

        $data[$key] = [ // Überschreibt Duplikate korrekt
            'rank' => $rank,
            'title' => $key,
            'streams' => $streams,
            'daily' => $daily
        ];

        $rank_counter++;
    }

    fclose($handle);
    return $data;
}

// CSV speichern
function saveCsv($file, $data){
    $handle = fopen($file,'w');
    fputcsv($handle, ['Rank','Song Title','Streams','Daily']);
    $rank_counter = 1;
    foreach($data as $row){
        fputcsv($handle, [
            $rank_counter,
            $row['title'],
            $row['streams'],
            $row['daily']
        ]);
        $rank_counter++;
    }
    fclose($handle);
}

// Alle CSVs durchlaufen und fixen
foreach($files as $file){
    $rows = readCsv($file);

    // Optional nach Streams absteigend sortieren
    usort($rows, function($a,$b){ return $b['streams'] - $a['streams']; });

    saveCsv($file, $rows);

    echo "CSV automatisch fixiert: $file – Songs: " . count($rows) . "<br>";
}
?>