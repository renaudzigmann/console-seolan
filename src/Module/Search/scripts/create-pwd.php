<?php
$argv=[
	'toto',
	'Toto12!!',
	'5womDjhd0xu53oegzzGi2UVrvCR+LQe7JO19xfV5fys='
];
if (isset($argv[2])){
   var_dump($argv);
   $salt = base64_decode($argv[2]);
   echo("\n-> salt : $salt");
   $pwd = $argv[0];
   $encoded = base64_encode(hash('sha256', hash('sha256', $salt.$pwd)));
   echo("\n-> encoded : $encoded");
   
}

echo("\n\n");

