<?php
namespace Seolan\Model\DataSource\Comments;
use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Module\Module;
class Comments extends \Seolan\Model\DataSource\Table\Table {
  
  public static $tablename = TZR_TABLE_COMMENT_NAME;
  
  public function __construct($boid = 0) {
    parent::__construct($boid);
  }
  // la bonne instance du datasource
  static function factory(){
    return DataSource::objectFactoryHelper8('SPECS='.self::$tablename);
  }
  /**
   * ajout un commentaire sur l'objet dont l'oid est passé par oid ou _oid
   * + un commentaire/modulename qui précise l'origine dans le mail 
   * aux users notifiés
   */
  function insertComment($ar){
    
    $p = new \Seolan\Core\Param($ar, []);

    $moid = $p->get('moid'); // module depuis lequel est emis le commentaire
    $modulename = $p->get('modulename'); 
    $commentaire = $p->get('data'); // commentaire
    
    if ($p->is_set('_oid')){
      $coid = $p->get('_oid'); // oid de l'objet commenté
    } else {
      $coid = $p->get('oid'); // oid de l'objet commenté
    }
    if($commentaire!=NULL  && !empty(trim($commentaire))  && $coid !=NULL){
      preg_match_all("/#[a-zA-Z0-9_\-]*/",$commentaire,$tes); 
      
      foreach ($tes[0] as $value) {
	$st.=" ".$value;
      }
      if($p->is_set('upd')){ // si vous passez un UPD, il est forcé dans le commentaire
	$upd = $p->get('upd');
	$result = $this->procInput(array('_local'=>1, 'TAG'=>$st,'COMMENTAIRE'=>$commentaire,'COBJECT'=>$coid,'UPD'=>$upd,'CREAD'=>$upd));
      } else {
	$result = $this->procInput(array('_local'=>true, 'TAG'=>$st,'COMMENTAIRE'=>$commentaire,'COBJECT'=>$coid));
      }
      if (isset($result['oid'])){
	$this->sendUsersNotifications($result['oid'], $coid, $modulename, $moid);
      } else {
	\Seolan\Core\Logs::critical(__METHOD__,"Error insert comment for object : $coid");
      }
    }
  }
  /**
   * lire les commentaires d'un objet
   */
  function getObjectComments($oid,$select=NULL, $maxrecords=100){
    
    $cond=array();
        if(!empty($select)) $cond=$select;
        $cond['COBJECT']=array('=',$oid);
        $query=$this->select_query(array('cond'=>$cond));
        $p1=array('select'=>$query,
		  'selectedfields'=>'all',
		  'pagesize'=>$maxrecords,
		  'order'=>'CREAD desc',
		  'tplentry'=>TZR_RETURN_DATA,
		  '_mapping'=>array('COBJECT'=>'cobject','CREAD'=>'CREAD','COMMENTAIRE'=>'COMMENTAIRE'));
	return $this->browse($p1);
  }
  
  function resetComments($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $koid=$p->get("data");
    getDB()->execute("Delete from ".self::$tablename." where COBJECT=?",array($koid));
  }
  function resetAllCommentsFromModule($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $table=$p->get("data");
    getDB()->execute("Delete from ".self::$tablename." where COBJECT LIKE ?",array($table.'%'));
  }
  /**
   * Lors du premier commentaire d'un user donné, on notifie les utilisateurs ayant commenté la fiche
   * et le prop. si il y en a un 
   * Lors des ajouts ultérieurs, on ne notifie que le prop. de la fiche, si il y en a un (OWN est optionnel)
   * @param string $commentOid commentaire qui vient d'être ajouté
   * @param string $oid : oid de l'objet commenté
   */
  protected function sendUsersNotifications($commentOid, $koid, $modulename, $moid){
    
    // le nouveau commentaire
    $commentCurrent = getDB()->fetchRow("select commentaire, cread from {$this->getTable()} where koid=?", [$commentOid]);
    
    // commentaire précédent éventuel
    $commentPrevRs = getDB()->select("select own, commentaire from {$this->getTable()} where cobject=? and koid!=? and cread<=? order by cread desc", [$koid, $commentOid, $commentCurrent['cread'] ]);
    
    // les utilisateurs ayant déjà commenté l'objet
    $rusers = getDb()->fetchCol('select distinct OWN from '.$this->getTable().' where COBJECT=? and KOID != ?', [$koid, $commentOid]);
    
    // user en cours
    $userCurrent = \Seolan\Core\User::get_user()->_cur;
    
    // recherche d'un propriétaire éventuel
    $table = \Seolan\Core\Kernel::getTable($koid);
    
    if (\Seolan\Core\System::fieldExists($table, 'OWN')){ // prop. de la fiche
      $ownerid = getDB()->fetchOne('select OWN from '.$table.' where LANG=? and KOID=?',[TZR_DEFAULT_LANG, $koid]);
      $owner = getDB()->fetchRow('select koid, email,fullnam from USERS where KOID=?',[$ownerid]); // nom et email de l'owner de la fiche
    } else {
      $owner = false;
    }

    // titre de l'objet commenté
    $d = DataSource::objectFactoryHelper8($table)->display(['oid'=>$koid, 'tlink'=>true]);

    // utilisateurs par leur id
    $listusers=getDb()->select('select koid, fullnam, email from USERS')->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);
    
    $mail = new \Seolan\Library\Mail();

    if(!in_array($userCurrent['KOID'], $rusers)){ // premier commentaire de $user
      
      foreach ($rusers as $userid){
	if (isset($listusers[$userid])){   // utilisateur supprimé + incohérence 
	  $mail->addaddress($listusers[$userid]['email']);
	}
      }
      // owner de la fiche dans les destinataires si il n'y est pas
      if ($owner !== false && $owner['koid']!==$userCurent['KOID']){	
	if(!in_array($owner['koid'], $rusers)){
	  $mail->addAddress($owner['email']);
	}
      }
    } else { // commentaires ultérieurs
      if ($owner !== false && $owner['koid']!==$userCurrent['KOID']){
	$mail->addAddress($owner['email']);
      }
    }
    
    $allRecepts = $mail->getAllRecipients();

    // lors d'un premier commentaire par le OWN par exemple, il n'y a pas de destinataire
    if (count($allRecepts['to'])>0){
      $subject = "{$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','comments_mail_subject','text')} ($modulename)";
      $fullself = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(true, true);
      $body = vsprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','comments_mail_body', 'text'), 
		       [makeUrl($fullself, ['moid'=>Module::getMoid(XMODADMIN_TOID),
					    'template'=>'Core.layout/main.html',
					    'function'=>'portail',
					    'gopage'=>makeUrl($fullself,
							      ['function'=>'goto1',
							       'moid'=>$moid,
							       'oid'=>$koid])
		       ]),
			$d['tlink'],
			$modulename,
			$userCurrent['fullnam'],
			nl2br($commentCurrent['commentaire'], false)
		       ]);
      if ($commentPrevRs->rowCount()>0){
	$commentPrev = $commentPrevRs->fetch();
	$userPrev = getDB()->fetchRow("select fullnam from USERS where koid=?",[$commentPrev['own']]);
	if (empty($userPrev['fullnam'])){
	  $userPrev['fullnam'] = "'Unknown user'";
	}
	$body .= vsprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','comments_mail_body2', 'text'), 
			    [
			      $userPrev['fullnam'],
			      nl2br($commentPrev['commentaire'], false)
			    ]);
      }
      
      $mail->sendPrettyMail($subject, $body);
      
    }
    
  }  

  static function numberOfComments($oid){
    return getDB()->count("select count(*) from ".self::$tablename." where COBJECT='".$oid."'");
  }
    static function createCommentTable(){
      if(!\Seolan\Core\System::tableExists(self::$tablename)){
        //Créer une nouvelle table 
        $com=array();
        $com['translatable']=0;
        $com['auto_translate']=0;
        $com['trackchanges']=NULL;
        $com['btab']=self::$tablename;
        $com['bname'][TZR_DEFAULT_LANG]="ADMIN - Comments";
	$com['classname'] =  '\Seolan\Model\DataSource\Comments\Comments';
        \Seolan\Model\DataSource\Table\Table::procNewSource($com);
	
        //Créer un nouveau champ COMMENTAIRE
            $tab=DataSource::objectFactoryHelper8('SPECS='.self::$tablename);
            
            $tab->procEditField(array('field'=>'OWN','options'=>array('generate_link'=>0,'display_format'=>'%_fullnam','display_text_format'=>'%_fullnam')));
            
            $ar2 = [];
            $ar2['field'] = 'COMMENTAIRE';
            $ar2['ftype'] = '\Seolan\Field\Text\Text';
            $ar2['forder'] = '6';
            $ar2['fcount'] =40;
            $ar2['label'] = array();
            $ar2['label'][TZR_DEFAULT_LANG] = 'Commentaire';
            $ar2['browsable'] = 1;
            $ar2['queryable'] = 1;
            $ar2['translatable'] = 0;
            $ar2['multivalued'] = 0;
            $ar2['published'] = 1;
            $tab->procNewField($ar2);

            //Créer un nouveau champ COBJECT
            $ar2 = [];
            $ar2['field'] = 'COBJECT';
            $ar2['ftype'] = '\Seolan\Field\Link\Link';
            $ar2['forder'] = '7';
            $ar2['fcount'] =0;
            $ar2['label'] = array();
            $ar2['label'][TZR_DEFAULT_LANG] = 'CObject';
	    $ar2['target'] = TZR_DEFAULT_TARGET;
            $ar2['browsable'] = 1;
            $ar2['queryable'] = 1;
            $ar2['translatable'] = 0;
            $ar2['multivalued'] = 0;
            $ar2['published'] = 1;
            $tab->procNewField($ar2);

            //Créer un nouveau champ CREAD
            $ar2 = [];
            $ar2['field'] = 'CREAD';
            $ar2['ftype'] = '\Seolan\Field\DateTime\DateTime';
            $ar2['forder'] = '8';
            $ar2['fcount'] =0;
            $ar2['label'] = [];
            $ar2['label'][TZR_DEFAULT_LANG] = 'Date';
            $ar2['browsable'] = 1;
            $ar2['queryable'] = 1;
            $ar2['translatable'] = 0;
            $ar2['multivalued'] = 0;
            $ar2['published'] = 1;
            $tab->procNewField($ar2);

        }
    }
}
