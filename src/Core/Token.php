<?php
namespace Seolan\Core;
use \Seolan\Core\DbIni;
use \Seolan\Library\SecurityCheck;
class Token {
  private static $instance = null;
  public static function factory(){
    if (!isset(static::$instance))
      static::$instance = new Token();
    return static::$instance;
  }
  /**
   * creation d'un token
   * @param $type 'preview'
   * @param $signleUse le token sera effacé après usage (=check)
   * @param $duration durée de validité en minutes
   * @param $moid le moid du module qui crée le token
   * @param $more autres infos 
   */
  public function create(string $type, bool $singleUse, int $duration, ?int $moid=null, array $more=[]):string{
    $id = uniqid("AUTHTOKEN:{$type}:");
    DbIni::set($id, json_encode([
      'uid'=>\Seolan\Core\User::get_current_user_uid(),
      'type'=>$type,
      'moid'=>$moid,
      'singleUse'=>$singleUse,
      'expirationDate'=>date('YmdHis',strtotime("+{$duration} minutes")),
      'more'=>$more
    ]));
    return $id;
  }
  /**
   * @param string $id l'identifiant à vérifier
   * @return array : 'ok|notfound|expired', token
   */
  public function check(?string $id=null):array{
    SecurityCheck::assertIsAuthToken($id);
    if (empty($id))
      return ['notfound', null];
    $value = DbIni::get($id, 'val');
    if (empty($value))
      return ["notfound", null];
    $token = json_decode($value, true);
    if(date('YmdHis') >= $token['expirationDate'])
      return ['expired', null];
    
    if ($token['singleUse'])
      DbIni::clear($id, false);
    
    return ['ok', $token];
  }
  /**
   * effacement de tout les token exipirés
   */
  public function purge(){
    $now = date('YmdHis');
    $dbini = $GLOBALS['XDBINI'];
    $todel = [];
    foreach(getDB()->select('select name,value from _VARS where name like "AUTHTOKEN:%"') as $line){
      $value = json_decode(unserialize(stripslashes($line['value'])), true);
      if($now >= $value['expirationDate']){
	$todel[] = $line['name'];
	if(isset($dbini->cache[$line['name']])) 
	  unset($dbini->cache[$line['name']]);
      }
    }
    if (count($todel)>0){
      getDb()->execute('delete from _VARS where name in ('.implode(',', array_fill(0, count($todel), '?')).')', $todel);
    }
  }
  protected function __construct(){}
      
}
