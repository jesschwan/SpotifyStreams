<?php
$artist_file = __DIR__ . '/artist_urls.txt';
$csv_path = __DIR__;

// Read artist URLs
$artist_urls = file($artist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$artist_urls) die("Keine Künstler in artist_urls.txt gefunden!");

// Loop through each artist
foreach ($artist_urls as $line) {
    // Split URL and artist name
    $parts = explode('#', $line);
    $url = trim($parts[0]);
    $artist_name = isset($parts[1]) ? trim($parts[1]) : 'Unknown Artist';

    echo "Processing $artist_name<br>";

    // Load HTML with SSL context
    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ]);
    $html = file_get_contents($url, false, $context);

    if (!$html) { 
        echo "Cannot load $artist_name<br>"; 
        continue; 
    }

    // Extract last updated date
    if (preg_match('/Last updated:\s*([0-9\/]+)/i', $html, $matches)) {
        $raw_date = $matches[1]; 
        $chart_date = date('Y-m-d', strtotime($raw_date));
    } else { 
        echo "Date not found for $artist_name<br>"; 
        continue; 
    }

    $filename = $csv_path . DIRECTORY_SEPARATOR . "$artist_name $chart_date.csv";
    if (file_exists($filename)) { 
        echo "CSV exists for $chart_date<br><br>"; 
        continue; 
    }

    // Parse HTML
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // Alle Tabellen
    $tables = $xpath->query("//table");
    $rows = $xpath->query("//table[2]/tr"); // Standard: 2. Tabelle = Hauptsongs

    // Notfall-Fallback: keine Tabelle gefunden -> erste Tabelle nehmen
    if (empty($rows) && $tables->length > 0) {
        echo "Keine Hauptsongs-Tabelle gefunden für $artist_name, nehme erste Tabelle als Fallback<br>";
        $rows = $tables->item(0)->getElementsByTagName('tr');
    }

    $today_data = [];
    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length < 4) continue;
        $title = trim($cols->item(1)->nodeValue);
        if ($title === '' || strtolower($title) === 'song title') continue;

        $rank = trim($cols->item(0)->nodeValue);
        $streams = trim($cols->item(2)->nodeValue);
        $daily = trim($cols->item(3)->nodeValue);

        $today_data[$title] = [
            'rank' => $rank,
            'streams' => $streams,
            'daily' => $daily
        ];
    }

    echo "Songs found: ".count($today_data)."<br>";
    foreach ($today_data as $t => $d) {
        echo "$t: ".$d['streams']." streams<br>";
    }

    // Read previous CSV if exists
    $previous_file = glob($csv_path . DIRECTORY_SEPARATOR . "$artist_name *.csv");
    rsort($previous_file); // newest first
    $prev_data = [];
    if ($previous_file) {
        $prev_file = $previous_file[0];
        if (($handle = fopen($prev_file, 'r')) !== false) {
            fgetcsv($handle); // skip header
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[1]) || trim($row[1]) === '') continue;
                $prev_data[$row[1]] = $row;
            }
            fclose($handle);
        }
    }

    // Prepare CSV rows with diffs
    $csv_rows = [["Song Title","Streams","Daily","Streams Vortag","Diff. Streams","Daily Vortag","Diff Daily"]];
    foreach ($today_data as $title => $data) {
        $prev = $prev_data[$title] ?? null;

        $streams_today = (int) str_replace(['.',','], '', $data['streams']);
        $daily_today = (int) str_replace(['.',','], '', $data['daily']);

        if ($prev) {
            $streams_prev = (int) str_replace(['.',','], '', $prev[2]);
            $daily_prev = (int) str_replace(['.',','], '', $prev[3]);
            $diff_streams = $streams_today - $streams_prev;
            $diff_daily = $daily_today - $daily_prev;
        } else {
            $streams_prev = $diff_streams = $daily_prev = $diff_daily = '';
        }

        $csv_rows[] = [
            $title, $streams_today, $daily_today,
            $streams_prev, $diff_streams, $daily_prev, $diff_daily
        ];
    }

    // Write CSV
    $fp = fopen($filename, 'w');
    foreach ($csv_rows as $row) fputcsv($fp, $row);
    fclose($fp);

    echo "CSV created for $artist_name $chart_date<br><br>";
}
?>