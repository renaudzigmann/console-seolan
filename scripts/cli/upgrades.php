<?php
$options = getopt('C:',["class:","upgrade:","config:","dry","help"]);
define('TZR_ADMINI',1);
define('TZR_SCHEDULER',1);
define('TZR_BATCH',1);

if(isset($options["help"])) {
    $help=<<<END
Usage: php-seolan10 upgrades.php [options]
   sans options: exécution des taches en attente
   --help : ce message
   --upgrade numéro upgrade à appliquer. Par exemple: 20190131
   --config nom du fichier de config
   --class nom de la classe sur laquelle appliquer les upgrades
   --dry : détection, traces etc sans appliquer
Examples:
Application des mises à jours en attente
php-seolan10 upgrades.php 

Application des mises à jour en attente sur une classe
php-seolan10 upgrades.php --class \\\\Seolan\\\\Core\\\\Shell

Application d'une mise à jour particulière
php-seolan10 upgrades.php --class \\\\Seolan\\\\Core\\\\Shell --upgrade 20190131

END;
    echo $help;
    exit();
}

// fichier de configuration par défaut
if(empty($options['config'])) $options['config']=getenv('HOME').'/../tzr/local.php';

if(file_exists($options['config'])) {
  include_once($options['config']);
  include_once($LIBTHEZORRO.'bootstrap.php');
} else {
  die("config file ({$options['config']}) not found");
}

// traitement des upgrades
// application d'un upgrade spécifique
$className = @$options['class'];
$upgradeNo = @$options['upgrade'];

// à vide
if (isset($options['dry'])){
  define('TZR_UPGRADE_DRYRUN', '1');
}

// il existe des upgrades qui ne sont accessibles que aux admin
setUserRoot();
$XSHELL = new \Seolan\Core\Shell();
$XSHELL->_load_user();
$XSHELL->_cache=false;
\Seolan\Library\Upgrades::applyUpgrades($className, $upgradeNo);


