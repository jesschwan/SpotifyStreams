<?php
$csv_file = 'C:/xampp/htdocs/SpotifyStreams/Leony 2026-03-22.csv'; // alte CSV wählen

// Titel normalisieren (inklusive * vorne)
function normalizeTitle($title) {
    $title = trim($title);
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

// CSV einlesen
function readOldCsv($file){
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
        $rawTitle = isset($row[$colTitle]) ? trim($row[$colTitle]) : '';
        if($rawTitle === '' || strtolower($rawTitle) === 'song title') continue;

        $key = normalizeTitle($rawTitle);

        $rank = $hasRankColumn && isset($row[$colRank]) && is_numeric($row[$colRank])
                ? (int)$row[$colRank]
                : $rank_counter;

        $streams = isset($row[$colStreams]) ? (int) preg_replace('/[^0-9]/','',$row[$colStreams]) : 0;
        $daily   = isset($row[$colDaily])   ? (int) preg_replace('/[^0-9]/','',$row[$colDaily])   : 0;

        $data[] = [
            'rank' => $rank,
            'title' => $key, // jetzt mit * vorne, falls vorhanden
            'streams' => $streams,
            'daily' => $daily
        ];

        $rank_counter++;
    }

    fclose($handle);
    return $data;
}

// Test ausführen
$rows = readOldCsv($csv_file);

echo "<h2>Test: Alte CSV einlesen</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Rank</th><th>Titel</th><th>Streams</th><th>Daily</th></tr>";

foreach($rows as $r){
    echo "<tr>";
    echo "<td>".$r['rank']."</td>";
    echo "<td>".htmlspecialchars($r['title'])."</td>";
    echo "<td>".number_format($r['streams'],0,',','.')."</td>";
    echo "<td>".number_format($r['daily'],0,',','.')."</td>";
    echo "</tr>";
}

echo "</table>";
?>