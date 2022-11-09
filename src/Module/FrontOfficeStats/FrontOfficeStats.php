<?php
namespace Seolan\Module\FrontOfficeStats;
/// Module de gestion des statistiques de visualisation des pages du site
class FrontOfficeStats extends \Seolan\Core\Module\Module {
  public $prefix='';
  public $totalon='total';
  static public $upgrades=["20200311"=>'public'];

  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','systemproperties','text');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('browseMarksYearly'=>array('ro','rw','rwv','admin'),
	     'browseMarksPeriod'=>array('ro','rw','rwv','admin'),
	     'clearMarks'=>array('admin'));
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('object_sec');
    $alabel=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','modulename');
    $this->_options->setOpt('Prefix','prefix','text',NULL,NULL,$alabel);
    $this->_options->setOpt('Total On','totalon','text',NULL,NULL,$alabel);
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=browseMarksYearly&template=Module/FrontOfficeStats.browseMarksYearly.html&tplentry=br';
  }

  /// Liste des actions du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    $o1=new \Seolan\Core\Module\Action($this,'browseMarks',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','yearly_stats','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksYearly&template=Module/FrontOfficeStats.browseMarksYearly.html&tplentry=br','display');
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['browse']=$o1;
    if($this->secure('','clearMarks')){
      $o1=new \Seolan\Core\Module\Action($this,'clearMarks&',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','clearmarks','text'),
			    '&moid='.$this->_moid.'&_function=clearMarks&template=Core.message.html&tplentry=br','display');
      $o1->needsconfirm=\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','clearmarks','text').' ?';
      $o1->setToolbar('Seolan_Core_General','delete');
      $my['clearMarks']=$o1;
    }
    if($this->interactive){
      $o1=new \Seolan\Core\Module\Action($this,'browseMarks',$this->getLabel(),
					 '&moid='.$this->_moid.'&_function=browseMarksYearly&template=Module/FrontOfficeStats.browseMarksYearly.html&tplentry=br','display');
      $my['stack'][]=$o1;
    }
  }

  /// recuperation des fichiers de log
  protected function _daemon($when='any') {
    if (!$lck = \Seolan\Library\Lock::getLock('\Seolan\Module\FrontOfficeStats\FrontOfficeStats')) {
      return;
    }
    $files = glob(TZR_LOG_DIR . 'markers-*');
    foreach ($files as $file) {
      $handle = fopen($file, 'r+');
      if (!$handle || !flock($handle, LOCK_EX)) {
        continue;
      }
      $lines = [];
      while ($lines[] = fgetcsv($handle, 0, ';')) {}
      ftruncate($handle, 0);
      rewind($handle);
      flock($handle, LOCK_UN);
      fclose($handle);
      foreach ($lines as $line) {
        $this->_integrateHit($line[2], $line[0], $line[1], $line[3], $line[4]);
      }
      $time = explode('-', $file)[1];
      if ($time < date('YmdH')) {
        unlink($file);
      }
    }
    \Seolan\Library\Lock::releaseLock($lck);
  }

  function al_browseMarksPeriod(&$my){
    $br=\Seolan\Core\Shell::from_screen('br');
    $o1=new \Seolan\Core\Module\Action($this,'viewref1',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','viewref1','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksPeriod&template=Module/FrontOfficeStats.browseMarksPeriod.html&&tplentry=br&threshold=0&'.
			  'date='.$br['datestart']->raw.'&nbdays='.$br['nbdays'],'display');
    $o1->menuable=1;
    $my['viewref1']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'viewref2',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','viewref2','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksPeriod&template=Module/FrontOfficeStats.browseMarksPeriod.html&&tplentry=br&threshold=1&'.
			  'date='.$br['datestart']->raw.'&nbdays='.$br['nbdays'],'display');
    $o1->menuable=1;
    $my['viewref2']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'viewref3',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','viewref3','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksPeriod&template=Module/FrontOfficeStats.browseMarksPeriod.html&&tplentry=br&threshold=5&'.
			  'date='.$br['datestart']->raw.'&nbdays='.$br['nbdays'],'display');
    $o1->menuable=1;
    $my['viewref3']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'aweekbefore',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','aweekbefore','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksPeriod&template=Module/FrontOfficeStats.browseMarksPeriod.html&&tplentry=br&'.
			  'threshold='.$br['threshold'].'&date='.$br['dateprev'],'display');
    $o1->setToolbar('Seolan_Core_General','previous');
    $my['aweekbefore']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'aweekahead',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','aweekahead','text'),
			  '&moid='.$this->_moid.'&_function=browseMarksPeriod&template=Module/FrontOfficeStats.browseMarksPeriod.html&&tplentry=br&'.
			  'threshold='.$br['threshold'].'&date='.$br['datenext'],'display');
    $o1->setToolbar('Seolan_Core_General','next');
    $my['aweekahead']=$o1;
  }
  
  /// Remettre a zero toutes les tables de comptage
  function clearMarks($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    getDB()->execute('TRUNCATE _MARKS');
    \Seolan\Core\Shell::toScreen2('','message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_FrontOfficeStats_FrontOfficeStats','markscleared','text'));
  }

  /// Affichage de statistiques à la date du jour
  function browseMarksYearly($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('days'=>30));
    $tplentry=$p->get('tplentry');
    $totalon='('.$this->prefix.$this->totalon.')';

    // Pages vues par mois
    global $TZR_LANGUAGES;
    $years=$yearly=array();
    $def=array('y'=>'','l'=>'','01'=>'','02'=>'','03'=>'','04'=>'','05'=>'','06'=>'','07'=>'','08'=>'','09'=>'','10'=>'','11'=>'','12'=>'','total'=>0);
    $rs=getDB()->select('select * from _MARKS WHERE name="'.$totalon.'" order by ts,mlang');
    while($rs && $ors=$rs->fetch()) {
      if (!isset($TZR_LANGUAGES[$ors['mlang']]))
        continue;
      $date=explode('-',$ors['ts']);
      $key=$date[0].$ors['mlang'];
      if(empty($yearly[$key])){
	if(!in_array($date[0],$years)) $years[]=$date[0];
	foreach($TZR_LANGUAGES as $lang=>$iso) {
	  $key2=$date[0].$lang;
	  $yearly[$key2]=$def;
	  $yearly[$key2]['y']=$date[0];
	  $yearly[$key2]['l']=$lang;
	}
      }
      if (!is_numeric($yearly[$key][$date[1]]))
	$yearly[$key][$date[1]] = 0;
      $yearly[$key][$date[1]]+=$ors['cnt'];
      $yearly[$key]['total']+=$ors['cnt'];
    }
    rsort($years);
    \Seolan\Core\Shell::toScreen2($tplentry.'t','y',$yearly);
    \Seolan\Core\Shell::toScreen2($tplentry.'t','yl',$years);


    // Pages vues par jour par mois
    $daily=$yearly;
    $now=date('Y-m');
    foreach($daily as $key=>&$c) {
      foreach($c as $col=>&$v){
	if(empty($v)) continue;
	if($col=='y' || $col=='l') continue;
	if($col=='total'){
	  $v=round($v/365);
	  continue;
	}
	$start=mktime(0,0,0,$col,1,$v['y']);
	if($v['y'].'-'.$col==$now) $end=mktime(0,0,0);
	else $end=mktime(0,0,0,($col+1),-1,$v['y']);
	$v=round($v/(($end-$start)/60/60/24+1));
      }
    }
    \Seolan\Core\Shell::toScreen2($tplentry.'t','d',$daily);
    
    // Pages vues sur les x derniers jours
    $days=$p->get('days');
    $tablem=array();
    $rs=getDB()->select('select * from _MARKS where ts>=DATE_SUB(NOW(), INTERVAL '.$days.' DAY) and name="'.$totalon.'" order by ts,mlang');
    while($rs && $ors=$rs->fetch()){
      @$tablem[$ors['ts']]['ts']=$ors['ts'];
      @$tablem[$ors['ts']]['cnt']+=$ors['cnt'];
      @$tablem[$ors['ts']]['lcnt'][$ors['mlang']]+=$ors['cnt'];
    }
    foreach($tablem as $i=>&$tmp) {
      foreach($TZR_LANGUAGES as $lang=>$foo) $tmp['pcnt'][$lang]=round($tmp['lcnt'][$lang]*100/$tmp['cnt'],2);
    }
    \Seolan\Core\Shell::toScreen2($tplentry.'t','m',$tablem);

    \Seolan\Core\Shell::toScreen2($tplentry.'t','langs',$TZR_LANGUAGES);
  }

  /// Affichage de statistiques sur une periode donnee
  function browseMarksPeriod($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array('date'=>'now','threshold'=>1,'nbdays'=>30));
    $tplentry=$p->get('tplentry');
    $th=$p->get('threshold');

    // Calcul de l'intervalle de date
    list($datestart,$dateend,$nbdays)=$this->getDates($ar);
    // Calcul des marqueurs
    global $TZR_LANGUAGES;
    $byname=array();
    $totalon='('.$this->totalon.')';
    $rs=getDB()->select('select name,mlang,sum(cnt) as tot from _MARKS where ts>="'.$datestart.'" and ts<="'.$dateend.'" and '.
		    '(name like "'.$this->prefix.'%" OR name like "('.$this->prefix.'%)") group by name,mlang order by name');
    while($rs && $ors=$rs->fetch()) {
      @$byname[$ors['name']]['name']=str_replace($this->prefix,'',$ors['name']);
      @$byname[$ors['name']]['lcnt'][$ors['mlang']]+=$ors['tot'];
      @$byname[$ors['name']]['cnt']+=$ors['tot'];
    }
    foreach($byname as $name=>&$cell) {
      foreach($TZR_LANGUAGES as $l1=>$l2) $cell['pcnt'][$l1]=round($cell['lcnt'][$l1]*100/$cell['cnt'],2);
    }
    // Supression des pages dont le taux est inferieur à threshold
    if($th){
      foreach($byname as $name=>&$cell) {
	if($name!='(omitted)') {
	  $found=false;
	  foreach($TZR_LANGUAGES as $l1=>$l2) {
	    if($cell['pcnt'][$l1]>$th) {
	      $found=true;
	      break;
	    }
	  }
	  if(!$found) {
	    @$byname['(omitted)']['name']='(omitted)';
	    @$byname['(omitted)']['cnt']+=$cell['cnt'];
	    foreach($TZR_LANGUAGES as $l1=>$l2) {
	      @$byname['(omitted)']['lcnt'][$l1]+=$cell['lcnt'][$l1];
	      @$byname['(omitted)']['pcnt'][$l1]+=$cell['pcnt'][$l1];
	    }
	    unset($byname[$name]);
	  }
	}
      }
    }
    // Pages vues par jours
    foreach($byname as $name=>&$cell) {
      foreach($TZR_LANGUAGES as $l1=>$l2) $cell['pdcnt'][$l1]=$cell['lcnt'][$l1]/$nbdays;
    }
    uasort($byname, function ($a, $b) { return $a['cnt'] < $b['cnt']; });
    \Seolan\Core\Shell::toScreen2($tplentry.'t','n',$byname);

    $xdate=new \Seolan\Field\Date\Date();
    $r['nbdays']=$nbdays;
    $r['datestart']=$xdate->display($datestart);
    $r['dateend']=$xdate->display($dateend);
    $r['threshold']=$th;
    $r['dateprev']=date('Y-m-d',strtotime($datestart.' -7 days'));
    $r['datenext']=date('Y-m-d',strtotime($datestart.' +7 days'));
    \Seolan\Core\Shell::toScreen2($tplentry.'t','langs',$TZR_LANGUAGES);
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Recupere les bornes d'une periode
  function getDates($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('date'=>'now','threshold'=>1,'nbdays'=>30));
    $datestart=$p->get('date');
    $nbdays=$p->get('nbdays');

    // Calcul de l'intervalle de date
    $rs=getDB()->select('select min(ts) from _MARKS where cnt>0 and (name like "'.$this->prefix.'%" OR name like "('.$this->prefix.'%)")');
    if($rs && $ors=$rs->fetch()) $datestartmin=$ors['min(ts)'];
    if($datestart=='now') {
      $datestart=max($datestartmin,date('Y-m-d',strtotime('-'.$nbdays.' days')));
      $dateend=date('Y-m-d');
    }else{
      $datestart=max($datestart,$datestartmax);
      $exploded=explode('-',$datestart);
      if($exploded[2]=='01') $nbdays=date('t',strtotime($datestart));
      $dateend=date('Y-m-d',strtotime($datestart.' +'.($nbdays-1).' days'));
      $dateend=min($dateend,date('Y-m-d'));
    }
    $explodeds=explode('-',$datestart);
    $explodede=explode('-',$dateend);
    $nbdays=mktime(0,0,0,$explodede[1],$explodede[2],$explodede['0'])-mktime(0,0,0,$explodeds[1],$explodeds[2],$explodeds['0']);
    $nbdays/=60*60*24;
    $nbdays++;
    return array($datestart,$dateend,$nbdays);
  }
  
  /// Recupere le nombre de page vu aujourd'hui
  private function _countLastDay() {
    return getDB()->fetchOne('select SUM(cnt) from _MARKS where ts="'.date('Y-m-d').'" and name="('.$this->prefix.$this->totalon.')"');
  }

  /// Contenu de la page d'accueil
  public function &_portlet() {
    $txt='';
    $daylabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','day');
    $nb=$this->_countLastDay();
    if(!empty($nb)) $txt=$daylabel.' : '.$nb;
    return $txt;
  }

  function status($ar=NULL) {
    $nb = $this->_countLastDay();
    $daylabel=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','day');
    if(!empty($nb)) $msg=$daylabel.':'.$nb;
    $msg1=\Seolan\Core\Shell::from_screen('imod','status');
    if(empty($msg)) $msg1=array();
    if(!empty($msg)) $msg1[]=$msg;
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }

  private function _integrateHit($now, $m='(nomarker)', $lang=TZR_DEFAULT_LANG, $totalon='(total)', $serverName) {
    global $IS_VHOST, $HAS_VHOSTS;
    if (@$IS_VHOST)
      return;
    if (@$HAS_VHOSTS && $db = \Seolan\Module\MiniSite\MiniSite::getDBFromVhost($serverName))
      $db .= '.';
    else
      $db = '';
    $q="SELECT * FROM {$db}_MARKS WHERE name = ? AND mlang = ? and ts=? limit 1";
    if(!getDB()->fetchExists($q,array($m, $lang, $now))) {
      $oid=\Seolan\Core\DataSource\DataSource::getNewBasicOID('_MARKS');
      $q="INSERT INTO {$db}_MARKS SET KOID=?,LANG='".TZR_DEFAULT_LANG."',name=?,".
	"mlang=?, cnt=1, ts=?";
      getDB()->execute($q,array($oid,$m,$lang,$now));
    }else{
      $q="UPDATE LOW_PRIORITY {$db}_MARKS SET cnt=cnt+1 WHERE name = ? AND mlang = ? and ts=? limit 1";
      getDB()->execute($q,array($m,$lang,$now));
    }
    
    $q="SELECT * FROM {$db}_MARKS WHERE name = ? AND mlang = ? and ts=? limit 1";
    if(!getDB()->fetchExists($q,array($totalon,$lang,$now))) {
      $oid=\Seolan\Core\DataSource\DataSource::getNewBasicOID('_MARKS');
      $q="INSERT INTO {$db}_MARKS SET KOID=?,LANG='".TZR_DEFAULT_LANG."',name=?,".
	"mlang=?, ts=?, cnt=1";
      getDB()->execute($q,array($oid,$totalon,$lang,$now));
    }else{
      $q="UPDATE {$db}_MARKS SET cnt=cnt+1 WHERE name = ? AND mlang = ? and ts= ? limit 1";
      getDB()->execute($q,array($totalon,$lang,$now));
    }
  }
}
