<?php
    $csv_file_name ='One Direction 2026-01-06.csv'; 

    $handle = fopen($csv_file_name, 'r');

    $data = fgetcsv($handle, 1000, ",");

    $all_record_arr = [];

    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE ){
        $all_record_arr[] = $data;
    }

    fclose($handle);

?>

<html>
    <head>
        <meta charset="UTF-8">
        <title>Spotify Top Songs</title>
        <link href="styles.css" rel="stylesheet">
    </head>

    <body>
        <div class="form-container">
            <form method="post" class="form-container">
                <label for="interpretDropdown">Wähle:</label>
                <select name="interpretDropdown" id="interpretDropdown" class="dropdown">
                    
                </select>
                <button type="submit" class="button-submit">Submit</button>
            </form>
        </div>

        <h1>Spotify Top Songs</h1>
        <h2>One Direction</h2>
        <table>
            <thead>
                <th>Rank</th><th>Titel</th><th>Streams</th><th>Daily</th>;
            </thead>
            <tbody>
                <?php foreach($all_record_arr as $rec){?>
                    <tr><td><?=$rec[0]?></td><td><?=$rec[1]?></td><td><?=$rec[2]?></td><td><?=$rec[3]?></td></tr>
                <?php } ?>  
            </tbody>
        </table>
    </body>
</html>