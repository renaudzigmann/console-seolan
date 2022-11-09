<?php
namespace Seolan\Library;
class Lock {
  static $locks = array(); // conserve un pointeur sur les locks, sinon ne fonctionne pas (gc?)

  static function getLock($lock, $nbslot=1, $tries=1, $sleep=0, $wait=false) {
    return \Seolan\Library\Lock::getMultiLock($lock, true, $nbslot, $tries, $sleep, $wait);
  }

  /// obtenir un lock partagé dans un namespace local
  static function getSharedLock($lock) {
    $file=TZR_TMP_DIR.$lock;
    $fp = fopen($file, 'c+');
    if(!flock($fp, LOCK_SH|LOCK_NB)) {
      return NULL;
    } else {
      return $fp;
    }
  }
  /// obtenir un lock exclusif dans un namespace local. Si un lock
  /// partagé est en cours, cela génère un échec (résultat NULL)
  static function getExclusiveLock($lock) {
    $file=TZR_TMP_DIR.$lock;
    $fp = fopen($file, 'c+');
    if(!flock($fp, LOCK_EX|LOCK_NB)) {
      return NULL;
    } else {
      return $fp;
    }
  }

  static function getGlobalLock($lock, $nbslot=1, $tries=1, $sleep=0, $wait=false) {
    return \Seolan\Library\Lock::getMultiLock($lock, false, $nbslot, $tries, $sleep, $wait);
  }
  
  static function getMultiLock($lock, $locallock=true, $nbslot=6, $tries=5, $sleep=0, $wait=false) {
    if($locallock===true) $file=TZR_TMP_DIR.$lock;
    else $file=TZR_LOCK_DIR.$lock;
    for($try=1;$try<=$tries;$try++) {
      // on regarde tous les slots pour voir s'il y en a un de libre
      for($i=1;$i<=$nbslot; $i++) {
	$lock=$file.$i;
        $mask = $wait ? LOCK_EX : LOCK_EX|LOCK_NB;
	$fp = fopen($lock, 'c+');
	if(flock($fp, $mask)) {
	  return self::$locks[] = $fp;
	}
      }
      
      // on dort un delai aleatoire entre chaque essai
      if($try<$tries){
        sleep($sleep+rand(1,10)*$try);
      }
    }
    return FALSE;
  }

  static function releaseLock($lock) {
    flock($lock, LOCK_UN);
    return fclose($lock);
  }
}

