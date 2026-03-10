<?php
$artist_file = __DIR__ . '/artist_urls.txt';
$csv_path = __DIR__;

// Künstler-URLs einlesen
$artist_urls = file($artist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$artist_urls) die("Keine Künstler in artist_urls.txt gefunden!");

foreach ($artist_urls as $line) {
    $parts = explode('#', $line);
    $url = trim($parts[0]);
    $artist_name_raw = isset($parts[1]) ? trim($parts[1]) : 'Unknown Artist';
    $artist_name = preg_replace('/[\/:*?"<>|]/', '', $artist_name_raw);

    echo "Processing $artist_name<br>";

    $existing_files = glob($csv_path . DIRECTORY_SEPARATOR . "$artist_name *.csv");
    $last_csv_date = null;
    if ($existing_files) {
        rsort($existing_files);
        $last_csv_file = $existing_files[0];
        $last_csv_date = substr(basename($last_csv_file), strlen($artist_name)+1, 10);
    }

    $context = stream_context_create(["ssl"=>["verify_peer"=>false,"verify_peer_name"=>false]]);
    
    $html = file_get_contents($url, false, $context);
    if (!$html) { echo "Cannot load $artist_name<br>"; continue; }

    if (preg_match('/Last updated:\s*(\d{4})\/(\d{2})\/(\d{2})/i', $html, $matches)) {

        $year  = $matches[1];
        $month = $matches[2];
        $day   = $matches[3];

        $chart_date = "$year-$month-$day";

        echo "Detected chart date: $chart_date<br>";

    } else { 
        echo "Date not found for $artist_name<br>"; 
        continue; 
    }

    if ($last_csv_date && strtotime($chart_date) <= strtotime($last_csv_date)) {
        echo "CSV exists for $artist_name $last_csv_date<br><br>";
        continue;
    }

    // HTML parsen
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $rows = $xpath->query("//table[@class='addpos sortable']/tbody/tr");
    if (!$rows || $rows->length === 0) {
        echo "No rows for $artist_name<br><br>";
        continue;
    }

    $today_data = [];
    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length < 3) continue;
        $link = $cols[0]->getElementsByTagName('a');
        if ($link->length === 0) continue;
        $title = trim($link->item(0)->nodeValue);
        $streams = (int) preg_replace('/[^0-9]/', '', $cols[1]->nodeValue);
        $daily   = (int) preg_replace('/[^0-9]/', '', $cols[2]->nodeValue);
        $rank = count($today_data) + 1;
        $today_data[$title] = ['rank' => $rank, 'streams' => $streams, 'daily' => $daily];
    }

    if (count($today_data) === 0) {
        echo "No songs for $artist_name<br><br>";
        continue;
    }

    // CSV erzeugen
    $filename = $csv_path . DIRECTORY_SEPARATOR . "$artist_name $chart_date.csv";
    $csv_rows = [["Rank", "Song Title", "Streams", "Daily"]];
    foreach ($today_data as $title => $data) {
        $csv_rows[] = [$data['rank'], $title, $data['streams'], $data['daily']];
    }

    $fp = fopen($filename, 'w');
    foreach ($csv_rows as $row) fputcsv($fp, $row);
    fclose($fp);

    echo "CSV created for $artist_name $chart_date<br><br>";
}
?>