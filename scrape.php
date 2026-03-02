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

    // Load HTML with SSL context to avoid certificate errors
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

    // Extract last updated date using regex
    if (preg_match('/Last updated:\s*([0-9\/]+)/i', $html, $matches)) {
        $raw_date = $matches[1]; // e.g., 2026/03/01
        $chart_date = date('Y-m-d', strtotime($raw_date));
    } else { 
        echo "Date not found for $artist_name<br>"; 
        continue; 
    }

    // Skip if CSV exists
    $filename = $csv_path . DIRECTORY_SEPARATOR . "$artist_name $chart_date.csv";
    if (file_exists($filename)) { 
        echo "CSV exists for $chart_date<br><br>"; 
        continue; 
    }

    // Parse HTML to extract table rows
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    $rows = $xpath->query("//table/tr");
    $today_data = [];
    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length < 4) continue;

        $rank = trim($cols->item(0)->nodeValue);
        $title = trim($cols->item(1)->nodeValue);
        $streams = trim($cols->item(2)->nodeValue);
        $daily = trim($cols->item(3)->nodeValue);

        $today_data[$title] = [
            'rank' => $rank,
            'streams' => $streams,
            'daily' => $daily
        ];
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
                $prev_data[$row[1]] = $row; // index by song title
            }
            fclose($handle);
        }
    }

    // Prepare CSV rows with diffs
    $csv_rows = [["Rank","Title","Streams","Daily","Streams Prev","Diff Streams","Daily Prev","Diff Daily","Rank Change"]];
    foreach ($today_data as $title => $data) {
        $prev = $prev_data[$title] ?? null;

        $streams_today = (int) str_replace('.', '', $data['streams']);
        $daily_today = (int) str_replace('.', '', $data['daily']);
        $rank_today = (int)$data['rank'];

        if ($prev) {
            $streams_prev = (int) str_replace('.', '', $prev[2]);
            $daily_prev = (int) str_replace('.', '', $prev[3]);
            $diff_streams = $streams_today - $streams_prev;
            $diff_daily = $daily_today - $daily_prev;
            $rank_prev = (int)$prev[0];

            if ($rank_prev === 0) $rank_change = "new";
            elseif ($rank_today < $rank_prev) $rank_change = "+";
            elseif ($rank_today > $rank_prev) $rank_change = "-";
            else $rank_change = "–";
        } else {
            $streams_prev = $daily_prev = $diff_streams = $diff_daily = $rank_change = "-";
        }

        $csv_rows[] = [
            $rank_today, $title, $streams_today, $daily_today,
            $streams_prev, $diff_streams, $daily_prev, $diff_daily, $rank_change
        ];
    }

    // Write CSV
    $fp = fopen($filename, 'w');
    foreach ($csv_rows as $row) fputcsv($fp, $row);
    fclose($fp);

    echo "CSV created for $artist_name $chart_date<br><br>";
}
?>