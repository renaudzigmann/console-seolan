<?php
namespace Seolan\Module\Calendar;
/// Module de gestion des Agenda
class Calendar extends \Seolan\Core\Module\Module {
  public $prefs;            /* Preferences du module */
  public $day;
  public $month;
  public $year;
  public $week;
  public $date;
  public $paques;
  public $ascencion;
  public $pentecote;
  public $request;          /* tableau avec les elements de base pour les requetes sur les evenements */
  public $diary;            /* resulset de l'agenda selectionne */
  public $group_of_diary;
  public $tcolors='#a3d0f2,#ef6464,#77c26d,#eb815d,#a794f0,#d5c128,#b4aeb0,#83d9ff,#e8879e,#98e057,#f68f3d,#c5a3e4,#e8da3f,#c9c1cd,#9fdbf3,#f19999,#c1ec4b,#efaf6a,#b3b8ef,#f4f078,#bac9cd';
  public $synchro=false;     /* Indique si on est sur une synchronisation ou non (par defaut non) */
  public $sendToUser=false;  /* Indique si les mails doivent etre envoyes a  l'utilisateur loge */
  public $defcal='';
  public $categories;
  public $display_begin;
  public $display_end;
  public $cal_content;
  public $tagenda;	    /* liste des agendas connus */
  public $tevt;	            /* evenements */
  public $tlinks;	    /* liens entre agendas et evenements */
  public $tcatevt;	    /* categories d'evenements */
  public $tcatagenda;       /* categories d'agendas */
  public $tplan;            /* liste des planifications en cours de traitement */
  public $tplaninv;         /* liste des invités pour les planification */
  public $tplandates;       /* liste des dates pour les planification */
  public $xsetevt;          /* xset sur la table de evenement */ 
  public $catperso;         /* oid de la categorie d'agenda perso */
  /* ensemble des variables a passer aux templates (_fieldlist : liste des champs de base de l'agenda) */
  public $bloc=array('_fieldlist'=>array('text','begin','end','allday','cat','place','descr','visib','repet','end_rep','repexcept','recall',
					 'isrecal','attext','rrule','UIDI','KOIDD','KOIDS','KOIDIT'));
  public $caldav_request;
  static public $upgrades=['20210429'=>''];

  static protected $iconcssclass='csico-calendar';
  
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Calendar_Calendar');
    $this->colors=explode(',',$this->tcolors);

    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $now=$p->get('now');


    if (@$_REQUEST['caldav_request']) {
      $this->caldav_request = true;
      if($p->get('oid')){
        $oid =$this->tagenda.':'.$p->get('oid');
      }
    }
    

    // Dans la cas du traitement d'une planification, on ne construit pas les infos de l'agenda
    if(\Seolan\Core\Kernel::getTable($oid)==$this->tplaninv){
      return;
    }

    // Recupere la date a visualiser
    if(isset($_REQUEST['now']) || !empty($now)){
      $this->day=date('j');
      $this->month=date('n');
      $this->year=date('Y');
    }else{
      $this->day=$p->get('day');
      $this->month=ltrim($p->get('month'),'0');
      $this->year=ltrim($p->get('year'),'0');
    }
    $this->date=$this->year.'-'.str_pad($this->month,2,'0',STR_PAD_LEFT).'-'.str_pad($this->day,2,'0',STR_PAD_LEFT);
    $this->bloc['day']=$this->day;
    $this->bloc['month']=$this->month;
    $this->bloc['year']=$this->year;

    // S'authentifie a la connexion pour une synchro par PUT 
    if(!empty($_REQUEST['_function'])) $f=$_REQUEST['_function'];
    elseif(!empty($_REQUEST['function'])) $f=$_REQUEST['function'];
    else $f = null;
    if($f=='synchro'){
      if(empty($_REQUEST['oid']) && !$this->caldav_request){
	die('nok');
      }
      if(!\Seolan\Core\User::authentified()){
	$sess=new \Seolan\Core\Session();
	$ret=$sess->procAuth();
	if(!$ret){
	  header('HTTP/1.0 401 Authorization Required');
	  die('Authorization Required');
	}
      } 
    }

    $this->xsetevt=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tevt);
    $this->xsetevt->desc['begin']->display_format=$this->xsetevt->desc['end']->display_format=
      $this->xsetevt->desc['begin']->edit_format=$this->xsetevt->desc['end']->edit_format='H:M';
 
    // Si pas d'utilisateur, ca ne sert à rien de continuer car on est dans un upgrade
    if(empty($GLOBALS['XUSER'])) return;

    // Recupere les preferences, l'agenda courant
    $this->getPrefs();
    if(empty($this->defcal) && !empty($this->prefs['defaultcal']))
      $this->defcal=$this->prefs['defaultcal'];
    if(empty($oid)){
      if(!empty($this->prefs['defaultcal']))
	$oid=$this->prefs['defaultcal'];
      else
	$oid=$this->defcal;

      if (!empty($oid)
	  && \Seolan\Core\Kernel::getTable($oid) == $this->tagenda
	  && !getDB()->fetchOne("select 1 from {$this->tagenda} where koid=?", [$oid]))
      $oid = null;

    }
    
    // Dans le cas ou il n'y a ni oid ni agenda par defaut on prend le premier des agendas autorises
    if(empty($oid) && !$this->caldav_request) {
      $oids=$this->getAuthorizedDiaries();
      if(!empty($oids)) {
	reset($oids);
	$oid=current($oids);
      }
    }

    // Vue par défaut
    if(!empty($this->prefs['defview'])) $this->defview=$this->prefs['defview'];
    
    // Verifications de securite
    $params_to_check=array('day','week','year','month');
    foreach($params_to_check as $t) {
      $t1=$p->get($t);
      if(!empty($t1) && !is_numeric($t1)) {
	$this->$t=$t1;
      }
    }

    $this->initialize($oid);

    date_default_timezone_set($this->diary['tz']);
  }
  
  /// Initialise le module pour un agenda donné : Recupere les données de l'agenda et prepare les composants de base pour les requetes SQL
  function initialize($oid){
    $this->diary=getDB()->fetchRow('SELECT * FROM '.$this->tagenda.' where KOID=?',array($oid));
    if($this->diary){
      if(empty($this->diary['defvisi']) || !in_array($this->diary['defvisi'],array('PU','OC','PR'))) $this->diary['defvisi']='PR';
      $this->bloc['diary']=&$this->diary;
      $ar=explode(':',$this->diary['begin']);
      $this->diary['beginnum']=$ar[0]+$ar[1]/60;
      $ar=explode(':',$this->diary['end']);
      $this->diary['endnum']=$ar[0]+$ar[1]/60;
      if($this->diary['cons']==1) {
	$ar=explode('||',$this->diary['agcons']);
	$br=array($this->diary['KOID']);
	foreach($ar as $i=>$oid){
	  if(!empty($oid)){
	    $br[]=$oid;
	    $this->bloc['diariesprop'][$oid]['color']=$this->colors[$i];
	  }
	}
	$this->bloc['group_of_diary']=$this->group_of_diary=true;
      } else {
	$this->diary['rwsecure']=$this->secure($this->diary['KOID'],'saveEvt');
	$this->bloc['diariesprop'][$this->diary['KOID']]['rwsecure']=$this->diary['rwsecure'];
	$this->bloc['group_of_diary']=$this->group_of_diary=false;
	$this->bloc['diariesprop'][$this->diary['KOID']]['color']=$this->colors[0];
	$br=array($this->diary['KOID']);
      }

      $all=$br;
      // Ajoute les consolidations
      $cons=$this->getDiariesForConsolidation();
      if(!empty($cons['active'])){
	$list=array_keys($cons['active']);
	$br=array_merge($br,$list);
      }
      if(!empty($cons['list'])){
	$all=array_merge($all,array_keys($cons['list']));
      }

      // Récupère les noms des differentes consolidations
      $rs=getDB()->fetchAll('select KOID,name from '.$this->tagenda.' where KOID in ("'.implode('","',$all).'")');
      foreach($rs as $ors) $this->bloc['diariesprop'][$ors['KOID']]['name']=$ors['name'];
      unset($rs);

      foreach($cons['list'] as $oid=>$foo){
	$this->bloc['diariesprop'][$oid]['color']=$foo['color'];
	if(is_numeric($oid)){
	  $mod=\Seolan\Core\Module\Module::objectfactory(array('moid'=>$oid,'tplentry'=>TZR_RETURN_DATA));
	  if(!is_object($mod)) continue;
	  $this->bloc['diariesprop'][$oid]['name']=$mod->getLabel();
	  $this->bloc['diariesprop'][$oid]['url']=$mod->XCalGetUrl('display');
	  $this->bloc['diariesprop'][$oid]['generalurl']=$mod->XCalGetUrl('browse');
	}
      }

      // Supprime les consolidations erronées (qui n'ont pas de nom) et met à jour le cache
      foreach($cons['list'] as $oid=>&$foo){
	if(empty($this->bloc['diariesprop'][$oid]['name'])){
	  unset($cons['list'][$oid]);
	  unset($cons['active'][$oid]);
	}
      }
      $this->bloc['consolidation']=$this->cache['consolidations']=$cons;
      $rw=$this->getAuthorizedDiaries('rw');
      $this->bloc['privatediaries']=$rw;

      // Champs dans les request (+ KOID et LANG qui ne sont pas dans le tableau) (ordre important dans un union en sql)
      $this->request['selectedfields']=array('begin','end','allday','visib','DOWNER','DNAME','DKOID','cat');
      // Requete
      $this->request['select']='select '.$this->tevt.'.KOID,'.$this->tevt.'.KOIDD,'.$this->tevt.'.LANG,'.$this->tevt.'.begin,'.$this->tevt.'.end,'.
	$this->tevt.'.allday,'.$this->tevt.'.visib,'.$this->tagenda.'.OWN as DOWNER,'.$this->tagenda.'.name as DNAME,'.
	$this->tagenda.'.KOID as DKOID,'.$this->tevt.'.cat,"'.$this->_moid.'" as MOID';
      $this->request['from']='from '.$this->tevt.','.$this->tlinks.','.$this->tagenda;
      $this->request['where']='where '.$this->tagenda.'.KOID in ("'.implode('","',$br).'") AND '.
	$this->tagenda.'.KOID='.$this->tlinks.'.KOIDD AND '.$this->tevt.'.KOID='.$this->tlinks.'.KOIDE AND '.
			      '('.$this->tagenda.'.KOID in ("'.implode('","',$rw).'") OR '.$this->tevt.'.visib!="PR")';

      // catégories d'evt accessibles pour cet agenda
      $rs=getDB()->fetchAll('SELECT * FROM '.$this->tcatevt.' WHERE (commun=1 OR OWN=?) AND IFNULL(name,"")!="" ORDER BY name',
			    [$this->diary['OWN']]);
      foreach($rs as $cat) {
	$this->categories[$cat['KOID']]=['name'=>$cat['name'],'color'=>@$cat['color']];
      };
      unset($rs);
      
      $this->bloc['categories']=$this->categories;

    }
  }

  /// Recupere les evenements en rappel et envoie un mail de rappel
  protected function _daemon($period='any') {
    $rs=getDB()->fetchAll('select '.$this->tevt.'.*, '.$this->tagenda.'.tz, USERS.email '.
		     'from '.$this->tevt.','.$this->tlinks.','.$this->tagenda.',USERS '.
		     'where '.$this->tagenda.'.KOID='.$this->tlinks.'.KOIDD AND '.$this->tlinks.'.KOIDE='.$this->tevt.'.KOID AND '.
		     'USERS.KOID='.$this->tagenda.'.OWN AND '.
		     'UNIX_TIMESTAMP('.$this->tevt.'.begin)<=UNIX_TIMESTAMP("'.gmdate('Y-m-d H:i:s',time()).'")+'.$this->tevt.'.recall*60'.
		     ' AND recall!=0', array());
    foreach($rs as $ev) {
      date_default_timezone_set($ev['tz']);
      $rs2=getDB()->execute('update '.$this->tevt.' set recall=0 where KOID=?',array($ev['KOID']));
      $mail=new \Seolan\Library\Mail();
      $mail->_modid=$this->_moid;
      $mail->FromName='';
      $mail->From='noreply@xsalto.com';
      $mail->Subject='Rappel évènement';
      $mail->IsHTML(true);
      $mail->Body = 'Du '.date('d/m/Y H:i',strtotime($ev['begin'].' GMT')).' au '.
	date('d/m/Y H:i',strtotime($ev['end'].' GMT')).' : '.$ev['text'];
      $mail->AddAddress($ev['email']);
      $mail->Send();
    }
    unset($rs);
    return true;
  }

  /// suppression du module
  function delete($ar=NULL) {
    parent::delete($ar);
  }

  /// initialisation des proprietes
  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt('Vue par defaut', 'defview', 'list', 
			    array('values'=>array('displayDay', 'displayWeek', 'displayMonth', 'displayYear'),
				  'labels'=>array('Jour', 'Semaine','Mois', 'Annee')));
    $this->_options->setOpt('Alerte mail a la personne connectee','sendToUser', 'boolean', NULL ,false);
    $this->_options->setOpt('Agenda : table des agendas', 'tagenda', 'table');
    $this->_options->setOpt('Agenda : table des évènements', 'tevt', 'table');
    $this->_options->setOpt('Agenda : table des liens', 'tlinks', 'table');
    $this->_options->setOpt('Agenda : table des catégories d\'evenements', 'tcatevt', 'table');
    $this->_options->setOpt('Agenda : table des catégories d\'agendas', 'tcatagenda', 'table');
    $this->_options->setOpt('Planification : table des planifications', 'tplan', 'table');
    $this->_options->setOpt('Planification : table des paticipants', 'tplaninv', 'table');
    $this->_options->setOpt('Planification : table des dates', 'tplandates', 'table');
    $p1=\Seolan\Core\Module\Module::findParam($this->_moid);
    $this->_options->setOpt('Categorie "agenda personnel"', 'catperso', 'object', array('table'=>$p1['MPARAM']['tcatagenda']));
    $this->_options->setOpt('Couleurs des agendas', 'tcolors', 'text',array('rows'=>5,'cols'=>50));
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('index'			=>array('ro','rw','rwv','admin'),
             'displayDay'		=>array('ro','rw','rwv','admin'),
             'displayWeek'		=>array('ro','rw','rwv','admin'),
             'displayMonth'		=>array('ro','rw','rwv','admin'),
             'displayYear'		=>array('ro','rw','rwv','admin'),
             'addEvt'			=>array('ro','rw','rwv','admin'),
             'insertPlanif'		=>array('rwv','admin'),
             'procInsertPlanif'		=>array('rwv','admin'),
	     'confirmPlanif'            =>array('none','ro','rw','rwv','admin'),
	     'procConfirmPlanif'        =>array('none','ro','rw','rwv','admin'),
	     'browsePlanif'             =>array('rwv','admin'), 
	     'displayPlanif'            =>array('rwv','admin'), 
	     'editPlanif'               =>array('rwv','admin'), 
	     'procEditPlanif'           =>array('rwv','admin'), 
	     'delPlanif'                =>array('rwv','admin'),
             'getEmails'		=>array('ro','rw','rwv','admin'),
             'saveEvt'			=>array('rwv','admin'),
             'saveFastEvt'		=>array('rwv','admin'),
             'editDiary'		=>array('ro','rw','rwv','admin'),
             'saveDiary'		=>array('rwv','admin'),
	     'delEvt'                   =>array('rwv','admin'),
             'exportEvt'		=>array('ro','rw','rwv','admin'),
             'saveExport'		=>array('ro','rw','rwv','admin'),
             'importEvt'		=>array('rwv','admin'),
	     'saveImportTable'          =>array('rwv','admin'),
	     'synchro'                  =>array('ro','rw','rwv','admin'),
	     'setDefault'               =>array('ro','rw','rwv','admin'),
	     'addConsolidation'         =>array('ro','rw','rwv','admin'),
	     'updateConsolidation'      =>array('ro','rw','rwv','admin'),
	     'paramsConsolidation'      =>array('ro','rw','rwv','admin'),
	     'ajaxEdit'                 =>array('rwv','admin'),
	     'ajaxDel'                  =>array('rwv','admin')
            );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function update_sync_token($evt_oid){
    $attendees = getDB()->fetchCol('select KOIDD from '.$this->tlinks.' where KOIDE=?',[$evt_oid]);
    \Seolan\Core\Logs::notice(__METHOD__,"oid :  '{$evt_oid}' attendees : ".tzr_var_dump($attendees));
    \Seolan\Core\System::loadVendor('CalDAV/CalDAVDataBase.php');
    foreach($attendees as $at){
      \CalDAVDataBase::update_calendar_sync_token($at, $this);
    }
  }

  function ajaxEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('koid');
    $ar['oid']=$oid;
    $daybegin=$p->get('daybegin');
    $dayend=$p->get('dayend');
    $allday=$p->get('allday');
    if(!$allday)
      $ar['options']['begin']['togmt']=$ar['options']['end']['togmt']=true;
    if(!empty($oid)){
      $ors=getDB()->fetchRow('select begin,KOIDS from '.$this->tevt.' where KOID=? LIMIT 1', array($oid));
      // L'evenement fait parti d"une repetition et n'est pas l'evenement de base : on ajoute une exception à l'evenement de base et on le rend independant
      if(!empty($ors['KOIDS'])){
	$ar['KOIDS']='';
	$date=substr($ors['begin'],0,10);
	$ors2=getDB()->fetchRow('select `repexcept` from '.$this->tevt.' where KOID=? LIMIT 1', array($ors['KOIDS']));
	if(empty($ors2['repexcept'])) $except=$date;
	else $except=$ors2['repexcept'].';'.$date;
	getDB()->execute('update '.$this->tevt.' set UPD=UPD, `repexcept`=? where KOID=?', array($except,$ors['KOIDS']));
      }else{
	$ors2=getDB()->fetchRow('select KOID from '.$this->tevt.' where KOIDS=? order by begin limit 1', array($oid));
	// L'evenement fait parti d"une repetition et est pas l'evenement de base : la premiere repet devient l'evenement de base
	if(!empty($ors2)){
	  getDB()->execute('update '.$this->tevt.' set KOIDS=? where KOIDS=?', array($ors2['KOID'],$oid));
	  getDB()->execute('update '.$this->tevt.' set KOIDS=NULL where KOID=?', array($ors2['KOID']));
	}
      }
      $ret=$this->xsetevt->procEdit($ar);
    }else{
      $text=$p->get('text');
      $begin=$p->get('begin');
      $end=$p->get('end');
      $ar1=array('text'=>$text,
		 'begindate'=>$begin['date'],
		 'beginhour'=>$begin['hour'],
		 'enddate'=>$end['date'],
		 'endhour'=>$end['hour'],
		 'visib'=>'PU',
		 'repet'=>'NO',
		 'recalltime'=>'',
		 'recalltype'=>'1',
		 'tplentry'=>TZR_RETURN_DATA);
      if($allday) $ar1['allday']=$ar1['allday_HID']=1;
      $ret=$this->saveEvt($ar1);
      $oid=$ret['oid'];
    }
    $ors=getDB()->fetchAll($this->request['select'].' '.$this->request['from'].' where '.$this->tagenda.'.KOID='.$this->tlinks.'.KOIDD AND '.
			   $this->tevt.'.KOID='.$this->tlinks.'.KOIDE AND '.$this->tevt.'.KOID= ? LIMIT 1', array($oid));
    $this->convertEventTime($ors[0]);
    $this->cutEvents($ors,$daybegin,$dayend);
    $ors=$ors[0];
    $this->update_sync_token($oid);
    die(json_encode(array('_isod'=>substr($ors['begin'],0,10),'_bd'=>$ors['_begindate'],'_bh'=>$ors['_beginhour'],
			  '_ed'=>$ors['_enddate'],'_eh'=>$ors['_endhour'],
			  '_obd'=>$ors['_cbegindate'],'_obh'=>$ors['_cbeginhour'],
			  '_oed'=>$ors['_cenddate'],'_oeh'=>$ors['_cendhour'],
			  'oid'=>$ors['oid'],'text'=>$ors['otext']->html,'descr'=>$ors['odescr']->html,'place'=>$ors['oplace']->raw,
			  'allday'=>($ors['allday']==1?'1':'0'),'color'=>$this->bloc['diariesprop'][$this->diary['KOID']]['color'],
			  'rw'=>1,'cat'=>$this->bloc['categories'][$ors['cat']]['color'],'url'=>$ors['_url'],'dname'=>$ors['DNAME']
			  )));
  }

  function ajaxDel($ar=NULL){
    $ret=$this->delEvt($ar);
    if($ret) die(json_encode('ok'));
    else die(json_encode('nok'));
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function='.$this->defview.'&oid='.$this->diary['KOID'].
      '&tplentry=br&template=Module/Calendar.'.$this->defview.'.html&now=1';
  }
  
  function secure($oid,string $func,$user=NULL,$lang=TZR_DEFAULT_LANG){
    $evoid=@$_REQUEST['koid'];
    if($func=='addEvt' && empty($evoid) && !parent::secure($oid,'saveEvt',$user,$lang)){
      return false;
    }
    if($func=='saveEvt' && !empty($evoid)){
      $cnt=getDB()->count('select COUNT(KOID) from '.$this->tevt.' where KOID=? and KOIDD=?', array($evoid, $this->diary['KOID']));
      if($cnt==0) return false;
    }
    $table=\Seolan\Core\Kernel::getTable($oid);
    if($table==$this->tplaninv){
      $ors=getDB()->fetchRow('select who from '.$this->tplaninv.' where KOID=? LIMIT 1', array($oid));
      $who=$ors['who'];
      if(\Seolan\Core\Kernel::getTable($who)==$this->tagenda){
	$ors=getDB()->fetchRow('select OWN from '.$this->tagenda.' where KOID=? LIMIT 1', array($who));
	$who=$ors['OWN'];
      }
      if($who==\Seolan\Core\User::get_current_user_uid()) return true;
      else return false;
    }
    return parent::secure($oid,$func,$user,$lang);
  }

  /// Recupere tous les notes et evenements du jour
  function getTasklet(){
    $txt='';
    $date=date('Y-m-d');
    $display_begin_gmt=gmdate('Y-m-d H:i:s',strtotime($date.' 00:00:00'));
    $display_end_gmt=gmdate('Y-m-d H:i:s',strtotime($date.' 23:59:00'));
    $rs=$this->getAlls($display_begin_gmt,$display_end_gmt,$date.' 00:00:00',$date.' 23:59:59',
		       'where '.$this->tagenda.'.KOID='.$this->tlinks.'.KOIDD AND '.$this->tlinks.'.KOIDE='.$this->tevt.'.KOID AND '.
		       '('.$this->tagenda.'.OWN="'.\Seolan\Core\User::get_current_user_uid().'" OR '.$this->tagenda.'.KOID="'.$this->defcal.'")');
    while($rs && ($ors=$rs->fetch())){
      $this->getCompleteEvent($ors);
      $date=explode('-',$ors['begin']);
      $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&oid='.$ors['DKOID'].'&moid='.$this->_moid.'&_function=displayDay&'.
	'template=Module/Calendar.displayDay.html&tplentry=br&day='.substr($date[2],0,2).'&month='.$date[1].'&year='.$date[0];
      $txt.='<p><a href="'.$url.'">'.$ors['text'].'</a> se d&eacute;roule aujourd\'hui';
      if($ors['allday']!=1){
	$txt.=' (de '.date('H\hi',strtotime($ors['begin']. 'GMT')).' &agrave; '.date('H\hi',strtotime($ors['end'].' GMT')).')';
      }
      $txt.='.</p>';
    }
    return $txt;
  }

  function getShortTasklet(){
    return $this->getTasklet();
  }
  
  function isCaldavRequest(){
      
      return $_REQUEST['caldav'];
  }

  /// synchronisation des agendas
  function synchro($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array());
    $this->synchro=true;
    $oid = $p->get('oid');

    //Permet d'être sûr d'avoir un oid valide (car dans Caldav l'oid n'a pas le TABLE:)
    if(strpos($oid,$this->tagenda.':') !== 0){
        $oid = $this->tagenda.':'.$oid;
    }
    $rw=$this->secure($oid, 'importEvt');
    if($this->caldav_request){
        if($rw){
	  \Seolan\Core\System::loadVendor('CalDAV/ServerApi.php');
	  \Seolan\Core\Logs::debug(__METHOD__." {$oid} caldav request start ".tzr_var_dump($_REQUEST));
	  $sapi = new \SApi();
	  $request = $sapi->getRequest($this);
	  $response = new \Response($request,$this);
	  
	  $sapi->sendResponse($response);

	  \Seolan\Core\Logs::debug(__METHOD__.' done');
	  
	}
	die();
    }
    if(!empty($_REQUEST['phpputdata']) && !$this->isCaldavRequest()) {
      if($rw){
	$_FILES['filetoimp']['tmp_name']=$_REQUEST['phpputdata'];
	$_FILES['filetoimp']['size']=filesize($_REQUEST['phpputdata']);
	$_FILES['filetoimp']['name']='Importics.ics';
	$this->importEvt($ar);
      }
    }else{
      $ar['period']='all';
      $this->saveExport($ar);
    }
    $id=session_id();
    session_destroy();
    die();
  }

  function index($ar=NULL)  {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $r=array();
    if($tplentry=='*return*')  	{
      return $r;
    } else {
      $this->setTplCommons();
      \Seolan\Core\Shell::toScreen1($tplentry,$r);
    }
  }

  /// cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    if(!$this->day) $date='now';
    else $date='day='.$this->day.'&month='.$this->month.'&year='.$this->year;

    $uniqid=\Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $dir='Module/Calendar.';
    $oidcal=@$this->diary['KOID'];
    parent::_actionlist($my);
    
    if($this->interactive) {
      $o1=new \Seolan\Core\Module\Action($this,'def',$this->getLabel(),
					 '&moid='.$moid.'&_function='.$this->defview.'&tplentry=br&template='.$dir.$this->defview.'.html&now','display');
      $my['stack'][]=$o1;
      
      if(!empty($oidcal)){
	$o1=new \Seolan\Core\Module\Action($this,'diary',$this->diary['name'],
					   '&moid='.$moid.'&oid='.$oidcal.'&_function='.$this->defview.'&tplentry=br&template='.$dir.$this->defview.'.html&now',
			      'display');
	$my['stack'][]=$o1;
      }
    }
    if(empty($oidcal)) return;
    // Liste des agendas
    if(!empty($this->bloc['diary_list']['lines_oid']) && count($this->bloc['diary_list']['lines_oid'])>1){
      $o1=new \Seolan\Core\Module\Action($this,'chooseag', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','diary'),'#');
      $o1->newgroup='chooseag';
      $o1->menuable=true;
      $my['chooseag']=$o1;
      foreach($this->bloc['diary_list']['lines_oid'] as $i=>$oid){
	$o1=new \Seolan\Core\Module\Action($this,'chooseag', $this->bloc['diary_list']['lines_oname'][$i]->html,
			      '&moid='.$this->_moid.'&oid='.$oid.'&function='.$this->defview.'&tplentry=br&template=Module/Calendar.'.$this->defview.'.html&now','chooseag');
	$o1->menuable=true;
	$my['chooseag'.$i]=$o1;
      }
    }

    // Assigner agenda par defaut
    $o1=new \Seolan\Core\Module\Action($this, 'setDefault', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','default'),
			  '&moid='.$moid.'&oid='.$oidcal.'&display='.\Seolan\Core\Shell::_function().'&_function=setDefault','edit');
    $o1->menuable=true;
    $my['setDefault']=$o1;

    // Aujourd'hui
    $o1=new \Seolan\Core\Module\Action($this, 'displayToday', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','today'),
			  '&moid='.$moid.'&oid='.$oidcal.
			  '&_function='.$this->defview.'&tplentry=br&template='.$dir.$this->defview.'.html&now','display');
    $o1->setToolbar('Seolan_Module_Calendar_Calendar','today');
    $my['displaytoday']=$o1;
    // Affichage quotidien
    $o1=new \Seolan\Core\Module\Action($this, 'displayDay', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','displayday'),
			  '&moid='.$moid.'&oid='.$oidcal.
			  '&_function=displayDay&tplentry=br&template='.$dir.'displayDay.html&'.$date,'display');
    $o1->setToolbar('Seolan_Module_Calendar_Calendar','displayday');
    $my['displayday']=$o1;
    // Affichage hebdo
    $o1=new \Seolan\Core\Module\Action($this, 'displayWeek', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','displayweek'),
			  '&moid='.$moid.'&oid='.$oidcal.
			  '&_function=displayWeek&tplentry=br&template='.$dir.'displayWeek.html&'.$date,'display');
    $o1->setToolbar('Seolan_Module_Calendar_Calendar','displayweek');
    $my['displayweek']=$o1;
    // Affichage mensuel
    $o1=new \Seolan\Core\Module\Action($this, 'displayMonth', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','displaymonth'),
			  '&moid='.$moid.'&oid='.$oidcal.
			  '&_function=displayMonth&tplentry=br&template='.$dir.'displayMonth.html&'.$date,'display');
    $o1->setToolbar('Seolan_Module_Calendar_Calendar','displaymonth');
    $my['displaymonth']=$o1;
    // Affichage annuel
    $o1=new \Seolan\Core\Module\Action($this, 'displayYear', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','displayyear'),
			  '&moid='.$moid.'&oid='.$oidcal.
			  '&_function=displayYear&tplentry=br&template='.$dir.'displayYear.html&'.$date,'display');
    $o1->setToolbar('Seolan_Module_Calendar_Calendar','displayyear');
    $my['displayyear']=$o1;

    // Parcourir planification en cours
    if($this->secure($this->diary['KOID'],'browsePlanif')){
      $o1=new \Seolan\Core\Module\Action($this, 'browsePlanif', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','browseplanif'),
			    '&moid='.$moid.'&oid='.$oidcal.'&_function=browsePlanif&template=Module/Calendar.browsePlanif.html&tplentry=br',
			    'edit');
      $o1->menuable=true;
      $o1->separator=true;
      $my['browsePlanif']=$o1;
    }
    // Ajouter planification
    if($this->secure($this->diary['KOID'],'insertPlanif')){
      $o1=new \Seolan\Core\Module\Action($this, 'insertPlanif', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','insertplanif'),
			    '&moid='.$moid.'&oid='.$oidcal.'&_function=insertPlanif&template=Module/Calendar.newPlanif.html&tplentry=br',
			    'edit');
      $o1->menuable=true;
      $my['insertPlanif']=$o1;
    }

    // Next et prev dans la toolbar
    $tb=\Seolan\Core\Shell::from_screen('br','toolbar');
    if(!empty($tb['prev'])) {
      $o1=new \Seolan\Core\Module\Action($this, 'next',  \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','previous'),
			    '&moid='.$moid.'&oid='.$oidcal.
			    '&_function='.\Seolan\Core\Shell::_function().'&tplentry=br&template='.$_REQUEST['template'].'&'.$tb['prev'],'display');
      $o1->setToolbar('Seolan_Core_General','previous');
      $my['previous']=$o1;
    }
    if(!empty($tb['next'])) {
      $o1=new \Seolan\Core\Module\Action($this, 'next',  \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','next'),
			    '&moid='.$moid.'&oid='.$oidcal.
			    '&_function='.\Seolan\Core\Shell::_function().'&tplentry=br&template='.$_REQUEST['template'].'&'.$tb['next'],'display');
      $o1->setToolbar('Seolan_Core_General','next');
      $my['next']=$o1;
    }
    
    // Edition de l'agenda courant
    if(!empty($oidcal)){
      $o1=new \Seolan\Core\Module\Action($this,'edit',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','editdiary'),
			    '&moid='.$moid.'&oid='.$oidcal.'&_function=editDiary&tplentry=br&'.
			    'template=Module/Calendar.editDiary.html&day='.$this->day.'&month='.$this->month.'&year='.$this->year,'admin');
      $o1->order=2;
      $o1->setToolbar('Seolan_Module_Calendar_Calendar','editdiary');
      $my['edit']=$o1;
    }

    // Abonnement
    $modsubmoid=\Seolan\Core\Module\Module::getMoid(XMODSUB_TOID);
    if(!empty($modsubmoid)){
      $o1=new \Seolan\Core\Module\Action($this, 'subscribe', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Subscription_Subscription','subadd'),
			    'javascript:v'.$uniqid.'.addSub("'.$modsubmoid.'","'.$oidcal.'");','more');
      $o1->menuable=true;
      $my['subscribe']=$o1;
    }
  }

  protected function _lasttimestamp() {
    $upd=getDB()->fetchOne('select ifnull(MAX(UPD),0) from '.$this->tlinks);
    return $upd;
  }

  function _whatsNew($ts,$user,$group=NULL,$specs=NULL,$timestamp) {
    $oid=$specs['oid'];
    $details=$specs['details'];
    if($oid){
      $lvl=\Seolan\Core\User::secure8maxlevel($this,$oid);
      if(in_array($lvl,array('rw','rwv','admin'))){
	$rs=getDB()->select('select '.$this->tlinks.'.* from '.$this->tlinks.' left outer join '.$this->tevt.' on '.
			 $this->tevt.'.KOID='.$this->tlinks.'.KOIDE where '.$this->tlinks.'.UPD>=? and '.
			    $this->tlinks.'.KOIDD=?', array($ts, $oid));
      }elseif($lvl=='ro'){
	$rs=getDB()->select('select '.$this->tlinks.'.* from '.$this->tlinks.' left outer join '.$this->tevt.' on '.
			 $this->tevt.'.KOID='.$this->tlinks.'.KOIDE where '.$this->tlinks.'.UPD>=? and '.
			    $this->tlinks.'.KOIDD=? and '.$this->tevt.'.visib="PU"', array($ts, $oid));
      }
    }else{
      $prcals=$this->getAuthorizedDiaries('ro','array',true);
      $pucals=$this->getAuthorizedDiaries('rw','array',true);
      $prcals=implode('","',array_diff($prcals,$pucals));
      $pucals=implode('","',$pucals);
      $rs=getDB()->select('select '.$this->tlinks.'.* from '.$this->tlinks.' left outer join '.$this->tevt.' on '.
		       $this->tevt.'.KOID='.$this->tlinks.'.KOIDE where '.$this->tlinks.'.UPD>=? and '.
		       '((visib="PU" and '.$this->tlinks.'.KOIDD in ("'.$prcals.'")) or ('.$this->tlinks.'.KOIDD in ("'.$pucals.'"))) '.
			  'order by '.$this->tlinks.'.KOIDE', array($ts));
    }
    $txt='';
    $prevoid='';
    $rs2=getDB()->select('SELECT KOID,name FROM '.$this->tagenda);
    $diaries=array();
    while($rs2 && ($ors2=$rs2->fetch())){
      $diaries[$ors2['KOID']]=$ors2['name'];
    }    
    while($rs && ($ors=$rs->fetch())){
      if($prevoid!=$ors['KOIDE']){
	$d=$this->xsetevt->display(array('oid'=>$ors['KOIDE'],'tplentry'=>TZR_RETURN_DATA));
	$when=$d['oUPD']->html;
	$who=$d['lst_upd']['usernam'];
	$prevoid=$ors['KOIDE'];
      }
      $txt.='<li><a href="'.$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'moid='.$this->_moid.'&function=addEvt&mode=view&oid='.$ors['KOIDD'].
	'&koid='.$d['oid'].'&tplentry=br&template=Module/Calendar.addEvt.html&_direct=1">"'.$d['otext']->html.'"</a> sur "'.$diaries[$ors['KOIDD']].'" '.
	'('.$when.', '.$who.')</li>';
    }
    return $txt;
  }

  function actionListAdd(&$my,$f){
    if(!$this->group_of_diary && $this->diary['rwsecure']){
      $o1=new \Seolan\Core\Module\Action($this,'addevt',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','addevt'),
			    '&moid='.$this->_moid.'&_function=addEvt&tplentry=br&template=Module/Calendar.addEvt.html&oid='.$this->diary['KOID'].
			    '&day='.$this->day.'&month='.$this->month.'&year='.$this->year.'&display='.$f,'edit');
      $o1->setToolbar('Seolan_Module_Calendar_Calendar','addevt');
      $my['addevt']=$o1;
      $o1=new \Seolan\Core\Module\Action($this,'import',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','importevt'),
			    '&moid='.$this->_moid.'&_function=importEvt&tplentry=br&template=Module/Calendar.importEvt.html&oid='.$this->diary['KOID'].
			    '&day='.$this->day.'&month='.$this->month.'&year='.$this->year.'&display='.$f,'edit');
      $o1->menuable=true;
      $my['importevt']=$o1;
    }
    $o1=new \Seolan\Core\Module\Action($this,'export',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','exportevt'),
			  '&moid='.$this->_moid.'&_function=exportEvt&tplentry=br&template=Module/Calendar.exportEvt.html&oid='.$this->diary['KOID'].
			  '&day='.$this->day.'&month='.$this->month.'&year='.$this->year.'&display='.$f,'edit');
    $o1->menuable=true;
    $my['exportevt']=$o1;
  }

  function al_displayDay(&$my){
    // Si pas d'utilisateur, ca ne sert à rien de continuer car on est dans un upgrade
    if(empty($GLOBALS['XUSER']) || \Seolan\Core\User::isNobody()) return;
    $uniqid=\Seolan\Core\Shell::uniqid();
    $moid=$this->_moid;
    $oidcal=@$this->diary['KOID'];
    $cons=$this->getDiariesForConsolidation();
    $f=\Seolan\Core\Shell::_function();
    $this->actionListAdd($my,$f);
    $o1=new \Seolan\Core\Module\Action($this, 'cons', \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','consolidation','text'),'#','edit');
    $o1->newgroup='cons_index';
    $o1->menuable=true;
    $my['cons']=$o1;
    $auths=$this->getAuthorizedDiaries();
    foreach($auths as $oid){
      if($oid==$this->diary['KOID'] || !empty($cons['list'][$oid])) continue;
      if(empty($my['consag'])){
	$o1=new \Seolan\Core\Module\Action($this, 'consag', \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','diary'),'#');
	$o1->group='cons_index';
	$o1->newgroup='consag_index';
	$o1->menuable=true;
	$my['consag']=$o1;
      }
      $ors=getDB()->fetchRow('select name from '.$this->tagenda.' where KOID=?', array($oid));
      $o1=new \Seolan\Core\Module\Action($this, 'consag', $ors['name'],'javascript:v'.$uniqid.'.addConsolidation("'.$oid.'");');
      $o1->group='consag_index';
      $o1->menuable=true;
      $my['consag'.$oid]=$o1;
    }

    $o1=new \Seolan\Core\Module\Action($this, 'consmod', \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','module'),'#');
    $o1->group='cons_index';
    $o1->newgroup='consmod_index';
    $o1->menuable=true;
    $my['consmod']=$o1;
    $mods=\Seolan\Core\Module\Module::authorizedModules();
    $rs=getDB()->fetchAll('select MOID,MODULE from MODULES where TOID in("'.XMODTABLE_TOID.'") order by MODULE');
    foreach($rs as $ors) {
      $moid=$ors['MOID'];
      if(!in_array($moid,$mods) || !empty($cons['list'][$moid])) continue;
      $o1=new \Seolan\Core\Module\Action($this, 'consag', $ors['MODULE'],'javascript:v'.$uniqid.'.addConsolidation("'.$moid.'");');
      $o1->group='consmod_index';
      $o1->menuable=true;
      $my['consmod'.$ors['MODULE']]=$o1;
    }
    unset($rs);
  }
  function al_displayWeek(&$my){
    $this->al_displayDay($my);
  }
  function al_displayMonth(&$my){
    $this->al_displayDay($my);
  }
  function al_displayYear(&$my){
    $this->al_displayDay($my);
  }
  function al_addEvt(&$my){
    $this->actionListAdd($my,@$_REQUEST['display']);
  }
  function al_editDiary(&$my){
    $this->actionListAdd($my,@$_REQUEST['display']);
  }
  function al_exportEvt(&$my){
    $this->actionListAdd($my,@$_REQUEST['display']);
  }
  function al_importEvt(&$my){
  }
  function al_browsePlanif(&$my){
    $uniqid=\Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'delPlan',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete','text'),'javascript:v'.$uniqid.'.deleteselected();','edit');
    $o1->order=3;
    $o1->setToolbar('Seolan_Core_General','delete');
    $my['delPlan']=$o1;
  }
  function al_displayPlanif(&$my){
    $o1=new \Seolan\Core\Module\Action($this,'editPlan',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit','text'),str_replace('function=displayPlanif','function=editPlanif',$_SERVER['REQUEST_URI']),'edit');
    $o1->menuable=true;
    $my['editPlan']=$o1;
  }

  /// Afficher les infos sur une journee
  function displayDay($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $this->bloc['calendar_mode']='';
    $this->createCalendar($p);

    $display_begin_gmt=gmdate('Y-m-d H:i:s',strtotime($this->date.' 00:00:00'));
    $display_end_gmt=gmdate('Y-m-d H:i:s',strtotime($this->date.' 23:59:00'));
    $this->display_begin=date('Y-m-d H:i:s',strtotime($this->date.' 00:00:00'));
    $this->display_end=date('Y-m-d H:i:s',strtotime($this->date.' 23:59:00'));
    $day_begin=$this->diary['beginnum'];
    $day_end=$this->diary['endnum'];

    // Recupere les notes et evenements
    $notes=$this->getNotes($this->display_begin,$this->display_end,false,true);
    $events=$this->getEvents($display_begin_gmt,$display_end_gmt,false,true);
    $this->getDisplayHours($events,$day_begin,$day_end);
    $this->cutNotes($notes);
    $this->cutEvents($events,$day_begin,$day_end);

    // Construit les infos a envoyer au template
    $this->bloc['daybegin']=$day_begin;
    $this->bloc['dayend']=$day_end;
    $this->bloc['dates']=array($this->date);
    $this->bloc['events']=$events;
    $this->bloc['notes']=$notes;
    $this->bloc['toolbar']=array('prev'=>date('\d\a\y=j&\m\o\n\t\h=n&\y\e\a\r=Y',strtotime($this->date.' -1 day')),
                                 'next'=>date('\d\a\y=j&\m\o\n\t\h=n&\y\e\a\r=Y',strtotime($this->date.' +1 day')));
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }

  /// Afficher les infos sur une semaine
  function displayWeek($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());

    $first_day_week=$this->calculateWeekDetails($p);
    $this->createCalendar($p);

    $display_begin_gmt=gmdate('Y-m-d H:i:s',$first_day_week);
    $display_end_gmt=gmdate('Y-m-d H:i:s',strtotime('+7 day -1 minute',$first_day_week));
    $this->display_begin=date('Y-m-d H:i:s',$first_day_week);
    $this->display_end=date('Y-m-d H:i:s',strtotime('+7 day -1 minute',$first_day_week));
    $day_begin=$this->diary['beginnum'];
    $day_end=$this->diary['endnum'];

    // Recupere les notes et evenements
    $notes=$this->getNotes($this->display_begin,$this->display_end,false,true);
    $events=$this->getEvents($display_begin_gmt,$display_end_gmt,false,true);
    $this->getDisplayHours($events,$day_begin,$day_end);
    $this->cutNotes($notes);
    $this->cutEvents($events,$day_begin,$day_end);

    // Construit les infos a envoyer au template
    $this->bloc['daybegin']=$day_begin;
    $this->bloc['dayend']=$day_end;
    $this->bloc['dates']=array();
    for($i=0;$i<7;$i++) $this->bloc['dates'][]=date('Y-m-d',strtotime('+'.$i.' day',$first_day_week));
    $this->bloc['events']=$events;
    $this->bloc['notes']=$notes;
    $this->bloc['toolbar']=array('prev'=>date('\w\e\e\k=W&\y\e\a\r=o',strtotime('-1 week '.$this->date)),
                                 'next'=>date('\w\e\e\k=W&\y\e\a\r=o',strtotime('+1 week '.$this->date))
				 );
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }
  
  /// Afficher les infos sur un mois
  function displayMonth($ar=NULL)  {
    $p=new \Seolan\Core\Param($ar,array());
    $events=$table=array();
    $this->day=1;
    
    $this->createCalendar($p);
    
    $month_list_label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','monthlist');
    $this->bloc['header']=$month_list_label[$this->month-1].' '.$this->year;

    // Les infos a afficher sont deja calculer pour le mini calendrier du bas. On les recupere donc pour les afficher en entier
    $this->bloc['body']=$this->cal_content;
    $this->bloc['toolbar']=array('prev'=>date('\d\a\y=1&\m\o\n\t\h=n&\y\e\a\r=Y',strtotime($this->date.' -1 month')),
                                 'next'=>date('\d\a\y=1&\m\o\n\t\h=n&\y\e\a\r=Y',strtotime($this->date.' +1 month'))
				 );
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }

  /// Afficher les infos sur une annee
  function displayYear($ar=NULL)  {
    $p=new \Seolan\Core\Param($ar,array());

    $month_list_label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','monthlist');
    $this->createCalendar($p);

    $display_begin_gmt=gmdate('Y-m-d H:i:s',strtotime($this->year.'-01-01 00:00:00'));
    $display_end_gmt=gmdate('Y-m-t H:i:s',strtotime($this->year.'-12-31 23:59:00'));
    $this->display_begin=date('Y-m-d H:i:s',strtotime($this->year.'-01-01 00:00:00'));
    $this->display_end=date('Y-m-t H:i:s',strtotime($this->year.'-12-31 23:59:00'));
    
    // Recupere les notes et les evenements
    $rs=$this->getAlls($display_begin_gmt,$display_end_gmt,$this->display_begin,$this->display_end);
    while($rs && ($event=$rs->fetch())) {
      $this->convertEventTime($event);
      $begin=date('z',strtotime($event['begin']));
      $end=date('z',strtotime($event['end']));
      for($i=$begin;$i<=$end;$i++) $events[$i]=1;
    }
    
    for($j=1;$j<13;$j++) {
      $nb_day_prev_month=date('t', mktime(12,0,0,$j-1,1,$this->year));
      $nb_day_month=date('t', mktime(12,0,0,$j,1,$this->year));
      $first_day=date('N',mktime(12,00,00,$j,1,$this->year));
      $last_day=date('N',mktime(12,00,00,$j+1,0,$this->year));
      $day_of_year=date('z',mktime(12,00,00,$j,1,$this->year));
      $prev_month=date('n',mktime(12,00,00,$j-1,1,$this->year));
      $next_month=date('n',mktime(12,00,00,$j+1,1,$this->year));
      $prev_year=date('Y',mktime(12,00,00,$j-1,1,$this->year));
      $next_year=date('Y',mktime(12,00,00,$j+1,1,$this->year));
      
      $month_content=array();
      
      for($i=$first_day-2;$i>-1;$i--) {
	$month_content[]=array('day'=>$nb_day_prev_month-$i,'month'=>$prev_month,'year'=>$prev_year,'event'=>false,'style'=>'out');
      }
      for($i=1;$i<=$nb_day_month;$i++) {
	$month_content[]=array('day'=>$i,'month'=>$j,'year'=>$this->year,'event'=>(isset($events[$day_of_year+$i-1]))?true:false,
			       'style'=>'in');
      }
      for($i=1;$i<8-$last_day;$i++) {
	$month_content[]=array('day'=>$i,'month'=>$next_month,'year'=>$next_year,'event'=>false,'style'=>'out');
      }
      
      $content[$j]=array('header'=>$month_list_label[$j-1].' '.$this->year,'content'=>$month_content);
    }
    
    $this->bloc['body']=$content;
    $this->bloc['toolbar']=array('prev'=>'day=1&month1=n&year='.($this->year-1),'next'=>'day=1&month1=n&year='.($this->year+1));
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }

  /// Retourne une liste d'evenements/notes/tous sur une periode
  function browsePeriod($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('type'=>'all','begin'=>'1970-01-01','end'=>'2037-12-31'));
    $type=$p->get('type');
    $begin=$p->get('begin');
    $end=$p->get('end');
    $tplentry=$p->get('tplentry');
    $getrsonly=$p->get('getrsonly');
    if($type=='all') $rs=$this->getAlls(gmdate('Y-m-d H:i:s',strtotime($begin.' 00:00:00')),
					gmdate('Y-m-d H:i:s',strtotime($end.' 23:59:00')),
					$begin.' 00:00:00',$end.' 23:59:59');
    if($getrsonly) return $rs;
    $result=array();
    while($rs && ($ors=$rs->fetch())){
      $this->getCompleteEvent($ors);
      $ors['_begindate']=date('d/m/Y',strtotime($ors['begin'].' GMT'));
      $ors['_beginhour']=date('H:i',strtotime($ors['begin'].' GMT'));
      $ors['_enddate']=date('d/m/Y',strtotime($ors['end'].' GMT'));
      $ors['_endhour']=date('H:i',strtotime($ors['end'].' GMT'));
      $result[]=$ors;
    }
    return \Seolan\Core\Shell::toScreen2($tplentry,'list',$result);
  }

  /// rend les emails d'un user ou des users d'un groupe
  function getEmails($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $doid=$p->get("doid");
    if(\Seolan\Core\Kernel::getTable($doid)=='GRP') {
      $users = \Seolan\Module\Group\Group::users(array($doid));
    } else {
      $users=array($doid);
    }
    $emails=array();
    foreach($users as $oid) {
      $xuser=new \Seolan\Core\User(array('UID'=>$oid));
      $emails[]=$xuser->email();
      unset($xuser);
    }
    echo json_encode($emails);
    exit(0);
  }

  /// Ajout/edition/consultation d'un evenement
  function addEvt($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('getintattendees'=>true,'hour'=>'08:00:00'));
    $mode=$p->get('mode');
    $oid=$p->get('oid');
    $koid=$p->get('koid');
    $hour=$p->get('hour');
    $allday=$p->get('allday');
    $getintattendees=$p->get('getintattendees');

    // Definit la fonction a executer
    if($mode=='view'){
      $function='display';
      $secure=false;
    }else{
      $secure=$this->secure($oid,'saveEvt');
      if(empty($koid) && $secure) $function='input';
      elseif($secure) $function='edit';
      else $function='display';
    }
    if(!empty($koid)){
      $ors=getDB()->fetchRow('select * from '.$this->tevt.' where KOID=? LIMIT 1', array($koid));
      if(!empty($ors['KOIDS'])) $ar['oid']=$ors['KOIDS'];
      else $ar['oid']=$koid;
      if($ors['allday']!=1){
	$ar['options']['begin']['tz']='GMT';
	$ar['options']['end']['tz']='GMT';
      }
    }else{
      $ar['options']['begin']['value']=$this->date.' '.$hour;
      $ar['options']['end']['value']=date('Y-m-d H:i:s',strtotime($this->date.' '.$hour.' +1 hour'));
    }
    $ar['tplentry']=TZR_RETURN_DATA;
    $this->xsetevt->desc['end_rep']->compulsory=true;
    // filtre champ catégorie : cat de l'agenda + celle positionnée
    // <- legacy, les cat issues d'un remote sont sous le nom du user qui crée l'evt
    $cats = array_keys($this->categories);
    if ($function == 'edit' && isset($ors['cat'])){
      $cats[] = $ors['cat'];
    }
    $catfilter = implode('","',$cats);
    $ar['options']['cat']['filter'] = "(KOID in (\"{$catfilter}\"))";
   
    $ev=$this->xsetevt->$function($ar);
    $ev['secure']=$secure;

    // Formate les donnees dans le cas d'une edition
    if(!empty($koid)) {
      if(!empty($ev['orepexcept']->raw)) {
	$datedef=new \Seolan\Field\Date\Date();
	$tab_except=explode(';',$ev['orepexcept']->raw);
	foreach($tab_except as $tmp) $this->bloc['except'][$tmp]=$datedef->dateFormat($tmp);
      }
      $rs=getDB()->fetchAll('SELECT KOIDD from '.$this->tlinks.' where KOIDE=? and KOIDD!=?',
			    array($koid,$this->diary['KOID']));
      foreach($rs as $attendee) $all_attendees[$attendee['KOIDD']]='';
      unset($rs);
    }

    // Liste des differents mode de repetition et champ date except
    $this->bloc['repetition']=array('NO'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','no'),
				    'DAILY'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','daily'),
				    'WEEKLY'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','weekly'),
				    'MONTHLY'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','monthly'),
				    'YEARLY'=>\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','annual'));
    $xdatedef=clone($this->xsetevt->desc['end_rep']);
    $xdatedef->compulsory=false;
    $ev['oexcepttmp']=$xdatedef->edit($v='',$opt=array('fieldname'=>'excepttmp'));

    // Prepare la liste des invites possible (par defaut, liste des agendas non consolides)
    if($getintattendees){
      if($this->object_sec){
	if($function=="display") $cplt=" AND KOID IN (".$this->getAuthorizedDiaries("ro","sql").")";
	else $cplt=" AND KOID IN (".$this->getAuthorizedDiaries("rwv","sql").")";
      }else{
	$cplt="";
      }
      $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
      $vals=implode('||',array_keys($all_attendees));
      $opts=array('filter'=>"(cons IS NULL OR cons!=1) and KOID!=\"{$this->diary['KOID']}\"".$cplt);
      if($function=='display') $ev['oattendees']=$xset->desc['agcons']->display($vals,$opts);
      else $ev['oattendees']=$xset->desc['agcons']->edit($vals,$opts);

      $ev['oattendees']->sys=false;
    }else{
      $ev['oattendees']->sys=true;
    }

    if(!empty($ev['oattext'])){
      list($acl_user, $acl_grp)=\Seolan\Core\User::getUsersAndGroups();
      \Seolan\Core\Shell::toScreen1('users',$acl_user);
      \Seolan\Core\Shell::toScreen1('grps',$acl_grp);
    }
    \Seolan\Core\Shell::toScreen1('ev',$ev);
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }


  /// Sauvegarde un evenement
  public function saveEvt($ar =null) {
    $p=new \Seolan\Core\Param($ar, ['alertMail_HID'=>['val'=>'on']]);
    $koid=$p->get('koid');
    $text=$p->get('text');
    $cat=$p->get('cat');
    $descr=$p->get('descr');
    $visib=$p->get('visib');
    if(empty($visib)) $visib='PU';
    $repet=$p->get('repetition');
    $recalltime=$p->get('recalltime');
    $recalltype=$p->get('recalltype');
    $allday=$p->get('allday');
    $allday_HID=$p->get('allday_HID');
    $attext=$p->get('attext');
    $excepttab=$p->get('except');
    $alertMail = $p->get('alertMail_HID')['val'];
    $xset_link=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tlinks);
    
    $allday=$this->xsetevt->desc['allday']->post_edit($allday,array('allday_HID'=>$allday_HID));
    $allday=$allday->raw;
    $datedef=new \Seolan\Field\Date\Date();
    $ar1=array();
    $except=$except_mail=null;
    $list_mail_text='';
    $attselected=$p->get('selected');

    // Recupere l'agenda de l'evenement (si modifiation sur un agenda consolide, recupere l'agenda de l'evenement, sinon agenda courant)
    if($this->group_of_diary && !empty($koid)) {
      $tmp=getDB()->fetchRow('SELECT KOIDD FROM '.$this->tevt.' where KOID=?',array($koid));
      $diary=$tmp['KOIDD'];
    } 
    else {
      $diary=$this->diary['KOID'];
    }

    // Formatage des dates/heures
    $foo=$p->get('begin');
    if(!empty($foo) && is_array($foo)){
      $begindate=$foo['date'];
      $beginhour=$foo['hour'];
    }else{
      $begindate=$p->get('begindate');
      $beginhour=$p->get('beginhour');
    }
    $foo=$p->get('end');
    if(!empty($foo) && is_array($foo)){
      $enddate=$foo['date'];
      $endhour=$foo['hour'];
    }else{
      $enddate=$p->get('enddate');
      $endhour=$p->get('endhour');
    }
    if($allday=='1') {
      $beginhour='00:00:00';
      $endhour='23:59:00';
    } else {
      $beginhour=$beginhour.':00';
      $endhour=$endhour.':00';
    }
    if($p->get('caldav_request') && $allday == 1) {
        $enddate = $begindate;
    }
    $begin=$this->xsetevt->desc['begin']->post_edit(array('date'=>$begindate,'hour'=>$beginhour));
    $end=$this->xsetevt->desc['end']->post_edit(array('date'=>$enddate,'hour'=>$endhour));
    if($begin->raw>$end->raw) return array();
    $dbegin=$this->xsetevt->desc['begin']->display($begin->raw);
    $dend=$this->xsetevt->desc['end']->display($end->raw);
    $date_begin_tsp=strtotime($begin->raw);
    $date_end_tsp=strtotime($end->raw);
    // Preparation de la repetition
    if($repet=='NO' || empty($repet)) {
      $until=null;
      $until_tsp=0;
    } else {
      if($repet=='DAILY') $add_repetition='DAY';
      elseif($repet=='WEEKLY') $add_repetition='WEEK';
      elseif($repet=='MONTHLY') $add_repetition='MONTH';
      else $add_repetition='YEAR';
      $tmp=$datedef->post_edit($p->get('end_rep'));
      $until=$tmp->raw;
      
      $until_tsp=strtotime($until.' 23:59:00');
      if($p->get('caldav_request')) {
          $until = $p->get('until');
          if($until) {
              $until = $until['date']. ' 23:59:00';
          }
      }

      // Preparation des exceptions
      if(!empty($excepttab)) {
	foreach($excepttab as $tmp) {
	  $tmp2=$datedef->post_edit($tmp);
	  $except.=$tmp2->raw.';';
	  $except_mail.=$tmp.';';
	}
	$except=substr($except,0,(strlen($except)-1));
	$except_mail=substr($except_mail,0,(strlen($except_mail)-1));
      }
    }

    // Preparation du tableau de donnees
    $ar1=$ar;
    $ar1['repet']=$repet;
    $ar1['end_rep']=$until;
    $ar1['except']=$except;
    $ar1['recall']=($recalltime*$recalltype);
    $ar1['KOIDD']=$diary;
    if($p->get('UIDI')) {
      $ar1['UIDI'] = $p->get('UIDI');
    } else {
      $ar1['UIDI']=null;
    }
    $ar1['tplentry']=TZR_RETURN_DATA;
    $ar1['visib']=$visib;
    if($this->caldav_request) {
        $ar1['rrule'] = $p->get('rrule');
        $ar1['except'] = $p->get('except');
        $ar1['recall'] = $p->get('trigger');
        
        
    } else {
      $ar1['rrule']='';
    }
    // Si on est dans une modification, on efface les evenements lies a la source mais on garde l'evenement d'origine pour le modifier
    if(!empty($koid)) {
      $this->delEvt(array('koid'=>$koid,'noalert'=>true,'nodelsource'=>true));
      $ar1['oid']=$koid;
      $function='procEdit';
    }else{
      $function='procInput';
    }
  
   // Tant que l'on est dans l'intervale de repetition, on insere des entrees
    $repet=false;
    $koids=NULL;
    do{
      $date_begin=date('Y-m-d',$date_begin_tsp);
      // Si la date n'est pas dans les exceptions
      if(strpos($except,$date_begin)===FALSE) {
       	$ar1['begin']=($allday!='1') ? gmdate('Y-m-d H:i:s',$date_begin_tsp) : date('Y-m-d H:i:s',$date_begin_tsp);
        $ar1['end']=($allday!='1') ? gmdate('Y-m-d H:i:s',$date_end_tsp) : date('Y-m-d H:i:s',$date_end_tsp);
        $ar1['KOIDS']=$koids;
        $ret=$this->xsetevt->$function($ar1);
        if($function=='procEdit')
	  $ret['oid']=$koid;
        $xset_link->procInput(array('KOIDE'=>$ret['oid'],'KOIDD'=>$diary));
        foreach($attselected as $att) {
          // On ne copie pas l'événement dans l'agenda à l'origine de cet événement
          if ($att == $diary) continue;
          $ar2=array('KOIDD'=>$att,'KOIDE'=>$ret['oid']);
          $xset_link->procInput($ar2);
        }
        if(!$repet)
	  $koids=$ret['oid'];
      }
      $repet=true;
      $function='procInput';
      if(isset($add_repetition)) {
	$date_begin_tsp=strtotime('+1 '.$add_repetition,$date_begin_tsp);
	$date_end_tsp=strtotime('+1 '.$add_repetition,$date_end_tsp);
      }
    } while($date_begin_tsp<$until_tsp+1 && !$p->get('caldav_request'));
    // Envoie des invitations
    if($alertMail == 'on'){
      if ($koid)
	$place = $this->xsetevt->rDisplay($koid,[], false,'','',['selectedfields'=>['place']]);
      $mbody = '';
      $diaryowner=$this->getMailSender($ar);
      $mail=new \Seolan\Library\Mail();
      $mail->_modid=$this->_moid;
      $mail->From=$diaryowner['email'];
      $mail->FromName='';
      $msubject = sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','attendeesmail_sub',null),$diaryowner['fullnam'],$text);
      $mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','attendeesmail_header',null).'<br>';
      $mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','diary',null).' : '.$this->diary['name'].'<br>';
      $mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','text',null).' : '.$text.'<br>';
      $ldate=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','date',null);
      $lthe=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','the',null);
      $lat=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','at1',null);
      $lto=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','to1',null);
      if($allday=='1'){
	if($dbegin->date->raw==$dend->date->raw) $mbody.=$ldate.' : '.$lthe.' '.$dbegin->date->html.'<br>';
	else $mbody.=$ldate.' : '.$lat.' '.$dbegin->date->html.' '.$lto.' '.$dend->date->html.'<br>';
      }else{
	$mbody.=$ldate.' : '.$lat.' '.$dbegin->html.' '.$lto.' '.$dend->html.'<br>';
      }
      $mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','place',null).' : '.$place['oplace']->html.'<br>';
      $mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','description',null).' : '.$descr.'<br>';
      if($until){
	$mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','repetition',null).' : '.
	  \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar',$p->get('repetition'),null).' '.
	  \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','until',null).' '.$this->xsetevt->desc['end_rep']->display($until)->html;
	if (!empty($except_mail))
	  $mbody = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','except',null).' '.$except_mail;
	$mbody .= '<br><br>';
      }
      
      // Recupere la liste d'envoi
      $list=$this->getSendList($ar);

      $mailatt='';
      foreach($list as $email){
	if(!empty($email['fullnam']) && $email['fullnam']!='NOTATT') $mailatt.=$email['fullnam'].', ';
	elseif($email['fullnam']!='NOTATT') $mailatt.=$email['email'].', ';
      }
      if(!empty($mailatt)){
	$mailatt=substr($mailatt,0,-2);
	$mbody.=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','attendees',null)." : ".$mailatt.'<br>';
      }

      // Ajout de champs supplementaires au mail
      if(defined('XMODCALENDAR_MAILFIELDS')){
	$addfields=explode(',',XMODCALENDAR_MAILFIELDS);
	if(!empty($addfields)){
	  $ev=$this->xsetevt->display(array('oid'=>$koids,'tplentry'=>TZR_RETURN_DATA));
	  foreach($addfields as $f){
	    $mbody.=$ev['o'.$f]->fielddef->label.' : '.$ev[$f].'<br>';
	  }
	}
      }

      $mbody.='<br>'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','attendeesmail_msg',null).'<br>';
      foreach($list as $email){
	if(!empty($email['email'])) $mail->AddAddress($email['email']);
      }

      // Ajout du fichier iCal UTF8
      $file=TZR_TMP_DIR.uniqid('ical-').'.ics';

      $fd=fopen($file,'a');
      fwrite($fd,$this->saveExport(array('expoid'=>$koids,'intcall'=>true)));
      fclose($fd);
      $mail->AddAttachment($file);
      // Ajout du fichier iCal latin1 pour outlook
      $file2=TZR_TMP_DIR.uniqid('outlook-').'.ics';
      $fd=fopen($file2,'a');
      fwrite($fd,$this->saveExport(array('expoid'=>$koids,'intcall'=>true,'charset'=>'latin1')));
      fclose($fd);
      $mail->AddAttachment($file2);
      $mail->sendPrettyMail($msubject,$mbody); 
      unlink($file);
      unlink($file2);
    }
    $_REQUEST['_next']=str_replace('adddate',date('\d\a\y=j&\m\o\n\t\h=n&\y\e\a\r=Y',strtotime($begin->raw)),$_REQUEST['_next']);
    $this->update_sync_token($koids);
    return array('oid'=>$koids);
  
    }
       

  /// Renvoie la liste des emails pour les invitations
  public function getSendList($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $curuid=\Seolan\Core\User::get_current_user_uid();
    $maillist=array();
    
    // Ajout du possesseur de l'agenda a la liste d'envoi
    if(!empty($this->diary['mail']) && $this->diary['mail']==1){
      $diaryowner=getDB()->fetchRow('SELECT email,"NOTATT" as fullnam FROM USERS WHERE KOID=?',array($this->diary['OWN']));
      $maillist[$diaryowner['email']]=$diaryowner;
    }
    // Ajout du user loge a la liste d'envoi
    if($this->sendToUser){
      $rs=getDB()->fetchAll('SELECT email,"NOTATT" as fullnam FROM USERS WHERE KOID=?',array($curuid));
      foreach($rs as $email) $maillist[$email['email']]=$email;
      unset($rs);
    }

    // Ajoute les mails externes a la liste d'envoi
    $att_ext=$p->get('attext');
    if($att_ext!='') {
      $tab_attendees_ext=explode(';',str_replace("\n",';',str_replace("\r\n",';',$att_ext)));
      foreach($tab_attendees_ext as $tmp){
	if($tmp) $maillist[$tmp]=array('email'=>trim($tmp),'fullnam'=>trim($tmp));
      }
    }

    // Ajoute les mails internes et l'utilisateur de l'agenda courant a la liste d'envoi
    $att_selected=$p->get('selected');
    if(!empty($att_selected)) {
      $attendees_mysql=array();
      foreach($att_selected as $att) $attendees_mysql[]=$this->tagenda.".KOID=\"$att\"";
      $attendees_mysql="(USERS.KOID={$this->tagenda}.OWN AND (".implode(" OR ",$attendees_mysql)."))";
      $rs=getDB()->fetchAll('SELECT DISTINCT USERS.email,CONCAT(USERS.fullnam," (",'.$this->tagenda.'.name,")") as fullnam '.
			    'from USERS,'.$this->tagenda.' where '.$attendees_mysql);
      foreach($rs as $email) $maillist[$email['email']]=$email;
      unset($rs);
    }
    return $maillist;
  }
  
  /// Fixe l'expediteur du mail : manuellement (tableau avec email et fullnam) ou automatique a partir du user loge ou du possesseur
  private function getMailSender($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('mailSender'=>'user'));
    $mailSender=$p->get('mailSender');
    if(is_array($mailSender))
      $diaryowner=&$mailSender;
    else{
      if($mailSender=='user') $diaryowner=getDB()->fetchRow('SELECT * FROM USERS WHERE KOID=?',array(\Seolan\Core\User::get_current_user_uid()));
      elseif($mailSender=='owner') $diaryowner=getDB()->select('SELECT * FROM USERS WHERE KOID=?',array($this->diary['OWN']));
    }
    return $diaryowner;
  }

  /// Sauvegarde d'un evenement via le formulaire rapide
  function saveFastEvt($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $visib=$p->get('visib');
    if(empty($visib)) $visib='PU';
    $datedef=new \Seolan\Field\Date\Date();
    $cat=getDB()->fetchRow('SELECT * FROM '.$this->tcatevt.' where KOID=?',array($p->get('cat')));
    $date=$datedef->post_edit($p->get('date'));
    if($cat['allday']==1) {
      $begindate=$date->raw;
      $beginhour='00:00';
      $enddate=$date->raw;
      $endhour='23:59';
    } else {
      $begin_tsp=strtotime($date->raw.' '.$p->get('begin').':00');
      $begindate=date('Y-m-d',$begin_tsp);
      $beginhour=date('H:i',$begin_tsp);
      $during=strtotime('+'.$cat['time'].' minute',$begin_tsp);
      $enddate=date('Y-m-d',$during);
      $endhour=date('H:i',$during);
    }
    $ar1=array('begindate'=>$begindate,
	       'beginhour'=>$beginhour,
	       'enddate'=>$enddate,
	       'endhour'=>$endhour,
	       'visib'=>$visib,
	       'repet'=>'NO',
	       'allday'=>$cat['allday'],
	       'recalltime'=>$cat['recall'],
	       'recalltype'=>'1',
	       'tplentry'=>TZR_RETURN_DATA);
     return $this->saveEvt($ar1);
  }

  /// Suppression d'un evenement
  function delEvt($ar) {
    $p=new \Seolan\Core\Param($ar,array('noalert'=>false,'nodelsource'=>false));
    $oid=$p->get('koid');
    $noalert=$p->get('noalert');
    $nodelsource=$p->get('nodelsource');
    $curuid=\Seolan\Core\User::get_current_user_uid();
    // On prévient tout les agendas concernés qu'il y a eu une suppression
    $this->update_sync_token($oid);
    // Verifie si on supprime depuis l'agenda propriétaire ou non
    $rs=getDB()->select('select KOID from '.$this->tevt.' where KOID=? and KOIDD=?', array($oid, $this->diary['KOID']));
    if($rs->rowCount()==1) $fromown=true;
    else $fromown=false;

    // Si on ne specifie pas qu'on ne veut pas d'alert
    if(!$noalert){
      $diaryowner=$this->getMailSender($ar);
      $orsevt=getDB()->fetchRow('SELECT * FROM '.$this->tevt.' where KOID=?',array($oid));
      $mail=new \Seolan\Library\Mail();
      $mail->_modid=$this->_moid;
      $mail->FromName='';
      $mail->From=$diaryowner['email'];
      $mail->setTZRSubject(sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','delmail_sub'),$diaryowner['fullnam'],$orsevt['text']));
      if($orsevt['allday']==1){
	if(substr($orsevt['begin'],0,10)==substr($orsevt['end'],0,10)){
	  $datetext=strtolower(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','the').' '.date('d/m/Y',strtotime($orsevt['begin'])));
	}else{
	  $datetext=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','at1').' '.date('d/m/Y',strtotime($orsevt['begin'])).' '.
	    \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','to1').' '.date('d/m/Y',strtotime($orsevt['end']));
	}
      }else{
        $datetext=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','at1').' '.date('d/m/Y H\hi',strtotime($orsevt['begin'].' GMT')).' '.
                   \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','to1').' '.date('d/m/Y H\hi',strtotime($orsevt['end'].' GMT'));
      }
      $mail->Body=sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','delmail_msg'),$orsevt['text'],$datetext);
      if($fromown){
        $rs=getDB()->fetchCol('SELECT KOIDD FROM '.$this->tlinks.' WHERE KOIDE=?',array($oid));
        foreach($rs as $ors)
        $ar2['selected'][]=$ors;
        unset($rs);
        $ar2['attext']=$orsevt['attext'];
      }else{
          $ar2['selected'][]=$this->diary['KOID'];
      }
      $list=$this->getSendList($ar2);
      foreach($list as $email){
          if(!empty($email['email'])) $mail->AddAddress($email['email']);
      }
      $msubject = $mail->Subject;
      $mbody = $mail->Body;
      $mail->Subject = null;
      $mail->Body = null;
      $mail->sendPrettyMail($msubject, $mbody);
    }

    // Recuperation de l'objet source si necessaire
    $tmp=getDB()->fetchRow('SELECT KOIDS FROM '.$this->tevt.' where KOID=?',array($oid));
    if(!empty($tmp['KOIDS'])) $oid=$tmp['KOIDS'];

    // Suppression de tous les object non source et de leur lien
    $rs=getDB()->fetchCol('SELECT KOID FROM '.$this->tevt.' where KOIDS=?',array($oid));
    foreach($rs as $tmp) {
      if($fromown){
	$this->xsetevt->del(array('oid'=>$tmp));
	getDB()->execute('DELETE FROM '.$this->tlinks.' where KOIDE=?', array($tmp));
      }else{
	getDB()->execute('DELETE FROM '.$this->tlinks.' where KOIDE=? and KOIDD=?',array($tmp, $this->diary['KOID']));
      }
    }
    unset($rs);

    // Suppression du lien de l'objet source
    if($fromown) getDB()->execute('DELETE FROM '.$this->tlinks.' where KOIDE=?', array($oid));
    else getDB()->execute('DELETE FROM '.$this->tlinks.' where KOIDE=? and KOIDD=?', array($oid, $this->diary['KOID']));

    // Suppression de l'evenement source si pas de demande contraire et depuis agenda proprietaire
    if(!$nodelsource && $fromown) $this->xsetevt->del(array('oid'=>$oid));
    return true;
  }

  /// Prepare l'ajout/modifiaction d'un agenda
  function editDiary($ar=NULL) {
    $uid=\Seolan\Core\User::get_current_user_uid();
    $bloc = $this->bloc;

    $p=new \Seolan\Core\Param($ar,array());
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['selectedfields']=array('name','begin','end');
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
    if($uid==$this->diary['OWN']) $ins=$xset->edit($ar);
    else $ins=$xset->display($ar);
    // Recupere l'alias et le mot de passe pour le lien de synchro
    $ors=getDB()->fetchRow('select alias from USERS where KOID=? LIMIT 1',
			   array(\Seolan\Core\User::get_current_user_uid()));
    $ins['user']=$ors;
    $ins['urlsynch_ical']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true)
                .'&moid='.$this->_moid.'&function=synchro&password='
                .'&login='.$ors['alias']
                .'&oid='.$bloc['diary']['KOID'];
    $ins['urlsynch_ical'] = str_replace('admin.php?', 'auth.php?', $ins['urlsynch_ical']);
    
    $ins['urlsynch_simple_ical']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true)
                .'&moid='.$this->_moid.'&function=synchro'
                .'&oid='.$bloc['diary']['KOID'];
    
    $urlsync_caldav = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true)
                . '/' . $this->_moid 
                . '/calendars/' . $ors['alias'] 
                . '/' . $bloc['diary']['KOID'] .'/';
    $urlsync_caldav = str_replace($this->tagenda.':', '', $urlsync_caldav);
    $ins['urlsynch_caldav'] = str_replace('admin.php?', 'caldav.php', $urlsync_caldav);
    
    $urlsync_ipad = $GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true)
                . '/' . $this->_moid 
                . '/principals/' . $ors['alias'] .'/';
    $urlsync_ipad = str_replace($this->tagenda.':', '', $urlsync_ipad);
    $ins['urlsynch_ipad'] = str_replace('admin.php?', 'caldav.php', $urlsync_ipad);

    \Seolan\Core\Shell::toScreen1('ins',$ins);
    \Seolan\Core\Shell::toScreen1('br',$bloc);
  }

  /// Sauvegarde un agenda
  function saveDiary($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oid=$p->get('oid');
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
    $xset->procEdit($ar);
  }

  /// Prepare l'export de l'agenda
  function exportEvt($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $display=$p->get('display');
    if($display=='displayDay') {
      $filter_begin=$filter_end=$this->date;
    } elseif($display=='displayWeek') {
      $first_day_week=$this->calculateWeekDetails($p);
      $filter_begin=date('Y-m-d',$first_day_week);
      $filter_end=date('Y-m-d',strtotime('+6 day',$first_day_week));
    } elseif($display=='displayMonth') {
      $filter_begin=date('Y-m-01',strtotime($this->date));
      $filter_end=date('Y-m-t',strtotime($this->date));
    } else {
      $filter_begin=$this->year.'-01-01';
      $filter_end=$this->year.'-12-31';
    }
    $xdatedef=new \Seolan\Field\Date\Date();
    $xdatedef->compulsory=true;
    $this->bloc['begindate']=$xdatedef->edit($v=$filter_begin,$opt=array('fieldname'=>'begindate'));
    $this->bloc['enddate']=$xdatedef->edit($v=$filter_end,$opt=array('fieldname'=>'enddate'));
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }

  /// Exporte des evenements
  function saveExport($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $uid=\Seolan\Core\User::get_current_user_uid();
    $export=array();
    $expoid=$p->get('expoid');
    $intcall=$p->get('intcall');
    $charset=$p->get('charset');
    $period=$p->get('period');
    $begindate=$p->get('begindate');
    $enddate=$p->get('enddate');
    $caldav_request=false;

    if($p->get('calendar_koid')) {
        
        $this->diary['KOID'] = $p->get('calendar_koid');
        $caldav_request = true;
    }
    if(empty($charset))
      $charset='UTF-8';

    $xdataEvent = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->tevt);

    if($expoid){
      $events=$xdataEvent->browse(['selectedfields'=>'all',
				   'select'=>"select {$this->tevt}.* from {$this->tevt} where KOID=".getDB()->quote($expoid)]);
    }elseif($period=='all') {
      $events=$xdataEvent->browse(['selectedfields'=>'all',
				   'select'=>"select {$this->tevt}.* from {$this->tevt}, {$this->tlinks} where {$this->tevt}.KOID={$this->tlinks}.KOIDE and {$this->tlinks}.KOIDD=".getDB()->quote($this->diary['KOID'])." and ({$this->tevt}.KOIDS IS NULL or {$this->tevt}.KOIDS=\"\")"]);
    } else {
      $tab_date_1=explode('/',$begindate);
      $tab_date_2=explode('/',$enddate);
      $date_begin_tsp=strtotime($tab_date_1[2].$tab_date_1[1].$tab_date_1[0].' 00:00:00');
      $date_end_tsp=strtotime($tab_date_2[2].$tab_date_2[1].$tab_date_2[0].' 23:59:00');
      $events=$xdataEvent->browse(['selectedfields'=>'all',
				   'select'=>"select {$this->tevt}.* from {$this->tevt}, {$this->tlinks} where {$this->tevt}.KOID={$this->tlinks}.KOIDE and {$this->tlinks}.KOIDD=".getDB()->quote($this->diary['KOID'])." and UNIX_TIMESTAMP({$this->tevt}.begin) BETWEEN {$date_begin_tsp} AND {$date_end_tsp}"]);
    }

    // dans Response::setHeaders, on positionne content-type application/xml; charset=utf-8 ...
    $content="BEGIN:VCALENDAR\r\n";
    if($charset=="UTF-8")
      $content.="VERSION:2.0\r\n"; // Declaration normale de la version d'iCal
    else
      $content.="VERSION:2\r\n"; // Si pas en utf8, c'est qu'on exporte pour outlook et outlook n'aime pas le .0 alors en le met pas

    $content.="PRODID:-//Agenda Seolan\r\n";

    $myCategories = getDB()->select("SELECT KOID, IFNULL(name, '') AS name FROM {$this->tcatevt}", [])->fetchAll(\PDO::FETCH_KEY_PAIR);
    
    \Seolan\Core\Logs::debug(__METHOD__." synchro calendar {$events['last']} ".count($events['lines_oid']));
    \Seolan\Core\Logs::debug(__METHOD__." synchro calendar {$events['select']}");

    $isAuthorizedDiary = in_array($this->diary['KOID'],$this->getAuthorizedDiaries('rw'));
    
    for($i=0;$i<$events['last'];$i++){
      if(!in_array($events['lines_oid'][$i],$export)
         && !in_array($events['lines_oKOIDS'][$i]->raw,$export)) {
          if(!empty($events['lines_oKOIDS'][$i]->raw)) {
            // lecture et injection des données du parent => lots d'evts de la répet de même uid
            // sans quoi la répétition est pas bonne dans thundirbird
            // à voir cependant ? un seul evt + rrule de répétition devrait suffire ?
            $events['lines_oid'][$i] = $events['lines_oKOIDS'][$i]->raw;
            $event = $xdataEvent->display(['oid'=>$events['lines_oKOIDS'][$i]->raw,'selectedfields'=>'all']);
            foreach($event['fields_object'] as $field){
              $lines = 'lines_o'.$field->fielddef->field;
              if($events[$lines]){
                $events[$lines][$i]=$event['o'.$field->fielddef->field];
              }
            }
          }

	// Visibilite
        if($events['lines_ovisib'][$i]->raw=='PU'){
	  $visibility='PUBLIC';
	}elseif($events['lines_ovisib'][$i]->raw=='PR'){
	  if(!$isAuthorizedDiary)
	    continue;
	  $visibility='PRIVATE';
	}else{
	  $visibility='CONFIDENTIAL';
	  if(!$isAuthorizedDiary)
	    $events['lines_otext'][$i]->raw=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','occupy');
	}
	// Heure
	if($events['lines_oallday'][$i]->raw==1) {
	  $begin=';VALUE=DATE:'.date('Ymd',strtotime($events['lines_obegin'][$i]->raw));
	  $end=';VALUE=DATE:'.date('Ymd',strtotime($events['lines_oend'][$i]->raw.' +1 day'));
	} else {

	  $events['lines_obegin']->raw = date('Y-m-d H:i:s',strtotime($events['lines_obegin']->raw.' GMT'));
	  $events['lines_oend']->raw = date('Y-m-d H:i:s',strtotime($event['end'].' GMT'));
	  
	  $begin=':'.date('Ymd\THis\Z',strtotime($events['lines_obegin'][$i]->raw));
	  $end=':'.date('Ymd\THis\Z',strtotime($events['lines_oend'][$i]->raw));
	  $beginhour=date('\THis\Z',strtotime($events['lines_obegin'][$i]->raw));
        }

	$summary = trim($events['lines_otext'][$i]->raw);
	if (empty($summary)){
	  \Seolan\Core\Logs::debug(__METHOD__." synchro calendar continue summary is empty");
	  continue;
	}

	// Debut evenement
	$content.="BEGIN:VEVENT\r\n";
	if($caldav_request) {
          if (!empty($events['lines_oUIDI'][$i]->raw)) {
            $content.='UID:'.$events['lines_oUIDI'][$i]->raw."\r\n";
	  } else {
	    $content.='UID:'.str_replace($this->tevt.':', '', $events['lines_oid'][$i])."\r\n";
	  }            
        } else {
	  $content.='UID:'.$events['lines_oid'][$i]."\r\n";            
        }
	$content.='LAST-MODIFIED:'.gmdate('Ymd\THis\Z',strtotime($events['lines_oUPD'][$i]->raw))."\r\n";
	$content.='DTSTART'.$begin."\r\n";
	$content.='DTEND'.$end."\r\n";
	$content.="STATUS:CONFIRMED\r\n";

	// Repetition
	if(!empty($events['lines_orrule'][$i]->raw)){
	  $content.='RRULE:'.$events['lines_orrule'][$i]->raw."\r\n";
	}elseif(!empty($events['lines_orepet'][$i]->raw) && $events['lines_orepet'][$i]->raw!='NO'){
	  $content.='RRULE:FREQ='.$events['lines_orepet'][$i]->raw;
          if($events['lines_oend_rep'][$i]->raw !== '0000-00-00') {
              $content .=';UNTIL='.str_replace('-','',$events['lines_oend_rep'][$i]->raw);
          }
          $content .= "\r\n";
	}
	if(!empty($events['lines_orepexcept'][$i]->raw)) {
	  $except=explode(';',$events['lines_orepexcept'][$i]->raw);
	  if($events['lines_oallday'][$i]->raw==1) {
	    foreach($except as $tmp) {
	      $content.='EXDATE;VALUE=DATE:'.str_replace('-','',$tmp)."\r\n";
	    }
	  }else{
	    foreach($except as $tmp) {
	      $content.='EXDATE:'.str_replace('-','',$tmp)."$beginhour\r\n";
	    }
	  }
	}

	$content.="CLASS:$visibility\r\n";
      
	$content.='SUMMARY:'.$this->escapeICSText($events['lines_otext'][$i]->raw)."\r\n";
	$categories = explode(';', $events['lines_ocat'][$i]->raw);
	$catparts = [];
	foreach ($categories as $categorie) {
          $catparts[] = $myCategories[$categorie]??"";
	}
	$cattext = implode(',', $catparts);
	// la catégorie n'est pas obligatoire dans Thundirbird et il existe des catégories sans texte
	// on ne transmet que si il y a quelque chose, champ à priori pas obligatoire dans un ics
	// mais ne devrait pas être transmis vide
	if (!empty($cattext))
	  $content.='CATEGORIES:'.$this->escapeICSText($cattext)."\r\n";	

	if($visibility=='PUBLIC' || $isAuthorizedDiary){
	  if(!empty($events['lines_oplace'][$i]->html)) $content.='LOCATION:'.$this->escapeICSText($events['lines_oplace'][$i]->html)."\r\n";
	  if(!empty($events['lines_odescr'][$i]->html)) $content.='DESCRIPTION:'.$this->escapeICSText($events['lines_odescr'][$i]->raw)."\r\n";

	  // Invites
	  
	  if(!empty($events['lines_oattext'][$i]->raw)) {
	    $attendees_ext = preg_split('/[\n\r; ]/', $events['lines_oattext'][$i]->raw, -1, PREG_SPLIT_NO_EMPTY);
	    foreach($attendees_ext as $att) {
	      $content.='ATTENDEE:MAILTO:'.$att."\r\n";
	    }
	  }
	  $rs2=getDB()->fetchAll('select USERS.fullnam, USERS.email, '.$this->tagenda.'.name from '.$this->tlinks.','.$this->tagenda.',USERS '.
				 'where '.$this->tlinks.'.KOIDD='.$this->tagenda.'.KOID and '.$this->tagenda.'.OWN=USERS.KOID '.
				 'and '.$this->tlinks.'.KOIDE=? and '.$this->tlinks.'.KOIDD!=?', array($events['lines_oid'][$i],$events['lines_oKOIDD'][$i]->raw) );
	  foreach($rs2 as $att) {
	    $content.='ATTENDEE;CN=[CS] '.trim(str_replace(':','-',$att['fullnam'])).
	      ' ('.trim(str_replace(':','-',$att['name'])).'):MAILTO:'.$att['email']."\r\n";
	  }
	  unset($rs2);
	  
	  // Rappel
	  if(!empty($events['lines_orecall'][$i]->raw)){
	    $content.="BEGIN:VALARM\r\n";
	    $content.="TRIGGER;VALUE=DURATION:-PT".$events['lines_orecall'][$i]->raw."M\r\n";
	    $content.="ACTION:DISPLAY\r\n";
	    $content.="END:VALARM\r\n";
	  }
	  
	}
	$content.="END:VEVENT\r\n";
        $export[]=$events['lines_oid'][$i];
      }
    }
    
    \Seolan\Core\Logs::debug(__METHOD__." synchro calendar end count evts :  ".count($export));

    $content.="END:VCALENDAR\r\n";

    convert_charset($content,'UTF-8',$charset);

    if($intcall)
      return $content;
    else{
      header('Content-Type: text/calendar; charset='.$charset);
      header('Content-Transfer-Encoding:'.$charset);
      header('Content-disposition: attachment; filename=export_seolan.ics');
      header('Content-Length: '.strlen($content));
      header('Pragma: private');
      header('Cache-Control: no-cache, must-revalidate');
      header('Expires: 0');
      echo $content;
    }
  }

  function escapeICSText($text){
    $text=str_replace(array('&','\\','"',';',',',"\n","\r","\t"),array('et','\\\\','\"','\;','\,','\n','\r','\t'),$text);
    return $text;
  }
  function unescapeICSText($text){
    if (preg_match('@^text/html,[a-zA-Z0-9%\.]+":(.*)$@s', $text, $parts)){
      $text = $parts[1];
    }
    $text=str_replace(array('&','\\\\','\"','\;','\,','\n','\r','\t','\\\''),array('et','\\','"',';',',',"\n","\r","\t",'\''),$text);
    return $text;
  }
  /// réception d'une prop. ICS LOCATION => champ place de l'evt
  /// à voir : champ lien aussi ?
  public function processICSLocation($icsvalue){
    if (!$this->xsetevt->fieldExists('place'))
      return null;
    $placefield = $this->xsetevt->getField('place');
    if ($placefield instanceof \Seolan\Field\StringSet\StringSet){
      $place = $placefield->import($icsvalue,(Object)['create'=>false,'srcField'=>'notraw'])['value'][0];
      if (empty($place)){
	\Seolan\Core\Logs::notice(__METHOD__, " {$tis->field} {$this->table} : '$icsvalue' not exists in set'");
      }
    } else {
      $place = $icsvalue;
    }
    return $place;
  }
  /// Importe un ICS dans l'agenda
  function importEvt($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $error=false;
    if(isset($_FILES['filetoimp'])) {
      $filename=$_FILES['filetoimp']['tmp_name'];
      \Seolan\Core\System::loadVendor('ical/functions/ical_parser.php');
      $events = ical_parser($this, $filename);
      foreach($events as $evt){
        $this->update_sync_token($evt);
      }
      if(!$this->synchro) {
	if(!$error) return 0;
      }
    }
    $this->bloc['error']=$error; 
    return \Seolan\Core\Shell::toScreen1('br',$this->bloc);
  }

  /// Positionne l'agenda par defaut de l'utilisateur sur l'agenda courant
  function setDefault($ar=NULL){
    global $XSHELL;
    $p=new \Seolan\Core\Param($ar,NULL);
    $uid=$p->get('uid','local');
    $display=$p->get('display');
    $_nonext=$p->get('_nonext');
    if(empty($uid)) $uid=\Seolan\Core\User::get_current_user_uid();
    $oid=$this->diary['KOID'];
    getDB()->execute('update '.$this->tagenda.' set def=2 where OWN="'.$uid.'" AND KOID!="'.$oid.'"');
    if($uid==$this->diary['OWN']) getDB()->execute('update '.$this->tagenda.' set def=1 where KOID=?', array($oid));

    $prefs=array('defaultcal'=>$oid);
    // Définie aussi la vue par défaut si l'action est appelé depuis un affichage du calendrier
    if(strpos($display,'display')===0) $prefs['defview']=$display;
    $this->savePrefs($uid,$prefs);

    if(empty($_nonext)){
      $back=\Seolan\Core\Shell::get_back_url();
      \Seolan\Core\Shell::setNext($back);
    }
    return;
  }

  /// Ajout une entrée dans les consolidations de l'agenda courant
  function addConsolidation($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $save=$p->get('save');
    $color=$p->get('color');
    $target=$p->get('target');
    $text=$p->get('text');
    $begin=$p->get('begin');
    $end=$p->get('end');
    $descr=$p->get('descr');
    $place=$p->get('place');
    $params=array('color'=>$color,'text'=>$text,'begin'=>$begin,'end'=>$end,'descr'=>$descr,'place'=>$place);
    if($save){
      $cons=$this->prefs['consolidations'];
      $cons['list'][$target]=$params;
      $cons['active'][$this->diary['KOID']][$target]=1;
      $this->setPref(array('prop'=>'consolidations','propv'=>$cons));
    }else{
      $sess=getSessionVar('\Seolan\Module\Calendar\Calendar'.$this->_moid.':consolidations');
      $sess['list'][$target]=$params;
      $sess['active'][$this->diary['KOID']][$target]=1;
      setSessionVar('\Seolan\Module\Calendar\Calendar'.$this->_moid.':consolidations',$sess);
    }
  }

  /// Actualise la partie consolidation
  function updateConsolidation($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $active=$p->get('active');
    $activehid=$p->get('active_HID');
    $del=$p->get('del');
    $sess=getSessionVar('\Seolan\Module\Calendar\Calendar'.$this->_moid.':consolidations');
    
    foreach($activehid as $oid=>$foo){
      if(isset($this->prefs['consolidations']['list'][$oid])){
	if(isset($active[$oid])) $this->prefs['consolidations']['active'][$this->diary['KOID']][$oid]=1;
	else unset($this->prefs['consolidations']['active'][$this->diary['KOID']][$oid]);
      }
      if(isset($sess['list'][$oid])){
	if(isset($active[$oid])) $sess['active'][$this->diary['KOID']][$oid]=1;
	else unset($sess['active'][$this->diary['KOID']][$oid]);
      }
    }
    
    foreach($del as $oid=>$v){
      if(!$v) continue;
      unset($this->prefs['consolidations']['list'][$oid],$sess['list'][$oid]);
      foreach($this->prefs['consolidations']['active'] as $d=>&$foo) unset($foo[$oid]);
      foreach($sess['active'] as $d=>&$foo) unset($foo[$oid]);
    }
    $this->setPref(array('prop'=>'consolidations','propv'=>$this->prefs['consolidations']));
    setSessionVar('\Seolan\Module\Calendar\Calendar'.$this->_moid.':consolidations',$sess);
  }

  /// Recupere la liste des parametres pour l'importation d'evenements a partir d'une table
  function paramsConsolidation($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    \Seolan\Core\Shell::toScreen1($tplentry,$this->bloc);
    $moid=$p->get('cmoid');
    if(is_numeric($moid)){
      $mod=\Seolan\Core\Module\Module::objectFactory($moid);
      $mod->XCalParamsConsolidation($ar);
    }
  }

  /// Prepare les donnees pour la partie basse de l'agenda (navigation rapide)
  function createCalendar($p) {
    $nb_day_prev_month=date('t', mktime(12,0,0,$this->month-1,1,$this->year));
    $first_day=date('N',mktime(12,00,00,$this->month,1,$this->year));
    $last_day=date('N',mktime(12,00,00,$this->month+1,0,$this->year));
    $prev_month=date('n',mktime(12,00,00,$this->month-1,1,$this->year));
    $next_month=date('n',mktime(12,00,00,$this->month+1,1,$this->year));
    $prev_year=date('Y',mktime(12,00,00,$this->month-1,1,$this->year));
    $next_year=date('Y',mktime(12,00,00,$this->month+1,1,$this->year));
    $month_list_label_min=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','monthlistmin');
    $month_list_label=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','monthlist');
    $day_max_list=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','daymax');
    $day_inter_list=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','dayinter');
    $this->calculateFreeDay();
    
    // Creation de la liste des semaines du mois selctionne pour le calendrier
    $first_cal_week=intval(date('W',mktime(00,00,00,$this->month,1,$this->year)));
    $last_cal_week=date('W',mktime(00,00,00,$this->month+1,0,$this->year));
    if($first_cal_week>51) {
      $week_cal_list[]=array('week'=>$first_cal_week,'year'=>($this->year-1));
      $first_cal_week=1;
    }
    if($last_cal_week==1) {
      $last_cal_week=52;
      $tmp_week=$last_cal_week;
    }
    for($i=$first_cal_week;$i<=$last_cal_week;$i++) {
      $week_cal_list[]=array('week'=>$i,'year'=>$this->year);
    }
    if(isset($tmp_week)) {
      $week_cal_list[]=array('week'=>1,'year'=>($this->year+1));
    }

    // Creation du calendrier
    for($i=1;$i<32;$i++) {
      $table[$i]=null;
    }

    // Mois precedent
    $events=$notes=$table;
    $events_tmp=$notes_tmp=array();
    $display_begin_gmt=gmdate('Y-m-20 H:i:s',strtotime($prev_year.'-'.$prev_month.'-20 00:00:00'));
    $display_end_gmt=gmdate('Y-m-t H:i:s',strtotime($this->year.'-'.$this->month.'-00 23:59:00'));
    $this->display_begin=date('Y-m-20 H:i:s',strtotime($prev_year.'-'.$prev_month.'-20 00:00:00'));
    $this->display_end=date('Y-m-t H:i:s',strtotime($this->year.'-'.$this->month.'-00 23:59:00'));
    $rs=$this->getAlls($display_begin_gmt,$display_end_gmt,$this->display_begin,$this->display_end);
    while($rs && ($event=$rs->fetch())) {
      $this->convertEventTime($event);
      $start=strtotime($event['begin']);
      $start_date = date('Y-m-d', $start);
      $end_date = date('Y-m-d', strtotime($event['end']));
      if($start_date<substr($this->display_begin,0,10)) {
	$event['begin']=substr($this->display_begin,0,10).' 00:00:00';
      }
      if($end_date>substr($this->display_end,0,10)) {
	$event['end']=substr($this->display_end,0,10).' 23:59:00';
      }
      if($event['allday']==1) {
	$notes_tmp[]=$event;
      } else {
        $events_tmp[]=$event;
      }
    }
    $this->cutNotes($notes_tmp);
    $this->cutEvents($events_tmp,null,null);
    foreach($notes_tmp as $note) {
      $notes[date('j',strtotime($note['begin']))][]=$note;
    }
    foreach($events_tmp as $event) {
      $events[date('j',strtotime($event['begin']))][]=$event;
    }
    for($i=$first_day-2;$i>-1;$i--) {
      $this->cal_content[]=array('day'=>$nb_day_prev_month-$i,
                                 'month'=>$prev_month,
                                 'year'=>$prev_year,
                                 'day_cal_type'=>'out',
                                 'day_display_type'=>'out',
                                 'notes'=>$notes[$nb_day_prev_month-$i],
                                 'events'=>$events[$nb_day_prev_month-$i]);
    }
    // Mois actuel
    $events=$notes=$table;
    $events_tmp=$notes_tmp=array();
    $display_begin_gmt=gmdate('Y-m-d H:i:s',strtotime($this->year.'-'.$this->month.'-01 00:00:00'));
    $display_end_gmt=gmdate('Y-m-t H:i:s',strtotime($next_year.'-'.$next_month.'-00 23:59:00'));
    $this->display_begin=date('Y-m-d H:i:s',strtotime($this->year.'-'.$this->month.'-01 00:00:00'));
    $this->display_end=date('Y-m-t H:i:s',strtotime($next_year.'-'.$next_month.'-00 23:59:00'));
    $rs=$this->getAlls($display_begin_gmt,$display_end_gmt,$this->display_begin,$this->display_end);
    while($rs && ($event=$rs->fetch())) {
      $this->convertEventTime($event);
      $start=strtotime($event['begin']);
      $start_date = date('Y-m-d', $start);
      $end_date = date('Y-m-d', strtotime($event['end']));
      if($start_date<substr($this->display_begin,0,10)) {
	$event['begin']=substr($this->display_begin,0,10).' 00:00:00';
      }
      if($end_date>substr($this->display_end,0,10)) {
	$event['end']=substr($this->display_end,0,10).' 23:59:00';
      }
      if($event['allday']==1)  {
	$notes_tmp[]=$event;
      } else {
        $events_tmp[]=$event;
      }
    }
    $this->cutNotes($notes_tmp);
    $this->cutEvents($events_tmp,null,null);
    foreach($notes_tmp as $note) {
      $notes[date('j',strtotime($note['begin']))][]=$note;
    }
    foreach($events_tmp as $event) {
      $events[date('j',strtotime($event['begin']))][]=$event;
    }
    for($i=1;$i<date('t',mktime(12,00,00,$this->month,$this->day,$this->year))+1;$i++) {
      $this->cal_content[]=array('day'=>$i,
				 'month'=>$this->month,
				 'year'=>$this->year,
				 'day_cal_type'=>$this->dayType($i,$this->month),
				 'day_display_type'=>'in',
				 'notes'=>$notes[$i],
				 'events'=>$events[$i]);
    }

    // Mois suivant
    $events=$notes=$table;
    $events_tmp=$notes_tmp=array();
    $display_begin_gmt=gmdate('Y-m-d H:i:s',strtotime($next_year.'-'.$next_month.'-01 00:00:00'));
    $display_end_gmt=gmdate('Y-m-d H:i:s',strtotime($next_year.'-'.$next_month.'-10 23:59:00'));
    $this->display_begin=date('Y-m-d H:i:s',strtotime($next_year.'-'.$next_month.'-01 00:00:00'));
    $this->display_end=date('Y-m-d H:i:s',strtotime($next_year.'-'.$next_month.'-10 23:59:00'));

    $rs=$this->getAlls($display_begin_gmt,$display_end_gmt,$this->display_begin,$this->display_end);
    while($rs && ($event=$rs->fetch())) {
      $this->convertEventTime($event);
      $start=strtotime($event['begin']);
      $start_date = date('Y-m-d', $start);
      $end_date = date('Y-m-d', strtotime($event['end']));
      if($start_date<substr($this->display_begin,0,10)) {
	$event['begin']=substr($this->display_begin,0,10).' 00:00:00';
      }
      if($end_date>substr($this->display_end,0,10)) {
	$event['end']=substr($this->display_end,0,10).' 23:59:00';
      }
      if($event['allday']==1) {
	$notes_tmp[]=$event;
      } else {
	$events_tmp[]=$event;
      }
    }
    $this->cutNotes($notes_tmp);
    $this->cutEvents($events_tmp,null,null);
    foreach($notes_tmp as $note) {
      $notes[date('j',strtotime($note['begin']))][]=$note;
    }
    foreach($events_tmp as $event) {
      $events[date('j',strtotime($event['begin']))][]=$event;
    }
    for($i=1;$i<8-$last_day;$i++) {
      $this->cal_content[]=array('day'=>$i,
				 'month'=>$next_month,
				 'year'=>$next_year,
				 'day_cal_type'=>'out',
				 'day_display_type'=>'out',
				 'notes'=>$notes[$i],
				 'events'=>$events[$i]);
    }
    
    // Creation de la liste des semaines de l'annee en cours
    $first_week=date('W',mktime(00,00,00,1,1,$this->year));
    $last_week=date('W',mktime(00,00,00,12,31,$this->year));
    $first_monday=strtotime('Monday',mktime(00,00,00,12,29,$this->year-1));
    if($last_week==1) {
      $last_week=52;
    }
    if($first_week!=1) {
      $week_begin_tsp=strtotime('-1 week',$first_monday);
      $tmp_1=strptime(strftime('%d/%m/%Y',$week_begin_tsp),'%d/%m/%Y');
      $tmp_2=strptime(strftime('%d/%m/%Y',strtotime('+6 day',$week_begin_tsp)),'%d/%m/%Y');
      $week_list[$first_week.'-'.($this->year-1)]=$tmp_1['tm_mday'].' '.$month_list_label_min[$tmp_1['tm_mon']].' - '.$tmp_2['tm_mday'].
	                                          ' '.$month_list_label_min[$tmp_2['tm_mon']];
    }
    for($i=1;$i<=$last_week;$i++) {
      $week_begin_tsp=strtotime('+'.($i-1).' week',$first_monday);
      $tmp_1=strptime(strftime('%d/%m/%Y',$week_begin_tsp),'%d/%m/%Y');
      $tmp_2=strptime(strftime('%d/%m/%Y',strtotime('+6 day',$week_begin_tsp)),'%d/%m/%Y');
      $week_list[$i.'-'.$this->year]=$tmp_1['tm_mday'].' '.$month_list_label_min[$tmp_1['tm_mon']].' - '.$tmp_2['tm_mday'].' '.
	                             $month_list_label_min[$tmp_2['tm_mon']];
    }
    if(date('W',mktime(00,00,00,12,31,$this->year))==1) {
      $week_begin_tsp=strtotime('+52 week',$first_monday);
      $tmp_1=strptime(strftime('%d/%m/%Y',$week_begin_tsp),'%d/%m/%Y');
      $tmp_2=strptime(strftime('%d/%m/%Y',strtotime('+6 day',$week_begin_tsp)),'%d/%m/%Y');
      $week_list['1-'.($this->year+1)]=$tmp_1['tm_mday'].' '.$month_list_label_min[$tmp_1['tm_mon']].' - '.$tmp_2['tm_mday'].' '.
	                               $month_list_label_min[$tmp_2['tm_mon']];
    }

    // Creation de la liste des mois
    for($i=1;$i<13;$i++) {
      $month_list[$i]=$month_list_label[$i-1].' '.$this->year;
    }
    
    // Creation de la liste des agenda
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
    $xset->order='name';
    if($this->object_sec){
      $oids=$this->getAuthorizedDiaries();
      $select=$xset->select_query(array('order'=>'name desc','cond'=>array('KOID'=>array('=',$oids))));
    }else{
      $select='select '.$this->tagenda.'.* from '.$this->tagenda.' order by name desc';
    }
    $diary_list=$xset->browse(['selectedfields'=>['name'],
			       'select'=>$select,
			       '_options'=>['local'=>true]]);

    // Formate la semaine en cours
    $this->week=intval(date('W',mktime(12,00,00,$this->month,$this->day,$this->year)));
    if($this->week>50 && $this->month==1) {
      $this->bloc['week']=$this->week.'-'.($this->year-1);
    } elseif($this->week==1 && $this->month==12) {
      $this->bloc['week']=$this->week.'-'.($this->year+1);
    } else {
      $this->bloc['week']=$this->week.'-'.$this->year;
    }

    $this->bloc['month_list']=$month_list;
    $this->bloc['week_list']=$week_list;
    $this->bloc['week_cal_list']=$week_cal_list;
    $this->bloc['prev_month']=$prev_month;
    $this->bloc['next_month']=$next_month;
    $this->bloc['prev_year']=$prev_year;
    $this->bloc['next_year']=$next_year;
    $this->bloc['cal_contents']=$this->cal_content;
    $this->bloc['diary_list']=$diary_list;
  }

  /// Calcul les jours feries
  function calculateFreeDay() {
    $tmp=24*3600;
    $this->paques=date('j-n', easter_date($this->year)+$tmp);
    $this->ascension=date('j-n', easter_date($this->year)+39*$tmp);
    $this->pentecote=date('j-n', easter_date($this->year)+50*$tmp);
  }

  /// Renvoie le type du jour : aujourd'hui, jour selectionne, ferie, weekend, normal 
  function dayType($day,$month) {
    if(date('j-n-Y')==$day.'-'.$month.'-'.$this->year) return('today');
    if($this->day.'-'.$this->month.'-'.$this->year==$day.'-'.$month.'-'.$this->year) return('select');
    switch ($day.'-'.$month) {
    case $this->paques:
    case $this->ascension:
    case $this->pentecote:
    case '1-1':
    case '1-5':
    case '8-5':
    case '14-7':
    case '15-8':
    case '1-11':
    case '11-11':
    case '25-12':
      return('free');
      break;
    }

    switch (date('N', mktime(12,00,00,$month,$day,$this->year))) {
    case 6:
    case 7:
      return('weekend');
      break;
    default:
      return('work');
      break;
    }
  }

  function decimal($num) {
    return $num-floor($num);
  }

  /// Calcul les heures de debut et de fin d'affichage
  function getDisplayHours(&$events,&$day_begin,&$day_end) {
    foreach($events as &$event){
      $this->convertEventTime($event);
      $begin_hour=date('H',strtotime($event['begin']));
      $begin_minute=date('i',strtotime($event['begin']));
      $end_hour=date('H',strtotime($event['end']));
      $end_minute=date('i',strtotime($event['end']));
      $bagin['hour'];
      if(strtotime($event['begin'])>=strtotime($this->display_begin) && $begin_hour<$day_begin) $day_begin=$begin_hour;
      if(strtotime($event['end'])<=strtotime($this->display_end) && $end_hour>$day_end) $day_end=$end_hour;
    }
    return;
  }
  
  /// Calcul les elements de la date pour un affichage hebdo
  function calculateWeekDetails($p) {
    $this->week=$p->get('week');
    
    if(empty($this->week)) {
      $this->week=date('W',mktime(00,00,00,$this->month,$this->day,$this->year));

      if($this->day<4 && $this->month==1 && $this->week!=1){
	$first_monday=strtotime('Monday',mktime(00,00,00,12,29,$this->year-2));
      }elseif($this->day>28 && $this->month==12 && $this->week==1) {
	$first_monday=strtotime('Monday',mktime(00,00,00,12,29,$this->year));
      } else {
	$first_monday=strtotime('Monday',mktime(00,00,00,12,29,$this->year-1));
      }
      $first_day_week=strtotime('+'.($this->week-1).' week',$first_monday);
    } else {
      $first_monday=strtotime('Monday',mktime(00,00,00,12,29,$this->year-1));
      $first_day_week=strtotime('+'.($this->week-1).' week',$first_monday);
      
      if($this->week==1) {
	$this->day=4;
	$this->month=1;
      } else {
	$this->day=date('j',$first_day_week);
	$this->month=date('n',$first_day_week);
      }
    }
    $this->bloc['day']=$this->day;
    $this->bloc['month']=$this->month;
    $this->date=$this->year.'-'.str_pad($this->month,2,'0',STR_PAD_LEFT).'-'.str_pad($this->day,2,'0',STR_PAD_LEFT);
    return $first_day_week;
  }
  
  /// Arrondi le debut et la fin de l'evenement en fonction de l'interval d'affichage
  function calculateRound(&$actual_event,&$round_begin,&$round_end,$interval_minute,$day_begin,$day_end) {
    if($actual_event['allday']==1) {
      $actual_event['begin']=$round_begin=strtotime(date('H:i:00',mktime(00,($day_begin*60),00,01,01,2006)),
						    strtotime($actual_event['begin']));
      $actual_event['end']=$round_end=strtotime(date('H:i:00',mktime(00,($day_end*60),00,01,01,2006)),strtotime($actual_event['end']));
    } else {
      $event_begin=strtotime($actual_event['begin']);
      $event_end=strtotime($actual_event['end']);
      $round_begin=$event_begin-$this->decimal(date('i',$event_begin)/$interval_minute)*$interval_minute*60;
      $tmp=$this->decimal(date('i',$event_end)/$interval_minute)*$interval_minute;
      if($tmp==0) {
        $round_end=$event_end;
      } else {
        $round_end=$event_end+($interval_minute-$tmp)*60;
      }
    }
  }
  
  /**
   * Convertit la date GMT vers le fuseau de l'agenda
   * $event doit être un display ou un ors
   */
  function convertEventTime(&$event) {
    if(!empty($event['_timeconvert'])) return;
    if(!empty($event['oallday'])) {
      if($event['oallday']->raw!=1){
        $event['obegin']->raw=date('Y-m-d H:i:s',strtotime($event['obegin']->raw.' GMT'));
        $event['oend']->raw=date('Y-m-d H:i:s',strtotime($event['oend']->raw.' GMT'));
	$opt=array();
	$event['obegin']=$this->xsetevt->desc['begin']->edit($event['obegin']->raw,$opt);
	$event['oend']=$this->xsetevt->desc['end']->edit($event['oend']->raw,$opt);
	$event['begin']=$event['obegin']->raw;
	$event['end']=$event['oend']->raw;
      }
    }elseif($event['allday']!=1) {
      $event['begin']=date('Y-m-d H:i:s',strtotime($event['begin'].' GMT'));
      $event['end']=date('Y-m-d H:i:s',strtotime($event['end'].' GMT'));
    }
    $event['_timeconvert']=true;
  }

  /// Recupere les notes brutes dans un interval (gmt)
  function getNotes($begin,$end,$where=false,$all=false){
    $rq=$this->request['select'].' '.$this->request['from'].' ';
    if($where) $rq.=$where;
    else $rq.=$this->request['where'];
    $rq.=' AND '.$this->tevt.'.allday=1 AND ('.$this->tevt.'.begin BETWEEN "'.$begin.'" AND "'.$end.'" OR '.
      '"'.$begin.'" BETWEEN '.$this->tevt.'.begin AND '.$this->tevt.'.end)';
    $rq=array($rq);
    $cons=$this->getDiariesForConsolidation();
    foreach($cons['active'] as $oid=>$foo){
      if(is_numeric($oid)){
        $mod=\Seolan\Core\Module\Module::objectFactory($oid);
        if(!is_object($mod)) continue;
        $req=$mod->XCalGetConsolidationQuery($this->diary,$cons['list'][$oid],$this->request['selectedfields'],$begin,$end,'note');
	if(!empty($req)) $rq[]=$req;
      }
    }
    $rq='('.implode(') UNION (',$rq).') order by begin,end desc';
    if($all) return getDB()->fetchAll($rq);
    else return getDB()->select($rq);
  }

  /// Recupere les evenements bruts dans un interval (gmt)
  function getEvents($begin,$end,$where=false,$all=false){
    $rq=$this->request['select'].' '.$this->request['from'].' ';
    if($where) $rq.=$where;
    else $rq.=$this->request['where'];
    $rq.=' AND ('.$this->tevt.'.allday!=1 or '.$this->tevt.'.allday is null) AND '.
      '('.$this->tevt.'.begin BETWEEN "'.$begin.'" AND "'.$end.'" OR '.
      '"'.$begin.'" BETWEEN '.$this->tevt.'.begin AND '.$this->tevt.'.end)';
    $rq=array($rq);
    $cons=$this->getDiariesForConsolidation();
    foreach($cons['active'] as $oid=>$foo){
      if(is_numeric($oid)){
        $mod=\Seolan\Core\Module\Module::objectFactory($oid);
        if(!is_object($mod)) continue;
	$req=$mod->XCalGetConsolidationQuery($this->diary,$cons['list'][$oid],$this->request['selectedfields'],$begin,$end,'event');
	if(!empty($req)) $rq[]=$req;
      }
    }
    $rq='('.implode(') UNION (',$rq).') order by begin,end desc';
    if($all) return getDB()->fetchAll($rq);
    else return getDB()->select($rq);
  }

  /// Recupere les notes et les evenements dans un interval (gmt)
  function getAlls($evbegin,$evend,$nobegin,$noend,$where=false){
    $rq=$this->request['select'].' '.$this->request['from'].' ';
    if($where) $rq.=$where.' ';
    else $rq.=$this->request['where'].' ';
    $rq.=' AND ((('.$this->tevt.'.allday!=1 or '.$this->tevt.'.allday is null) AND '.
      '('.$this->tevt.'.begin BETWEEN "'.$evbegin.'" AND "'.$evend.'" OR '.
      '"'.$evbegin.'" BETWEEN '.$this->tevt.'.begin AND '.$this->tevt.'.end)) OR '.
      '('.$this->tevt.'.allday=1 AND '.
      '('.$this->tevt.'.begin BETWEEN "'.$nobegin.'" AND "'.$noend.'"'.
      'OR "'.$nobegin.'" BETWEEN '.$this->tevt.'.begin AND '.$this->tevt.'.end)))';
    $rq=array($rq);
    $cons=$this->getDiariesForConsolidation();
    foreach($cons['active'] as $oid=>$foo){
      if(is_numeric($oid)){
	$mod=\Seolan\Core\Module\Module::objectFactory($oid);
	if(!is_object($mod)) continue;
	$req=$mod->XCalGetConsolidationQuery($this->diary,$cons['list'][$oid],$this->request['selectedfields'],$nobegin,$noend);
	if(!empty($req)) $rq[]=$req;
      }
    }
    
    $rq='('.implode(') UNION (',$rq).') order by begin,end desc';
    $rs=getDB()->select($rq);
    return $rs;
  }

  /// Coupe les evenements sur plusieurs jours
  function cutEvents(&$events, $day_begin, $day_end) {
    $events_tmp=array();
    if(is_null($day_begin)) {
      $day_begin='00:00:00';
      $day_end='23:59:00';
    } else {
      $day_begin=date('H:i:s',strtotime('2010-10-10 '.floor($day_begin).':'.($this->decimal($day_begin)*60).':00'));
      if($day_end==24) {
        $day_end='23:59:00';
      } else {
        $day_end=date('H:i:s',strtotime('2010-10-10 '.floor($day_end).':'.($this->decimal($day_end)*60).':00'));
      }
    }
    foreach($events as $event) {
      $this->getCompleteEvent($event);
      $start = strtotime($event['begin']);
      $end = strtotime($event['end']);
      $start_date = date('Y-m-d', $start);
      $end_date = date('Y-m-d', $end);
      $start_hour = date('H:i:00', $start);
      $end_hour = date('H:i:00', $end);
      $event_tmp=array();
      $event['_cbegin']=$event['begin'];
      $event['_cend']=$event['end'];
      if($start_date == $end_date) {
	$events_tmp[]=$event;
      } else {
        $start2=$start;
	if($start_date.' 12:00:00'>=$this->display_begin) {
          $event['end']=$start_date.' '.$day_end;
          $events_tmp[]=$event;
        }
	$start = strtotime('+1 day', $start);
	$start_date = date('Y-m-d', $start);
        while ($start_date < $end_date) {
	  if($start_date.' 12:00:00'>$this->display_begin && $start_date.' 12:00:00'<$this->display_end) {
	    $event['begin']=$start_date.' '.$day_begin;
	    $event['end']=$start_date.' '.$day_end;
	    $events_tmp[]=$event;
          }
	  $start = strtotime('+1 day', $start);
	  $start_date = date('Y-m-d', $start);
	}
        if($start_date.' 12:00:00'>$this->display_begin && $start_date.' 12:00:00'<$this->display_end) {
	  $event['begin']=$start_date.' '.$day_begin;
	  $event['end']=date('Y-m-d H:i:s',$end);
	  $events_tmp[]=$event;
	}
      }
    }
    uasort($events_tmp, function($a,$b) {
        if ($a["begin"] == $b["begin"] && $a["_cbegin"] == $b["_cbegin"]) return 0;
        if($a["begin"] < $b["begin"]) return -1;
        return ($a["begin"] == $b["begin"] && $a["_cbegin"] < $b["_cbegin"]) ? -1 : 1;
    });
    foreach($events_tmp as $i=>&$event){
      $event['_begindate']=date('d/m/Y',strtotime($event['begin']));
      $event['_beginhour']=date('H:i',strtotime($event['begin']));
      $event['_cbegindate']=date('d/m/Y',strtotime($event['_cbegin']));
      $event['_cbeginhour']=date('H:i',strtotime($event['_cbegin']));
      $event['_enddate']=date('d/m/Y',strtotime($event['end']));
      $event['_endhour']=date('H:i',strtotime($event['end']));
      $event['_cenddate']=date('d/m/Y',strtotime($event['_cend']));
      $event['_cendhour']=date('H:i',strtotime($event['_cend']));
    }
    $events=$events_tmp;
  }

  /// Coupe les notes sur plusieurs jours
  function cutNotes(&$notes) {
    $notes_tmp=array();
    foreach($notes as $note) {
      $this->getCompleteEvent($note);
      $start = strtotime($note['begin']);
      $end = strtotime($note['end']);
      $start_date = date('Y-m-d', $start);
      $end_date = date('Y-m-d', $end);
      $note['_cbegin']=$note['begin'];
      $note['_cend']=$note['end'];
      while ($start_date <= $end_date) {
	if($start_date.' 12:00:00'>$this->display_begin && $start_date.' 12:00:00'<$this->display_end) {
	  $note['begin']=$start_date.' 00:00:00';
	  $note['end']=$start_date.' 23:59:00';
	  $notes_tmp[]=$note;
	}
	$start = strtotime('+1 day', $start);
	$start_date = date('Y-m-d', $start);
      }
    }
    foreach($notes_tmp as $i=>&$note){
      $note['_begindate']=date('d/m/Y',strtotime($note['begin']));
      $note['_cbegindate']=date('d/m/Y',strtotime($note['_cbegin']));
      $note['_enddate']=date('d/m/Y',strtotime($note['end']));
      $note['_cenddate']=date('d/m/Y',strtotime($note['_cend']));
    }
    $notes=$notes_tmp;
  }

  /// Complete les données d'un evenement via un rdisplay (au depart, $event ne contient que certains champs (voir fin du construct))
  function getCompleteEvent(&$event){
    // Si l'evenement à déjà été traité
    if(!empty($event['_url'])) return;
    $this->convertEventTime($event);
    if($event['MOID']!=$this->_moid){
      $mod=\Seolan\Core\Module\Module::objectFactory($event['MOID']);
      $cons=$this->getDiariesForConsolidation();
      $params=$cons['list'][$event['MOID']];
      $tmp=$mod->XCalRDisplay($event['KOID'],array('text'=>$params['text'],'place'=>$params['place'],'descr'=>$params['descr']));
      if(!empty($tmp)) $event=array_merge($event,$tmp);
      $event['_url']=str_replace('%s',$event['KOID'],$this->bloc['diariesprop'][$event['DKOID']]['url']);
    }else{
      $tmp=$this->xsetevt->rDisplay($event['KOID'],array(), false,'','',array('selectedfields'=>array('text','place','descr')));
      if(!empty($tmp)) $event=array_merge($event,$tmp);
      $event['_url']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'oid='.$this->diary['KOID'].'&moid='.$this->_moid.'&function=addEvt&tplentry=br&'.
	'template=Module/Calendar.addEvt.html&day='.$this->day.'&month='.$this->month.'&year='.$this->year.'&koid='.$event['KOID'];
    }
  }

  // Supprime les lignes inutiles entre deux heures pleines
  // Attention : dans les tableaux, decalage de +1 entre le tableau des evenements et des heures (du aux notes)
  function delEmptyPeriod(&$table,$tablefordel,&$hours,$interval){
    if($interval==1) return;
    $nbrow=count($tablefordel);
    for($l=0;$l<$nbrow;$l++){
      $row=$tablefordel[$l];
      $del=0;
      $min=substr($hours[$l+1],-3);
      if($interval==0.5 && $min==':00' && $row==$tablefordel[$l+1]) $del=1;
      elseif($interval==0.25 && $min==':00' && $row==$tablefordel[$l+1] && $tablefordel[$l+2]==$tablefordel[$l+3]){
	if($row==$tablefordel[$l+2]) $del=3;
	else $del=1;
      }elseif($interval==0.25 && $min=':30' && $row==$tablefordel[$l+1] && !isset($table[$l-1])) $del=1;
      
      if($del>0){
	foreach($row as $col=>$tmp){
	  if($tmp!=='empty') $table[$tmp][$col]['nb_interval']-=$del;
	}
	if($del===1) unset($table[$l+1],$hours[$l+2]);
	elseif($del===3) unset($table[$l+1],$hours[$l+2],$table[$l+2],$hours[$l+3],$table[$l+3],$hours[$l+4]);
	$l+=$del;
      }
    }
    $table=array_values($table);
    $hours=array_values($hours);
  }

  public function usedTables() {
    return array($this->tagenda,$this->tevt,$this->tlinks,$this->tcatevt,$this->tcatagenda,$this->tplan,$this->tplaninv,$this->tplandates);
  }
  public function usedMainTables() {
    return array($this->tagenda);
  }
  public function usedBoids(){
    return array($this->xsetevt->getBoid());
  }
  /// ajoute une catégorie privée au user de l'agenda en cours
  public function createCategory($catname){
    $dscat = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tcatevt);
    $ret = $dscat->procInput(['name'=>$catname,
			      'time'=>60,
			      'visib'=>'PR',
			      'recall'=>0,
			      'commun'=>2,
			      'allday'=>0,
			      'OWN'=>$this->diary['OWN']??null,
			      'tplentry'=>TZR_RETURN_DATA]);
    return $ret['oid'];
  }
  /// rend la liste des agendas authorises
  public function getAuthorizedDiaries($level='ro',$mode='array',$forcecache=false){
    if($forcecache || empty($this->cache) || !array_key_exists('authdiaries-'.$level,$this->cache)){
      $this->cache['authdiaries-ro']=array();
      $this->cache['authdiaries-rw']=array();
      $this->cache['authdiaries-rwv']=array();
      $xsetag=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
      $oids=$xsetag->browseOids(array('_local'=>true));
      $lang_data=\Seolan\Core\Shell::getLangData();
      if($this->object_sec){
	$oidsrights=$GLOBALS['XUSER']->getObjectsAccess($this, $lang_data, $oids);
	list($access,$l)=$GLOBALS['XUSER']->getObjectAccess($this, $lang_data);
	foreach($oidsrights as $i => $rights) {
	  if(array_key_exists('ro',$rights) || in_array('ro',$access)) $this->cache['authdiaries-ro'][]=$oids[$i];
	  if(array_key_exists('rw',$rights) || in_array('rw',$access)) $this->cache['authdiaries-rw'][]=$oids[$i];
	  if(array_key_exists('rwv',$rights) || in_array('rwv',$access)) $this->cache['authdiaries-rwv'][]=$oids[$i];
	}
      }else{
	$this->cache['authdiaries-ro']=$oids;
	$this->cache['authdiaries-rw']=$oids;
	$this->cache['authdiaries-rwv']=$oids;
      }
    }
    $oids=$this->cache['authdiaries-'.$level];
    if($mode=='array') return $oids;
    else return '"'.implode('","',$oids).'"';
  }

  /// Renvoie la liste des consolidations
  public function getDiariesForConsolidation(){
    if(empty($this->cache['consolidations'])){
      $oid=$this->diary['KOID'];
      $ret=array('active'=>array(),'list'=>array());
      $sess=getSessionVar('\Seolan\Module\Calendar\Calendar'.$this->_moid.':consolidations');
      if(!empty($this->prefs['consolidations']['active'][$oid])) $ret['active']=$this->prefs['consolidations']['active'][$oid];
      if(!empty($this->prefs['consolidations']['list'])) $ret['list']=$this->prefs['consolidations']['list'];
      if(!empty($sess['active'][$oid])){
	foreach($sess['active'][$oid] as $foid=>&$foo){
	  $ret['active'][$foid]=$foo;
	}
      }
      if(!empty($sess['list'])){
	foreach($sess['list'] as $foid=>&$foo){
	  $ret['list'][$foid]=$foo;
	}
      }
      unset($ret['active'][$oid]);
      unset($ret['list'][$oid]);
      $this->cache['consolidations']=$ret;
    }
    return $this->cache['consolidations'];
  }

  /// verification qu'un module est bien installé + nettoyage des preferences
  public function chk(&$message=NULL) {
    $rs=getDB()->select('select * from OPTS where dtype="pref" and modid=?',array($this->_moid));
    while($ors=$rs->fetch()) {
      if(!empty($ors['specs']))
	$prefs= \Seolan\Library\Opts::decodeSpecs($ors['specs']);
      else
	$prefs=array();
      // Supprime l'agenda par defaut s'il n'existe plus
      $def=@$prefs['defaultcal'];
      if(!empty($def)){
	// DUBIOUS AFTER
	$rs=getDB()->select('select KOID from '.$this->tagenda.' where KOID=?', array($def));
	if($rs->rowCount()==0) unset($prefs['defaultcal']);
      }
      // Supprime les consolidation (list + active) qui n'existe plus
      $cons=@$prefs['consolidations'];
      if(!empty($cons['list'])){
	foreach($cons['list'] as $oid=>&$foo){
	  if(is_numeric($oid)) $rs=getDB()->select('select MOID from MODULES where MOID=?',array($oid));
	  else $rs=getDB()->select('select KOID from '.$this->tagenda.' where KOID=?',array($oid));
	  if($rs->rowCount()==0) unset($prefs['consolidations']['list'][$oid]);
	}
      }
      if(!empty($cons['active'])){
	foreach($cons['active'] as $oid=>&$foo){
	  $rs=getDB()->select('select KOID from '.$this->tagenda.' where KOID=?',array($oid));
          if($rs->rowCount()==0) unset($prefs['consolidations']['active'][$oid]);
	  else{
	    foreach($cons['active'][$oid] as $oid2=>&$foo){
	      if(is_numeric($oid2)) $rs=getDB()->select('select MOID from MODULES where MOID="'.$oid2.'"');
	      else $rs=getDB()->select('select KOID from '.$this->tagenda.' where KOID=?', array($oid2));
	      if($rs->rowCount()==0) unset($prefs['consolidations']['active'][$oid][$oid2]);
	    }
	  }
	}
      } 
      getDB()->execute('update OPTS set specs=? where KOID=?', [\Seolan\Library\Opts::encodeSpecs($prefs),$ors['KOID']]);
    }
    return parent::chk($ar);
  }

  /// Preparation d'une planification
  function insertPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');

    // Input planif
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $xset->desc['begin']->sys=true;
    $xset->desc['end']->sys=true;
    $xset->desc['rem']->sys=true;
    $xset->desc['cancel']->sys=true;
    $xset->desc['close']->sys=true;
    $ar['options']['ag']['value']=$this->diary['KOID'];
    $xset->input($ar);

    // Liste groupes/users
    $users = \Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $list=\Seolan\Core\User::getUsersAndGroups(true);
    \Seolan\Core\Shell::toScreen1($tplentry.'g',$list[1]);

    // Liste agenda
    $xsetag=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tagenda);
    if($this->object_sec) $cplt=' AND KOID IN ('.$this->getAuthorizedDiaries('ro','sql').')';
    else $cplt='';
    $opts=array('filter'=>'(cons IS NULL OR cons!=1) and KOID!="'.$this->diary['KOID'].'"'.$cplt);

    \Seolan\Core\Shell::toScreen2($tplentry,'oattendees',$xsetag->desc['agcons']->edit($foo='',$opts));
    \Seolan\Core\Shell::toScreen1($tplentry.'ag',$this->diary);
  }

  /// Valide l'ajout d'une planification
  function procInsertPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('attendees'=>array(),'uattendees_groups'=>array(),'uattendees'=>array()));
    $dates=$p->get('date');
    $begins=$p->get('begin');
    $ends=$p->get('end');
    $alldays=$p->get('allday');
    $invitt=stripslashes($p->get('invitt'));
    $title=stripslashes($p->get('title'));
    $datelim=$p->get('datelim');
    $atts=$p->get('attendees');
    $grps=$p->get('uattendees_groups');
    $users=$p->get('uattendees');
    if($users[0]=='') unset($users[0]);
    if(empty($users)) $users=array();

    // Ajout de la planification
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $ret=$xset->procInput($ar);

    // Création des dates
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    foreach($dates as $date){
      $dbegins=$begins[$date];
      $dends=$ends[$date];
      $allday=$alldays[$date];
      foreach($dbegins as $i=>$begin){
	if(empty($begin) || empty($dends[$i])) continue;
	$end=$dends[$i];
        $xset->procInput(array('planif'=>$ret['oid'],'begin'=>$date.' '.$begin,'end'=>$date.' '.$end,'confirm'=>'',
			       '_options'=>array('local'=>true)));
      }
      if(!empty($allday)){
        $xset->procInput(array('planif'=>$ret['oid'],'begin'=>$date.' 00:00','end'=>$date.' 00:00','confirm'=>'',
			       '_options'=>array('local'=>true)));
	
      }
    }
    // Creation des sous fiches invités
    $invoids=$emails=array();
    $xsetinv=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    $modgroups=\Seolan\Core\Module\Module::singletonFactory(XMODGROUP_TOID);
    $modusers=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $usersfromgroups=$modgroups->users($grps);
    $users=array_merge($usersfromgroups,$users);
    $users=array_unique($users);
    $oids=array_merge($users,$atts);
    foreach($oids as $oid){
      $ret2=$xsetinv->procInput(array('planif'=>$ret['oid'],'who'=>$oid,'part'=>1,'_options'=>array('local'=>1)));
      $invoids[]=$ret2['oid'];
    }
    $emails=$this->getMailsForPlanif($oids);
    $user=$modusers->display(array('oid'=>\Seolan\Core\User::get_current_user_uid(),'tplentry'=>TZR_RETURN_DATA,
				   'selectedfields'=>array('fullnam','email')));
    $sub=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planinvitmail_subject','mail'),$title);
    $body=$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planinvitmail_body','mail');
    $cplt='';
    if($invitt){
      $cplt=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planmail_bodycomm','mail'),
		    $user['ofullnam']->raw,$invitt);
    }
    $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=confirmPlanif&template=Module/Calendar.confirmPlanif.html'.
      '&tplentry=br&_direct=1&oid=';
    $text=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','procinsertplanifconfirm');
    foreach($emails as $i=>$mail){
      $message = sprintf($body,$title,$url.$invoids[$i],$datelim,$cplt);
      $this->sendMail2User($sub, $message, $mail, [$user['oemail']->raw,$user['ofullnam']->raw]);
      $text.='&nbsp;- '.$mail['name'].' ('.$mail['mail'].')<br>';
    }
    setSessionVar('message',$text);
  }

  /// Confirmer une planification
  function confirmPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    // Edition de sa fiche (remarque + autres champs eventuels)
    $xsetinv=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    $ret=$xsetinv->edit();
    // Display de la planification
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $xset->desc['begin']->sys=true;
    $xset->desc['end']->sys=true;
    $xset->desc['rem']->sys=true;
    $xset->desc['cancel']->sys=true;
    $xset->desc['close']->sys=true;
    $d=$xset->display(array('tplentry'=>$tplentry.'p','oid'=>$ret['oplanif']->raw,'_options'=>array('local'=>true)));

    // Si la date limite est apssé, on remplace l'edit par un display
    if($d['odatelim']->raw<date('Y-m-d') || $d['ocancel']->raw==1 || $d['oclose']->raw==1){
      $ret=$xsetinv->display();
      \Seolan\Core\Shell::toScreen2($tplentry,'_mode','display');
    }
    // Browse des dates
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    $xset->browse(array('tplentry'=>$tplentry.'d','select'=>'select * from '.$this->tplandates.' where planif="'.$ret['oplanif']->raw.'"',
			'selectedfields'=>array('begin','end','confirm'),'order'=>'begin,end','_options'=>array('local'=>true)));
  }

  /// Enregistre une confirmation
  function procConfirmPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar=NULL);
    $oid=$p->get('oid');
    $confirm=$p->get('confirm');
    $confirmhid=$p->get('confirm_HID');
    $nopart=$p->get('nopart');
    // Verifie la date limite de réponse
    $rs=getDB()->select('select '.$this->tplan.'.KOID from '.$this->tplan.' '.
		     'left outer join '.$this->tplaninv.' on '.$this->tplaninv.'.planif='.$this->tplan.'.KOID '.
		     'where '.$this->tplaninv.'.KOID="'.$oid.'" and (datelim<"'.date('Y-m-d').'" or cancel=1 or close=1)');
    if($rs->rowCount()>0) \Seolan\Library\Security::warning('\Seolan\Module\Calendar\Calendar::procConfirmPlanif: Trying to edit '.$oid.' with datelim expired/close/cancel');

    // Enregistre remarque + autres champs eventuels
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    if(!empty($nopart)) $ar['part']=2; 
    else $ar['part']=1;
    $ret=$xset->procEdit($ar);
    // Enregistre les dates
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    foreach($confirmhid as $doid=>$foo){
      $ors=getDB()->fetchRow('select confirm from '.$this->tplandates.' where KOID=?', array($doid));
      $act=explode('||',$ors['confirm']);
      if(!empty($confirm[$doid]) && !in_array($oid,$act)){
	$act[]=$oid;
	$xset->procEdit(array('oid'=>$doid,'confirm'=>$act,'_options'=>array('local'=>true)));
      }elseif(empty($confirm[$doid]) && in_array($oid,$act)){
	$k=array_search($oid,$act);
	unset($act[$k]);
	$xset->procEdit(array('oid'=>$doid,'confirm'=>$act,'_options'=>array('local'=>true)));
      }
    }

    $text=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Calendar_Calendar','procconfirmplanifconfirm');
    setSessionVar('message',$text);
  }

  /// Parcourir les planifications
  function browsePlanif($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Table_Table');
    $p=new \Seolan\Core\Param($ar=NULL);
    $tplentry=$p->get('tplentry');
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    if(!\Seolan\Core\Shell::isRoot()) $q=$xset->select_query(array('cond'=>array('OWN'=>array('=',\Seolan\Core\User::get_current_user_uid()))));
    $r=&$xset->browse(array('tplentry'=>TZR_RETURN_DATA,'select'=>$q,'pagesize'=>20));
    $this->browsePlanif_actions($r,$assubmodule);
    $r['function']='browsePlanif';
    $r['_noselectionlink']=1;
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Droits sur les fiches losr d'un browse
  function browsePlanif_actions(&$r,$assubmodule=false){
    $uniqid=\Seolan\Core\Shell::uniqid();
    $self1=$GLOBALS['TZR_SESSION_MANAGER']::complete_self();
    $self1.='&moid='.$this->_moid.'&oid='.$this->diary['KOID'].'&tplentry=br&function=';
    if(!is_array($r['lines_oid'])) return;
    $viewico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','view');
    $viewtxt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','view');
    $editico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','edit');
    $edittxt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','edit');
    $delico = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','delete');
    $deltxt = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete');
    foreach($r['lines_oid'] as $i =>$oid) {
      $rs=getDB()->select('select KOID from '.$this->tplan.' where KOID=? and (cancel=1 or close=1)', array($oid));
      $count=$rs->rowCount();
      $url=$self1.'displayPlanif&koid='.$oid.'&template=Module/Calendar.viewPlanif.html';
      $r['actions'][$i][0]='<a class="cv8-ajaxlink" href="'.$url.'" title="'.$viewtxt.'">'.$viewico.'</a>';
      $r['actions_url'][$i][0]=$url;
      $r['actions_label'][$i][0]=$viewico;
      // edition
      if($count==0){
	$url=$self1.'editPlanif&koid='.$oid.'&template=Module/Calendar.viewPlanif.html';
	$r['actions'][$i][1]='<a class="cv8-ajaxlink" href="'.$url.'" title="'.$edittxt.'">'.$editico.'</a>';
	$r['actions_url'][$i][1]=$url;
	$r['actions_label'][$i][1]=$editico;
      }
      // Suppression
      $url=$self1.'delPlanif&koid='.$oid.'&template=message.html';
      $r['actions'][$i][2]='<a href="'.$url.'" class="cv8-delaction" title="'.$deltxt.'">'.$delico.'</a>';
      $r['actions_url'][$i][2]=$url;
      $r['actions_label'][$i][2]=$deltxt;
    }
  }

  /// Affichage d'une planification
  function displayPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar=NULL);
    $oid=$p->get('koid');
    $tplentry=$p->get('tplentry');
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $xset->desc['begin']->sys=true;
    $xset->desc['end']->sys=true;
    $xset->desc['rem']->sys=true;
    $xset->desc['cancel']->sys=true;
    $xset->desc['close']->sys=true;
    $xset->desc['datelim']->sys=true;
    $xset->desc['invitt']->sys=true;
    $xset->display(array('oid'=>$oid,'tplentry'=>$tplentry));
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    $q=$xset->select_query(array('cond'=>array('planif'=>array('=',$oid))));
    $xset->browse(array('tplentry'=>$tplentry.'d','order'=>'begin','selectedfields'=>array('begin','end','confirm'),'select'=>$q));
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    $q=$xset->select_query(array('cond'=>array('planif'=>array('=',$oid))));
    $xset->browse(array('tplentry'=>$tplentry.'i','order'=>'who','selectedfields'=>array('who','part','remark'),'select'=>$q));
    \Seolan\Core\Shell::toScreen2($tplentry,'_mode','display');
  }

  /// Edition d'une planification
  function editPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar=NULL);
    $oid=$p->get('koid');
    $tplentry=$p->get('tplentry');
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $xset->desc['begin']->sys=true;
    $xset->desc['end']->sys=true;
    $xset->desc['rem']->sys=true;
    $xset->desc['cancel']->sys=true;
    $xset->desc['close']->sys=true;
    $xset->desc['datelim']->sys=true;
    $xset->desc['invitt']->sys=true;
    $xset->desc['begin']->compulsory=true;
    $xset->desc['end']->compulsory=true;
    $xset->edit(array('oid'=>$oid,'tplentry'=>$tplentry));
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    $q=$xset->select_query(array('cond'=>array('planif'=>array('=',$oid))));
    $xset->browse(array('tplentry'=>$tplentry.'d','order'=>'begin','selectedfields'=>array('begin','end','confirm'),'select'=>$q));
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    $q=$xset->select_query(array('cond'=>array('planif'=>array('=',$oid))));
    $xset->browse(array('tplentry'=>$tplentry.'i','order'=>'who','selectedfields'=>array('who','part','remark'),'select'=>$q));
  }

  /// Enregistrer une modification sur une planification
  function procEditPlanif($ar=NULL){
    $p=new \Seolan\Core\Param($ar=NULL);
    $oid=$p->get('koid');
    $close=$p->get('close');
    $cancel=$p->get('cancel');
    $begin=$p->get('begin');
    $end=$p->get('end');
    $allday=$p->get('allday');
    $rem=$p->get('rem');
    $tplentry=$p->get('tplentry');
    $ar['oid']=$oid;
    $ar['end']['date']=$end['date']=$begin['date'];
    $ar['end']['hour']=$end['hour'];

    // Verifie la date limite de réponse
    $rs=getDB()->select('select KOID from '.$this->tplan.' where KOID=? and (cancel=1 or close=1)', array($oid));
    if($rs->rowCount()>0) \Seolan\Library\Security::warning('\Seolan\Module\Calendar\Calendar::procEditPlanif: Trying to edit '.$oid.' with close/cancel=1');

    $modusers=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $user=$modusers->display(array('oid'=>\Seolan\Core\User::get_current_user_uid(),'tplentry'=>TZR_RETURN_DATA,
				   'selectedfields'=>array('fullnam','email')));
    $xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $ret=$xset->procEdit($ar);
    $d=$xset->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA));
    $cplt='';
    if($rem){
      $cplt=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planmail_bodycomm','mail'),
		    $user['ofullnam']->raw,$rem);
    }
    if($cancel==1){
      $sub=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','plancancelmail_subject','mail'),$d['otitle']->html);
      $body=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','plancancelmail_body','mail'),$d['otitle']->html,$cplt);
    }elseif($close==1){
      $sub=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planclosemail_subject','mail'),$d['otitle']->html);
      if($allday){
	$datetext=$begin['date'].' ('.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','allday').')';
      }else{
	$datetext=$begin['date'].' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','at2').' '.$begin['hour'].' '.
	  \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_Calendar_Calendar','to2').' '.$end['hour'];
      }
      $body=sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Module_Calendar_Calendar','planclosemail_body','mail'),$d['otitle']->html,$datetext,$cplt);
    }
    $oids=array();
    $rs=getDB()->select('select who from '.$this->tplaninv.' where planif="'.$oid.'"');
    while($rs && ($ors=$rs->fetch())) $oids[]=$ors['who'];
    $emails=$this->getMailsForPlanif($oids);
    // Création des evenements dans les agendas
    if($close==1){
      $agatts=array();
      $otheratts=array();
      foreach($emails as $mail){
	if($mail['type']=='agenda') $agatts[]=$mail['oid'];
	else $otheratts[]=$mail['mail'];
      }
      $ret2=$this->saveEvt(array('text'=>addslashes($d['otitle']->raw),'descr'=>addslashes($d['odescr']->raw),'cat'=>$d['ocat']->raw,
				 'allday'=>$allday,'begindate'=>$begin['date'],'enddate'=>$end['date'],'beginhour'=>$begin['hour'],
				 'endhour'=>$end['hour'],'attext'=>implode(';',$otheratts),'selected'=>$agatts,'noalert'=>true,
				 '_options'=>array('local'=>true)));
      // Ajout du fichier iCal UTF8
      $file=TZR_TMP_DIR.uniqid('ical-').'.ics';
      $fd=fopen($file,'a');
      fwrite($fd,$this->saveExport(array('expoid'=>$ret2['oid'],'intcall'=>true)));
      fclose($fd);
      // Ajout du fichier iCal latin1 pour outlook
      $file2=TZR_TMP_DIR.uniqid('outlook-').'.ics';
      $fd=fopen($file2,'a');
      fwrite($fd,$this->saveExport(array('expoid'=>$ret2['oid'],'intcall'=>true,'charset'=>'latin1')));
      fclose($fd);
      $filesname=array($file,$file2);
      $filestitle=array(rewriteToAscii($d['otitle']->html).'.ics',rewriteToAscii($d['otitle']->html).'_Outlook.ics');
    }
    $this->sendMail2User($sub,
			 $body,
			 $emails,
			 [$user['oemail']->raw,$user['ofullnam']->raw], 
			 true,
			 $filesname,
			 $filestitle);
    
    if($close==1){
      unlink($file);
      unlink($file2);
    }
    return $ret;
  }

  /// Supression d'une planification
  function delPlanif($ar=NULL){
    $p = new \Seolan\Core\Param($ar,array());
    $parentoid=$p->get('_selected');
    $selectedok=$p->get('_selectedok');
    $parentoid=array_keys($parentoid);
    if(($selectedok!='ok') || empty($parentoid)) $parentoid=$p->get('koid');
    if(!is_array($parentoid)) $parentoid=array($parentoid);
    $xsetplan=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplan);
    $xsetdate=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplandates);
    $xsetinv=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->tplaninv);
    foreach($parentoid as $oid){
      $xsetplan->del(array('oid'=>$oid,'_options'=>array('local'=>true)));
      $rs=getDB()->select('select KOID from '.$this->tplandates.' where planif="'.$oid.'"');
      while($rs && ($ors=$rs->fetch())) $xsetdate->del(array('oid'=>$ors['KOID'],'_options'=>array('local'=>true)));
      $rs=getDB()->select('select KOID from '.$this->tplaninv.' where planif="'.$oid.'"');
      while($rs && ($ors=$rs->fetch())) $xsetinv->del(array('oid'=>$ors['KOID'],'_options'=>array('local'=>true)));
    }
  }

  /// Recupere la liste des mails des invité d'une planification
  function getMailsForPlanif($oids,$filter=''){
    $modusers=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $emails=array();
    foreach($oids as $oid){
      if(\Seolan\Core\Kernel::getTable($oid)!=$this->tagenda && $filter!='agenda'){
	$r1=$modusers->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA));
         if(empty($altmoid)) $emails[]=array('mail'=>$r1['oemail']->raw,'name'=>$r1['ofullnam']->raw,'type'=>'user','oid'=>$oid);
         else{
           $tmp=$modusers->xset->emailsFromDisplay($r1);
           foreach($tmp as $mail){
             $emails[]=array('mail'=>$mail,'name'=>$r1['tlink'],'type'=>'user','oid'=>$oid);
           }
         }
      }elseif($filter!='user'){
	$ors=getDB()->fetchRow('select OWN from '.$this->tagenda.' where KOID=? LIMIT 1', array($oid));
	$r1=$modusers->display(array('oid'=>$ors['OWN'],'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>array('fullnam','email')));
	$emails[]=array('mail'=>$r1['oemail']->raw,'name'=>$r1['ofullnam']->raw,'type'=>'agenda','oid'=>$oid);
      }
    }
    return $emails;
  }

  /// Recupere les preferences du module pour une édition
  function getParamPrefs(){
    $desc['defaultcal']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'defaultcal','FTYPE'=>'\Seolan\Field\Link\Link','MULTIVALUED'=>0,
							       'COMPULSORY'=>false,'LABEL'=>'Calendrier par défaut',
							       'TARGET'=>$this->tagenda));
    $desc['defaultcal']->checkbox=false;
    $desc['defview']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'defview','FTYPE'=>'\Seolan\Field\ShortText\ShortText','MULTIVALUED'=>0,
							    'COMPULSORY'=>false,'LABEL'=>'Vue par defaut'));
    $options['defview']['select_box_values']=array('displayDay', 'displayWeek', 'displayMonth', 'displayYear');
    // consolidations ??
    return array('desc'=>$desc,'options'=>$options);
  }
}
?>
