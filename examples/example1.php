<?php

require_once('../lib/ColorDetection.php');

$colorDetection = new ColorDetection();

$imageColors = $colorDetection->detectColors('./images/oranges.jpg');
var_dump($imageColors);

$imageColors = $colorDetection->detectColors('./images/car.jpg');
var_dump($imageColors);

$imageColors = $colorDetection->detectColors('./images/zebra.jpg');
var_dump($imageColors);

?>
