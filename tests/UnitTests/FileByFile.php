<?php
echo("\nRunning *Tests.php for '.' : ");
$files = scandir('.');
$results = [];
foreach($files as $name){

  if (preg_match('/^[a-zA-Z]+Tests.php$/', $name)){

    echo("\n".str_pad("<<{$name}>>", 80, " ", STR_PAD_BOTH));

    $res = [];

    exec("php-seolan10-7.4 phpunit $name", $res);

    foreach($res as $i=>$line){
      echo("\n\t {$line}");
      if (preg_match('/^OK[^\,]/', $line))
	$results[$name] = $line;
      elseif(preg_match('/^OK[\,]/', $line)){
	$results[$name] = 'OK but : '.$res[$i+1];
      }elseif(preg_match('/^ERRORS/', $line)){
	$results[$name] = 'ERROR : '.$res[$i+1];
      }
    }
    echo("\n".str_pad("<<{$name}>>", " ", 80, STR_PAD_BOTH));
  }
}


echo("\n ==== \n");


foreach($results as $name=>$result){
  echo("\n\t ".str_pad($name,60,".",STR_PAD_RIGHT)." {$result}");
}

echo("\n ==== \n");
