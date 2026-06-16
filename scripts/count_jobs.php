<?php
$db = new PDO('sqlite:C:/Program1/Projects/Shuttle/Laravel/database/database.sqlite');
$count = $db->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
echo $count.PHP_EOL;
