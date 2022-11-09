<?php
namespace Seolan\Module\Contact;
/// Gestion de la demande d'informations sur un site Internet
class Contact extends \Seolan\Module\CRM\CRMSourceTable {
  public $sender='info@xsalto.com';
  public $sendername='Service Commercial XSALTO';
  public $firstnamefield='prenom';
  public $lastnamefield='nom';
  public $emailfield='email';
  public $processedfield='pok';
  public $archivefield='arch';
  public $mailingokfield='';
  public $sendbyemail=false;
  public $send2User=false;
  public $delaybeforewarning=false;
  public $register_in_newsletter=NULL;
  public $destField=NULL;
  public $destLabel=NULL;
  public $destEmail=NULL;

  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Contact_Contact');
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array('answer'=>array('rw','rwv','admin'),
	     'procAnswer'=>array('rw','rwv','admin'),
	     'insert'=>array('none','ro','rw','rwv','admin'),
	     'procInsert'=>array('none','ro','rw','rwv','admin'),
	     'editDup'=>[],
	     'procEditDup'=>[]
	     );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  /// edit = traiter/répondre
  function browseActionEditText($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','process','text');
  }
  function browseActionEditIco($linecontext=null){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','process');
  }
  /// Initialisation des propriétés
  public function initOptions() {
    parent::initOptions();
    $this->_options->delOpt('object_sec');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','sender'), 'sender', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','sendername'), 'sendername', 'text',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','subject'), 'subject', 'ttext',array());
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','emailfield'),'emailfield','field',
			    array('table'=>'table'));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','processedfield'), 'processedfield', 'field',
			    array('table'=>'table','type'=>'\Seolan\Field\Boolean\Boolean'));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','archivefield'), 'archivefield', 'field',
			    array('table'=>'table'));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','group'), 'mlmodule', 'module',array('toid'=>XMODMAILINGLIST_TOID));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','sendbyemail'), 'sendbyemail','boolean',array(),false);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','send2User'), 'send2User','boolean',array(),false);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','delaybeforewarning'), 'delaybeforewarning','text',NULL,'5');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','mailingok'), 'mailingokfield','field',
			    array('table'=>'table','type'=>'\Seolan\Field\Boolean\Boolean','compulsory'=>false,'validate'=>true),false);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact', 'register_in_newsletter'), 'register_in_newsletter',
			    'module', array('toid'=>XMODMAILINGLIST_TOID));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact', 'recipient_field'), 'destField', 'field', ['compulsory' => 0, 'type' => '\Seolan\Field\Link\Link'], '');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact', 'recipient_field_comment'), 'destField');
  }

  function load($moid) {
    parent::load($moid);
    if ($this->destField) {
      $this->_options->setOpt('Champ(s) label destinataire', 'destLabel', 'field',
        ['table' => $this->xset->desc[$this->destField]->target, 'type' => '\Seolan\Field\ShortText\ShortText', 'multivalued' => 1], '');
      $this->_options->setOpt('Champ email destinataire', 'destEmail', 'field',
        ['table' => $this->xset->desc[$this->destField]->target, 'type' => '\Seolan\Field\ShortText\ShortText'], '');
    }
    $this->_options->setValues($this, \Seolan\Core\Module\Module::findParam($moid)['MPARAM']);
  }
  /// Surcharge pour vérification du mail "sender"
  function procEditProperties($ar) {
    parent::procEditProperties($ar);
    if (!empty($this->sender)){
      $res = $this->checkEmail(['_options'=>['local'=>1], 
				'email'=>$this->sender,
				'tplentry'=>TZR_RETURN_DATA]);
      if ($res != 'OK'){
	\Seolan\Core\Shell::alert(\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_MailingList_MailingList','email_not_safe', 'warning'));
      }
    }
  }
  /// Menu spécifique au display
  function al_display(&$my){
    parent::al_display($my);
    if(!empty($my['edit'])){
      $moid=$this->_moid;
      $myoid=@$_REQUEST['oid'];
      $my['edit']->name = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact', 'process', 'text');
      $my['edit']->setUrl('&function=edit&moid='.$moid.'&template=Module/Contact.edit.html&tplentry=br&oid='.$myoid);
    }
  }

  /// Menu spécifique au browse
  function al_browse(&$my){
    parent::al_browse($my);
    if($this->_getSession('all')=="1") {
      $o1=new \Seolan\Core\Module\Action($this,'display_unprocessed',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','display_unprocessed','text'),
			    '&moid='.$this->_moid.'&function=browse&template=Module/Table.browse.html&tplentry=br&all=2','display');
      $o1->menuable=true;
      $my['display_unprocessed']=$o1;
    }else{
      $o1=new \Seolan\Core\Module\Action($this,'displayall',\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','displayall','text'),
			    '&moid='.$this->_moid.'&function=browse&template=Module/Table.browse.html&tplentry=br&all=1','display');
      $o1->menuable=true;
      $my['displayall']=$o1;
    }
    $o1->group = 'edit';
  }

  /// Retourne les infos de l'action editer du browse
  function browseActionEditUrl($usersel, $linecontext=null){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().($usersel?'&_bdxnewstack=1':'').'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=edit&template=Module/Contact.edit.html';
  }

  function insert($ar) {
    if (!\Seolan\Core\Shell::admini_mode() && $this->destField && $this->destLabel) {
      $this->xset->desc[$this->destField]->compulsory = 1;
      $this->xset->desc[$this->destField]->display_format = '%_' . implode(' %_', $this->destLabel);
    }
    return parent::insert($ar);
  }
  /// Prépare l'edition d'une fiche
  function edit($ar) {
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LETTERS');
    $q=$x->select_query(array('cond'=>array('modid'=>array('=',$this->_moid))));
    $x->browse(array('tplentry'=>'tpls','selected'=>0,'pagesize'=>30,'select'=>$q,'order'=>'name'));
    parent::display($ar);
  }

  /// Parcourir les demandes
  function browse($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $select=$p->get('select','local');
    $all=$p->get('all');
    if($all) $this->_setSession('all',$all);
    $pok=$this->processedfield;
    if(empty($select)) {
      if($this->_getSession('all')=="1") $q=$this->xset->select_query(array('cond'=>array()));
      else $q=$this->xset->select_query(array('cond'=>array($pok=>array('=',array(2,NULL)))));
      $ar['select']=$q;
    }
    return parent::browse($ar);
  }

  /// Recupere le nombre de demandes non traitées
  protected function _countUnprocessed() {
    $pok=$this->processedfield;
    $q=$this->xset->select_query(array('where'=>$this->filter,'cond'=>array($pok=>array('!=',1))));
    $nb=getDB()->count($q,array(),true);
    return $nb;
  }

  /// Recupere la date de la plus vielle demande non traitée
  protected function _dateOlderUnprocessed() {
    $pok=$this->processedfield;
    $q=$this->xset->select_query(array('where'=>$this->filter,'cond'=>array($pok=>array('!=',1))));
    $rs=getDB()->select($q.' order by UPD');
    if($ors=$rs->fetch()) $upd=$ors['UPD'];
    $rs->closeCursor();
    if(empty($upd)) $upd=date('Y-m-d');
    return $upd;
  }

  function status($ar=NULL) {
    parent::status($ar);
    $nb=$this->_countUnprocessed();
    if($nb>=1) $msg="$nb ".\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','pending_queries');
    else $msg=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','no_pending_query');
    $msg1=\Seolan\Core\Shell::from_screen('imod','status');
    if(empty($msg)) $msg1=array();
    $msg1[]=$msg;
    \Seolan\Core\Shell::toScreen2('imod','status',$msg1);
  }

  /// Prepare le formulaire de réponse
  function answer($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $oidcust=$p->get('oidcust');
    $oidtpl=$p->get('oidtpl');
    $tpl=$p->get('tplentry');
    $tplcust=$tpl.'cust';
    $lang=$p->get('lang');
    $crm=$this->xset->display(array('oid'=>$oidcust,'tplentry'=>TZR_RETURN_DATA));
    \Seolan\Core\Shell::toScreen2($tplcust,'email',$crm['o'.$this->emailfield]->raw);

    // Les modeles de contenu de lettres
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LETTERS');
    $options['disp']=array('filter'=>'(modid="'.$this->_moid.'")');
    $x->edit(array('tplentry'=>'letter','oid'=>$oidtpl,'LANG_DATA'=>$lang,'options'=>$options));

    // Groupe d'envoi
    if($this->mlmodule) {
      $x1=\Seolan\Core\Module\Module::objectFactory($this->mlmodule);
      $r1=$x1->storedQueries();
      \Seolan\Core\Shell::toScreen1('queries',$r1);
    }
    \Seolan\Core\Shell::toScreen2($tpl,'oidcust',$oidcust);
    \Seolan\Core\Shell::toScreen2($tpl,'oidtpl',$oidtpl);
  }

  /// Traitement d'une reponse. Envoi d'un message ok
  function procAnswer($ar=NULL) {
    $ds_letters=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=LETTERS');
    $p=new \Seolan\Core\Param($ar,array());
    $tpl=$p->get('tplentry');
    $subject=$p->get('subject');
    $text=$p->get('letter');
    if($ds_letters->desc['letter']->ftype=='\Seolan\Field\Text\Text'){
      $ishtml=false;
      $htmltext=nl2br($text);
    }else{
      $ishtml=true;
      $htmltext=$text;
    }
    $sender=$p->get('sender');
    $LANG_DATA=\Seolan\Core\Shell::getLangData();
    $sendername=$p->get('sendername');
    $email=$p->get('email');
    $cc=$p->get('cc');
    if($cc) $cc=preg_split('@[:;,]@',$cc);
    $oidtpl=$p->get('oidtpl');
    $oidcust=$p->get('oidcust');
    $sendemail=$p->get('sendemail');
    $processed=$p->get('processed');
    $saveanswer=$p->get('saveanswer');
    $pdfanswer=$p->get('pdfanswer');
    $disp=$p->get('disp');
    $template_context=$p->get('template_context');
    $groups=$p->get('groups');
    $r1=$this->xset->display(array('oid'=>$oidcust,'tplentry'=>TZR_RETURN_DATA));
    if(isset($this->archivefield) && $this->archivefield!='') $archive=$r1['o'.$this->archivefield]->raw;
    if(isset($sendemail)) {
      // Recupération du papier à lettre
      if(!empty($disp)) {
	$letter=$ds_letters->display(array("oid"=>$oidtpl,"tplentry"=>TZR_RETURN_DATA));
	if($letter['odisp']->raw) {
	  $ds_templates=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
	  $tpl=$ds_templates->display(array("oid"=>$letter["odisp"]->raw,"tplentry"=>TZR_RETURN_DATA));
          if (isset($pdfanswer) && $tpl['oprint']->raw)
            $tpl=new \Seolan\Core\Template($tpl['oprint']->filename);
          else
            $tpl=new \Seolan\Core\Template($tpl['odisp']->filename);
          $resp = array('sender' => $sender, 'sendername' => $sendername, 'subject' => $subject, 'text' => $htmltext, 'to' => $email);
          $tplData = array('br' => $r1, 'resp' => $resp, 'context' => $template_context);
	  $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
	  $tpl->set_glob(array('labels'=>&$labels));
	  $file=$tpl->parse($tplData, $this);
	  $template=preg_replace(array('(<sender>)','(<sendername>)',
				       '(<subject>)','(<text>)','(<Text>)','(<to>)'),
				 array($sender,$sendername,$subject,$htmltext,$htmltext,$email), $file);
	}
      }
      if (isset($pdfanswer)) {
        if ($file)
          $pdf = princeTidyXML2PDF(NULL, $template);
        else
          $pdf = princeTidyXML2PDF(NULL, $text);
        $pdfname = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','answer').'_'.$email.'_'.date('Y_m_d').'.pdf';
        \Seolan\Core\Shell::setNextFile($pdf, $pdfname, 'application/pdf');
      } else {
        $mailClient=new \Seolan\Library\Mail();
        $mailClient->From=$sender;
        $mailClient->FromName=$sendername;
        $mailClient->AddAddress($email,'');
        $mailClient->Subject=$subject;
        if($file) {
          $mailClient->Body=$template;
          $mailClient->AltBody=strip_tags($template);
        } else {
          if(!$ishtml){
            $mailClient->Body=$text;
            $mailClient->isHTML(false);
          }else{
            $mailClient->Body='<html><body>'.$text.'</body></html>';
          }
        }
        if ($_FILES['attachment']['size'] > 0) {
          $mailClient->addAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name']);
        }
        $mailClient->initLog(array('modid'=>$this->_moid,'mtype'=>'answer'));
        $mailClient->Send();
      }

      // Envoi de l'email en copie a d'autres personnes regroupées en groupes dont les oids sont fournis dans groups
      if(($this->mlmodule && !empty($groups)) || !empty($cc)) {
	$emails=array();

	if($this->mlmodule) {
	  $m=\Seolan\Core\Module\Module::objectFactory($this->mlmodule);
	  $emails=$m->getEmails($groups);
	}
	if(!empty($cc)) {
	  $emails=array_merge($emails, $cc);
	}
	$mailp=new \Seolan\Library\Mail();
	$mailp->From=$sender;
	$mailp->FromName=$sendername;
	if(!preg_match('@(\[)@',$subject)) $subject='['.\Seolan\Core\Ini::get('societe').'] '.$subject;
	$mailp->Subject=$subject;
	$tpldata['br']=$this->display(array('oid'=>$oidcust,"tplentry"=>TZR_RETURN_DATA));
	$r3=array();
	$xt=new \Seolan\Core\Template('Module/Contact.raw-view.html');
	$content=$xt->parse($tpldata,$r3,NULL);
	$mailp->Body=$content;
	$mailp->initLog(array('modid'=>$this->_moid,'mtype'=>'answer copy'));
	foreach($emails as $i=>$email) {
	  if(!empty($email)) {
	    $mailp->ClearAllRecipients();
	    $mailp->AddAddress($email);
	    $mailp->Send();
	  }
	}
      }
    }

    // Modification de la fiche
    $ar1=array('oid'=>$oidcust,'_options'=>array('local'=>1),'LANG_DATA'=>$LANG_DATA);
    if($processed) $ar1[$this->processedfield]=1;
    if(!empty($this->archivefield) && $saveanswer) {
      if($ishtml){
	$archive.='<hr style="border:1px solid black;height:0px;"/>Subject: '.$subject.'<br>Date: '.date('Y-m-d H:i:s').
	  '<br>Body: <br>'.$text;
      }else{
	$archive.="-----------\nSubject: ".$subject."\nDate: ".date('Y-m-d H:i:s')."\nBody: \n".$text;
      }
      $ar1[$this->archivefield]=$archive;
    }
    $this->xset->procEdit($ar1);
  }

  /*
  Insertion d'une nouvelle fiche
  @param $ar (array) $_POST/$_GET
  Voir la fonction _subscribe() créée le 08/06/2018 pour permettre la surcharge de cette partie spécifique.
  (Légalement on n'ajoute que le champ email, mais le Diocèse souhaite avoir en plus les prénoms et noms)
  */
  function procInsert($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,array());
    $tplentry=$p->get('tplentry');
    $ar["tplentry"]=TZR_RETURN_DATA;
    $pok=$p->get($this->processedfield);
    if(empty($pok)) $ar[$this->processedfield] = 2;
    $r1 = parent::procInsert($ar);

    // Inscription en newsletter si la demande a été faite
    \Seolan\Core\Logs::debug(__METHOD__."{$this->register_in_newsletter} {$this->mailingokfield} {$r1['oid']}");
    if(!empty($this->register_in_newsletter) && $this->xset->fieldExists($this->mailingokfield) && !empty($r1['oid'])) {
      $f1=$this->xset->getField($this->mailingokfield);
      $value=$p->get($this->mailingokfield);
      $hidden=$p->get($this->mailingokfield.'_HID');
      $ar[$this->mailingokfield.'_HID']=$hidden;
      $v1=$f1->post_edit($value,$ar);
      // Si la demande est ok on inscrit
      if($v1->raw==1) {
	$this->_subscribe($p);
      }
    }
    // Envoi de la demande par mail
    if($this->sendbyemail && !empty($r1['oid'])) {
      $options = [];
      if ($this->destField) {
        $options[$this->destField] = ['target_fields' => array_merge($this->destLabel, [$this->destEmail])];
      }
      $disp = $this->display(array('oid'=>$r1['oid'],'tplentry'=>TZR_RETURN_DATA,'_published'=>'private', 'options' => $options));
      $r3=array();
      $template = $p->get('autoResponseTemplate');
      if(empty($template)) $template = TZR_SHARE_DIR.'Module/Contact.raw-request-core.html';
      $xt=new \Seolan\Core\Template($template);
      $content=$xt->parse($r = ['br' => $disp], $r3,NULL);
      if(empty($this->subject)) $subject='Module '.$this->getLabel();
      else $subject=$this->subject;
      if ($this->destField && $disp['o'.$this->destField]->link['o'.$this->destEmail]->raw) {
        $destEmail = $disp['o'.$this->destField]->link['o'.$this->destEmail]->raw;
      } else {
	$destEmail = "{$this->senderName}<{$this->sender}>";
      }
      $replyToEmail = [$disp['o'.$this->emailfield]->raw];
      $fromEmail = 'Module '.$this->getLabel()."<{$this->sender}>";
      $this->sendAdminMail2User($subject,$content,$destEmail,$fromEmail,
				true,null,null,null,null,
				['reply-to'=>$replyToEmail,
				 '???-return-path???'=>null
				 ]);
      if($this->send2User){
          $this->sendAdminMail2User($subject,$content,$replyToEmail,$fromEmail);
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry, $r1);
  }

  /*
  Effectuer la souscription depuis un formulaire de contact
  @param $p \Seolan\Core\Param
  */
  protected function _subscribe($p) {
    $mod1=\Seolan\Core\Module\Module::objectFactory($this->register_in_newsletter);
  	$mod1->captcha=false;

  	$email=$p->get($this->emailfield);
  	$n1=array(
      $mod1->key=>$email,
      'captcha_ok'=>true
    );
    if( !empty($mod1->consent_field) && !empty($this->consent_field) ){
      $n1[$mod1->consent_field."_HID"] = $p->get($this->consent_field.'_HID');
      $n1[$mod1->consent_field] = $p->get($this->consent_field);
    }
  	$mod1->subscribe($n1);
  }

  /// Contenu de la page d'accueil
  public function &_portlet() {
    $txt='';
    $nb = $this->_countUnprocessed();
    $unp = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','display_unprocessed');
    if(!empty($nb)) $txt=$unp.': '.$nb;
    return $txt;
  }
  /// Methode appellee dans le scheduler
  protected function _daemon($period='any') {
    parent::_daemon($period);
    if($period=='daily') {
      if(!empty($this->delaybeforewarning)) {
	$pok=$this->processedfield;
	$q=$this->xset->select_query(array('where'=>$this->filter,'cond'=>array($pok=>array('!=',1))));
	$rs=getDB()->select($q);
	$found=false;
	while(!$found && $rs && ($ors=$rs->fetch())) {
	  $d1d=mktime(0,0,0,(int)substr($ors['UPD'],5,2),(int)substr($ors['UPD'],8,2),(int)substr($ors['UPD'],0,4));
	  $now=time();
	  $d2=($now-$d1d)/60/60/24;
	  if($d2>=$this->delaybeforewarning){
	    $found=true;
	    break;
	  }
	}
	if($found) {
	  $this->sendMail2User('Module '.$this->getLabel().' : '.\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','warning','text'),
			       \Seolan\Core\Labels::getSysLabel('Seolan_Module_Contact_Contact','queryistooold'),
			       $this->reportto, 
			       $this->sender);
	}
      }
    }
  }

  public function getCRMFields() {
    $fields = parent::getCRMFields();
    $fields['NbDemandes'] = new \Seolan\Field\ShortText\ShortText((object) ['FCOUNT' => 4, 'DPARAM' => ['default' => 0]]);
    return $fields;
  }

  public function getCRMContactInfos($email) {
    $contact = parent::getCRMContactInfos($email);
    if ($contact) {
      $contact['NbDemandes'] = count($contact['_rows']);
    }
    return $contact;
  }

}

