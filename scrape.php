<?php
$artist_file = __DIR__ . '/artist_urls.txt';
$csv_path = __DIR__;

// Künstler-URLs einlesen
$artist_urls = file($artist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$artist_urls) die("Keine Künstler in artist_urls.txt gefunden!");

// Durch alle Künstler iterieren
foreach ($artist_urls as $line) {
    $parts = explode('#', $line);
    $url = trim($parts[0]);
    $artist_name_raw = isset($parts[1]) ? trim($parts[1]) : 'Unknown Artist';
    $artist_name = preg_replace('/[\/:*?"<>|]/', '', $artist_name_raw);

    echo "Processing $artist_name<br>";

    // **Künstlerordner erstellen**
    $artist_folder = $csv_path . DIRECTORY_SEPARATOR . $artist_name;
    if (!is_dir($artist_folder)) {
        mkdir($artist_folder, 0777, true);
        echo "Ordner erstellt: $artist_folder<br>";
    }

    // Letzte CSV prüfen
    $existing_files = glob($artist_folder . DIRECTORY_SEPARATOR . '*.csv');
    $last_csv_date = null;
    if ($existing_files) {
        rsort($existing_files);
        $last_csv_file = $existing_files[0];
        $last_csv_date = substr(basename($last_csv_file), 0, 10); // Datum im Format YYYY-MM-DD
    }

    // HTML abrufen
    $context = stream_context_create(["ssl"=>["verify_peer"=>false,"verify_peer_name"=>false]]);
    $html = @file_get_contents($url, false, $context);
    if (!$html) { echo "Cannot load $artist_name<br><br>"; continue; }

    // Chart-Datum extrahieren
    if (preg_match('/Last updated:\s*(\d{4})\/(\d{2})\/(\d{2})/i', $html, $matches)) {
        $chart_date = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        echo "Detected chart date: $chart_date<br>";
    } else { 
        echo "Date not found for $artist_name<br><br>"; 
        continue; 
    }

    // Bereits vorhandene CSV überspringen
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

    // Song-Daten auslesen
    $today_data = [];
    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length < 3) continue;
        $link = $cols[0]->getElementsByTagName('a');
        if ($link->length === 0) continue;

        $titleNode = $link->item(0);
        $title = '';
        if ($titleNode) {
            foreach ($titleNode->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $title .= $child->nodeValue;
                } else if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'sup') {
                    $title .= trim($child->textContent);
                }
            }
            $title = trim($title);
        }

        $streams = (int) preg_replace('/[^0-9]/', '', $cols[1]->nodeValue);
        $daily   = (int) preg_replace('/[^0-9]/', '', $cols[2]->nodeValue);
        $rank = count($today_data) + 1;
        $today_data[] = ['rank' => $rank, 'title' => $title, 'streams' => $streams, 'daily' => $daily];
    }

    if (count($today_data) === 0) {
        echo "No songs for $artist_name<br><br>";
        continue;
    }

    // CSV-Datei im Künstlerordner speichern
    $filename = $artist_folder . DIRECTORY_SEPARATOR . "$chart_date.csv";
    $csv_rows = [["Rank", "Song Title", "Streams", "Daily"]];
    foreach ($today_data as $data) {
        $csv_rows[] = [$data['rank'], $data['title'], $data['streams'], $data['daily']];
    }

    $fp = fopen($filename, 'w');
    fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM für Excel
    foreach ($csv_rows as $row) fputcsv($fp, $row);
    fclose($fp);

    echo "CSV created for $artist_name $chart_date in folder $artist_folder<br><br>";
}
?>