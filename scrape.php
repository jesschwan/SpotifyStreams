<?php
$artist_file = __DIR__ . '/artist_urls.txt';
$csv_path = __DIR__;

// Künstler-URLs einlesen
$artist_urls = file($artist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$artist_urls) die("Keine Künstler in artist_urls.txt gefunden!");

// Titel normalisieren (für CSV)
// ✅ FIX: Keine Sonderzeichen werden mehr entfernt
function normalizeTitleForCsv($title) {
    $title = trim($title);
    if (class_exists('Normalizer')) {
        $title = Normalizer::normalize($title, Normalizer::FORM_C);
    }
    // ❗ KEINE Zeichenentfernung mehr
    return $title;
}

// 👉 Layout starten
echo "<table><tr><td valign='top'>";
$count = 0;

// Durch alle Künstler iterieren
foreach ($artist_urls as $line) {
    $parts = explode('#', $line);
    $url = trim($parts[0]);
    $artist_name_raw = isset($parts[1]) ? trim($parts[1]) : 'Unknown Artist';
    $artist_name = preg_replace('/[\/:*?"<>|]/', '', $artist_name_raw);

    echo "<b>Processing $artist_name</b><br>";

    // Künstlerordner erstellen
    $artist_folder = $csv_path . DIRECTORY_SEPARATOR . $artist_name;
    if (!is_dir($artist_folder)) mkdir($artist_folder, 0777, true);

    // Letzte CSV prüfen
    $existing_files = glob($artist_folder . DIRECTORY_SEPARATOR . '*.csv');
    $last_csv_date = null;
    if ($existing_files) {
        rsort($existing_files);
        $last_csv_file = $existing_files[0];
        $last_csv_date = substr(basename($last_csv_file), 0, 10);
    }

    // HTML abrufen
    $context = stream_context_create([
        "ssl" => ["verify_peer" => false, "verify_peer_name" => false]
    ]);
    $html = @file_get_contents($url, false, $context);
    if (!$html) { echo "Cannot load $artist_name<br><br>"; continue; }

    // Chart-Datum extrahieren
    if (preg_match('/Last updated:\s*(\d{4})\/(\d{2})\/(\d{2})/i', $html, $matches)) {
        $chart_date = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        echo "Detected chart date: $chart_date<br>";
    } else { echo "Date not found<br><br>"; continue; }

    // Bereits vorhandene CSV überspringen
    if ($last_csv_date && strtotime($chart_date) <= strtotime($last_csv_date)) {
        $count++;
        continue;
    }

    // HTML parsen
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $rows = $xpath->query("//table[@class='addpos sortable']/tbody/tr");
    if (!$rows || $rows->length === 0) { $count++; continue; }

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
                $text = trim($child->textContent);
                if ($text !== '') $title .= $text;
            }
        }

        // ✅ Aufruf bleibt – jetzt ohne zerstörerische Effekte
        $title = normalizeTitleForCsv($title);

        $streams = (int) preg_replace('/[^0-9]/', '', $cols[1]->nodeValue);
        $daily   = (int) preg_replace('/[^0-9]/', '', $cols[2]->nodeValue);
        $rank = count($today_data) + 1;

        $today_data[] = [
            'rank'    => $rank,
            'title'   => $title,
            'streams' => $streams,
            'daily'   => $daily
        ];
    }

    if (count($today_data) === 0) { $count++; continue; }

    // CSV speichern
    $filename = $artist_folder . DIRECTORY_SEPARATOR . "$chart_date.csv";
    $fp = fopen($filename, 'w');
    fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($fp, ["Rank", "Song Title", "Streams", "Daily"]);
    foreach ($today_data as $data) {
        fputcsv($fp, [$data['rank'], $data['title'], $data['streams'], $data['daily']]);
    }
    fclose($fp);

    echo "CSV created ($chart_date)<br><br>";
    $count++;
    if ($count % 5 == 0) echo "</td><td valign='top'>";
}

echo "</td></tr></table>";
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Übersicht vorhandener CSV-Dateien</title>
    <link rel="stylesheet" href="styles_scrape.css">
</head>
</html>