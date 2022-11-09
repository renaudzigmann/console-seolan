<?php
namespace Seolan\Module\Form;
class Form extends \Seolan\Module\Table\Table{
  static public $upgrades = [];
  // toid des modules sources de données pour les types liens
  protected static $linkobjectModulesToid = [XMODTABLE_TOID,
					     XMODUSER2_TOID,
					     XMODGROUP_TOID,
					     XMODCRM_TOID,
					     XMODMAILINGLIST_TOID,
					     XMODMULTITABLE_TOID,
					     XMODRECORD_TOID,
					     ];
  static protected $iconcssclass='csico-file-check';					     
  function __construct($ar=NULL){
    parent::__construct($ar);
    if ($this->xset->fieldExists('questionsproperties')){
      $this->xset->desc['questionsproperties']->sys = true;
    }
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['viewForm']=array('ro','rw','rwv','admin');
    $g['editForm']=array('rw','rwv','admin');
    $g['procEditForm']=array('rw','rwv','admin');
    $g['answer']=array('ro','rw','rwv','admin');
    $g['procAnswer']=array('ro','rw','rwv','admin');
    $g['delAnswers']=array('rw','rwv','admin');
    $g['dashboard']=array('rw','rwv','admin');
    $g['exportAnswers']=array('rw','rwv','admin');
    $g['sendInvitations']=array('rw','rwv','admin');
    $g['procSendInvitations']=array('rw','rwv','admin');
    $g['createMod']=array('rw','rwv','admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answerstable'),'atable','table',array('validate'=>true),NULL);
  }

  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my, $alfunction);
    $oid=@$_REQUEST['oid'];
    if(\Seolan\Core\Shell::_function()=='sendInvitations') {
      $br=$this->xset->rDisplayText($oid, array());
      $o1=new \Seolan\Core\Module\Action($this,'browse',$br['link'],
			    '&moid='.$moid.'&_function=display&template=Module/Table.view.html&tplentry=br&oid='.$oid,'display');
      $my['stack'][]=$o1;
    }
  }
  function al_edit(&$my){
    parent::al_edit($my);
    $oid=$_REQUEST['oid'];
    $o1=new \Seolan\Core\Module\Action($this,'saveandsend',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','saveandsend','text'),
				       '&moid='.$this->_moid.'&function=display&template=Module/Table.view.html&oid='.$oid.'&tplentry=br&_tabs=dashboard','more');
    $o1->menuable=false;
    $o1->actionable = true;
    $my['saveandsend']=$o1;
  }
  function al_display(&$my){
    parent::al_display($my);
    $oid=$_REQUEST['oid'];
    $o1=new \Seolan\Core\Module\Action($this,'createmod',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','createmod','text'),
			  '&moid='.$this->_moid.'&function=createMod&template=Core.message.html&oid='.$oid.'&skip=1&_next=refresh','more');
    $o1->menuable=true;
    $my['createmod']=$o1;
  }

  /// Creation d'un module ensemble de fiche sur les reponses d'un formulaire
  function createMod($ar=NULL){
    $p=new \Seolan\Core\Param($oid,NULL);
    $oid=$p->get('oid');
    $ors=getDB()->fetchRow('select * from '.$this->table.' where KOID=? LIMIT 1',array($oid));
    if($ors['KOID'] && $ors['qtable']){
      $mod=new \Seolan\Module\Table\Wizard(array('newmoid'=>XMODTABLE_TOID));
      $mod->_module->modulename=$ors['title'];
      $mod->_module->group=$this->group;
      $mod->_module->table=$ors['qtable'];
      $moid=$mod->iend();
      getDB()->execute('update '.$this->table.' set qmod=?,qtable="" where KOID=?',array($moid,$oid));
      \Seolan\Core\User::copyModuleAccess($this->_moid,$moid);
      setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','createmodok','text'));
    }elseif($ors['qmod']){
      setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','createmodalreadyexists','text'));
    }
  }

  /// Affichage d'une fiche
  function display($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid = $p->get('oid');
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::display($ar);
    $ret['__ajaxtabs'][]=array('name'=>'preview', 
			       'title'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','viewform','text'),
			       'url'=>$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid='.$ret['oid'].
								       '&tplentry=br&function=viewForm&template=Module/Form.viewForm.html&_ajax=1&_raw=1&tabsmode=1&skip=1&_uniqid='.\Seolan\Core\Shell::uniqid());
    $ret['__ajaxtabs'][]=array('name'=>'dashboard',
			       'title'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','managing','text'),
			       'url'=>$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid='.$ret['oid'].'&tplentry=br'.
								       '&function=dashboard&template=Module/Form.dashboard.html&_ajax=1&_raw=1&tabsmode=1&skip=1&_uniqid='.\Seolan\Core\Shell::uniqid());
    
    // questions properties (caché. mais nécessaire dans touts les cas)
    if (isset($ret['oquestionsproperties'])){
      $ret['_questionsproperties'] = $ret['oquestionsproperties'];
    } elseif ($this->xset->fieldExists('questionsproperties')) {
      $raw = getDB()->fetchOne('select questionsproperties from '.$this->xset->getTable().' where KOID = ? and LANG = ?', [$oid, TZR_DEFAULT_LANG]);
      $fd = $this->xset->getField('questionsproperties');
      $ret['_questionsproperties'] = $fd->display($raw, []);
      $fd->decodeRaw($ret['_questionsproperties']);
    }
    // champ vide, reprise d'anciens formulaires
    if (!is_object($ret['_questionsproperties']->decoded_raw)){
      $ret['_questionsproperties']->decoded_raw = (Object)['separators'=>[]];
    } else {
      // champ vide, reprise d'anciens formulaires
      if (!is_object($ret['_questionsproperties']->decoded_raw)){
        $ret['_questionsproperties']->decoded_raw = (Object)['separators'=>[]];
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Edition d'une fiche
  function edit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=parent::edit($ar);
    $ret['__ajaxtabs'][]=array('title'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','editform','text'),
			       'url'=>$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid='.$ret['oid'].'&skip=1&'.
			       '&tplentry=br&function=editForm&template=Module/Form.editForm.html&_ajax=1&_raw=1&tabsmode=1&_uniqid='.\Seolan\Core\Shell::uniqid());
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Créé un nouveau questionnaire
  function procInsert($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $t=$p->get('qtable');
    $m=$p->get('qmod');
    $title=$p->get('title');
    // Création de la table des questions/réponses
    if(!empty($m)) $ar['qtable']='';
    if(empty($t) && empty($m)){
      $ar1=array();
      $ar1['translatable']=0;
      $ar1['auto_translate']=0;
      $ar['qtable']=$ar1['btab']=\Seolan\Model\DataSource\Table\Table::newTableNumber('FORM');
      $ar1['bname'][TZR_DEFAULT_LANG]=$this->getLabel().' : '.$title;
      $ar1['publish']=0;
      \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$ar['qtable']);
      $x->createField('email','Email','\Seolan\Field\ShortText\ShortText','255','2','0','1','0','0','0','0');
      // il n'y a pas de "question" "TAG"
      if ($x->fieldExists('TAG')){
	$x->delField(['field'=>'TAG','action'=>'OK']);
      }
    }
    $isopen=$p->get('isopen');
    $hid=$p->get('isopen_HID');
    if(!$hid && $isopen==1 || $hid['val']){
      $oid=$ar['newoid']=$this->xset->getNewOID($ar);
      $ar['accesurl']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).'&moid='.$this->_moid.'&oid='.$oid.
	'&function=answer&template=Core.layout/raw.html&insidefile=Module/Form.viewForm.html&tplentry=br';
      $ar['directaccess_HID']['val'] = 2;
      $ar['directaccess'] = 2;
      // re edition imp
      $ar['reedit_HID']['val'] = 2;
      $ar['reedit'] = 2;
    }else{
      $ar['accesurl']='Accès restreint. Aucune URL publique.';
    }
    $ar['questionsproperties'] = (Object)['separators'=>[]];
    $ret=parent::procInsert($ar);
    $this->_createCompulsoryFields($ret['oid']);
    return $ret;
  }
  /// duplications : mise en forme des champs
  function editDup($ar=null){
    if ($this->xset->fieldExists('qmod')){
      $fd = $this->xset->getField('qmod');
      $fd->fgroup = 'Questions';
      $fd->acomment = 'La table des données du module et le module seront dupliqués.';
      $fd->readonly = true;
    }
    if ($this->xset->fieldExists('qtable')){
      $fd = $this->xset->getField('qtable');
      $fd->fgroup = 'Questions';
      $fd->acomment = 'La table sera dupliquée.';
      $fd->readonly = true;
    }
    return parent::editDup($ar);
  }
  /// Dupplique la table des questions avant de duppliquer le formulaire
  function procEditDup($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,NULL);
    $oid = $p->get('oid');
    $title = $p->get('title');
    $oldrs = getDB()->fetchRow('SELECT title, qmod, qtable, questionsproperties FROM '.$this->table.' WHERE KOID=? and LANG=?',array($oid,TZR_DEFAULT_LANG));

    if($title == $oldrs['title']){
      $ar['title'] = $title = $title.' (clone)';
    }
    $qmod = $oldrs['qmod'];
    $qtable = $oldrs['qtable'];

    $new_qtable=\Seolan\Model\DataSource\Table\Table::newTableNumber('FORM');

    if(!empty($qmod)){
      $mod=\Seolan\Core\Module\Module::objectFactory($qmod);
      $ret=$mod->duplicateModule
	(array('modulename'=>$this->getLabel().' : '.$title,
	       'tables'=>[$mod->table=>['newtable'=>$new_qtable,
					'mtxt' => $this->getLabel().' : '.$title]]
	       ));
      $ar['qmod']=$ret['moid'];
      $ar['qtable'] = '';
      $qtable=$mod->table;
    }elseif($qtable){
      $xset_qtable = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$qtable);
      $xset_qtable->procDuplicateDataSource(array(
        'newtable' => $new_qtable,
        'mtxt' => $this->getLabel().' : '.$title
      ));
      $ar['qtable'] = $new_qtable;
    }
    // Duplication des réponses dans la table associée
    if ($this->atable) {
      // Récupération des choix groupés par KOID (pour dupliquer le multilingue)
      $choices = getDB()->select("SELECT KOID,{$this->atable}.* FROM {$this->atable} WHERE dtable=?", [$qtable])->fetchAll(\PDO::FETCH_GROUP);
      foreach ($choices as $koid => $choices_by_languages) {
        $koid = \Seolan\Core\DataSource\DataSource::getNewBasicOID($this->atable);
        foreach ($choices_by_languages as $choice) {
          $choice['KOID'] = $koid;
          $choice['dtable'] = $new_qtable;
          getDB()->execute(getDB()->getInsertQuery($this->atable, $choice));
        }
      }
      unset($choices);
    }
    $ar['lastanswer'] = TZR_DATETIME_EMPTY;
    $ar['dtsend'] = TZR_DATE_EMPTY;
    $ar['invitok_HID']['val'] = 2;
    $ar['questionsproperties'] = $oldrs['questionsproperties'];
    
    $ret=parent::procEditDup($ar);
    $this->_createCompulsoryFields($ret['oid']);
    return $ret;
  }

  /// Supprime la table des questions ainsi que les choix de réponses liées au formulaire
  function del($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,NULL);
    $oid = $p->get('oid');
    $delrepo = $p->get('delrepo');
    // Table des questions
    if($this->xset->fieldExists('qmod')){
      $form = getDB()->fetchRow('SELECT qmod,qtable FROM '.$this->table.' WHERE KOID=?',array($oid));
    } else {
      $form = getDB()->fetchRow('SELECT qtable FROM '.$this->table.' WHERE KOID=?',array($oid));
    }
    if($form['qmod']){
      // Suppression du module des questions
      $mod=\Seolan\Core\Module\Module::objectFactory($form['qmod']);
      if($delrepo) $mod->delete(array('withtable'));
      // Suppression des choix de réponses
      if($this->atable) getDB()->execute('DELETE FROM '.$this->atable.' WHERE dtable=?',array($mod->table));
    }elseif($form['qtable']){
      // Suppression des choix de réponses
      if($this->atable) getDB()->execute('DELETE FROM '.$this->atable.' WHERE dtable=?',array($form['qtable']));
      // Suppression de la table des questions
      if($delrepo) \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$form['qtable'])->procDeleteDataSource();
    }
    return parent::del($ar);  
  }

  /// Edition d'un questionnaire
  function procEdit($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    if(is_array($oid)) return parent::procEdit($ar);
    $isopen=$p->get('isopen');
    $hid=$p->get('isopen_HID');
    if(!$hid && $isopen==1 || $hid['val']){
      $ar['accesurl']=$GLOBALS['TZR_SESSION_MANAGER']::complete_self(false,true).'&moid='.$this->_moid.'&oid='.$oid.
	'&function=answer&template=Core.layout/raw.html&insidefile=Module/Form.viewForm.html&tplentry=br';
      $ar['directaccess_HID']['val'] = 2;
      $ar['directaccess'] = 2;
      $ar['reedit_HID']['val'] = 2;
      $ar['reedit'] = 2;
    }elseif($hid){
      $ar['accesurl']='Accès restreint. Aucune URL publique.';
    }
    if($p->get('qmod')) $ar['qtable']='';
    $ret=parent::procEdit($ar);
    $this->_createCompulsoryFields($oid);
    return $ret;
  }

  /// Voir le formulaire
  function viewForm($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$this->display($ar);
    $repo=$this->_getRepository($ret['oqmod']->raw,$ret['oqtable']->raw);
    $this->setSysFields($repo->XFormGetDataSource());
    $this->configureRepositoryFields($repo->XFormGetDataSource(), $ret['_questionsproperties']->decoded_raw);
    $ret['__table']=$repo->XFormInput($ar);
    $ret['__table']['fields_qproperties']=$this->getQuestionsProperties($ret['__table']['fields_object'], $ret['_questionsproperties']->decoded_raw);
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Repondre au formulaire
  function answer($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('email'=>'nobody'));
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $email=$p->get('email');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$this->display($ar);
    $date=date('Y-m-d');
    if($date<$ret['odtstart']->raw && $date>$ret['odtend']->raw){
      $ret['__error']=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','wrongdate');
    }else{
      $repo=$this->_getRepository($ret['oqmod']->raw,$ret['oqtable']->raw);
      $this->setSysFields($repo->XFormGetDataSource());
      $table=$repo->XFormGetDataSource()->getTable();

      $this->configureRepositoryFields($repo->XFormGetDataSource(), $ret['_questionsproperties']->decoded_raw);

      // Recupere une eventuelle reponse déjà enregistrée (si formulaire reeditable, on veut la fiche en cours en priorité)
      if($ret['oreedit']->raw==1) $order=' order by FIELD(close,1)';
      else $order='';
      $ors=array();
      if(!\Seolan\Core\User::isNobody()){
	$ors=getDB()->fetchRow('select * from '.$table.' where OWN=?'.$order.' limit 1',
			       [\Seolan\Core\User::get_current_user_uid()]);
      } elseif($email!='nobody'){
	$ors=getDB()->fetchRow('select * from '.$table.' where email=?'.$order.' limit 1', [$email]);
      } elseif($ret['oisopen']->raw !=1 && $ret['odirectaccess']->raw == 1){
	$uid = $p->get('uid');
	$ors=getDB()->fetchRow('select * from '.$table.' where OWN=?'.$order.' limit 1', [$uid]);
      } 
      if(empty($ors)){
	// Pas de reponse enregistrée => insertion 
	$ret['__table']=$repo->XFormInput($ar);
      }elseif($ret['oamulti']->raw!=1 && !($ret['oreedit']->raw==1 && $ors['close']!=1)){
	// Fiche deja existante et plus modifiable => erreur
	$ret['__error']=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answeralreadyexists');
      }elseif($ret['oreedit']->raw==1 && $ors['close']!=1){
	// Fiche editable presente => edition de la ficheg
	$ar['oid']=$ors['KOID'];
	$ret['__table']=$repo->XFormEdit($ar);
      }else{
	// Tous les autres cas => insertion d'une nouvelle fiche
	$ret['__table']=$repo->XFormInput($ar);
      }
      $ret['__table']['fields_qproperties']=$this->getQuestionsProperties($ret['__table']['fields_object'], $ret['_questionsproperties']->decoded_raw);
    }
    if($ret['oisopen']->raw==1) $ret['__captcha']=$this->createCaptcha(array('tplentry'=>TZR_RETURN_DATA),true);
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }
  /// Modifications locale des propriétes des champs de la table de réponse
  /// workaround pour demande de pouvoir ordonner les valeurs dans le cas d'une question lien
  function configureRepositoryFields($ds, $questionsProperties){
    $links = $ds->getFieldsList(['\Seolan\Field\Link\Link']);
    foreach($links as $fn){
      $fd = $ds->getField($fn);
      $qtype = $this->getQuestionType($fd, $questionsProperties);
      if ($qtype == 'objectlink' && !empty($fd->sourcemodule)){
	$targetds = \Seolan\Core\Module\Module::objectFactory(['moid'=>$fd->sourcemodule, 'tplentry'=>TZR_RETURN_DATA])->xset;
	if ($targetds->fieldExists('ORD')){
	  // nb : un éventuel filtre module est ajouté si nécessaire par l'edit du champ
	  $fd->query = $targetds->select_query(['order'=>'ORD ASC']);
	}
      }
    }
  }
  /// Enregistre une reponse
  function procAnswer($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $aoid=$p->get('aoid');
    $close=$p->get('close');
    $uid = $p->get('uid');

    $f=$this->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA));

    if($f['oisopen']->raw==1){
      $captcha_key = md5 ( $p->get ( 'captcha_key' ) );
      $captcha_id = $p->get ( 'captcha_id' );
      $cnt = getDB ()->count ( 'SELECT COUNT(*) FROM _VARS WHERE name = ? AND value = ? ', array (
	'CAPTCHA_' . $captcha_id,
	$captcha_key 
      ) );
      getDB ()->execute ( 'DELETE FROM _VARS WHERE name=? or (UPD < ? AND name LIKE ?)', array (
	'CAPTCHA_' . $captcha_id,
	date ( 'YmdHis', strtotime ( '- 20 minutes' ) ),
	"CAPTCHA_%" 
      ) );
      if (! $cnt) {
	$onerror = $p->get ( 'onerror' );
	if (! empty ( $onerror )) {
	  if (! preg_match ( '@(^https?://|^/)@', $onerror ))
	    $onerror = $GLOBALS ['TZR_SESSION_MANAGER']::complete_self () . $onerror;
	} else {
	  $onerror = $_SERVER ['HTTP_REFERER'];
	}
	header ( 'Location: ' . $onerror );
	die ();
      }
    }
    // !open + access direct : OWN vient de la request (vérifié dans secure)
    if($f['oisopen']->raw !=1 && $f['odirectaccess']->raw == 1){
      $ar['OWN'] = $uid;
    }
    $repo=$this->_getRepository($f['oqmod']->raw,$f['oqtable']->raw);
    if($aoid){
      $ar['oid']=$aoid;
      $ret=$repo->XFormProcEdit($ar);
    }else{
      $ret=$repo->XFormProcInput($ar);
    }
    getDB()->execute('update '.$this->table.' set lastanswer=? where KOID=?',array(date('Y-m-d H:i:s'),$oid));
    if(!$repo->XFormGetDataSource()->desc['close'] || $close==1) setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answerokclose'));
    else setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','answerok'));
    return $ret;
  }
  /// Prepare l'edition du questionnaire
  function editForm($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$this->display($ar);
    $repo=$this->_getRepository($ret['oqmod']->raw,$ret['oqtable']->raw);
    $repo_xds=$repo->XFormGetDataSource();
    $this->setSysFields($repo_xds);
    $axds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->atable);
    $ret['__table']=array();
    $max=0;
    foreach($repo_xds->desc as $n=>$f){
      if($f->sys) continue;
  
      $moduleSelect = \Seolan\Core\Module\Module::moduleSelector([
                                                'fieldname' => 'target['.$n.']',
                                                'value'     => $f->sourcemodule,
                                                'misc'      => '',
                                                'emptyok'   => true,
                                                'toid'      => self::$linkobjectModulesToid,
                                                'table'     => null,
                                              ]);
      
      $table=array('field'=>$n,'q'=>$f->get_labels(),'compulsory'=>$f->get_compulsory(),'comment'=>$f->get_option('comment'),
		   'type'=>$this->getQuestionType($f, $ret['_questionsproperties']->decoded_raw),
		   'fgroup'=>$f->get_option('fgroup'), 'ftype'=>$f->ftype,
		   'target' => $f->target,
		   'display_format' => $f->display_format,
		   'display_text_format' => $f->display_text_format,
		   'moduleSelect' => $moduleSelect,);
      if($f->ftype=='\Seolan\Field\Link\Link' && $f->target == $this->atable){
	foreach($GLOBALS['TZR_LANGUAGES'] as $lang=>$foo) {
	  $table['answers'][$lang]=$axds->browse(array('selectedfields'=>array('title','score'),'_filter'=>'dtable="'.$repo_xds->getTable().'" and '.
						       'dfield="'.$n.'"','tplentry'=>TZR_RETURN_DATA,'order'=>'ord','LANG_DATA'=>$lang,'pagesize'=>999));
	}
      }
      $ret['__table'][]=$table;
      /* Attention, max prend tout type de donnees.
       * Il faut le caster sinon le module formulaire ne fonctionne qu'avec des champs Fx et non des champs base sur une table existante.
       */
      $max=max($max,(int)substr($n,1));
    }
    $ret['__lastnum']=$max+1;
  
    $moduleSelectNewQuestion = \Seolan\Core\Module\Module::moduleSelector([
                                                         'fieldname' => 'target[xxx]',
                                                         'value'     => null,
                                                         'misc'      => null,
                                                         'emptyok'   => true,
                                                         'toid'      => self::$linkobjectModulesToid,
                                                         'table'     => null,
                                                       ]);
  
    $ret['__newQuestionModuleSelect'] = $moduleSelectNewQuestion;
    
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Efface toutes les réponses
  function delAnswers($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');

    $repo=$this->_getRepository($oid);
    if($repo) getDB()->execute('TRUNCATE TABLE '.$repo->XFormGetDataSource()->getTable());

    // reset du form à voir : il faut recharger la page fiche
    $this->xset->procEdit(['_options'=>['local'=>1],
			   'oid'=>$oid,
			   'dtsend'=>TZR_DATE_EMPTY,
			   'lastanswer'=>TZR_DATETIME_EMPTY,
			   'invitok'=>2
    ]);
  }

  /// Enregistre l'edition d'un formulaire
  function procEditForm($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $qs=$p->get('question');
    $cs=$p->get('comment');
    $qts=$p->get('qtype');
    $comps=$p->get('compulsory');
    $answers=$p->get('answers');
    $fgroup=$p->get('fgroup');
    $scores=$p->get('scores');
    $targets = $p->get('target');
    $displayFormats = $p->get('display_format');
    $displayTextFormats = $p->get('display_text_format');
    $formoid = $p->get('oid');

    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$this->display($ar);

    // prop. des questions qui ne sont pas stockées dans les champs
    $questionsProperties = $ret['_questionsproperties']->decoded_raw;
    $questionsProperties->separators = []; // on refait le tableau

    $repo=$this->_getRepository($ret['oqmod']->raw,$ret['oqtable']->raw);
    $repo_xds=$repo->XFormGetDataSource();
    $table=$repo_xds->getTable();
    $this->setSysFields($repo_xds);
    $fields=array('email','close');
    $i=2;
    foreach($qs as $n=>$q){
      if(empty($q)) continue;
      // Récupération des parametres des champs
      $editopt=array();
      $qt=$qts[$n];
      $c=$cs[$n];
      $group=$fgroup[$n];
      $comp=$comps[$n];
      $ans=$answers[$n];
      $sc=$scores[$n];
      $target=$targets[$n];
      $dispFormat=$displayFormats[$n];
      $dispTextFormat=$displayTextFormats[$n];
      $targetTable = $this->atable;
      if(!$comp) $comp=false;
      switch($qt){
	case 'shorttext':
	  $type='\Seolan\Field\ShortText\ShortText';
	  $size=255;
	  $multi=false;
	  $setans=false;
	  $editopt=array('listbox'=>false);
	  break;
	case 'integer':
	  $type='\Seolan\Field\Real\Real';
	  $size=10;
	  $multi=false;
	  $setans=false;
	  $editopt=array('default'=>'0','edit_format'=>'^([0-9]+)$','decimal'=>'0','target'=>'');
	  break;
	case 'longtext':
	  $type='\Seolan\Field\Text\Text';
	  $size=60;
	  $multi=false;
	  $setans=false;
	  break;
	case 'select':
	  $type='\Seolan\Field\Link\Link';
	  $size=0;
	  $editopt=array('checkbox'=>'0','filter'=>'dtable="'.$table.'" and dfield="'.$n.'"',
			 'query'=>"SELECT KOID FROM ".$this->atable." WHERE dtable='$table' AND dfield='$n' ORDER BY ord");
	  $multi=false;
	  $setans=true;
	  break;
	case 'checkbox':
	  $type='\Seolan\Field\Link\Link';
	  $size=0;
	  $editopt=array('checkbox'=>1,'checkbox_cols'=>1,'filter'=>'dtable="'.$table.'" and dfield="'.$n.'"',
			 'query'=>"SELECT KOID FROM ".$this->atable." WHERE dtable='$table' AND dfield='$n' ORDER BY ord");
	  $multi='on';
	  $setans=true;
	  break;
	case 'orderedlist':
	  $type='\Seolan\Field\Link\Link';
	  $size=0;
	  $editopt=array('checkbox'=>false,'autocomplete'=>false,'doublebox'=>1,'doubleboxorder'=>0, 'boxsizxe'=>8,'boxsize'=>8,
	     'filter'=>'dtable="'.$table.'" and dfield="'.$n.'"',
	     'query'=>"SELECT KOID FROM ".$this->atable." WHERE dtable='$table' AND dfield='$n' ORDER BY ord");
	  $multi='on';
	  $setans=true;
	  break;
	case 'radio':
	  $type='\Seolan\Field\Link\Link';
	  $size=0;
	  $editopt=array('checkbox'=>1,'checkbox_cols'=>1,'usedefault'=>false,'filter'=>'dtable="'.$table.'" and dfield="'.$n.'"',
			 'query'=>"SELECT KOID FROM ".$this->atable." WHERE dtable='$table' AND dfield='$n' ORDER BY ord");
	  $multi=false;
	  $setans=true;
	  break;
	case 'date':
	  $type='\Seolan\Field\Date\Date';
	  $size=0;
	  $editopt=array();
	  $multi=false;
	  $setans=true;
	  break;
	case 'file':
	  $type='\Seolan\Field\File\File';
	  $size=0;
	  $editopt=array();
	  $multi=false;
	  $setans=true;
	  break;

	case 'objectlink':

          $oldField = $repo_xds->getField($n);
	  // On vide le filter et query si l'on passe d'un type select, checkbox ou radio à objectlink
	  // !! cas où la table des données est une table pré existante, pas spécifique au form
	  if ($oldField->target == $this->atable){
	    $filter = '';
	    $query = '';
	  }
	  $targetTable  = \Seolan\Core\Module\Module::objectFactory(['moid' => $target, 'intereactive' => false, 'tplentry' => TZR_RETURN_DATA])->table;  
	  $type         = '\Seolan\Field\Link\Link';
	  $size         = 0;
	  $editopt      = ['display_format'      => $dispFormat,
      'display_text_format' => $dispTextFormat,
			   'filter'              => $filter,
			   'query'               => $query,
      'sourcemodule'        => $target,
	  ];
	  // !! cas où la table des données est une table pré-existante, pas spécifique au form
	  $multi        = (bool)$repo_xds->getField($n)->multivalued;
	  $setans       = true;
	  
	  break;
          
	case 'image':
	  $type='\Seolan\Field\Image\Image';
	  $size=0;
	  $editopt=array();
	  $multi=false;
	  $setans=true;
	  break;
	  
	case 'boolean':
	  $type='\Seolan\Field\Boolean\Boolean';
	  $size=0;
	  $editopt=array();
	  $multi=false;
	  $setans=true;
	  break;
	  
	case 'separator':
	  $type='\Seolan\Field\Text\Text';
	  $size=70;
	  $multi=false;
	  $setans=false;
	  $isSep = true;
	  $questionsProperties->separators[] = $n;
	  break;
	  
      } // end switch type

      if($c) $editopt['comment']=$c;
      if($group) $editopt['fgroup']=$group;
      
      // Création du champ s'il n'existe pas encore
      if (is_array($q)){
	if (isset($q[TZR_DEFAULT_LANG])){
	  $fieldlabel = $q[TZR_DEFAULT_LANG];
	}
      } else {
	$fieldlabel = $q;
      }
      if(!isset($repo_xds->desc[$n])){
	$repo_xds->createField($n,$fieldlabel,$type,$size,$i+5,$comp,'1','1','0',$multi,'0',$targetTable);
      }
      // Mise à jour du champ avec toutes les options
      $repo_xds->procEditField(array('field'=>$n,'table'=>$table,'_todo'=>'save','options'=>$editopt,'_options'=>array('local'=>1),
                                     'ftype'=>$type,'compulsory'=>$comp,'multivalued'=>$multi,'fcount'=>$size,'forder'=>$i+5,'target'=>$targetTable,
                                     'label'=>$q));
      // Mise à jour des reponses possible pour les questions à choix multiple
      if($setans){
	$axds=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$this->atable);
	$soids=array();
	foreach($ans as $j=>$a){
	  if(empty($a)) continue;
	  if(substr($j,0,3)!='foo'){
	    $axds->procEdit(array('oid'=>$j,
				  'title'=>$a[TZR_DEFAULT_LANG],
				  'score'=>$sc[$j],
				  'ord'=>count($soids),
				  'LANG_DATA'=>TZR_DEFAULT_LANG,
				  '_options'=>array('local'=>true)));
	    $soids[]=$oid=$j;
	  }else{
	    $ret=$axds->procInput(array('dtable'=>$table,
					'dfield'=>$n,
					'title'=>$a[TZR_DEFAULT_LANG],
					'score'=>$sc[$j],
					'LANG_DATA'=>TZR_DEFAULT_LANG,
					'ord'=>count($soids),
					'_options'=>array('local'=>true)));
	    $soids[]=$oid=$ret['oid'];
	  }
	  foreach($a as $lang=>$title) {
	    $axds->procEdit(array('oid'=>$oid,'title'=>$a[$lang],'LANG_DATA'=>$lang,'_options'=>array('local'=>true)));
	  }
	}
	getDB()->execute('DELETE FROM '.$this->atable.' where dtable="'.$table.'" AND dfield="'.$n.'" AND KOID NOT IN("'.implode('","',$soids).'")');
      }else{
	getDB()->execute('DELETE FROM '.$this->atable.' where dtable="'.$table.'" AND dfield="'.$n.'"');
      }
      $i++;
      $fields[]=$n;
    }
    // Suppression des champs qui ne sont plus present dans le questionnaire
    foreach($repo_xds->desc as $n=>$f){
      if(!$f->sys && !in_array($n,$fields)) $repo_xds->delField(array('field'=>$n,'action'=>'OK'));
    }
    // mise à jour des propriétes des questions
    $this->xset->procEdit([
      '_options'=>['local'=>1],
      'questionsproperties'=>$questionsProperties,
      'oid'=>$formoid
    ]);
  }
  /// Suivi/Monitoring
  function dashboard($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $tplentry=$p->get('tplentry');
    $ar['tplentry']=TZR_RETURN_DATA;
    $ret=$this->display($ar);
    $table=$this->_getRepository($ret['oqmod']->raw,$ret['oqtable']->raw)->XFormGetDataSource()->getTable();
    $ret['__total']=getDB()->count('select count(*) from '.$table);
    if ($ret['oisopen']->raw != 1){
      $ret['__nbdest'] = count(array_unique(preg_split("/[;\n, ]/",$ret['odestm']->raw)));
      $ret['__nbdest'] += count($ret['odest']->oidcollection);
    }
    if($ret['oreedit']->raw==1){
      $ret['__ototal']=getDB()->count('select count(*) from '.$table.' where close!=1 or close is null');
      $ret['__ctotal']=getDB()->count('select count(*) from '.$table.' where close=1');
    }
    $date=date('Y-m-d');
    if($date<$ret['odtstart']->raw && $date>$ret['odtend']->raw){
      $ret['__closed'] = true;
    } else {
      $ret['__closed'] = false;
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Exporte les reponses d'un questionnaire
  function exportAnswers($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $oid=$p->get('oid');
    $ors=getDB()->fetchRow('select title,qmod,qtable from '.$this->table. ' where KOID="'.$oid.'" limit 1');
    $repo=$this->_getRepository($ors['qmod'],$ors['qtable']);
    $answers = $repo->XFormBrowse(array('tplentry'=>TZR_RETURN_DATA, 'pagesize'=>999999,'order'=>'UPD','selectedfields'=>'all'));
    $this->export(['fmt'=>'xl07', 'browse'=>$answers, 'nozip' => 0, '_options' => ['local' => 1]]);
  }
  /// Prepare l'envoi des invitations / des rappels
  function sendInvitations($ar=NULL){
    $r = $this->display($ar);
    if ($r['oinvitok']->raw == 1){
      $_REQUEST['reminder'] = 1;
    }
    return $r;
  }

  /**
   * Envoi des invitations / relances
   * -> en relance, on teste la présence d'une réponse
   */
  function procSendInvitations($ar=NULL){
    $p=new \Seolan\Core\Param($ar, ['reminder'=>0]);
    $oid=$p->get('oid');
    $ar['tplentry']=TZR_RETURN_DATA;
    $d=$this->display($ar);
    $reminder = $p->get('reminder');
    $sender=$p->get('sender');
    $sub=$p->get('subject');

    // ? formulaire clot, ouvert, etc ?

    $mess=$p->get('content');
    //$mess='<html><body>'.$mess.'</body></html>';
    $url=$GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid='.$oid.'&function=answer&template=Core.layout/raw.html&'.'insidefile=Module/Form.viewForm.html&tplentry=br';
    // relances (formulaire non ouvert)
    $hasResponseUid = [];
    $hasResponseEmail = [];
    $countalldest = 0;
    if ($reminder == 1){ 
      $repo = $this->_getRepository($d['oqmod']->raw,$d['oqtable']->raw)->XFormGetDataSource();
      $table = $repo->getTable();
      $hasResponseUid = getDB()->fetchCol('select distinct OWN from '.$table);
      $hasResponseEmail = getDB()->fetchCol('select distinct email from '.$table);
    }
    // Envoi aux personnes externes
    $emails=preg_split("/[;\n, ]/",$d['odestm']->raw);
    foreach($emails as $email){
      $email=trim($email);
      if(!preg_match('/^[^@]+@[^.]+.*$/',$email)) continue;
      // deja répondu / relance
      if ($reminder == 1 && in_array($email, $hasResponseEmail)){
	continue;
      }
      $tmess=str_replace('<url>','<a href="'.$url.'&email='.$email.'&key='.md5($oid.'-'.$email).'">',$mess);
      $tmess=str_replace('</url>','</a>',$tmess);
      $this->sendMail2User($sub,$tmess,$email,$sender);
      $countalldest++;
    }
    // Envoi aux personnes internes
    foreach($d['odest']->oidcollection as $uoid){
      $ors=getDB()->fetchRow('select email from USERS where KOID=? LIMIT 1', array($uoid));
      $email=$ors['email'];
      $email=trim($email);
      if(!preg_match('/^[^@]+@[^.]+.*$/',$email)) continue;
      // deja répondu / relance
      if ($reminder == 1 && in_array($uoid, $hasResponseUid)){
	continue;
      }
      // cas non ouvert et accès direct : ajout uid et key
      if ($d['oisopen']->raw != 1 && $d['odirectaccess']->raw == 1){
	$urlcplt = '&uid='.urlencode($uoid).'&key='.md5($oid.'-'.$uoid);
      } else {
	$urlcplt = '';
      }
      $tmess=str_replace('<url>','<a href="'.$url.$urlcplt.'">',$mess);
      $tmess=str_replace('</url>','</a>',$tmess);
      $this->sendMail2User($sub,$tmess,$email,$sender);
      $countalldest++;
    }
    // Notifie que l'envoi a été effetué
    if ($d['oinvitok']->raw != 1){
      getDB()->execute('update '.$this->table.' set UPD=UPD,invitok=1,dtsend=? where KOID=?', [date('Y-m-d'),$oid]);
    }
    if ($reminder == 1){
      $this->insertComment(['data'=>sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','remindersent'), $countalldest),
			    'oid'=>$oid,
			    '_options'=>['local'=>true]
			    ]);
    }
    setSessionVar('message',\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailingList_MailingList','sent'));
  }

  /// Retourne le type de champ simplifié du champ d'une source

  function getQuestionType($f, $questionsProperties){
    if($f->ftype=='\Seolan\Field\ShortText\ShortText') return 'shorttext';
    if($f->ftype=='\Seolan\Field\Real\Real') return 'integer';
    if($f->ftype=='\Seolan\Field\Date\Date') return 'date';
    if($f->ftype=='\Seolan\Field\File\File') return 'file';
    if($f->ftype=='\Seolan\Field\Text\Text'){
      if (in_array($f->field, $questionsProperties->separators)){
	return 'separator';
      } else {
	return 'longtext';
      }
    }
    if($f->ftype=='\Seolan\Field\Image\Image') return 'image';
    if($f->ftype=='\Seolan\Field\Boolean\Boolean') return 'boolean';
    if($f->ftype=='\Seolan\Field\Link\Link'){
      // type lien : choix multiples sauf si table arbitraire
      if ($f->target != $this->atable){
        return 'objectlink';
      }
      if($f->doublebox) return 'orderedlist';
      if($f->multivalued) return 'checkbox';
      if($f->checkbox) return 'radio';
      return 'select';
    }
  }

  /// Créé le champ clos, email sur un formulaire si besoin
  protected function _createCompulsoryFields($oid){
    $d=$this->display(array('oid'=>$oid,'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>array('qmod','qtable','reedit')));
    if(!($repo=$this->_getRepository($d['oqmod']->raw,$d['oqtable']->raw))) return;
    $x=$repo->XFormGetDataSource();
    if(!$x->desc['email']) $x->createField('email','Email','\Seolan\Field\ShortText\ShortText','255','','0','1','0','0','0','0');
    if(!$x->desc['close'] && $d['oreedit']==1) $x->createField('close','Clos','\Seolan\Field\Boolean\Boolean','0','','0','1','0','0','0','0');
  }

  /// Récupère la source du fomulaire (datasource ou module)
  protected function _getRepository($m,$t=NULL){
    // Si $t est vide et que $m n'est pas un module, aolrs $m contient l'oid du formulaire
    if(!$t && !is_numeric($m)){
      $row=getDB()->fetchRow('select qmod,qtable from '.$this->table.' where KOID=? LIMIT 1',array($m));
      $m=$row['qmod'];
      $t=$row['qtable'];
    }
    if($m) return \Seolan\Core\Module\Module::objectFactory($m);
    elseif($t) return \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$t);
    else return false;
  }
  /// Enrichi les champs asvec les données spécifiques
  function getQuestionsProperties($fieldsObjects, $questionsProperties){
    $qprops = [];
    foreach($fieldsObjects as $i=>$fo){
      $qprops[$i]= [];
      $fd = $fo->fielddef;
      if (!$fd->sys){
	$qprops[$i]['type'] = $this->getQuestionType($fd, $questionsProperties); 
      }
    }
    return $qprops;
  }
  /// Passe certains champs du questionnaire en champ systeme
  function setSysFields($x){
    if(!empty($x->desc['email'])) $x->desc['email']->sys=true;
    if(!empty($x->desc['close'])) $x->desc['close']->sys=true;
  }

  /**
   * Surcharge des droits : en fonction du type de questionnaire
   */
  function secure($oid,string $function,$user=NULL,$lang=TZR_DEFAULT_LANG){
    // Pour repondre à un formulaire, on vérifie avant que la personne est dans la liste des destinataires
    if($function=='answer' || $function=='procAnswer'){
      $nb=getDB()->count('select count(*) from '.$this->table.' where KOID=? and LANG=? and isopen=1', array($oid, $lang));
      if($nb) return true;
      if($user && $user->_curoid!=TZR_USERID_NOBODY){
	$nb=getDB()->count('select count(*) from '.$this->table.' where KOID=? and LANG=? and dest like ?',
                           array($oid, $lang, '%'.$user->_curoid.'%'));
      }elseif(!$user && !\Seolan\Core\User::isNobody()){
	$nb=getDB()->count('select count(*) from '.$this->table.' where KOID=? and LANG=? and dest like ?',
                           array($oid, $lang, '%'.\Seolan\Core\User::get_current_user_uid().'%'));
      }else{
	if (isset($_REQUEST['key']) && isset($_REQUEST['email'])){
	  // Pour les personnes externes, on verifie la clé d'acces
	  if($_REQUEST['key']!=md5($oid.'-'.$_REQUEST['email'])) return false;
	  $nb=getDB()->count('select count(*) from '.$this->table.' where KOID=? and LANG=? and destm like ?',
			     array($oid, $lang, '%'.$_REQUEST['email'].'%'));
	} elseif(isset($_REQUEST['key']) && isset($_REQUEST['uid'])){
	  // Pour les distinataires internes avec autorisation sans connexion
	  if($_REQUEST['key']!=md5($oid.'-'.$_REQUEST['uid'])) return false;
	  $nb=getDB()->count('select count(*)  from '.$this->table.' where koid=? and dest like ?', [$oid,'%'.$_REQUEST['uid'].'%']);
	}
      }
      if(!$nb) return false;
      else return true;
    }
    return parent::secure($oid,$function,$user,$lang);
  }

  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  function getUIFunctionList() {
    return array('answer'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','uianswer','text'));
  }
  function UIParam_answer(){
    $ret['__oid']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__oid','FTYPE'=>'\Seolan\Field\Link\Link','COMPULSORY'=>1,'TARGET'=>$this->table,
							 'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','form','text')));
    $ret['__nextalias']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'__nextalias','FTYPE'=>'\Seolan\Field\ShortText\ShortText','COMPULSORY'=>1,
							       'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','uiinsert_okalias','text')));
    $ret['__nextalias']->listbox=false;
    return $ret;
  }

  protected function _lasttimestamp() {
    $upd=getDB()->fetchOne('select ifnull(MAX(lastanswer),0) from '.$this->table);
    $upd2=parent::_lasttimestamp();
    return max($upd,$upd2);
  }

  // Abonnement
  protected function _whatsNew($ts,$user, $group=NULL, $specs=NULL,$timestamp=NULL) {
    $txt=parent::_whatsNew($ts,$user,$group,$specs,$timestamp);
    $koid=$specs['oid'];
    $details=$specs['details'];
    $query='select KOID from '.$this->table.' where lastanswer >= "'.$ts. '" and lastanswer<"'.$timestamp.'"';
    if($oid) $query.=' and KOID="'.$oid.'"';
    $oids = $this->xset->browseOids(array('select'=>$query, 'pagesize'=>'99', 'tplentry'=>TZR_RETURN_DATA, '_filter'=>$this->getFilter()));
    foreach($oids as $oid){
      $d1=$this->xset->display(array('tplentry'=>TZR_RETURN_DATA,'tlink'=>true,'oid'=>$oid,'_options'=>array('error'=>'return')));
      $url=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true,false).'&moid='.$this->_moid.'&function=display&oid='.$oid.'&tplentry=br&template=Module/Table.view.html&_direct=1';
      $repo=$this->_getRepository($d1['oqmod']->raw,$d1['oqtable']->raw);
      $c=getDB()->fetchOne('select count(distinct KOID) from '.$repo->XFormGetDataSource()->getTable().' where UPD>=? and UPD<?',array($ts,$timestamp));
      $txt.='<li><a href="'.$url.'">'.$d1['tlink'].'</a> ('.sprintf(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','newanswers','text'),$c).')</a></li>';
    }
    return $txt;
  }

  function browseActionDelHtmlAttributes(&$url,&$text,&$icon, $linecontext=null){
    return 'class="cv8-delaction" x-confirm="if(confirm(\''.\Seolan\Core\Labels::getSysLabel('Seolan_Module_Form_Form','delrepo','text').'\')) jQuery(self).attr(\'href\',jQuery(self).attr(\'href\')+\'&delrepo=1\'); var ret=true;"';
  }
  /**
   * table des formulaires, des choix de questions à chois multiples
   * et des formulaires en base
   */
  public function usedTables() {
    $qtables =  getDb()->select('select distinct qtable from '.$this->table.' where ifnull(qtable,"")!=""')->fetchAll(\PDO::FETCH_COLUMN);
    return array_merge([$this->table, $this->atable], $qtables);
  }
}
?>
