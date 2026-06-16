<?php
$db = new PDO('sqlite:C:/Program1/Projects/Shuttle/Laravel/database/database.sqlite');
$rows = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $t) {
    echo $t . PHP_EOL;
}
