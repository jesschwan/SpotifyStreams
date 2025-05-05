<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Spotify Streams</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 25px;
            margin: 0;
        }

        h1 {
            font-size: 40px;
            position: sticky;
            top: 0;
            background-color: white;
            border-bottom: 1px solid lightgrey;
            margin: 0;
            padding: 10px;
        }

        table {
            margin: auto;
            border-collapse: collapse;
            font-size: 25px;
            width: auto;
            color: black;
        }

        th, td {
            border: 2px solid black;
            padding: 10px;
        }

        th {
            background-color: blue;
            color: black;
            position: sticky;
            top: 55px;
        }

        tr td:first-child {
            font-weight: bold;
            background-color: blue;
            color: black;
            text-align: center;
        }

        td:nth-child(2), th:nth-child(2),
        td:nth-child(3), th:nth-child(3) {
            text-align: left;
        }

        td:nth-child(4), th:nth-child(4),
        td:nth-child(5), th:nth-child(5) {
            text-align: center;
        }

        .form-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        label {
            font-size: 25px;
            color: black;
            font-family: Arial;
            display: flex;
            align-items: center;
            height: 50px;
        }

        .dropdown, button {
            font-size: 25px;
            color: black;
            font-family: Arial;
            height: 50px;
            padding: 0 20px;
        }

        button {
            background-color: #ccc;
            border: 2px solid black;
            cursor: pointer;
        }

        .warning {
            color: red;
            font-size: 50px;
            font-weight: bold;
            margin-top: 50px;
        }
    </style>
</head>
<body>

<?php
$csvRoot = __DIR__ . "/CSV";

// Get all subfolders = artists
$interpreten = array_filter(glob($csvRoot . '/*'), 'is_dir');
rsort($interpreten); // Newest artist folder first
$letzterInterpret = basename($interpreten[0] ?? '');

// Use selected artist or fall back to latest one
$ausgewaehlterInterpret = $_GET['interpret'] ?? $letzterInterpret;
$verzeichnis = "$csvRoot/$ausgewaehlterInterpret";

// Get all CSV files in selected artist's folder
$dateien = glob("$verzeichnis/*.csv");
rsort($dateien); // Newest date file first
$letztesDatum = basename($dateien[0] ?? '');

// Use selected date or fallback to latest one
$ausgewaehltesDatum = $_GET['datum'] ?? $letztesDatum;

// Format filename (yyyy-mm-dd.csv) to TT.MM.JJ
function formatDatumKurz($filename) {
    $parts = explode('-', pathinfo($filename, PATHINFO_FILENAME));
    return sprintf('%02d.%02d.%02d', $parts[2], $parts[1], substr($parts[0], 2));
}

// Format filename (yyyy-mm-dd.csv) to TT.MM.JJJJ
function formatDatumLang($filename) {
    $parts = explode('-', pathinfo($filename, PATHINFO_FILENAME));
    return sprintf('%02d.%02d.%04d', $parts[2], $parts[1], $parts[0]);
}

// Get day before given filename
function vortag($filename) {
    $date = DateTime::createFromFormat('Y-m-d', pathinfo($filename, PATHINFO_FILENAME));
    $date->modify('-1 day');
    return $date->format('d.m.y');
}
?>

<!-- Form: first shows artist selection -->
<div class="form-container">
    <form method="get" style="display: flex; align-items: center; gap: 20px;">
        <label for="interpret">Interpret:</label>
        <select name="interpret" id="interpret" class="dropdown" onchange="this.form.submit()">
            <?php
            foreach ($interpreten as $ordnerPfad) {
                $ordner = basename($ordnerPfad);
                $selected = ($ordner === $ausgewaehlterInterpret) ? "selected" : "";
                echo "<option value=\"$ordner\" $selected>$ordner</option>";
            }
            ?>
        </select>

        <!-- If artist selected, show date dropdown -->
        <?php if (!empty($_GET['interpret'])): ?>
            <label for="datum">Datum:</label>
            <select name="datum" id="datum" class="dropdown">
                <?php
                foreach ($dateien as $datei) {
                    $basename = basename($datei);
                    $selected = ($basename === $ausgewaehltesDatum) ? "selected" : "";
                    // Format date to TT.MM.JJJJ
                    $date = DateTime::createFromFormat('Y-m-d', pathinfo($basename, PATHINFO_FILENAME));
                    echo "<option value=\"$basename\" $selected>" . $date->format('d.m.Y') . "</option>";
                }
                ?>
            </select>
            <button type="submit">Submit</button>
        <?php else: ?>
            <button type="submit">Submit</button>
        <?php endif; ?>
    </form>
</div>

<!-- Header with artist and date in long format -->
<?php
$h1 = "Spotify Streams";
if (!empty($ausgewaehlterInterpret) && !empty($ausgewaehltesDatum)) {
    $date = DateTime::createFromFormat('Y-m-d', pathinfo($ausgewaehltesDatum, PATHINFO_FILENAME));
    $h1 = "$ausgewaehlterInterpret – Spotify Streams – " . $date->format('d.m.Y');
}
echo "<h1>$h1</h1>";
?>

<!-- Output table if valid CSV -->
<?php
$csvPfad = "$csvRoot/$ausgewaehlterInterpret/$ausgewaehltesDatum";

if (file_exists($csvPfad)) {
    echo "<table>";
    if (($handle = fopen($csvPfad, "r")) !== FALSE) {
        $ersteZeile = true;
        while (($daten = fgetcsv($handle, 1000, ",")) !== FALSE) {
            echo "<tr>";
            foreach ($daten as $index => $wert) {
                if ($ersteZeile) {
                    if ($index == 1 || $index == 2) {
                        $wert .= " (" . formatDatumKurz($ausgewaehltesDatum) . ")";
                    } elseif ($index == 3) {
                        $wert .= " (" . vortag($ausgewaehltesDatum) . ")";
                    }
                    echo "<th>" . htmlspecialchars($wert) . "</th>";
                } else {
                    echo "<td>" . htmlspecialchars($wert) . "</td>";
                }
            }
            echo "</tr>";
            $ersteZeile = false;
        }
        fclose($handle);
    }
    echo "</table>";
} elseif (isset($_GET['datum'])) {
    echo "<p>Datei nicht gefunden.</p>";
}
?>

</body>
</html>
