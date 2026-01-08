<?php
    // Pfad zu den CSVs
    $csv_path = 'C:\xampp\htdocs\SpotifyStreams';

    // Liste der Künstler
    $artists = ['One Direction', 'Harry Styles'];

    // Standardwerte
    $selected_artist = '';
    $all_record_arr = [];

    // Prüfen, ob das Formular abgesendet wurde
    if (isset($_POST['interpretDropdown']) && in_array($_POST['interpretDropdown'], $artists)) {
        $selected_artist = $_POST['interpretDropdown'];

        // CSV-Dateien für den Künstler finden
        $files = glob($csv_path . DIRECTORY_SEPARATOR . "$selected_artist *.csv");

        if (!empty($files)) {
            // Die neueste Datei anhand des Datums im Dateinamen auswählen
            usort($files, function($a, $b) {
                preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/', $a, $matchA);
                preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/', $b, $matchB);
                return strtotime($matchB[1]) - strtotime($matchA[1]);
            });

            $latest_file = $files[0];
            preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/', $latest_file, $date_match);
            $display_date = date('d/m/Y', strtotime($date_match[1]));

            // CSV einlesen
            if (($handle = fopen($latest_file, 'r')) !== FALSE) {
                // Kopfzeile überspringen
                fgetcsv($handle, 1000, ",");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $all_record_arr[] = $data;
                }

                fclose($handle);
            }
        }
    }

    function getDataFromPreviousDate(string $csv_path, string $artist){
        $today_data = [];
        $previous_data = [];
        $display_date = '';
        $previous_date = '';

        // Alle CSV-Dateien für den Künstler
        $files = glob($csv_path . DIRECTORY_SEPARATOR . $artist . ' *.csv');
        if (!$files) return ['data' => [], 'display_date' => '', 'previous_date' => ''];

        // Dateien nach Datum sortieren
        usort($files, fn ($a, $b) => strtotime(substr($b, -14, 10)) - strtotime(substr($a, -14, 10)));

        // Neueste CSV = heute
        $latest_file = $files[0];
        $display_date = date('d/m/Y', strtotime(substr($latest_file, -14, 10)));

        // Vorherige CSV (falls vorhanden)
        $previous_file = $files[1] ?? null;
        if ($previous_file){
            $previous_date = date('d/m/Y', strtotime(substr($previous_file, -14, 10)));
        }

        // Heute einlesen
        if (($handle = fopen($latest_file, 'r')) !== FALSE) {
            fgetcsv($handle); // Kopfzeile überspringen
            while (($row = fgetcsv($handle)) !== FALSE) {
                $today_data[$row[1]] = $row; // Index nach Songtitel
            }
            fclose($handle);
        }

        // Vortag einlesen
        if ($previous_file && ($handle = fopen($previous_file, 'r')) !== FALSE) {
            fgetcsv($handle); // Kopfzeile überspringen
            while (($row = fgetcsv($handle)) !== FALSE) {
                $previous_data[$row[1]] = $row; // Index nach Songtitel
            }
            fclose($handle);
        }

        // Kombinierte Daten vorbereiten
        $all_record_arr = [];
        foreach ($today_data as $song => $row) {
            $prev = $previous_data[$song] ?? [0, $song, 0, 0]; // Default falls kein Vortag

            // Alle Zahlen-Spalten parsen (Rank, Streams, Daily, Streams Prev, Daily Prev)
            $rank = (int)$row[0];
            $streams_today = (int) str_replace('.', '', $row[2]);
            $daily_today   = (int) str_replace('.', '', $row[3]);
            $streams_prev  = (int) str_replace('.', '', $prev[2]);
            $daily_prev    = (int) str_replace('.', '', $prev[3]);

            $all_record_arr[] = [
                $rank,                        // Rank → int
                $row[1],                      // Titel → string
                $streams_today,               // Streams → int
                $daily_today,                 // Daily → int
                $streams_prev,                // Streams Vortag → int
                $streams_today - $streams_prev, // Diff. Streams
                $daily_prev,                  // Daily Vortag → int
                $daily_today - $daily_prev    // Diff. Daily
            ];
        }

        return [
            'data' => $all_record_arr,
            'display_date' => $display_date,
            'previous_date' => $previous_date
        ];
    }

    $spotifyData = getDataFromPreviousDate($csv_path, $selected_artist);

    // Daten für die Tabelle
    $all_record_arr = $spotifyData['data'];
    $display_date   = $spotifyData['display_date'];
    $previous_date  = $spotifyData['previous_date'];
?>

<html>
<head>
    <meta charset="UTF-8">
    <title>Spotify Top Songs</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <h1>Spotify Top Songs</h1>

    <div class="form-container">
        <form method="post" class="form-container">
            <label for="interpretDropdown">Wähle:</label>
            <select name="interpretDropdown" id="interpretDropdown" class="dropdown">
                <option value="">-- Bitte wählen --</option>
                <?php foreach ($artists as $artist): ?>
                    <option value="<?= $artist ?>" <?= $artist === $selected_artist ? 'selected' : '' ?>>
                        <?= $artist ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button-submit">Submit</button>
        </form>
    </div>

    <?php if ($selected_artist && !empty($all_record_arr)): ?>
        <h2><?= htmlspecialchars($selected_artist) ?> - <?= $display_date ?></h2>
        <table>
            <thead>
                <tr><th>Rank</th><th>Titel</th><th>Streams</th><th>Daily</th><th>Streams <?= $previous_date ?></th><th>Diff. Streams</th><th>Daily <?= $previous_date ?></th><th>Diff. Daily</th></tr>
            </thead>
            <tbody>
                <?php foreach($all_record_arr as $rec): ?>
                    <tr>
                        <td><?= htmlspecialchars($rec[0]) ?></td>
                        <td><?= htmlspecialchars($rec[1]) ?></td>
                        <td><?= htmlspecialchars($rec[2]) ?></td>
                        <td><?= htmlspecialchars($rec[3]) ?></td>
                        <td><?= htmlspecialchars($rec[4]) ?></td>
                        <td><?= htmlspecialchars($rec[5]) ?></td>
                        <td><?= htmlspecialchars($rec[6]) ?></td>
                        <td><?= htmlspecialchars($rec[7]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_artist): ?>
        <p>Keine CSV-Datei für <?= htmlspecialchars($selected_artist) ?> gefunden.</p>
    <?php endif; ?>
</body>
</html>