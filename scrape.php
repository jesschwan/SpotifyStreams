<?php
$artist_file = __DIR__ . '/artist_urls.txt';
$csv_path = __DIR__;

// Read artist URLs
$artist_urls = file($artist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!$artist_urls) die("Keine Künstler in artist_urls.txt gefunden!");

// Loop through each artist
foreach ($artist_urls as $line) {
    $parts = explode('#', $line);
    $url = trim($parts[0]);
    $artist_name = isset($parts[1]) ? trim($parts[1]) : 'Unknown Artist';

    echo "Processing $artist_name<br>";

    // Get last CSV date for this artist
    $existing_files = glob($csv_path . DIRECTORY_SEPARATOR . "$artist_name *.csv");
    $last_csv_date = null;
    if ($existing_files) {
        rsort($existing_files);
        $last_csv_file = $existing_files[0];
        $last_csv_date = substr(basename($last_csv_file), strlen($artist_name)+1, 10);
    }

    // Load HTML
    $context = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
    $html = file_get_contents($url, false, $context);
    if (!$html) { echo "Cannot load $artist_name<br>"; continue; }

    // Extract chart date
    if (preg_match('/Last updated:\s*([0-9\/]+)/i', $html, $matches)) {
        $raw_date = $matches[1]; 
        $chart_date = date('Y-m-d', strtotime($raw_date));
    } else { 
        echo "Date not found for $artist_name<br>"; 
        continue; 
    }

    // Check if this chart date is newer than last CSV
    if ($last_csv_date && strtotime($chart_date) <= strtotime($last_csv_date)) {
        echo "CSV exists for $artist_name $last_csv_date<br><br>";
        continue; // Skip reading songs
    }

    // Parse HTML for songs
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $rows = $xpath->query("//table[@class='addpos sortable']/tbody/tr");
    if (!$rows || $rows->length === 0) {
        echo "CSV exists for $artist_name $last_csv_date<br><br>";
        continue;
    }

    $today_data = [];
    foreach ($rows as $row) {
        $cols = $row->getElementsByTagName('td');
        if ($cols->length < 3) continue;
        $link = $cols[0]->getElementsByTagName('a');
        if ($link->length === 0) continue;
        $title = trim($link->item(0)->nodeValue);
        $streams = trim($cols[1]->nodeValue);
        $daily   = trim($cols[2]->nodeValue);
        $rank = count($today_data) + 1;
        $today_data[$title] = ['rank'=>$rank,'streams'=>$streams,'daily'=>$daily];
    }

    if (count($today_data) === 0) {
        echo "CSV exists for $artist_name $last_csv_date<br><br>";
        continue;
    }

    // Prepare CSV
    $filename = $csv_path . DIRECTORY_SEPARATOR . "$artist_name $chart_date.csv";

    // Read previous CSV if exists for diffs
    $prev_data = [];
    if ($existing_files) {
        rsort($existing_files);
        $prev_file = $existing_files[0];
        if (($handle = fopen($prev_file,'r')) !== false) {
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[0], $row[1])) continue;
                $prev_data[$row[0]] = $row;
            }
            fclose($handle);
        }
    }

    // CSV rows with diffs
    $csv_rows = [["Song Title","Streams","Daily","Streams Vortag","Diff. Streams","Daily Vortag","Diff Daily"]];
    foreach ($today_data as $title => $data) {
        $prev = $prev_data[$title] ?? null;
        $streams_today = (int) str_replace(['.',','], '', $data['streams']);
        $daily_today = (int) str_replace(['.',','], '', $data['daily']);
        if ($prev) {
            $streams_prev = (int) str_replace(['.',','], '', $prev[1]);
            $daily_prev = (int) str_replace(['.',','], '', $prev[2]);
            $diff_streams = $streams_today - $streams_prev;
            $diff_daily = $daily_today - $daily_prev;
        } else {
            $streams_prev = $diff_streams = $daily_prev = $diff_daily = '';
        }
        $csv_rows[] = [$title, $streams_today, $daily_today, $streams_prev, $diff_streams, $daily_prev, $diff_daily];
    }

    // Write CSV
    $fp = fopen($filename,'w');
    foreach ($csv_rows as $row) fputcsv($fp,$row);
    fclose($fp);

    echo "CSV created for $artist_name $chart_date<br><br>";
}
?>