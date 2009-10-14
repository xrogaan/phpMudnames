<?php

$number = 5;

// --

chdir('../');
require('mudnames.php');

$gname = new mudnames();

$names = array();
while ($number > 0) {
	$names[] = $gname->generate_name_from('random');
	$number--;
}

echo implode('<br />', $names);

echo "<br/><br/>Memory peak ~" , round(memory_get_peak_usage()/1024, 2) , "ko";
