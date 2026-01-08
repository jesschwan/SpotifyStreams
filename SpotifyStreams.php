<?php
// Pfad zu den CSVs
$csv_path = 'C:\xampp\htdocs\SpotifyStreams';

// Liste der Künstler (kann beliebig erweitert werden)
$artists = ['One Direction', 'Harry Styles'];

// Standardwerte
$selected_artist = '';
$date = null; // optional: manuelles Datumsauswahl
$all_record_arr = [];

/**
 * Liest aktuelle CSV-Daten eines Künstlers ein, berechnet Differenzen zum Vortag und Rank-Veränderungen
 * 
 * @param string $csv_path Pfad zu den CSV-Dateien
 * @param string $artist Künstlername
 * @param string|null $date optionales Datum im Format YYYY-MM-DD
 * @return array ['data'=>[], 'display_date'=>'', 'previous_date'=>'', 'available_dates'=>[]]
 */
function getCurrentArtistStats(string $csv_path, string $artist, ?string $date = null) {
    $today_data = [];
    $previous_data = [];
    $all_record_arr = [];

    // Alle CSV-Dateien für den Künstler finden
    $files = glob($csv_path . DIRECTORY_SEPARATOR . "$artist *.csv");
    if (!$files) return [
        'data' => [],
        'display_date' => '',
        'previous_date' => '',
        'available_dates' => []
    ];

    // Dateien nach Datum sortieren (neueste zuerst)
    usort($files, fn($a,$b) => strtotime(substr($b,-14,10)) - strtotime(substr($a,-14,10)));

    // Alle verfügbaren Daten für Datum-Dropdown
    $available_dates = array_map(fn($f) => substr($f,-14,10), $files);

    // Gewähltes Datum oder neueste CSV
    $selected_file = $date ? $csv_path . DIRECTORY_SEPARATOR . "$artist $date.csv" : $files[0];
    $display_date  = date('d/m/Y', strtotime(substr($selected_file,-14,10)));

    // Vorherige CSV (falls vorhanden)
    $previous_file_index = array_search($selected_file, $files);
    $previous_file = $previous_file_index !== false && isset($files[$previous_file_index + 1])
        ? $files[$previous_file_index + 1]
        : null;
    $previous_date = $previous_file ? date('d/m/Y', strtotime(substr($previous_file,-14,10))) : '';

    // Heute einlesen
    if (($handle = fopen($selected_file,'r')) !== FALSE) {
        fgetcsv($handle); // Kopfzeile überspringen
        while (($row = fgetcsv($handle)) !== FALSE) {
            $today_data[$row[1]] = $row; // nach Titel indexieren
        }
        fclose($handle);
    }

    // Vortag einlesen
    if ($previous_file && ($handle = fopen($previous_file,'r')) !== FALSE) {
        fgetcsv($handle); // Kopfzeile überspringen
        while (($row = fgetcsv($handle)) !== FALSE) {
            $previous_data[$row[1]] = $row;
        }
        fclose($handle);
    }

    // Kombinierte Daten vorbereiten
    foreach ($today_data as $song => $row) {
        $prev = $previous_data[$song] ?? [0, $song, 0, 0]; // default falls kein Vortag

        // Zahlen-Spalten parsen
        $rank = (int)$row[0];
        $streams_today = (int) str_replace('.', '', $row[2]);
        $daily_today   = (int) str_replace('.', '', $row[3]);
        $streams_prev  = (int) str_replace('.', '', $prev[2]);
        $daily_prev    = (int) str_replace('.', '', $prev[3]);
        $rank_prev     = (int)$prev[0];

        // Rank-Veränderung berechnen
        if ($rank_prev === 0) {
            $rank_change = 'neu';
        } elseif ($rank < $rank_prev) {
            $rank_change = '↑';
        } elseif ($rank > $rank_prev) {
            $rank_change = '↓';
        } else {
            $rank_change = '–';
        }

        $all_record_arr[] = [
            $rank,
            $row[1],
            $streams_today,
            $daily_today,
            $streams_prev,
            $streams_today - $streams_prev,
            $daily_prev,
            $daily_today - $daily_prev,
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

// Wenn Formular abgesendet
if (isset($_POST['interpretDropdown'])) {
    $selected_artist = $_POST['interpretDropdown'];
    $date = $_POST['dateDropdown'] ?? null;
    $spotifyData = getCurrentArtistStats($csv_path, $selected_artist, $date);

    $all_record_arr = $spotifyData['data'];
    $display_date   = $spotifyData['display_date'];
    $previous_date  = $spotifyData['previous_date'];
    $available_dates = $spotifyData['available_dates'];
}
?>

<html>
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
            <select name="interpretDropdown" id="interpretDropdown">
                <option value="">-- Bitte wählen --</option>
                <?php foreach($artists as $artist): ?>
                    <option value="<?= htmlspecialchars($artist) ?>" <?= $artist === $selected_artist ? 'selected' : '' ?>>
                        <?= htmlspecialchars($artist) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Künstler auswählen</button>
        </form>
    </div>

    <!-- Datumsauswahl, nur wenn Künstler gewählt -->
    <?php if ($selected_artist && !empty($available_dates)): ?>
        <div class="form-container">
            <form method="post">
                <input type="hidden" name="interpretDropdown" value="<?= htmlspecialchars($selected_artist) ?>">
                <label for="dateDropdown">Datum wählen:</label>
                <select name="dateDropdown" id="dateDropdown">
                    <?php foreach ($available_dates as $d): ?>
                        <option value="<?= $d ?>" <?= ($date === $d) ? 'selected' : '' ?>>
                            <?= date('d/m/Y', strtotime($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Datum anzeigen</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Tabelle anzeigen -->
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
                        <td><?= number_format($rec[2],0,',','.') ?></td>
                        <td><?= number_format($rec[3],0,',','.') ?></td>
                        <td><?= number_format($rec[4],0,',','.') ?></td>
                        <td class="<?= $rec[5] < 0 ? 'negativ' : 'positiv' ?>"><?= number_format($rec[5],0,',','.') ?></td>
                        <td><?= number_format($rec[6],0,',','.') ?></td>
                        <td class="<?= $rec[7] < 0 ? 'negativ' : 'positiv' ?>"><?= number_format($rec[7],0,',','.') ?></td>
                        <td><?= htmlspecialchars($rec[8]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_artist): ?>
        <p>Keine Daten für <?= htmlspecialchars($selected_artist) ?> gefunden.</p>
    <?php endif; ?>
</body>
</html>
