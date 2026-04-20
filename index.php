<?php
$csv_path = 'C:/xampp/htdocs/SpotifyStreams';

// Künstler automatisch aus Ordnern ermitteln
$artists = [];
foreach (glob($csv_path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $dir) {
    $artists[] = basename($dir);
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

// Titel normalisieren (für Vergleich)
function normalizeKey($title) {
    $title = trim($title);
    if(class_exists('Normalizer')) {
        $title = Normalizer::normalize($title, Normalizer::FORM_C);
    }
    $title = preg_replace('/[\p{Cc}\p{Cf}]+/u','',$title);
    $title = preg_replace('/\s+/u',' ',$title);
    return $title;
}

// CSV einlesen
function readCsvFile($file){
    $data = [];
    if(!file_exists($file)) return $data;

    $handle = fopen($file,'r');
    if(!$handle) return $data;

    $header = fgetcsv($handle);
    if(!$header){ fclose($handle); return $data; }

    $colTitle   = array_search('Song Title', $header);
    $colStreams = array_search('Streams', $header);
    $colDaily   = array_search('Daily', $header);

    if($colTitle === false) $colTitle = 1;
    if($colStreams === false) $colStreams = 2;
    if($colDaily === false) $colDaily = 3;

    while(($row=fgetcsv($handle)) !== false){
        $title = isset($row[$colTitle]) ? trim($row[$colTitle]) : '';
        if($title === '' || strtolower($title) === 'song title') continue;

        $streams = (int) preg_replace('/[^0-9]/','',$row[$colStreams]);
        $daily   = (int) preg_replace('/[^0-9]/','',$row[$colDaily]);

        $data[] = [
            'original_title' => $title,
            'streams' => $streams,
            'daily' => $daily
        ];
    }
    fclose($handle);

    // sortieren nach Streams
    usort($data, function($a,$b){
        return $b['streams'] <=> $a['streams'];
    });

    // Rank neu vergeben
    $rank = 1;
    foreach($data as &$row){
        $row['rank'] = $rank++;
    }

    return $data;
}

// Neueste CSV auswählen
function selectLatestCsvWithData(array $files): ?string {
    usort($files, function($a,$b){
        return strtotime(extractDateFromFilename($b)) - strtotime(extractDateFromFilename($a));
    });

    foreach($files as $f){
        if(filesize($f) > 0){
            $data = readCsvFile($f);
            if(!empty($data)) return $f;
        }
    }
    return null;
}

// Hauptfunktion
function getCurrentArtistStats($csv_path, $artist, $date = null) {

    $safe_artist = preg_replace('/[\/:*?"<>|]/','',$artist);
    $artist_folder = $csv_path . DIRECTORY_SEPARATOR . $safe_artist;
    $files = glob($artist_folder . DIRECTORY_SEPARATOR . '*.csv');

    if (!$files) return ['data'=>[],'display_date'=>'','previous_date'=>'','available_dates'=>[]];

    usort($files, function($a,$b){
        return strtotime(extractDateFromFilename($b)) - strtotime(extractDateFromFilename($a));
    });

    $selected_file = $date
        ? $artist_folder . DIRECTORY_SEPARATOR . "$date.csv"
        : selectLatestCsvWithData($files);

    if(!$selected_file || !file_exists($selected_file)){
        return ['data'=>[],'display_date'=>'','previous_date'=>'','available_dates'=>[]];
    }

    // verfügbare Daten
    $available_dates = [];
    foreach($files as $f){
        $d = extractDateFromFilename($f);
        if($d) $available_dates[] = $d;
    }

    $selectedDate = extractDateFromFilename($selected_file);
    $display_date = date('d/m/Y', strtotime($selectedDate));

    // vorherige Datei suchen
    $previous_file = null;
    foreach($files as $f){
        $d = extractDateFromFilename($f);
        if($d && strtotime($d) < strtotime($selectedDate)){
            $tmp = readCsvFile($f);
            if(!empty($tmp)){
                $previous_file = $f;
                break;
            }
        }
    }

    $previous_date = $previous_file
        ? date('d/m/Y', strtotime(extractDateFromFilename($previous_file)))
        : '';

    $today_data = readCsvFile($selected_file);
    $previous_data = $previous_file ? readCsvFile($previous_file) : [];

    // 🔥 Eindeutige Keys: Rank + normalisierter Titel
    $previous_map = [];
    foreach ($previous_data as $p) {
        $key = sha1($p['original_title']);
        $previous_map[$key] = $p;
    }

    $all_record_arr = [];

    foreach($today_data as $row){
        $streams_today = $row['streams'];
        $daily_today   = $row['daily'];
        $rank          = $row['rank'];

        $current_key = sha1($row['original_title']);
        $prev = $previous_map[$current_key] ?? null;

        if($prev){
            $diff_streams = $streams_today - $prev['streams'];
            $diff_daily   = $daily_today - $prev['daily'];

            if($rank < $prev['rank']){
                $rank_change = '+' . ($prev['rank'] - $rank);
            } elseif($rank > $prev['rank']){
                $rank_change = '-' . ($rank - $prev['rank']);
            } else {
                $rank_change = '–';
            }
        } else {
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
            $prev['streams'] ?? '-',
            $diff_streams,
            $prev['daily'] ?? '-',
            $diff_daily,
            $rank_change
        ];
    }

    return [
        'data'=>$all_record_arr,
        'display_date'=>$display_date,
        'previous_date'=>$previous_date,
        'available_dates'=>$available_dates
    ];
}

// POST
if(isset($_POST['interpretDropdown'])){
    $selected_artist = $_POST['interpretDropdown'];
    $date = $_POST['dateDropdown'] ?? null;

    if($date){
        $spotifyData = getCurrentArtistStats($csv_path,$selected_artist,$date);
        $all_record_arr = $spotifyData['data'];
        $display_date   = $spotifyData['display_date'];
        $previous_date  = $spotifyData['previous_date'];
        $available_dates = $spotifyData['available_dates'];
    } else {
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
<?php if($selected_artist && !empty($available_dates)): ?>
<div class="form-container">
    <form method="post">
        <input type="hidden" name="interpretDropdown" value="<?= htmlspecialchars($selected_artist) ?>">
        <label for="dateDropdown">Datum wählen:</label>
        <select name="dateDropdown" id="dateDropdown" class="dropdown">
            <?php foreach($available_dates as $d): ?>
                <option value="<?= $d ?>" <?= $date === $d ? 'selected' : '' ?>>
                    <?= date('d/m/Y', strtotime($d)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button-submit">Daten anzeigen</button>
    </form>
</div>
<?php endif; ?>

<!-- Tabelle -->
<?php if(!empty($all_record_arr)): ?>
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
            <td><?= htmlspecialchars($rec[1], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= is_numeric($rec[2]) ? number_format($rec[2],0,',','.') : $rec[2] ?></td>
            <td><?= is_numeric($rec[3]) ? number_format($rec[3],0,',','.') : $rec[3] ?></td>
            <td><?= is_numeric($rec[4]) ? number_format($rec[4],0,',','.') : $rec[4] ?></td>
            <td class="<?= is_numeric($rec[5]) ? ($rec[5]>0 ? 'positiv' : ($rec[5]<0 ? 'negativ' : '')) : '' ?>">
                <?= is_numeric($rec[5]) ? number_format($rec[5],0,',','.') : $rec[5] ?>
            </td>
            <td><?= is_numeric($rec[6]) ? number_format($rec[6],0,',','.') : $rec[6] ?></td>
            <td class="<?= is_numeric($rec[7]) ? ($rec[7]>0 ? 'positiv' : ($rec[7]<0 ? 'negativ' : '')) : '' ?>">
                <?= is_numeric($rec[7]) ? number_format($rec[7],0,',','.') : $rec[7] ?>
            </td>
            <td><?= htmlspecialchars($rec[8]) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php elseif($selected_artist && $date): ?>
<p>Keine Daten für <?= htmlspecialchars($selected_artist) ?> am <?= date('d/m/Y', strtotime($selected_date)) ?> gefunden.</p>
<?php endif; ?>

</body>
</html>