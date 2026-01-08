<?php
// Pfad zu den CSVs
$csv_path = 'C:\xampp\htdocs\SpotifyStreams';

// Liste der Künstler
$artists = ['One Direction', 'Harry Styles'];

// Standardwerte
$selected_artist = '';
$all_record_arr = [];
$display_date = '';

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
        $display_date = date('d-m-Y', strtotime($date_match[1]));

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
?>

<html>
<head>
    <meta charset="UTF-8">
    <title>Spotify Top Songs</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
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
        <h1>Spotify Top Songs</h1>
        <h2><?= htmlspecialchars($selected_artist) ?> - <?= $display_date ?></h2>
        <table>
            <thead>
                <tr><th>Rank</th><th>Titel</th><th>Streams</th><th>Daily</th></tr>
            </thead>
            <tbody>
                <?php foreach($all_record_arr as $rec): ?>
                    <tr>
                        <td><?= htmlspecialchars($rec[0]) ?></td>
                        <td><?= htmlspecialchars($rec[1]) ?></td>
                        <td><?= htmlspecialchars($rec[2]) ?></td>
                        <td><?= htmlspecialchars($rec[3]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($selected_artist): ?>
        <p>Keine CSV-Datei für <?= htmlspecialchars($selected_artist) ?> gefunden.</p>
    <?php endif; ?>
</body>
</html>