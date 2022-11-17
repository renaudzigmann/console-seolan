<?php
include('lib2.php');

include('console-create-checkenv.php');

$homename =  getenv('USERNAME');

echo("\r\n\r\n");
$homename = myReadline("Répertoire d'installation ?", $homename);
$root = "/home/$homename/";

if(!file_exists($root)) {
  echo "Le path $root n'existe pas\r\n";
  die();
}

echo "\r\nBase de donnée\r\n";
$user = myReadline('User', $homename);
$pass = myReadline('Password', '');
$host = myReadline('Hostname', '');
$databse = myReadline('DB Name', $user);

$conn = getDBConn($host, $databse, $user, $pass);
$res = $conn->query("show variables like 'version'")->fetch();

list($version) = explode(':', $res['Value']);
list($versionnum) = explode('-', $version);

if (empty($versionnum) || (version_compare('10.3.18', $versionnum)==1)){
  die("\r\nVersion mysql {$version} trop ancienne, '10.3.18' ou >= attendue");
} else {
  echo("\r\nVersion base de donnée ({$version}) ok\r\n\r\n");
}


$siteurl = myReadline('Adresse du site ($HOME_ROOT_URL)', '');

$adminAddressDomain = myReadline('Domaine des addresses mails d\'administration');

echo "\r\nCompte admin (root)\r\n";
$rootpasswd = myReadline("Mot de passe du compte root");
$rootpasswdencrypted =  hash('sha256', $rootpasswd);
$rootemail = myReadline("Email du compte admin");


echo "\r\nCréation des fichiers/dossiers\r\n";

$required_path = array(
  "$root/tzr/",
  "$root/www/",
  "$root/www/templates_c",
  "$root/www/data/",
  "$root/logs/",
  "$root/var/",
  "$root/var/logs/",
  "$root/var/tmp/",
  "$root/var/cache",
  "$root/var/checkpoints"
);

foreach($required_path as $path) {
  if(!file_exists($path)) {
    mkdir($path);
  }
}

$console_path = realpath(__DIR__.'/..');
$links = array(
  $root."www/csx" => $console_path,
  $root."www/scripts" => 'csx/scripts/',
  $root."www/admin" => 'csx/admin/',
  $root."www/index.php" => 'csx/scripts/index.php',
  $root."www/json.php" => 'csx/scripts/json.php',
);
chdir($root."www");
foreach($links as $from => $to) {
  if(!file_exists($from)) {
    symlink($to, $from);
  }
}

echo "\r\nRécupération des données\r\n";

exec("rsync -az $console_path/contribs/console-create/data/ $root/www/data/");
exec("cp $console_path/contribs/console-create/local.ini $root/tzr/local.ini");
$localphp = file_get_contents("$console_path/contribs/console-create/local.php");
$localphp = sprintf($localphp,
	  $user,
	  $pass,
	  $host,
	  $databse,
	  $console_path.'/',
	  $siteurl,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain,
	  $adminAddressDomain
	  );
file_put_contents("$root/tzr/local.php", $localphp);
exec("gunzip < $console_path/contribs/console-create/default_dump.sql.gz | mysql -u$user -p$pass -h$host $databse");

exec("mysql -u$user -p$pass -h$host $databse -e 'update USERS set passwd=\"$rootpasswdencrypted\" where alias=\"root\"'");
exec("mysql -u$user -p$pass -h$host $databse -e 'update USERS set email=\"$rootemail\" where alias=\"root\"'");
exec("mysql -u$user -p$pass -h$host $databse -e 'update USERS set datef=\"2020-01-01\", datet=\"2030-01-01\" where alias=\"root\"'");
foreach(
[  
'_BATCH',
'STATS',
'_CACHE',
'_LINKS',
'_LOCKS',
'_MARKS10',
'_REFERERS',
'_REFERERS10',
'_REGISTRY',
'_RPATH',
'_RPATH10',
'_STATS',
'_VARS'
] 
  as $tb){
  exec("mysql -u$user -p$pass -h$host $databse -e 'truncate $tb'");
}
echo "\r\nFait !\r\n";

?>
