<?php
namespace Seolan\Module\MailingList;
class MailingList extends \Seolan\Module\Table\Table implements \Seolan\Module\MailLogs\ConnectionInterface, \Seolan\Module\CRM\CRMSourceInterface {
  static protected $iconcssclass='csico-contact-mail';
  public $key = 'F0001';
  public $from = 'info@xsalto.com';
  public $fromname = 'XSALTO';
  public $returnaddress = TZR_XMODMAILLIST_RETURN_ADDRESS;
  public $date = 'F0002';
  public $active = '';
  public $smsfield = NULL;
  public $faxfield = NULL;
  public $consentfield = NULL;
  public $newslettermodule = '';
  public $defaultfromisuser = false;
  public $newsletterurl = '';
  public $subject = 'Mailing List';
  public $prefix = '';
  public $iptask='';
  public $urlcounter=1;
  public $inlinestyles=false;
  public $unpublishOnBounce=true;
  // !! chemin csx pour le 'core' qui sert avec un fond de page, chemin file pour le local
  protected $letterConfirmCoreTemplate='Module/MailingList.letter-confirm-core.html'; // chemin 'csx'
  protected $letterConfirmTemplate='Module/MailingList/letter-confirm.html'; // chemin file
  protected $letterConfirmOkCoreTemplate='Module/MailingList.letter-confirm-ok-core.html';
  protected $letterConfirmOkTemplate='Module/MailingList/letter-confirm-ok.html';
  protected $letterUnsubscribeCoreTemplate='Module/MailingList.letter-unsubscribe-core.html';
  protected $letterUnsubscribeTemplate='Module/MailingList/letter-unsubscribe.html';
  private $smsAccounts = NULL;
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_MailingList_MailingList');
  }

  // initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','table'), 'table',
			    'table', array('validate'=>true),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','key'), 'key',
			    'field', array('table'=>'table','compulsory'=>false,'type'=>array('\Seolan\Field\Url\Url','\Seolan\Field\ShortText\ShortText')),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','datefield'), 'date',
			    'field', array('table'=>'table','compulsory'=>false,),'F0002',$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','faxfield'), 'faxfield',
			    'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\ShortText\ShortText'),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','smsfield'), 'smsfield',
			    'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\ShortText\ShortText'),NULL,$alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','consentfield'), 'consentfield',
          'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\Boolean\Boolean'),NULL,$alabel);

    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','active'), 'active',
			    'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\Boolean\Boolean'),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','defaultfromisuser'), 'defaultfromisuser', 'boolean',NULL,false,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','from').' (email)', 'from', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','from'), 'fromname', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','returnaddress'), 'returnaddress', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','unpublishonbounce'), 'unpublishOnBounce', 'boolean',NULL,true,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','prefix'), 'prefix', 'text',NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','newslettermodule'), 'newslettermodule', 'module',array('toid'=>XMODINFOTREE_TOID),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','newsletterurl'), 'newsletterurl', 'text',NULL,NULL,$alabel);
    $this->_options->setComment('moid, tplentry,function, charset seront par défaut ajoutés avec les valeurs respectives : "moid du module newletter", "it", "viewpage", "UTF-8"', 'newsletterurl');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','inlinestyles'), 'inlinestyles', 'boolean',NULL,false,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','fieldmobileapptype'), 'fieldmobiletype',
                            'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\ShortText\ShortText'),NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','fieldmobileappregistration'), 'fieldmobileappregistration',
                            'field', array('table'=>'table','compulsory'=>false,'type'=>'\Seolan\Field\ShortText\ShortText'),NULL,$alabel);
    $this->_options->setOpt('Gérer des accusés de réception', 'ar', 'boolean',NULL,false,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','extract_emails'), 'extract_emails', 'boolean',NULL,false,$alabel);
    $this->_options->setOpt('Lieu execution de la tache', 'iptask', 'text',NULL,'',$alabel);
    $this->_options->setOpt('Lettre de demande de confirmation', 'askconfedletter', 'letter',
			    array('moid'=>$this->_moid),'','Lettres');
    $this->_options->setOpt('Lettre de confirmation', 'confedletter', 'letter',
			    array('moid'=>$this->_moid),'','Lettres');
    $this->_options->setOpt('Confirmation de suppression ', 'deledletter', 'letter',
			    array('moid'=>$this->_moid),'','Lettres');
  }
  /// Rend la liste des fonctions utilisables dans le gestionnaire de rubriques en mode fonction (tableau de paires fonction=>label)
  function getUIFunctionList() {
    $list = parent::getUIFunctionList();
    $list['insert'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','uisubscribe');
    $list['genSubscribe'] = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','uisubscribe');
    return $list;
  }

  // verification qu'un module est bien installé. Si le parametre
  // repair est a oui, on fait les reparations si possible.
  // verification: existence du scheduler
  //
  public function chk(&$message=NULL) {
    $mod=\Seolan\Core\Module\Module::getMoid(XMODSCHEDULER_TOID);
    if(empty($mod)) {
      $m=\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Messages','xmodscheduler_missing');
      \Seolan\Core\Shell::toScreen2('','message',$m);
    }
    return parent::chk($message);
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['subscribe']=array('none','ro','rw','rwv','admin');
    $g['confirm']=array('none','ro','rw','rwv','admin');
    $g['genSubscribe']=array('none','ro','rw','rwv','admin');
    $g['unsubscribe']=array('none','ro','rw','rwv','admin');
    $g['genSend']=array('rw','rwv','admin');
    $g['genSendPre']=array('rw','rwv','admin');
    $g['procSend']=array('rw','rwv','admin');
    $g['procSendBatch']=array('rw','rwv','admin');
    $g['procSendBatchSMS']=array('rw','rwv','admin');
    $g['procSendBatchPush']=array('rw','rwv','admin');
    $g['genVerif']=array('rw','rwv','admin');
    $g['procVerif']=array('rw','rwv','admin');
    $g['testCss2inline']=array('admin');
    $g['procCss2inline']=array('admin');
    $g['exportBounce']=array('ro','rw','rwv','admin');
    $g['unBounce']=['rwv','admin'];
    $g['deleteBounceAddresses']=array('rwv','admin');
    $g['blankBounceAddresses']=array('rwv','admin');
    $g['show']=array('none','ro','rw','rwv','admin');
    $g['registerApplication']=array('none');
    $g['checkEmail']=['rw','rwv','admin'];
    $g['unsubscribeRequest']=['none'];
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  // liste des catégories reconnues dans cette classe
  function secList() {
    return array('none','list','ro','rw','rwv','admin');
  }

  function UIParam_genSubscribe() {
    $ret = parent::UIParam_insert();
    return $ret;
  }

  /**
   * Liste des fonctions utilisables sur la selection du module
   * -> ajout fonction "Envoyer"
   */
  function userSelectionActions(){
    $actions=parent::userSelectionActions();
    if($this->secure('','procSend')){
      $sendtxt=\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sendmessage');
      $actions['sgensend']='<a href="#" onclick="TZR.SELECTION.applyToInContentDiv('.$this->_moid.',\'genSend\',false,{template:\'Module/MailingList.xmodmaillist.html\', tplentry:\'br\'}); return false;">'.$sendtxt.'</a>';
    }
    return $actions;
  }

  /// Retourne les comptes possibles pour l'envoi de SMS
  public function smsAccounts(){
    if ($this->smsAccounts == NULL){
      $this->smsAccounts = \Seolan\Library\SMS::getSmsAccounts($this->_moid);
    }
    return $this->smsAccounts;
  }
  /// la configuration permet l'envoi de sms
  function smsActive(){
    return (!empty($this->smsfield) && $this->smsAccounts() != NULL);
  }

  /// Cette fonction est appliquee pour afficher l'ensemble des methodes de ce module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $f=\Seolan\Core\Shell::_function();
    $moid=$this->_moid;
    $uniqid=\Seolan\Core\Shell::uniqid();
    if($this->secure('','procSend')){
      if(($f =='browse') || ($f == 'procQuery')) {
	$o1=new \Seolan\Core\Module\Action($this,'genSend',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','send'),
					   'javascript:TZR.Table.sendSelected("'.$uniqid.'", "'.$this->_moid.'");');
	$o1->menuable=true;
	$o1->group='actions';
	$my['ggenSend']=$o1;

	if($this->smsActive()) {
	  $o1=new \Seolan\Core\Module\Action($this,'genSendSMS',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sendsms'),
					     'javascript:TZR.Table.sendSelectedSMS("'.$uniqid.'", "'.$this->_moid.'");', 'menu');
	  $o1->menuable=true;
	  $o1->setToolbar('Seolan_Core_General','send');
	  $my['ggenSendSMS']=$o1;
	}
        if ($this->pushActive()){
          $o1=new \Seolan\Core\Module\Action($this,'genSendPush',\Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\MailingList\MailingList','sendpush'),
                                'javascript:v'.$uniqid.'.sendSelectedPush();');
          $o1->menuable=true;
          $my['ggenSendSMS']=$o1;
        }
      } else {
	$o1=new \Seolan\Core\Module\Action($this,'genSendPre',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','send'),
			      '&moid='.$this->_moid.'&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br');
	$o1->menuable=true;
	$o1->setToolbar('Seolan_Core_General','send');
	$my['genSendPre']=$o1;

        if ($this->smsActive()){
	  $o1=new \Seolan\Core\Module\Action($this,'genSendPre',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sendsms'),
					     '&moid='.$this->_moid.'&sms=1&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br');
	  $o1->menuable=true;
	  $o1->setToolbar('Seolan_Core_General','send');
	  $my['genSendPreSMS']=$o1;
	}
        if ($this->pushActive()){
          $o1=new \Seolan\Core\Module\Action($this,'genSendPush',\Seolan\Core\Labels::getTextSysLabel('\Seolan\Module\MailingList\MailingList','sendpush'),
                                '&moid='.$this->_moid.'&push=1&_function=genSendPre&template=Module/MailingList.xmodmaillistpre.html&tplentry=br');
          $o1->menuable=true;
          $my['ggenSendSMS']=$o1;
        }
      }
    }
    if($this->secure('','procCss2inline')){
      $o1=new \Seolan\Core\Module\Action($this,'genCss2inline',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','testtemplate'),
			    '&moid='.$this->_moid.'&_function=testCss2inline&template=Module/MailingList.testcss2inline.html&tplentry=br');
	$o1->menuable=true;
	$o1->group='more';
	$my['genCss2inline']=$o1;
    }

    if($this->secure('','exportBounce')){
      $o1=new \Seolan\Core\Module\Action($this,'exportBounce',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','exportbounce'),
			    '&moid='.$this->_moid.'&template=Core.empty.txt&_function=exportBounce','edit');
      $o1->menuable=true;
      $my['exportBounce']=$o1;
    }

    if($this->secure('','deleteBounceAddresses')){
      $o1=new \Seolan\Core\Module\Action($this,'deleteBounceAddresses',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','deletebounceaddresses'),
			    '&moid='.$this->_moid.'&_function=deleteBounceAddresses&template=Core.message.html&tplentry=br','edit');
      $o1->menuable=true;
      $my['deleteBounceAddresses']=$o1;
    }

    if($this->secure('','blankBounceAddresses')){
      $o1=new \Seolan\Core\Module\Action($this,'blankBounceAddresses',\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailingList_MailingList','blankbounceaddresses'),
			    '&moid='.$this->_moid.'&_function=blankBounceAddresses&template=Core.message.html&tplentry=br','edit');
      $o1->menuable=true;
      $my['blankBounceAddresses']=$o1;
    }

    // menage des fonctions non accessibles
    if (!in_array($f, array('browse', 'procQuery', 'display', 'edit', 'insert'))){
      unset($my['sendacopy']);
      unset($my['sexport']);
      unset($my['print']);
    }
    // Lien pour la vérification d'une base (voir où le placer...)
    /*
    $o1=new \Seolan\Core\Module\Action($this,'genVerif','Verifier la base',
                          'class='.$myclass.'&amp;moid='.$this->_moid.
                          '&amp;_function=genVerif&amp;template=Module/MailingList.genVerif.html&amp;tplentry=br');
    $o1->menuable=$o1->homepageable=true;
    $o1->group='edit';
    $my['genVerif']=$o1;
    */

    if($this->secure('','unBounce')){
      $o1=new \Seolan\Core\Module\Action($this,'unbounce',
					 \Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs','checkmails','text'),
					 '&moid='.$this->_moid.'&tplentry=ml&&function=prepareUnBounce&template=Module/MailLogs.unbounce.html');  
    }
    $o1->menuable=true;
    $o1->group='actions';
    $my['unbounce']=$o1;

  }

  /// Exporte les bounce au format csv
  function exportBounce($ar=NULL){
    $ss=new \PHPExcel();

    $ss->setActiveSheetIndex(0);
    $ws=$ss->getActiveSheet();
    $ws->setTitle("emailsInvalides");
    $row = 1;
    $ws->setCellValueByColumnAndRow(0,$row,'email');
    $ws->setCellValueByColumnAndRow(1,$row,'nbdetect');
    $row=2;
    $rs=getDB()->select("select distinct B.email as email, B.nbdetect as nbdetect from _MBOUNCE B join {$this->table} T on B.email=T.".$this->key);

    while($ors=$rs->fetch()){
      $ws->setCellValueByColumnAndRow(0,$row,'"'.$ors['email'].'"');
      $ws->setCellValueByColumnAndRow(1,$row,$ors['nbdetect']);
      $row++;
    }
    $tmpFile = TZR_TMP_DIR . uniqid();
    $fileName = rewritetoAscii($this->getLabel()).'-'.date('Y-m-d').'-NPAI.xlsx';
    $objWriter=new \PHPExcel_Writer_Excel2007($ss);
    $objWriter->save($tmpFile);
    \Seolan\Core\Shell::setNext($this->getMainAction().'&fileoriginalname='.$fileName.'&filemime=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet&skip=1&filename='.urlencode($tmpFile));
  }

  /// suppression de tous les enregistrements contenant une adresse blacklistée
  function deleteBounceAddresses($ar=NULL) {
    $oids=getDB()->fetchCol('select distinct T.KOID from _MBOUNCE B,'.$this->table.' T where B.email like T.'.$this->key.' AND B.nbdetect>2');
    $this->del(array('oid'=>$oids,'_local'=>true));
    $message=count($oids).' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'deleted');
    $_REQUEST['message']=$message;
  }

  /// mise à blank du champ contenant l'adresse blacklistée
  function blankBounceAddresses($ar=NULL){
    $rs=getDB()->select('select distinct T.KOID,B.email from _MBOUNCE B,'.$this->table.' T where B.email like T.'.$this->key.' AND B.nbdetect>2');

    $message='';
    $cnt=0;
    while($ors=$rs->fetch()) {
      $this->procEdit(array('oid'=>$ors['KOID'], $this->key=>'', '_local'=>true));
      $message.=$ors['email'].', ';
      $cnt++;
    }
    $message.=$cnt.' '.\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList', 'addressesblanked');
    $_REQUEST['message']=$message;
  }

  function testCss2inline($ar){
  }
  function procCss2inline($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $urlhtml = $p->get('urlhtml');
    $urlcss = $p->get('urlcss');

    \Seolan\Core\System::loadVendor('css2inline/css2inline.php');
    if($urlhtml){
      $content = file_get_contents($urlhtml);
      if($urlcss)
	$css = file_get_contents($urlcss);
      $cssToInlineStyles = new \CSSToInlineStyles($content,$css);
      $cssToInlineStyles->setUseInlineStylesBlock(true);
      $content = $cssToInlineStyles->convert();
      header('Content-Type: text/html');
      die($content);
    }
  }
  function genVerif($ar=NULL){
  }
  function procVerif($ar=NULL){
    if($this->xset->fieldExists('confed')) {
      $tot=0;
      $key=$this->key;
      getDB()->execute('UPDATE '.$this->table.' SET confed=2');
      $rs=getDB()->select('SELECT KOID FROM '.$this->table);
      while($rs && ($ors=$rs->fetch())){
        // envoi de la demande de confirmation
        $tpldata['br']=$this->display(array('oid'=>$ors['KOID'],'tplentry'=>TZR_RETURN_DATA));
        $tpldata['br']['moid']=$this->_moid;
        $tpldata['br']['email']=$tpldata['br']['o'.$this->key]->text;
        $r3=array();
        $xt = new \Seolan\Core\Template('Module/MailingList.letter-confirm.html');
        $content=$xt->parse($tpldata,$r3,NULL);
        $this->sendMail2User(TZR_SERVER_NAME, $content, $tpldata['br']['o'.$this->key]->raw, $this->sender);
        $tot++;
      }
      $_REQUEST['message']=$tot.' demande(s) de confirmation envoyée(s).';
    }else{
      $_REQUEST['message']='Cette base n\'est pas configurée pour accepter les demande de confirmation.';
    }
  }

  function genSend($ar) {
    $p=new \Seolan\Core\Param($ar, array('_skiprecipientselection'=>0));
    $oids=$p->get('_selected');
    $tplentry=$p->get('tplentry');

    if (1 != $p->get('_skiprecipientselection')){
      $ar['tplentry']=TZR_RETURN_DATA;
      $ar['selectedfields']='';
      $ar['clearrequest']=0;
      $ar['pagesize']=101;

      // appel depuis une liste ou depuis la selection (fromfunction = browseSelection)
      if(!empty($oids)){
	$ar['_select']=$this->table.'.KOID="'.implode('" OR '.$this->table.'.KOID="',array_keys($oids)).'"';
	$ar['clearrequest'] = 1; // force la mémorisation de la nouvelle requette
      }

      $r=$this->procQuery($ar);

      //On fait le menage sur les utilisateur qui ont installe l'application uniquement
      if ((int)$p->get('push') === 1) {
        foreach ($r['lines_oid'] as $i => $user_oid){
          if (!$this->hasRegisterMobileApplication($user_oid)) {
            unset($r['lines_oid'][$i]);

            foreach ($r['selectedfields'] as $fieldName) {
              unset($r['lines_o'.$fieldName][$i]);
            }
          }
        }

        //Apres le menage on re-numerote les cles des tableaux
        $r['lines_oid'] = array_merge($r['lines_oid']);

        foreach ($r['selectedfields'] as $fieldName) {
          $r['lines_o'.$fieldName] = array_merge($r['lines_o'.$fieldName]);
        }

        //On met a jour le nombre de ligne
        $r['last'] = count($r['lines_oid']);
      }

    } else {
      $r['_skiprecipientselection'] = 1;
    }
    // ajout du compteur de lignes invalides
    if (!$p->is_set('sendsms') && !$p->is_set('sendpush')){
      $okf = [];
      if($this->xset->fieldExists('bounce')) $okf[] = ['bounce','2'];
      if($this->unpublishOnBounce && $this->xset->fieldExists('PUBLISH')) $okf[] = ['PUBLISH','1'];
      if($this->active && $this->xset->fieldExists($this->active)) $okf[] = [$this->active,'1'];
      
      $_storedquery=$this->_getSession('query');
      
      $arok = array_merge_recursive($_storedquery,$ar);
      
      if (!is_array($arok['_FIELDS']))
	$arok['_FIELDS'] = [];
      foreach($okf as $qf){
	$arok['_FIELDS'][$qf[0]] = $qf[0];
	$arok[$qf[0]] =  $qf[1];
      }
      
      $arok['getselectonly']=true;
      $qok=$this->xset->procQuery($arok);
      $r['_countValid'] = getDB()->count($qok, [], true);
    }

    $r['_noduplicate'] = 1;
    $r['message']='';

    $dateTimeExe = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>'dateTimeExe',
								   'FTYPE'=>'\Seolan\Field\DateTime\DateTime',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>0,
								   'TRANSLATABLE'=>false,
								   'LABEL'=>'dateTimeExe']);
    $dateTimeExe->edit_format = 'H:M';
    $r['_dateTimeExe'] = $dateTimeExe->edit(date('Y-m-d H:i:s'), ($foo=['datemax'=>date('Y-m-d', strtotime(date('Y-m-d').'+1 year')),
									'datemin'=>date('Y-m-d')]));


    $xt=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    $query=$xt->select_query(array('cond'=>array('modid'=>array('=',$this->_moid),'gtype'=>array('=','mail')),'order'=>'title'));
    $xt->browse(array('select'=>$query,'pagesize'=>100,'selectedfields'=>'all','tplentry'=>$tplentry.'tpl'));
    \Seolan\Core\Shell::toScreen1Merge($tplentry,$r);
    $br=\Seolan\Core\Shell::from_screen('br');
    if($this->defaultfromisuser) {
      $user=\Seolan\Core\User::get_user();
      // si on est rattaché à un projet, on change le préfixe du sujet
      if($prefix=$this->getPrefixFromProject()) {
	$br['mod']['prefix']='['.$prefix.']';
      }
      $br['mod']['from']=$user->email();
      $br['mod']['fromname']=$user->fullname();
      \Seolan\Core\Shell::toScreen1('br',$br);
    }
    if(!$br['mod']['from']) {
      $ors=getDB()->fetchRow('select * from USERS where KOID=?',array(\Seolan\Core\User::get_current_user_uid()));
      if($ors) {
	$br['mod']['from']=$ors['email'];
	\Seolan\Core\Shell::toScreen1('br',$br);
      }
    }
    // cas sms (template = Module/MailingList.sendsms.html)
    if(\Seolan\Core\Shell::admini_mode() && $this->interactive){
      \Seolan\Core\Shell::toScreen2('smsAccounts', 'list', ($smsAccounts = $this->smsAccounts()));
    }
    // Texte enrichi
    $fck=\Seolan\Field\RichText\RichText::getCKEditor('','richmessage',NULL,600,320,'Complete');
    \Seolan\Core\Shell::toScreen2('messagebox','rich',$fck);
  }

  function genSendPre($ar){
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $r=$this->xset->query(array('tplentry'=>TZR_RETURN_DATA,'fmoid'=>$this->_moid));
    if(\Seolan\Core\Shell::admini_mode() && $this->interactive && \Seolan\Core\DataSource\DataSource::sourceExists('QUERIES')) {
      $r1=$this->storedQueries();
      \Seolan\Core\Shell::toScreen1('queries',$r1);
    }
    \Seolan\Core\Shell::toScreen1Merge($tplentry, $r);
  }

  function genSubscribe($ar) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
  }

  function presubscribe() {
    $this->input();
  }
  /**
   * @desc : enregistrement d'une inscription avec demande éventuelle de confirmation
   */
  function subscribe($ar=NULL) {
    if($this->procEditCtrl($ar)) {
      $p = new \Seolan\Core\Param($ar,array());
      $responsemode=$p->get('responsemode');
      $tplentry=$p->get('tplentry');
      $key = $this->key;
      $keyval = strtolower(trim($p->get($key)));
      $ok=false;
      $query = $this->xset->select_query(array('cond'=>array( $key => array('=',$keyval))));
      $row = getDB()->fetchRow($query);
      if (!$row || ($this->active && $row[$this->active] == 2)) {
	$r1=$this->subscribeProcInput($ar,$keyval);
	if($this->xset->fieldExists('confed')){
	  $this->sendMail2Subscriber($r1['oid'], 
				     \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','registration'), 
				     $this->letterConfirmCoreTemplate,
				     $this->letterConfirmTemplate);
	}
	$message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','subscribed');
	$ok=true;
      } else {
	$message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','subscribe_already');
      }
      if($responsemode=='json') die(json_encode(array('status'=>$ok,'message'=>$message)));
      \Seolan\Core\Shell::toScreen2($tplentry,'message',$message);
    }
  }
  /**
   * @desc retourne une page ou un gabarit destiné aux fonds de mails
   */
  protected function getMailLayout(){
    if (empty($this->newslettermodule))
      return null;
    $itmod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->newslettermodule,'interactive'=>false]);
    if ($itmod == null)
      return null;
    $oidit = $itmod->getOidFromAlias($this->mailLayoutAlias);
    if ($oidit == null)
      return null;
    return ['page'=>[$this->mailLayoutAlias, $this->mailLayoutTemplate, $this->newslettermodule]];
  }
  /**
   * @desc envoi d'un mail à un internaute enregistré
   * @param $coreTemplate : le gabarit body pour le corps du message
   * @param $localTemplate : le gabarit message complet local qui prime si il existe
   */
  protected function sendMail2Subscriber($oid, $subject, $coreTemplate, $localTemplate){
    \Seolan\Core\Logs::notice(__METHOD__,"$oid, $subject, $coreTemplate, $localTemplate");
    $mailLayout = null;
    $template = false;
    if (defined('TZR_ALLOW_USER_TEMPLATES')){
      $template = file_exists($GLOBALS['USER_TEMPLATES_DIR'].$localTemplate);
    }
    if (!$template)
      $mailLayout = $this->getMailLayout();
    if ($template || $mailLayout){
      // rdisplay + _published == false pour les cas où la table a le champ PUBLISH
      $tpldata['br']=$this->xset->rDisplay($oid, null, false, null, null, ['_published'=>false]);
      $tpldata['br']['moid']=$this->_moid;
      $tpldata['br']['email']=$tpldata['br']['o'.$this->key]->text;
      $tpldata['br']['mlangs'] = !\Seolan\Core\Shell::getMonoLang();
      $r3=array();
      if ($mailLayout != null){ // il existe un Module/InfoTree avec une page mail
	$xt = new \Seolan\Core\Template($coreTemplate);
	$content=$xt->parse($tpldata,$r3,NULL);
	$mailOptions = array_merge(['sign'=>1], $mailLayout);
      } else { // on a un gabarit (=8.2)
	$xt = new \Seolan\Core\Template($localTemplate);
	$content=$xt->parse($tpldata,$r3,NULL);
	$mailOptions = ['sign'=>0];
      }
      $this->sendMail2User($subject,
		      $content,
		      $tpldata['br']['o'.$this->key]->raw,
		      [$this->from,$this->fromname],
		       true, // archive
		       null, null, null, null, // attachments et mime
		      $mailOptions
		      );
    } else {
      \Seolan\Core\Logs::critical(__METHOD__, "no layout, no template");
      echo('no layout');
    }
    return ($template || $mailLayout);
  }
  /**
   * @desc Procède a l'insertion du nouvel abonné
   * @param array $ar
   * @return array
   */
  protected function subscribeProcInput($ar,$keyval,$ar1=array()){
    if (!empty($ar1))
      $ar = $ar1;
    $ar[$this->date]=date('Y-m-d H:i:s');
    if($this->active) {
      $ar[$this->active] = 1;
      $ar['_unique'] = [$this->key];
      $ar['_updateifexists'] = 1;
    }
    if($this->xset->fieldExists('confed')) {
      $ar['confed']='0000-00-00 00:00:00';
    }
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar[$this->key]=$keyval;
    $maillogs = \Seolan\Core\Module\Module::singletonFactory(XMODMAILLOGS_TOID);
    $maillogs->removeFromBounce($keyval);
    return $this->xset->procInput($ar);
  }

  /**
   * @desc : enregistrement d'une confirmation d'inscription
   */
  function confirm($ar=NULL) {
    if(!$this->xset->fieldExists('confed'))
      return;
    $p = new \Seolan\Core\Param($ar,array());
    $tplentry = $p->get('typlentry');
    $email=$p->get('email');
    $oid=$p->get('oid');
    $query = 'UPDATE '.$this->table.' SET confed=NOW()';
    if ($this->xset->fieldExists('PUBLISH'))
      $query .= ", PUBLISH=1";
    if ($this->active) {
      $query .= ", $this->active=1";
    }
    $query .= ' WHERE '.$this->key.' = ? and KOID=?';
    getDB()->execute($query, [$email, $oid]);
    $this->sendMail2Subscriber($oid, 
			       \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','registration'),
			       $this->letterConfirmOkCoreTemplate,
			       $this->letterConfirmOkTemplate);
    
    return \Seolan\Core\Shell::toScreen2($tplentry,'message',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','subscribed'));
    
  }


  /// demande de desincription de la liste des destinataires
  function unsubscribeRequest($ar=NULL) {
    $p = new \Seolan\Core\Param($ar,array());
    $responsemode=$p->get('responsemode');
    $tplentry=$p->get('tplentry');
    $key = $this->key;
    $keyval = strtolower(trim($p->get($key)));
    $ok=false;
    $query = $this->xset->select_query(array('cond'=>array( $key => array('=',$keyval))));
    $row = getDB()->fetchRow($query);
    if ($row['KOID']) {
        $this->sendMail2Subscriber($row['KOID'],
          \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','unregistration'),
          $this->letterUnsubscribeCoreTemplate,
          $this->letterUnsubscribeTemplate);
    }
  }

  /// desincription de la liste des destinataires : invalidation ou suppressoin en fonction du paramétrage du module
  function unsubscribe($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $responsemode=$p->get('responsemode');
    $tplentry=$p->get('tplentry');
    $oid=$p->get("oid");
    $ok=false;
    if(!empty($oid)) {
      $query = 'select * from '.$this->table.' where KOID="'.$oid.'"';
    } else {
      $key = $this->key;
      $keyval = trim($p->get($key));
      $query = $this->xset->select_query(array('cond'=>array($key=>array('=',$keyval))));
      $query=str_replace(' '.$this->table.'.'.$key.' ',' TRIM('.$this->table.'.'.$key.') ',$query);
    }
    // on invalide ou un supprime l'entree
    if($ors=getDB()->fetchRow($query)) {
      if(empty($this->active) || !$this->xset->fieldExists($this->active)){
	$this->xset->delObject($ors['KOID']);
      } else {
	getDB()->execute('update '.$this->table.' set '.$this->active.'= ? where KOID= ?',array(2,$ors['KOID']));
      }
      $message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','unsubscribed');
      $ok=true;
    }
    else $message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','unsubscribe_failed');
    if($responsemode=='json') die(json_encode(array('status'=>$ok,'message'=>$message)));
    setSessionVar('message',$message);
    \Seolan\Core\Shell::toScreen2($tplentry,'message',$message);
  }

  /// envoi d'un message : création d'un job dans la file d'attente d'envoi
  function procSend($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('tplentry'=>$this->table,'from'=>$this->from,'fromname'=>$this->fromname,'cond1'=>'', 'noduplicate'=>0));
    $noduplicate = $p->get('noduplicate');
    $tplentry=$p->get('tplentry');
    $subject=stripslashes($p->get('subject'));
    $messagemode=$p->get('messagemode');
    if($messagemode=='text') $message=stripslashes($p->get('message'));
    else $message=stripslashes($p->get('richmessage'));
    unset($_REQUEST['message']); // sans quoi on affiche le body dans la zone de message du _next ?
    $from=$p->get('from');
    $priority=$p->get('priority');
    $conds=array();
    $conds['tplentry']=TZR_RETURN_DATA;
    $oldint=$this->interactive;
    $this->interactive=true;
    $q=$this->procQuery($conds);
    $this->interactive=$oldint;

    $q=$q['select'];
    $oids=$p->get('oid'); // les oids enleves
    $notsend='';
    foreach($oids as $oid){
      if(!empty($oid)) $notsend.=' AND KOID!="'.$oid.'"';
    }
    if($notsend != ''){
      if(!preg_match('/(order[ ]+by)/i',$q)) $q.=$notsend;
      else $q=preg_replace('/(order[ ]+by)/i',$notsend.' order by ',$q);
    }
    $newspage=$p->get('newspage');
    $newsletterlang=$p->get('newsletterlang');
    if(!empty($newspage)) {
      $ors=getDB()->fetchRow('select alias,title from '.\Seolan\Core\Kernel::getTable($newspage).' where KOID="'.$newspage.'" '.
			     'and LANG="'.$newsletterlang.'" limit 1');
    }
    $o=array();
    if($p->get('sendsms')){
      $o['sendsms']=$p->get('sendsms');
      $o['function']='procSendBatchSMS';
      $o['smsAccountId'] = $p->get('smsAccountId');
      if ($p->is_set('reportAddress')){
	$o['reportAddress'] = $p->get('reportAddress');
      }
      if ($p->is_set('otherrecipients')){
	$o['otherrecipients'] = trim($p->get('otherrecipients'));
      }
      if (!$p->is_set('_skiprecipientselection')){
	$o['query']=$q;
      }
    } elseif((int)$p->get('sendpush') === 1){
      $o['data']=$p->get('data');
      $o['sendpush']=$p->get('sendpush');
      $o['function']='procSendBatchPush';

      if ($p->is_set('reportAddress')){
        $o['reportAddress'] = $p->get('reportAddress');
      }

      if (!$p->is_set('_skiprecipientselection')){
        $o['query']=$q;
      }
    } else {
      $o['function']='procSendBatch';
      $o['query']=$q;
      $o['noduplicate'] = $noduplicate;
    }
    if ($p->is_set('sendfax')){
      $o['sendfax']=$p->get('sendfax');
      $o['faxquality']=$p->get('faxquality');
    }
    $o['message']=$message;
    $o['misc']=$p->get('misc');
    $o['ar']=$p->get('ar');
    if(empty($subject)) $o['subject']=$ors['title'];
    else $o['subject']=$subject;
    $o['from']=$from;
    $o['fromname']=$p->get('fromname');
    $o['uid']=getSessionVar('UID');
    if($messagemode=='html') $o['isHtml']=true;
    if($_FILES['htmlfile']['size']) $filestotar['htmlfile']=$_FILES['htmlfile'];
    if($_FILES['file']['size']) $filestotar['file']=$_FILES['file'];
    $o['newspage']=$newspage;
    $o['tpl']=$p->get('tpl');
    $o['newsletterlang']=$newsletterlang;
    
    // dateTimeExe
    $date=$p->get("Date_Year")."-".$p->get("Date_Month")."-".sprintf("%02d",$p->get("Date_Day")).
      " ".sprintf("%02d",$p->get("Time_Hour")).":".sprintf("%02d",$p->get("Time_Minute")).":".$p->get("Time_Second");

    $dateTimeExe = \Seolan\Core\Field\Field::objectFactory((object)['FIELD'=>'dateTimeExe',
								   'FTYPE'=>'\Seolan\Field\DateTime\DateTime',
								   'COMPULSORY'=>1,
								   'FCOUNT'=>0,
								   'TRANSLATABLE'=>false,
								   'LABEL'=>'dateTimeExe']);
    $dateTimeExe->edit_format = 'H:M';
    $date = $dateTimeExe->post_edit($p->get('dateTimeExe'));
    $date = $date->raw;
    
    // Ajoute les logs pour les différents envois
    $xmail=new \Seolan\Library\Mail();
    $xmail->From=$o['from'];
    $xmail->FromName=$o['fromname'];
    $xmail->Subject=$o['subject'];
    $xmail->Body=$message;
    $xmail->_modid=$this->_moid;
    $xmail->isHTML($o['isHtml']);
    $xmail->_data=array('oidit'=>$o['newspage'],'alias'=>$ors['alias'],'title'=>$ors['title'],'lang'=>$o['newsletterlang']);
    if($o['sendsms']){
      $o['xmailsmsoid']=$xmail->initLog(array('req'=>addslashes($q),'mtype'=>'sms mailing','datep'=>$date));
    }elseif($o['sendpush']){
      $o['xmailsmsoid']=$xmail->initLog(array('req'=>addslashes($q),'mtype'=>'push notification','datep'=>$date));
    }else{
      if($o['sendfax']!='faxonly'){
	$o['xmailoid']=$xmail->initLog(array('req'=>addslashes($q),'mtype'=>'mailing','datep'=>$date));
      }
      if($o['sendfax'] && $o['sendfax']!='none'){
	$o['xmailfaxoid']=$xmail->initLog(array('req'=>addslashes($q),'mtype'=>'fax mailing','datep'=>$date));
      }
    }

    $scheduler=new \Seolan\Module\Scheduler\Scheduler();
    $roid=$scheduler->createJob($this->_moid, $date,($o['sendsms'])?'SMS':'Mailing',$o,'', $filestotar, NULL);
    $message = '';
    //En cas de push, il faut verifier si les users ont bien installe l'application pour compter le nombre de destinataire
    if (array_key_exists('sendpush', $o) && $o['sendpush']) {
      $totaldest = 0;
      $recordSetUsers = getDB()->select($q);

      while ($user = $recordSetUsers->fetch(\PDO::FETCH_ASSOC)) {
        if ($this->hasRegisterMobileApplication($user['KOID'])) {
          $totaldest++;
        }
      }
      $message = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','recipientscount'), $totaldest);
    } elseif (!$o['sendsms']) {
      $totaldest = getDB()->count($q, [], true);
      // destinaires valides 
      $okf = [];
      if($this->xset->fieldExists('bounce')) 
	$okf[] = 'ifnull(bounce, 2)=2';
      if($this->unpublishOnBounce && $this->xset->fieldExists('PUBLISH'))
	$okf[] = 'PUBLISH=1';
      if($this->active && $this->xset->fieldExists($this->active)) 
	$okf[] = $this->active.'=1';
      if (count($okf)>1){
	$qval = 'AND ('.implode(' AND ', $okf).')';
	if(!preg_match('/(order[ ]+by)/i',$q)) $qok=$q.$qval;
	else $qok = preg_replace('/(order[ ]+by)/i',$qval.' order by ',$q);
	$validdest = getDB()->count($qok, [], true);
	$message =sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','validrecipientscount'), $validdest, $totaldest);
      } else {
	$message = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','recipientscount'), $totaldest);
      }
    }


    $now=date('Y-m-d H:i:s');
    if($totaldest<=100) {
      if($date<=$now) {
	$scheduler->executeJob($roid['oid'],false);
	$message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sent').". ".$message;
      }  else 
	$message = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','taskscheduled'), $date).". ".$message;	
    } else {
      if($date<=$now)
	$message = \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','taskongoing').". ".$message;	
      else
	$message = sprintf(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','taskscheduled'),$date).". ".$message;	
    }

    \Seolan\Core\Shell::toScreen2('','message',$message);
    
  }


  /// Envoi de mail/fax via une tache dans le scheduler
  function procSendBatch(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $q=$more->query;

    $newspage=$more->newspage;
    $sendfax=$more->sendfax;
    $faxquality=$more->faxquality;
    $noduplicate = (isset($more->noduplicate) && $more->noduplicate==1);
    $uniquerecipients = array();
    $duplicatecount = 0;
    $bcc=$more->bcc;
    $body = ''; // corps du mail de rapport d'envoi
    if(!is_array($bcc) && !empty($bcc)) $bcc=array($bcc);
    $htmlfile=NULL;
    if(is_array($o->file)) {
      $htmlfile=$o->file['htmlfile'];
      $file=$o->file['file'];
    }
    $rs=getDB()->select($q);
    $totaldest=$rs->rowCount();

    if($this->newslettermodule && !empty($newspage)) {
      $newsletterlang=$more->newsletterlang;
      if(empty($newsletterlang)) $newsletterlang=TZR_DEFAULT_LANG;
      $url = '&oidit='.$newspage.'&MLID='.$this->id().
	"&LANG_DATA=$newsletterlang&LANG_USER=$newsletterlang&nocache=1&_fqn=1&mlmoid=".$this->_moid.'&mloid='.$more->xmailoid;
      if(!empty($more->tpl)) {
	// on a choisi d'envoyer une newsletter avec un template specifique
	$myxt=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$more->tpl);
	$dispfmt=$myxt->display(array('oid'=>$more->tpl,'tplentry'=>TZR_RETURN_DATA));
	$filename=$dispfmt['odisp']->filename;
	$xt = new \Seolan\Core\Template('file:'.$filename);
	$r3=array();
	$more->moid=$this->_moid;
	$r3['more']=$more;
	$content=$xt->parse($tpldata,$r3,NULL);
      } else {
	if(!empty($this->newsletterurl)) {
          if (substr($this->newsletterurl, 0, 1) == '/') {
            $this->newsletterurl = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName() . $this->newsletterurl;
          }
	  if(strpos($this->newsletterurl,'&moid=')===false) $url.='&moid='.$this->newslettermodule;
	  if(strpos($this->newsletterurl,'&function=')===false
	     && strpos($this->newsletterurl,'&_function=')===false) $url.='&function=viewpage';
	  if(strpos($this->newsletterurl,'&tplentry=')===false) $url.='&tplentry=it';
	  if(strpos($this->newsletterurl,'&_charset=')===false) $url.='&_charset=UTF-8';
	  $tocall=$this->newsletterurl.$url.'&nlmoid='.$this->_moid;
	  \Seolan\Core\Logs::notice(__METHOD__,'tocall : '.$tocall);
	} else {
	  $tocall=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true).'&template=Module/MailingList.newsletter.html'.
      '&function=viewpage&tplentry=it&moid='.$this->newslettermodule.$url.'&nlmoid='.$this->_moid;
	  \Seolan\Core\Logs::notice(__METHOD__,'tocall : '.$tocall);
	}
	$content = @file_get_contents($tocall);

	if(!empty($this->inlinestyles)) {
	  // transformation de la feuille de styles en styles 'inlined' avec
	  // un processeur externe
	  \Seolan\Core\System::loadVendor('css2inline/css2inline.php');
          $cssToInlineStyles = new \CSSToInlineStyles($content);
          $cssToInlineStyles->setUseInlineStylesBlock(true);
          $content = $cssToInlineStyles->convert();
	}
      }

      if($content===0) $skip=true;
      else {
	if(empty($htmlfile))
	  $htmlfile['tmp_name'] = TZR_TMP_DIR.uniqid('tmp');
	$fp = fopen($htmlfile['tmp_name'],'w+');
	$content=preg_replace('/'.session_name().'=[A-Za-z0-9]+/','',$content);
	fwrite($fp, $content);
	fclose($fp);
	$htmlfile['type']='text/html';
	$htmlfile['name']='newsletter.html';
      }
    }
    $mail=new \Seolan\Library\Mail();
    $mail->IsHtml(false);
    $mail->Timeout=30;
    $mail->FromName = $more->fromname;
    $mail->From = $more->from;
    if(!empty($more->ar)) $mail->ConfirmReadingTo=$more->from;
    $mail->Host=getSmtp($totaldest);
    \Seolan\Core\Logs::debug('xmodmaillist: using '.$mail->Host);
    $mail->Subject=$more->subject;
    $mail->SMTPKeepAlive=True;
    $mail->ClearAttachments();
    $mail->AddCustomHeader('X-MLID: '.$this->id());
    $mail->AddCustomHeader('X-Mailer: XSALTO-Mailer');
    $mail->Sender=$this->returnaddress;
    $mail->AddReplyTo($more->from);
    $mail->urlCounter=$this->urlcounter;
    $mail->loid=$more->xmailoid;
    if($this->faxfield && $sendfax && $sendfax!='none') {
      $mailfax=new \Seolan\Library\Mail();
      $mailfax->IsHtml(false);
      $mailfax->FromName='';
      $mailfax->From=TZR_FAX_SENDER;
      $mailfax->Host=getSmtp($totaldest);
      if(isset($sendfax)) $faxsubject='//CODE1='.getBillingCode().$faxquality;
      $mailfax->Subject=$faxsubject;
      $mailfax->SMTPKeepAlive=True;
      $mailfax->ClearAttachments();
      $mailfax->AddCustomHeader('X-MLID: '.$this->id());
      $mailfax->AddCustomHeader('X-Mailer: XSALTO-Mailer');
      $mailfax->Sender=$this->returnaddress;
      $mailfax->AddReplyTo($from);
      $mailfax->loid=$more->xmailfaxoid;
    }
    $emailformat='plain text';
    // cas d'un fichier attaché zippé
    $dirname=NULL;
    if(($htmlfile['type'] == 'application/x-zip-compressed') || ($htmlfile['type'] == 'application/zip')) {
      $dirname=TZR_TMP_DIR.uniqid('mail');
      if(mkdir($dirname,0750)) {
  // decompression du fichier
        system('unzip '.$htmlfile['tmp_name']." -d $dirname > /dev/null");
  // recherche de la pièce html
  $dir=opendir($dirname);
  while ($afile = readdir($dir)) {
    if(preg_match('/(.html|.htm)$/i',$afile)) {
      $htmlfile['tmp_name'] = $dirname.'/'.$afile;
    }
  }
  closedir($dir);
  // chargement du contenu
  $filecontent = file_get_contents($htmlfile['tmp_name']);

  // recherche des pièces attachées
	$dir=opendir($dirname);
	while (($afile = readdir($dir)) !== false) {
	  if(preg_match('/([^\.]+)(.jpg|.gif|.png)$/i',$afile,$regs)) {
	    $filecontent=preg_replace('@'.$afile.'@','cid:'.$regs[1],$filecontent);
	    $mail->AddEmbeddedImage($dirname.'/'.$afile, $regs[1], $afile);
	  }
	}
	$mail->IsHTML(true);
	$mail->Body=$filecontent;

	if($mailfax) $mailfax->AddStringAttachment($filecontent,'info.html');
	closedir($dir);
      }
      $emailformat='inline html width images';
    } elseif(!empty($htmlfile)) {
      $mail->IsHTML(true);
      $mail->Body=file_get_contents($htmlfile['tmp_name']);
      if($mailfax) $mailfax->AddStringAttachment($mail->Body,'info.html');
      $emailformat='inline html';
    } else {
      if($more->isHtml){
	$mail->IsHTML(true);
	$more->message='<html><body>'.$more->message.'</body></html>';
      }
      $mail->Body=$more->message;
      if($mailfax) $mailfax->Body=$more->message;
    }
    if(!empty($file)) {
      $emailformat.=' with an attached file';
      $mail->AddAttachment($file['tmp_name'],$file['name']);
      if($mailfax) $mailfax->AddAttachment($file['tmp_name'],$file['name']);
    }
    
    $body.='<b>Billing code</b>: '.getBillingCode().'<br/>';
    $body.='<b>Date</b>: '.date('Y-m-d H:i:s').'<br/>';
    $body.='<b>Subject</b>: '.$more->subject.'<br/>';
    $body.='<b>Format</b>: '.$emailformat.'<br/>';
    if($this->faxfield && ($sendfax!='none')) {
      $body.="<b>Fax method</b>: $sendfax<br/>";
      $body.="<b>Fax quality</b>: $faxquality<br/>";
    }
    if($this->newslettermodule && ($newspage!="")) {
      $body.="<b>Newsletter</b>: page $newspage has been sent<br/>";
    }
    $body.='<hr/><ul>';

    // Envoi des emails par mail classique
    if($sendfax!='faxonly') {
      $mail->editLog(array('datee'=>date('Y-m-d H:i:s'),'html'=>$mail->getIsHTML(),'size'=>round(strlen($mail->Body)/1024,2)));
      $status='';
      $startemail=$scheduler->statusJob($o->KOID,$status);
      if(!empty($startemail)) 
	\Seolan\Core\Logs::debug("startemail $startemail");
      // on parcourt la liste des adresses a partir de la derniere envoyee si on fait une reprise
      // dans ce cas la derniere envoyee est startemail
      $memcachestatus=\Seolan\Library\ProcessCache::setStatus(false);
      $invalidStatusCount = 0;
      while(($ors=$rs->fetch()) && ($status =='running')) {
	// controle validité du mail, voir   public function emailStatus($email)
	if ($this->_emailStatus($ors) != 'ok'){
	  $invalidStatusCount++;
	  continue;
	}
	// extraction des emails
	$emails=array();
	$oid=$ors['KOID'];

	if($this->extract_emails) {
	  $emails=$this->xset->emails($ors);
	}else{
	  // nettoyage et vérification de l'adresse email
	  $fmail=$this->key;
	  if($this->xset->desc[$fmail]->ftype == '\Seolan\Field\Url\Url') {
	    list($l1,$l2,$l3) = explode(';',$ors[$fmail]);
	    $f1mail=$l2;
	  }else{
	    $f1mail=$ors[$fmail];
	  }
	  $emails[] = emailClean($f1mail);
	}
	$allAddresses = count($emails);
	// préparation des tableaux qui permettent de remplacer les champs
	// par les valeurs dans le mail, avec la syntaxe <nomduchamp>
	foreach($emails as $foo=>$email) {
	  if(empty($startemail) && !empty($email)) {
	    if ($noduplicate){
	      if (isset($uniquerecipients[strtolower($email)])){
		$duplicatecount++;
		continue;
	      }
	    }
	    $mail->ClearAllRecipients();
	    if (!$mail->AddAddress($email)){
	      continue; // idem email vide issu de emailClean
	    }
	    if(!empty($bcc)){
	      foreach($bcc as $i=>$mbcc) $mail->AddBCC($mbcc);
	    }
	    $mail->Sender=$this->returnaddress;
	    \Seolan\Core\Logs::debug($email);
	    $filecontent=$mail->Body;
	    $mail->CharSet=$mail->DefaultCharSet;

	    // quelques champs avec des noms conventionnels
	    // supplémentaires. Ces champs ne sont pas disponibles dans
	    // la vue sur le site.
	    $fields_names['s']='{sender}';
	    $fields_names['n']='{sendername}';
	    $fields_names['t']='{to}';
	    $fields_values['s']=$mail->From;
	    $fields_values['n']=$mail->FromName;
	    $fields_values['t']=$email;
            // pour css2inline
	    $fields_names['t2']='%7Bto%7D';
	    $fields_values['t2']=$email;
	    // remplacement des champs par les valeurs des champs dans
	    // le mail à envoyer, dans le corps et dans le sujet. Dans
	    // le sujet on utilise une valeur textuelle et as html
	    $mail->Subject=$this->replaceTagsInNewsletter($more->subject, $oid, array($fields_names, $fields_values), 'text');
	    $fields_names['u']='{subject}';
	    $fields_values['u']=$mail->Subject;
	    $mail->Body=$this->replaceTagsInNewsletter($filecontent, $oid, array($fields_names, $fields_values));
	    $this->customReplace($mail->Body, $more, $oid);
	    if(!$mail->Send()) {
	      \Seolan\Core\Logs::notice('\Seolan\Module\MailingList\MailingList::procSendBatch','delaying for 1sec at'.$email);
	      usleep(1000000);
	    }
	    if ($noduplicate){
	      $uniquerecipients[strtolower($email)] = 1;
	    }
	    $mail->Body=$filecontent;
	    $body.='<li>'.$email.' email';
	  }
	}
	unset($fields);
	if(!empty($startemail) && ($startemail==$email)) $startemail=NULL;
	else $scheduler->statusJob($o->KOID, $status, $email);
      }
      // on remet le cache memoire dans son état initial
      \Seolan\Library\ProcessCache::setStatus($memcachestatus);
      $mail->SmtpClose();
    }

    // Envoi des fax si option fax activee
    if($this->faxfield && $sendfax && $sendfax!='none') {
      $mailfax->editLog(array('datee'=>date('Y-m-d H:i:s'),'html'=>$mail->getIsHTML(),'size'=>round(strlen($mail->Body)/1024,2)));
      $i=0;
      $rs=getDB()->select($q);
      $totaldest=$rs->rowCount();
      $mailfax->FromName='TZR Support';
      $mailfax->From=TZR_FAX_SENDER;
      $mailfax->ClearAllRecipients();
      $mailfax->Subject=$faxsubject;
      $status='running';
      while($ors=$rs->fetch()) {
	$fmail=$this->key;
	$ffax=$this->faxfield;
	$email=$ors[$fmail];
	$email=emailClean($email);
	$no=$ors[$ffax];
	$no=preg_replace('/([^0-9]+)/','',$no);

	// si on est en failover on n'envoie pas les fax destinés aux gens qui ont déjà un email
	if(empty($no) || (!empty($email) && $sendfax=='failover')) continue;
	$faxmail=$no.'%M2F@nfax.xpedite.fr';
	$mailfax->AddAddress($faxmail);
	$i++;
	\Seolan\Core\Logs::debug($faxmail);
	$body.='<li>'.$email.'['.$no.'], fax';
	$c1=array();
	if($i>100) {
	  \Seolan\Core\Logs::debug('sent');
	  $mailfax->Send();
	  $mailfax->ClearAllRecipients();
	  $i=0;
	}
      }
      if($i>0) {
	\Seolan\Core\Logs::debug('sent');
	$mailfax->Send();
	$mailfax->ClearAllRecipients();
      }
      $mailfax->SmtpClose();
    }

    // Envoi du rapport
    $body.='</ul>';
    if ($noduplicate){
      $body .= $duplicatecount.' duplicate email(s) detected</br>';
    }
    $body .= $invalidStatusCount.' mail(s) ignored : invalid status (not active, unpublished, bounce)</br>';
    $this->sendMail2User('ML '.getBillingCode(),
			 $body,
			 ["TZR Support<{$this->reportto}>",'TZR Support<'.TZR_ARCHIVE_ADDRESS.'>']);
    
    if($dirname) \Seolan\Library\Dir::unlink($dirname);
    if($status!='running') \Seolan\Core\Logs::debug("mailing has been interrupted ($status)");
    else \Seolan\Core\Logs::debug('mailing finished');
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sent').' ('.$totaldest.' messages)';
  }

  /**
   * Envoi de sms via une tache dans le scheduler
   * @todo : personnalisation du message (voir mail std)
   */

  function procSendBatchSMS(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$o, &$more) {
    $smsAccounts = $this->smsAccounts();
    $message = \Seolan\Core\Shell::from_screen('', 'message');
    $smsAccount = $smsAccounts[$more->smsAccountId];
    $xsmser = new \Seolan\Library\SMS($smsAccount);
    $reportbody.='<b>This mail is automatically generated by TZR/Console Séolan</b><br/>';
    $reportbody.='<b>ClientId</b>: '.$smsAccount['name'].'/'.$smsAccount['clientid'].'<br/>';
    $reportbody.='<b>Date</b>: '.date('Y-m-d H:i:s').'<br/>';
    $reportbody.='<b>SMS Text</b>: '.$more->message.'<br/>';
    $reportbody.='<hr/>';
    // l'infrastruture ne le gère pas trop ... dirait-on
    setLocale(LC_CTYPE, 'en_US');
    convert_charset($more->message, TZR_INTERNAL_CHARSET, 'ASCII');
    if (isset($more->query)){
      $rs=getDB()->select($more->query);
      $totaldest=$rs->rowCount();
      $reportbody.='<b>Selected recipients</b>: '.$totaldest.'<br/>';
      $reportbody.='<b>Recipients</b>:<br><ul>';
      // envoi aux destinataires selectionnés
      while($ors=$rs->fetch()) {
	$oid=$ors['KOID'];
	$num = preg_replace('/([^0-9]+)/','',$ors[$this->smsfield]);
	if(!empty($num) && preg_match('/([0-9]{8})/',$num)) {
	  // remplacement des champs par les valeurs des champs dans
	  // le mail à envoyer, dans le corps et dans le sujet. Dans
	  // le sujet on utilise une valeur textuelle et as html
	  $message=$this->replaceTagsInNewsletter($more->message, $oid, array());
	  $this->customReplace($message, $more, $oid);
	  $rsms = $xsmser->sendSMS($num, $message, NULL, array('emoid'=>$this->_moid));
	  if ($rsms['status']['code'] != 1){
	    $reportbody.='<li>'.$num.'Error, sms not sent : '.$rsms['status']['txt'].'</li>';
	    $message .= "<br>".$num.' Error, sms not sent : '.$rsms['status']['txt'];
	  } else {
	    $reportbody.='<li>'.$num.'</li>';
	  }
	}
      }
    }
    if (isset($more->otherrecipients)){
      $otherrecipients =  preg_split("/([\n\r;]+)/m", $more->otherrecipients,  0, PREG_SPLIT_NO_EMPTY);
      $totaldest=count($otherrecipients);
      $reportbody.='<b>Other recipients</b>: '.$totaldest.'<br/>';
      $reportbody.='<b>Recipients</b>:<br><ul>';
      // envoi aux destinataires selectionnés
      foreach($otherrecipients as $orecipient){
	$num = preg_replace('/([^0-9]+)/','',$orecipient);
	if(!empty($num) && preg_match('/([0-9]{8})/',$num)) {
	  $rsms = $xsmser->sendSMS($num, $more->message, NULL, array('emoid'=>$this->_moid));
	  if ($rsms['status']['code'] != 1){
	    $reportbody.='<li>'.$num.'Error, sms not sent : '.$rsms['status']['txt'].'</li>';
	    if (!empty($message)){
	      $message .= "<br>".$num.' Error, sms not sent : '.$rsms['status']['txt'];
	    }
	  } else {
	    $reportbody.='<li>'.$num.'</li>';
	  }
	}
      }
    }
    // message
    if (!empty($message)){
      \Seolan\Core\Shell::toScreen2('', 'message', $message);
    }
    // Envoi du rapport
    $reportbody.='</ul><hr/>';
    $mail=new \Seolan\Library\Mail(true,false);
    $mail->FromName='';
    $mail->AddBCC(TZR_ARCHIVE_ADDRESS,'TZR Support');
    if($more->reportAddress){
      $mail->AddAddress($more->reportAddress);
    } else {
      $mail->AddAddress($this->reportto,'TZR Support');
    }
    $mail->From=TZR_SUPPORT_ADDRESS;
    $mail->sendPrettyMail('SMS '.$smsAccount['name'],
			  $reportbody);
    return \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','sent').'<br>'.$reportbody;
  }

  function id() {
    return md5($this->_moid.$_SERVER['SERVER_NAME']);
  }

  public function getEmails($groups) {
    $email=array();
    foreach($groups as $j => $group) {
      if(!empty($group)) {
	$r1=$this->xset->procQuery(array('tplentry'=>TZR_RETURN_DATA,'_storedquery'=>$group,
					 'selectedfields'=>array($this->key)));
	foreach($r1['lines_o'.$this->key] as $i =>$o) {
	  $email=preg_replace('/([^0-9A-Za-z@_.-])/','',$o->text);
	  if(preg_match('/^([^@]+@[^.]+.*)$/', $email)) {
	    $emails[]=$email;
	  }
	}
      }
    }
    return $emails;
  }

  /** remplacer dans $content les tags {nomduchamp} par les valeurs du champ
   * de l'enregistrement $oid
   */
  public function replaceTagsInNewsletter($content, $oid, $tags=NULL, $text_or_html='html') {
    // on essaie d'éviter de calculer le display de manière répétitive
    static $previousOid=NULL;
    static $fields=NULL;
    if($previousOid!=$oid) {
      unset($previousOid);
      unset($fields);
      $previousOid=$oid;
      $fields = $this->xset->rDisplay($oid);
    }
    if(!is_array($fields)) return $content;

    $fields_names = array();
    $fields_values = array();
    if(is_array($tags)) list($fields_names, $fields_values)=$tags;
    $fields_names['o']='{oid}';
    $fields_names['p']='%3Coid%3E';
    $fields_names['m']='{moid}';
    $fields_names['v']='%3Cmoid%3E';
    $fields_names['i']='&lt;oid&gt;';
    $fields_values['o']=$oid;
    $fields_values['p']=$oid;
    $fields_values['m']=$this->_moid;
    $fields_values['v']=$this->_moid;
    $fields_values['i']=$oid;
    foreach($fields['fields_object'] as $field) {
      $fields_names[]='{'.$field->fielddef->field.'}';
      $fields_values[]=$field->$text_or_html;
    }
    $text=str_replace($fields_names, $fields_values,$content);
    return $text;
  }

  public function customReplace(&$text, $more, $oid) {
  }
  /// Mail géré par le module
  public function emailStatus($email){
    $f = [];
    if($this->xset->fieldExists('bounce')) $f[] = 'bounce';
    if($this->unpublishOnBounce && $this->xset->fieldExists('PUBLISH')) $f[] = 'PUBLISH';
    if($this->active && $this->xset->fieldExists($this->active)) $f[] = $this->active;
    $rs = getDB()->fetchRow('select '.implode(',', $f).' from '.$this->table.' where '.$this->key.'=?',[$email]);
    if (!$rs){
      return 'unknown';
    }
    return $this->_emailStatus($rs);
  }
  protected function _emailStatus($rs){
    if (((!isset($rs[$this->active]) || $rs[$this->active] == 1)
	    && (!isset($rs['PUBLISH']) || $rs['PUBLISH'] == 1)
	    && (!isset($rs['bounce']) || $rs['bounce'] == 2)
	 )){
      return 'ok';
    } else {
      return 'ko';
    }
  }
  /// Repositionne un email comme actif
  public function unBounce($email){
    $update='';
    if($this->xset->fieldExists('bounce')) $update.=',bounce=2';
    if($this->unpublishOnBounce && $this->xset->fieldExists('PUBLISH')) $update.=',PUBLISH=1';
    if($this->active && $this->xset->fieldExists($this->active)) $update.=','.$this->active.'=1';

    if($update){
      getDB()->execute('update '.$this->table.' set UPD=UPD'.$update.' where '.$this->key.'=?',array($email));
    }
  }
  /// Traite un email comme bounce
  public function applyBounce($email){
    $update='';
    if($this->xset->fieldExists('bounce')) $update.=',bounce=1';
    if($this->unpublishOnBounce && $this->xset->fieldExists('PUBLISH')) $update.=',PUBLISH=2';
    if($this->active && $this->xset->fieldExists($this->active)) $update.=','.$this->active.'=2';

    if($update){
      getDB()->execute('update '.$this->table.' set UPD=UPD'.$update.' where '.$this->key.'=?',array($email));
    }
  }

  /// affichage d'une newsletter avec remplacement des champs
  public function show($ar) {
    $p=new \Seolan\Core\Param($ar, array());

    $newspage = $p->get('newspage');
    $oid=$p->get('oidu');

    $url = '&oidit='.$newspage.'&MLID='.$this->id().
      "&LANG_DATA=$newsletterlang&LANG_USER=$newsletterlang&nocache=1&_fqn=1&mlmoid=".$this->_moid.'&mloid='.$more->xmailoid;
    if(!empty($this->newsletterurl)) {
      if (substr($this->newsletterurl, 0, 1) == '/') {
        $this->newsletterurl = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName() . $this->newsletterurl;
      }
      if(strpos($this->newsletterurl,'&moid=')===false) $url.='&moid='.$this->newslettermodule;
      $tocall=$this->newsletterurl.$url.'&nlmoid='.$this->_moid;
      \Seolan\Core\Logs::notice('\Seolan\Module\MailingList\MailingList::procSendBatch','tocall : '.$tocall);
    } else {
      $tocall=$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true).'&template=Module/MailingList.newsletter.html'.
	'&function=viewpage&tplentry=it&moid='.$this->newslettermodule.$url.'&nlmoid='.$this->_moid;
    }
    $content = @file_get_contents($tocall);
    $content=$this->replaceTagsInNewsletter($content, $oid);
    $charset = \Seolan\Core\Lang::getCharset();
    echo $content;
    die();
  }


  /**
   * renvoie true s'il y a des comptes externe push
   * @return bool
   */
  public function pushActive(){
    return !is_null($this->pushAccounts());
  }


  /**
   * renvoie la liste des compte externe push
   * @return array|null
   */
  public function pushAccounts(){
    if (is_null($this->pushAccounts)){
      $this->pushAccounts = \Seolan\Library\NotificationPush::getNotificationAccounts($this->_moid);
    }
    return $this->pushAccounts;
  }


  /**
   * @param \Seolan\Module\Scheduler\Scheduler $scheduler
   * @param stdClass      $recordSetSchedule
   * @param stdClass      $more
   *
   * @return string
   */
  public function procSendBatchPush(\Seolan\Module\Scheduler\Scheduler &$scheduler, &$recordSetSchedule, &$more) {
    $oXNotif = new \Seolan\Library\NotificationPush($this->_moid, $more->xmailsmsoid);

    if (isset($more->query)){
      $recordSetUsers = getDB()->select($more->query);

      $recipients = [];
      if ($recordSetUsers->rowCount() > 0) {
        while ($userData = $recordSetUsers->fetch(\PDO::FETCH_ASSOC)) {
          $dataRegistration = $this->getRegistrationMobileApplication($userData['KOID']);
          if (is_array($dataRegistration)) {
            $userData['applicationRegistrationData'] = $dataRegistration['applicationRegistrationData'];
            $userData['applicationType'] = $dataRegistration['applicationType'];

            $oXNotif->addDevices($dataRegistration['applicationRegistrationData'], $dataRegistration['applicationType']);

            $recipients[] = $userData;
          }
        }
      }

      //ppp($recipients);

      $result = $oXNotif->send($more->subject, $more->message, $more->data, $more->link, $more->query);
      $details = $oXNotif->getDetailResult();

      $aDetailLog = [];
      foreach ($recipients as $recipient) {
        foreach ($details as $registerData => $detail) {
          if ($recipient['applicationRegistrationData'] === $registerData) {
            $details[$registerData]['userData'] = $recipient;

            if (array_key_exists('updateToken', $detail)) {
              $this->registerApplication(['email'             => $recipient['email'],
                                          'registration_data' => $detail['updateToken'],
                                          'application_type'  => $recipient['applicationType'],
                                         ]);
              $aDetailLog[] = ['KOID'    => $recipient['KOID'],
                               'dest'    => ['registration_data' => $detail['updateToken'], 'application_type' => $recipient['applicationType']],
                               'type'    => 'error',
                               'message' => 'Update registration data to : '.$detail['updateToken'],
              ];
            } else {
              if ($detail['success']) {
                $aDetailLog[] = [
                  'KOID'    => $recipient['KOID'],
                  'dest'    => ['registration_data' => $recipient['applicationRegistrationData'], 'application_type' => $recipient['applicationType']],
                  'type'    => 'success',
                  'message' => $detail['message'],
                ];
              } else {
                $aDetailLog[] = [
                  'KOID'    => $recipient['KOID'],
                  'dest'    => ['registration_data' => $recipient['applicationRegistrationData'], 'application_type' => $recipient['applicationType']],
                  'type'    => 'error',
                  'message' => $detail['message'],
                ];
              }
            }


            if (!$detail['success'] && in_array($detail['message'], ['Unavailable', 'InvalidRegistration', 'NotRegistered'])) {
              //$this->registerApplication(['email' => $recipient['email'], 'registration_data' => '', 'application_type' => '']);
            }
          }
        }
      }

      foreach ($aDetailLog as $detail) {
        $oXNotif->addDetailLog($detail['dest'], $detail['type'], $detail['message']);
      }

      //ppp($details);

      $status = 'finished';
      $scheduler->statusJob($recordSetSchedule->KOID, $status);

      if ($result) {
        return 'Notification envoyée avec succès.';
      } else {
        return 'Des erreurs se sont produite (cf. LOGS).';
      }
    }

    \Seolan\Core\Shell::toScreen2('', 'message', $more->message);
    \Seolan\Core\Shell::toScreen2('', 'subject', $more->subject);

    return '';
  }

  /**
   * @param array $ar
   */
  public function registerApplication($ar = null) {
    if (empty($this->key) || empty($this->table) || empty($this->fieldmobileappregistration) || empty($this->fieldmobiletype)) {// || $_SERVER['HTTP_USER_AGENT'] !== 'XSALTO_APP_MOBILE') {
      if (substr(php_sapi_name(), 0, 3) === 'cgi') {
        header('Status: 501 Not Implemented');
      } else {
        header('HTTP/1.1 501 Not Implemented');
      }

      http_response_code(501);

      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Configuration manquante !');
      die();
    }

    $p = new \Seolan\Core\Param($ar);

    $email = strtolower(trim($p->get('email')));
    $registrationData = trim($p->get('registration_data'));
    $applicationType = strtolower(trim($p->get('application_type')));

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && (in_array($applicationType, \Seolan\Library\NotificationPush::$AVAILABLE_TYPE, true) || empty($applicationType))) {
      // On verifie si le module newsletter est monte sur une vue ou non.
      // Si c'est sur une vue alors on va chercher la table qui contient les colonnes :
      // $this->fieldmobileappregistration, $this->fieldmobiletype et $this->key
      $listTable = getDB()->select('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_ASSOC);

      $type = 'table';
      foreach ($listTable as $item) {
        if ($item['Tables_in_'.$GLOBALS['DATABASE_NAME']]===$this->table) {
          if (strtoupper($item['Table_type'])==='VIEW') {
            $type = 'view';
          }

          break;
        }
      }

      if ($type==='view') {
        $sQuery = 'SELECT `VIEW_DEFINITION` FROM `INFORMATION_SCHEMA`.`VIEWS` WHERE `TABLE_SCHEMA` = ? and `TABLE_NAME` = ?';
        $viewDefinition = getDB()->select($sQuery, [
          $GLOBALS['DATABASE_NAME'],
          $this->table
        ])->fetchColumn();

        $matches = [];
        preg_match_all('/`'.$GLOBALS['DATABASE_NAME'].'`\\.`(\w+)`/', $viewDefinition, $matches);

        $tables = [];
        if (count($matches)==2) {
          $tables = array_unique($matches[1]);
        }

        $sQuery = 'SELECT `COLUMN_NAME` FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND `TABLE_NAME` = ?';

        $tableName = '';
        foreach ($tables as $table) {
          $columns = getDB()->select($sQuery, [
            $GLOBALS['DATABASE_NAME'],
            $table
          ])->fetchAll(\PDO::FETCH_ASSOC);
          $columns = array_map('current', $columns);

          if (in_array($this->key, $columns, true) && in_array($this->fieldmobileappregistration, $columns, true) && in_array($this->fieldmobiletype, $columns, true)) {
            $tableName = $table;
            break;
          }
        }

        if (!empty($tableName)) {
          $tableToUpdate = $tableName;
        } else {
          \Seolan\Core\Logs::critical(__METHOD__.' ', 'Impossible d\'enregistrer l\'application car le module newsletter est cable
          sur une vue et que l\'on ne trouve pas la table de la vue qui definit les 3 colonnes :
          email, mobileappregistration et mobiletype');
          die('KO');
        }
      } else {
        $tableToUpdate = $this->table;
      }
      
      //Si le user n'existe pas on le créé.
      if ((int)getDB()->fetchOne('SELECT count(1) FROM `'.$tableToUpdate.'` WHERE `'.$this->key.'` = ?', [$email]) === 0) {
        $sQuery = 'INSERT INTO `'.$tableToUpdate.'` (`'.$this->fieldmobileappregistration.'`, `'.$this->fieldmobiletype.'`, `'.$this->key.'`) VALUES(?, ?, ?)';
      } else {
        $sQuery = 'UPDATE `'.$tableToUpdate.'` SET `'.$this->fieldmobileappregistration.'` = ?, `'.$this->fieldmobiletype.'` = ? WHERE `'.$this->key.'` = ?';
      }
      
      getDB()->execute($sQuery, [
        $registrationData,
        $applicationType,
        $email
      ]);
    } else {
      \Seolan\Core\Logs::critical(__METHOD__.' ', 'Le parametre email est invalide !');
      die('KO');
    }

    \Seolan\Core\Logs::debug(__METHOD__.' Application enregistre avec success.');
    die('OK');
  }

  /**
   * renvoi true s'il y a une application d'enregistre pour l'utilisateur.
   *
   * @param string $oid
   *
   * @return bool
   */
  public function hasRegisterMobileApplication($oid) {
    if ($this->getRegistrationMobileApplication($oid) === false) {
      return false;
    }

    return true;
  }


  /**
   * @param string $oid
   *
   * @return array|bool
   */
  public function getRegistrationMobileApplication($oid) {
    if (empty($this->key) || empty($this->table) || empty($this->fieldmobileappregistration) || empty($this->fieldmobiletype)) {
      return false;
    }

    $sQuery = 'SELECT `'.$this->fieldmobileappregistration.'`, `'.$this->fieldmobiletype.'` FROM `'.$this->table.'` WHERE `KOID` = ?';
    $recordSet = getDB()->select($sQuery, [$oid])->fetch(\PDO::FETCH_ASSOC);

    if (array_key_exists($this->fieldmobileappregistration, $recordSet) && trim($recordSet[$this->fieldmobileappregistration]) !== '' &&
        array_key_exists($this->fieldmobiletype, $recordSet) && trim($recordSet[$this->fieldmobiletype]) != '') {
      return ['applicationRegistrationData' => $recordSet[$this->fieldmobileappregistration], 'applicationType' => $recordSet[$this->fieldmobiletype]];
    }

    return false;
  }
  // Wrapper sur MailLogs::prepareUnBounce.
  // A voir s'il ne faut valider que les bounces du module actuel.
  public function prepareUnBounce($ar=null) {
    $maillogs = \Seolan\Core\Module\Module::singletonFactory(XMODMAILLOGS_TOID);
    $maillogs->prepareUnBounce($ar);
  }

  public function getCRMFields() {
    return [];
  }

  public function getCRMEmails($since = TZR_DATETIME_EMPTY) {
    $filter = $this->filter ? "$this->filter and " : '';
    if ($this->xset->fieldExists('confed')) {
      $filter .= 'confed!=0 and ';
    }
    return getDB()->fetchCol("select $this->key from $this->table where $filter UPD>?", [$since]);
  }

  public function getCRMContactInfos($email) {
    $filter = $this->filter ? "$this->filter and " : '';
    if ($this->xset->fieldExists('confed')) {
      $filter .= 'confed!=0 and ';
    }
    $contact = getDB()->fetchRow("select * from $this->table where $filter $this->key=?", [$email]);
    if (!$contact) {
      return null;
    }
    return [
      'Email' => $contact[$this->key],
      'Marketing' => $this->_emailStatus($contact) == 'ok',
      'Sources' => $contact['KOID']
    ];
  }

}
