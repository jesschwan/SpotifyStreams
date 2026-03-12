<?php
$csv_path = 'C:/xampp/htdocs/SpotifyStreams';

// Alle Künstler automatisch ermitteln
$artists = [];
foreach (glob($csv_path . DIRECTORY_SEPARATOR . '*.csv') as $file) {
    $filename = basename($file, '.csv'); 
    $parts = explode(' ', $filename);
    array_pop($parts); // Datum entfernen
    $artist_name = implode(' ', $parts);
    if (!in_array($artist_name, $artists)) $artists[] = $artist_name;
}
sort($artists);

$selected_artist = '';
$date = null;
$all_record_arr = [];
$display_date = '';
$previous_date = '';
$available_dates = [];

// Datum aus Dateiname extrahieren
function extractDateFromFilename($file) {
    if (preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/', $file, $match)) return $match[1];
    return null;
}

// Titel normalisieren
function normalizeTitle($title) {
    $title = trim($title);
    if(class_exists('Normalizer')) $title = Normalizer::normalize($title, Normalizer::FORM_C);
    $title = str_replace(["'", "\u{2019}", "`"], "'", $title);
    $title = preg_replace('/[\p{Cc}\p{Cf}]+/u','',$title); // Steuerzeichen entfernen
    $title = preg_replace('/^\*\s*/','',$title); // Sternchen nur am Anfang entfernen
    $title = preg_replace('/\s+/u',' ',$title);
    return trim($title);
}

// CSV einlesen (unterstützt alte & neue Struktur, sortiert nach Streams)
function readCsvFile($file){
    $data = [];
    if(!file_exists($file)) return $data;

    $handle = fopen($file,'r');
    if(!$handle) return $data;

    $header = fgetcsv($handle);
    if(!$header){ fclose($handle); return $data; }

    $colRank    = array_search('Rank', $header);
    $colTitle   = array_search('Song Title', $header);
    $colStreams = array_search('Streams', $header);
    $colDaily   = array_search('Daily', $header);

    // Alte CSV ohne Rank-Spalte
    $rank_counter = 1;
    $hasRankColumn = $colRank !== false;

    if($colTitle === false) $colTitle = 1;
    if($colStreams === false) $colStreams = 2;
    if($colDaily === false) $colDaily = 3;

    $seenTitles = [];

    while(($row=fgetcsv($handle)) !== false){
        $title = isset($row[$colTitle]) ? trim($row[$colTitle]) : '';
        if($title === '' || strtolower($title) === 'song title') continue;

        // Titel normalisieren & Sternchen entfernen
        $key = trim(preg_replace('/[\p{Cc}\p{Cf}\*]+/u','',$title));

        if(isset($seenTitles[$key])) continue;
        $seenTitles[$key] = true;

        $streams = isset($row[$colStreams]) ? (int) preg_replace('/[^0-9]/','',$row[$colStreams]) : 0;
        $daily   = isset($row[$colDaily])   ? (int) preg_replace('/[^0-9]/','',$row[$colDaily])   : 0;

        $data[] = [
            'rank'           => $hasRankColumn && isset($row[$colRank]) && is_numeric($row[$colRank])
                                ? (int)$row[$colRank]
                                : 0, // Rank wird später neu gesetzt
            'streams'        => $streams,
            'daily'          => $daily,
            'original_title' => $title
        ];
    }

    fclose($handle);

    // 1) Nach Streams absteigend sortieren
    usort($data, function($a,$b){
        return $b['streams'] <=> $a['streams'];
    });

    // 2) Rank neu vergeben
    $rank_counter = 1;
    foreach($data as &$row){
        $row['rank'] = $rank_counter++;
    }

    // 3) Key für schnellen Zugriff auf den Titel
    $result = [];
    foreach($data as $row){
        $key = trim(preg_replace('/[\p{Cc}\p{Cf}\*]+/u','',$row['original_title']));
        $result[$key] = $row;
    }

    return $result;
}

// Funktion: Neueste CSV mit Daten auswählen
function selectLatestCsvWithData(array $files): ?string {
    usort($files, function($a,$b){
        return strtotime(extractDateFromFilename($b)) - strtotime(extractDateFromFilename($a));
    });

    foreach($files as $f){
        if(!file_exists($f) || filesize($f) <= 0) continue;

        $data = readCsvFile($f);
        if(!empty($data)) return $f;
    }

    return null;
}

// Alle Daten und Vergleich berechnen
function getCurrentArtistStats($csv_path, $artist, $date = null) {
    $safe_artist = preg_replace('/[\/:*?"<>|]/','',$artist);
    $files = glob($csv_path . DIRECTORY_SEPARATOR . $safe_artist . ' *.csv');
    if (!$files) return ['data'=>[],'display_date'=>'','previous_date'=>'','available_dates'=>[]];

    usort($files, function($a,$b){ 
        return strtotime(extractDateFromFilename($b)) - strtotime(extractDateFromFilename($a));
    });

    // Gewählte Datei bestimmen
    if ($date) {
        $selected_file = $csv_path . DIRECTORY_SEPARATOR . "$safe_artist $date.csv";
        if (!file_exists($selected_file)) $selected_file = null;
    } else {
        $selected_file = selectLatestCsvWithData($files);
    }

    if (!$selected_file) return ['data'=>[],'display_date'=>'','previous_date'=>'','available_dates'=>[]];

    $available_dates = [];
    foreach ($files as $f) {
        $tmpData = readCsvFile($f);
        if (!empty($tmpData) && ($d = extractDateFromFilename($f))) $available_dates[] = $d;
    }

    $selectedDate = extractDateFromFilename($selected_file);
    $display_date = $selectedDate ? date('d/m/Y', strtotime($selectedDate)) : '';

    $previous_file = null;
    foreach ($files as $f) {
        $d = extractDateFromFilename($f);
        if ($d && strtotime($d) < strtotime($selectedDate)) {
            $tmpData = readCsvFile($f);
            if (!empty($tmpData)) {
                $previous_file = $f;
                break;
            }
        }
    }
    $previous_date = $previous_file ? date('d/m/Y', strtotime(extractDateFromFilename($previous_file))) : '';

    $today_data = readCsvFile($selected_file);
    $previous_data = $previous_file ? readCsvFile($previous_file) : [];

    $all_record_arr = [];
    foreach($today_data as $key => $row) {
        $prev = $previous_data[$key] ?? null;

        $streams_today = $row['streams'];
        $daily_today   = $row['daily'];
        $rank          = $row['rank'];

        if ($prev) {
            $streams_prev = $prev['streams'];
            $daily_prev   = $prev['daily'];
            $diff_streams = $streams_today - $streams_prev;
            $diff_daily   = $daily_today - $daily_prev;
            $rank_change  = $rank < $prev['rank'] ? '+'.($prev['rank']-$rank)
                          : ($rank > $prev['rank'] ? '-'.($rank-$prev['rank'])
                          : '–');
        } else {
            $streams_prev = '-';
            $daily_prev   = '-';
            $diff_streams = '-';
            $diff_daily   = '-';
            $rank_change  = 'neu';
            $daily_today  = '-';
        }

        $all_record_arr[] = [
            $rank,
            $row['original_title'],
            $streams_today,
            $daily_today,
            $streams_prev,
            $diff_streams,
            $daily_prev,
            $diff_daily,
            $rank_change
        ];
    }

    return [
        'data' => $all_record_arr,
        'display_date' => $display_date,
        'previous_date' => $previous_date,
        'available_dates' => $available_dates
    ];
}

// POST Verarbeitung
if(isset($_POST['interpretDropdown'])){
    $selected_artist = $_POST['interpretDropdown'];
    $date = $_POST['dateDropdown'] ?? null;
    if($date){
        $spotifyData = getCurrentArtistStats($csv_path,$selected_artist,$date);
        $all_record_arr = $spotifyData['data'];
        $display_date   = $spotifyData['display_date'];
        $previous_date  = $spotifyData['previous_date'];
        $available_dates = $spotifyData['available_dates'];
    }else{
        $all_record_arr=[];
        $available_dates = getCurrentArtistStats($csv_path,$selected_artist)['available_dates'] ?? [];
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Spotify Top Songs</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Spotify Top Songs</h1>

    <!-- Künstler-Auswahl -->
    <div class="form-container">
        <form method="post">
            <label for="interpretDropdown">Künstler wählen:</label>
            <select name="interpretDropdown" id="interpretDropdown" class="dropdown">
                <option value="">-- Bitte wählen --</option>
                <?php foreach($artists as $artist): ?>
                    <option value="<?= htmlspecialchars($artist) ?>" <?= $artist === $selected_artist ? 'selected' : '' ?>>
                        <?= htmlspecialchars($artist) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button-submit">Submit</button>
        </form>
    </div>

    <!-- Datumsauswahl -->
    <?php if ($selected_artist && !empty($available_dates)): ?>
        <div class="form-container">
            <form method="post">
                <input type="hidden" name="interpretDropdown" value="<?= htmlspecialchars($selected_artist) ?>">
                <label for="dateDropdown">Datum wählen:</label>
                <select name="dateDropdown" id="dateDropdown" class="dropdown">
                    <?php foreach ($available_dates as $d): ?>
                        <option value="<?= $d ?>" <?= ($date === $d) ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button-submit">Daten anzeigen</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Tabelle -->
    <?php if (!empty($all_record_arr)): ?>
        <h2><?= htmlspecialchars($selected_artist) ?> – <?= $display_date ?></h2>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Titel</th>
                    <th>Streams</th>
                    <th>Daily</th>
                    <th>Streams <?= $previous_date ?></th>
                    <th>Diff. Streams</th>
                    <th>Daily <?= $previous_date ?></th>
                    <th>Diff. Daily</th>
                    <th>Veränderung Rank</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_record_arr as $rec): ?>
                    <tr>
                        <td><?= $rec[0] ?></td>
                        <td><?= htmlspecialchars($rec[1]) ?></td>
                        <td><?= is_numeric($rec[2]) ? number_format($rec[2],0,',','.') : $rec[2] ?></td>
                        <td><?= is_numeric($rec[3]) ? number_format($rec[3],0,',','.') : $rec[3] ?></td>
                        <td><?= is_numeric($rec[4]) ? number_format($rec[4],0,',','.') : $rec[4] ?></td>
                        <td class="<?php if(is_numeric($rec[5])) echo $rec[5] > 0 ? 'positiv' : ($rec[5] < 0 ? 'negativ' : '') ?>">
                            <?= is_numeric($rec[5]) ? number_format($rec[5],0,',','.') : $rec[5] ?>
                        </td>
                        <td><?= is_numeric($rec[6]) ? number_format($rec[6],0,',','.') : $rec[6] ?></td>
                        <td class="<?php if(is_numeric($rec[7])) echo $rec[7] > 0 ? 'positiv' : ($rec[7] < 0 ? 'negativ' : '') ?>">
                            <?= is_numeric($rec[7]) ? number_format($rec[7],0,',','.') : $rec[7] ?>
                        </td>
                        <td><?= htmlspecialchars($rec[8]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_artist && $date): ?>
        <p>Keine Daten für <?= htmlspecialchars($selected_artist) ?> am <?= date('d/m/Y', strtotime($date)) ?> gefunden.</p>
    <?php endif; ?>
</body>
</html>