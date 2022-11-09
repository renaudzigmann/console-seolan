<?php
namespace Seolan\Module\MailLogs;
class MailLogs extends \Seolan\Module\Table\Table{

  static public $upgrades = ['20190722'=>''];
  protected static $redirectedPrefix = '[REDIRECTED EMAIL (%s)]';
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs',"modulename","text");
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['prepareUnBounce'] = ['rw', 'rwv', 'admin'];
    $g['unBounce'] = ['rw', 'rwv', 'admin'];
    $g['resend'] = ['rw', 'rwv', 'admin'];
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  function browse_actions(&$r, $assubmodule = false, $ar = NULL) {
    parent::browse_actions($r, $assubmodule);
    if ($this->secure('', 'resend')) {
      $url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . '&moid=' . $this->_moid . '&function=resend';
      $label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs','resend','text');
      foreach ($r['lines_oid'] as $i => $oid) {
        $r['actions'][$i][] = '<a class="cv8-ajaxlink" href="'.$url.'&oid='.$oid.'" title="'.$label.'"><span class="glyphicon csico-send"></span></a>';
      }
    }
  }

  public function resend($ar) {
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');
    $row = getDB()->fetchRow('select * from _MLOGS where KOID=?', [$oid]);
    \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    if (!isEmail($row['dest'])) {
      \Seolan\Core\Shell::alert(\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs','invalidemail','text'));
      return;
    }
    $mail = new \Seolan\Library\Mail();
    $mail->loid = $oid;
    $mail->addAddress($row['dest']);
    $mail->setFrom($row['sender']);
    $mail->Subject = $row['subject'];
    $mail->Body = file_get_contents($this->xset->desc['bodyfile']->display($row['bodyfile'])->filename);
    if ($mail->Send()) {
      \Seolan\Core\Shell::alert(\Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs','resent','text'), 'info');
    } else {
      \Seolan\Core\Shell::alert($mail->ErrorInfo);
    }
  }

  /**
   * recherche d'emails en bounce
   * -> boite de saisie des mails
   * -> liste des modules MailLogs/ConnectionInterface accessibles
   */
  function prepareUnBounce($ar=null){
    $p = new \Seolan\Core\Param($ar, ['tplentry'=>'ml', 'step'=>'0']);
    $tpl = $p->get('tplentry');
    if ($p->get('step') == '1'){
      $ret = ['modlist'=>$this->authorizedConnectedModules()];
      $ret['modlist_hasemail'] = array_fill(false, count($ret['modlist']), false);
      $ret['step'] = '1';
      $mails = $p->get('mails');
      $ret['mails'] = [];
      // recherche des statuts des mails demandés : _MBOUNCE et NL
      $mails = preg_split("/[ ;\n]+/im", $mails, -1, PREG_SPLIT_NO_EMPTY);
      foreach($mails as $email){
	$email = trim($email);
	if (empty($email))
	  continue;
	$ms = ['email'=>$email, 'modstatus'=>[], 'status'=>null];
	if (isEmail($email)){
	  $ms['status'] = $this->getEmailStatus($email);
    if ($ms['status'] === 'uncertain') {
      $ms['nbdetect'] = $this->getNbEmailBounce($email);
    }
	} else if (!empty($email)) {
	  $ms['status'] = 'notanemailaddress';
	}
	foreach($ret['modlist'] as $i=>$mod){
	  $s = $mod->emailStatus($email);
	  $ms['modstatus'][] = $s;
	  if ($s != 'unknown'){
	    $ret['modlist_hasemail'][$i] = true;
	  }
	}
	$ret['mails'][] = $ms;
      }
    } else {
      $ret['step'] = '0';
    }
    \Seolan\Core\Shell::toScreen1($tpl, $ret);
  }
  /**d
   * maj du mail (on efface)
   * -> maj _MBOUNCE
   * -> notification des modules accessibles
   */
  function unBounce($ar=null){
    $p = new \Seolan\Core\Param($ar, []);
    $modlist = $this->authorizedConnectedModules();
    $modules = $p->get('modules');
    $emails = $p->get('emails');
    foreach($emails as $email){
      // mise à jour de _MBOUNCE
      $this->removeFromBounce($email);
      // notification des modules
      foreach($modlist as $i=>$mod){
	if (in_array($mod->_moid, $modules)){
	  $mod->unBounce($email);
	}
      }
    }
    if (count($emails)>0){
      setSessionVar('message', 'Modification enregistrée');
      \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=prepareUnBounce&tplentry=ml&template=Module/MailLogs.unbounce.html&step=1&mails='.implode(';', $emails));
    } else {
      \Seolan\Core\Shell::setNext($GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=prepareUnBounce&tplentry=ml&template=Module/MailLogs.unbounce.html');
    }

  }
  protected function authorizedConnectedModules(){
    $modlist=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,
						       'noauth'=>false,
						       'withmodules'=>true, '_local'=>1));
    $modlista = [];
    foreach($modlist['lines_mod'] as $i=>$mod){
      if(is_a($mod,'\Seolan\Module\MailLogs\ConnectionInterface')
	 && $mod->secure('', ':rw')){
	$modlista[] = $mod;
      }
    }
    return $modlista;
  }
  function _daemon($period='any'){
    $this->checkBounce();
  }
  function delete($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array('tplentry'=>''));
    $withtable=$p->get('withtable');
    if(!empty($withtable)) {
      $table='_MLOGSD';
      if(\Seolan\Core\DataSource\DataSource::sourceExists($table,true)) {
	$xbase=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
	$ret=$xbase->procDeleteDataSource(array('action'=>'OK','tplentry'=>TZR_RETURN_DATA));
      }
      $table='_MBOUNCE';
      if(\Seolan\Core\DataSource\DataSource::sourceExists($table,true)) {
	$xbase=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table);
	$ret=$xbase->procDeleteDataSource(array('action'=>'OK','tplentry'=>TZR_RETURN_DATA));
      }
    }
    parent::delete($ar);
  }

  /// permet de connaitre l'état d'un email donné
  function getEmailStatus($email) {
    $nbdetect=$this->getNbEmailBounce($email);
    if(empty($nbdetect) || $nbdetect<=0) return 'valid';
    elseif($nbdetect<TZR_BOUNCE_NB_CHECK) return 'uncertain';
    else return 'invalid';
  }

  // Permet de connaitre le nombre de bounces détectés
  function getNbEmailBounce($email) {
    return getDB()->fetchOne('select nbdetect from _MBOUNCE where email=?',
    array($email));
  }
  /// Traitement des redirections (en mode tests) sur un envoi de mail
  public function processMailRedirections(\Seolan\Library\Mail $mail){
    if (!$this->_testmode)  // test mode config only (pas tenir compte des IP)
      return;
    
    $redirect_rules = $this->getConfigurationOption('redirect_rules');

    // en mode test, on rejette tout si rien de spécial de defini
    if (empty($redirect_rules)){
      $redirect_rules = [
	'granted_addresses'=>[] // aucun domaine n'est autorisé
      ];
    }
    // par defaut, on redirige sur debug addresse
    if (empty($redirect_rules['redirect_to_addresses'])){
      $redirect_rules['redirect_to_addresses']=[TZR_DEBUG_ADDRESS];
    }

    // contrôle redirection d'une adresse
    $checkAddresses = function($addresses) use($redirect_rules){
      $keep = [];
      $redirect = [];
      $all = [];
      foreach($addresses as $addr){
	if (isset($addr[1]))
	  $all[] = "{$addr[1]}<{$addr[0]}>";
	else
	  $all[] = $addr[0];
	// recherche d'un domaine autorisé
	$granted = false;
	foreach($redirect_rules['granted_addresses'] as $domain){
	  if (preg_match("/{$domain}/", $addr[0])){
	    $granted = true;
            break;
          }
	}
	if ($granted)
	  $keep[]=$addr;
	else
	  $redirect[]=$addr;
      }
      return [$all, $keep, $redirect];
    };
    
    list($oldTo, $newTo, $redirectedTo) = $checkAddresses($mail->getToAddresses());
    list($oldCc, $newCc, $redirectedCc) = $checkAddresses($mail->getCcAddresses());
    list($oldBcc,$newBcc, $redirectedBcc) = $checkAddresses($mail->getBccAddresses());
   
    if (count($redirectedTo)>0 || count($redirectedCc)>0 || count($redirectedBcc)>0){
      // si cc contient la debug address, la virer
      // modifier le sujet (si 1 seul le mettre ?)
      if (count($oldTo) == 1)
	$mail->Subject = sprintf(static::$redirectedPrefix, $oldTo[0]).' '.$mail->Subject;
      else
	$mail->Subject = sprintf(static::$redirectedPrefix, 'see x-originated headers').' '.$mail->Subject;
      $mail->addCustomHeader('x-originated-to', implode(',', $oldTo));
      $mail->addCustomHeader('x-originated-cc', implode(',', $oldCc));
      $mail->addCustomHeader('x-originated-bcc', implode(',', $oldBcc));
      // effacer tout les recipients
      $mail->ClearAllRecipients();
      // remettre les nouveaux
      foreach([[$newTo, 'addAddress'],
	       [$newCc, 'addCC'],
	       [$newBcc, 'addBCC']] as list($newAddresses, $method)){
	foreach($newAddresses as $newAddr){
	  $mail->$method($newAddr[0], $newAddr[1]??'');
	}
      }
      // et les destinataires debug
      // ajout des destinataires en redirection
      foreach($redirect_rules['redirect_to_addresses'] as $redirAddr){
	$mail->addAddress($redirAddr);
      }
    } else {
      // pas de redirection, on touche rien
    }
  }
  /// Lite les bounces et les enregistre en base
  function checkBounce($ar=NULL){
    $xsetbounce=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MBOUNCE');
    $rs=getDB()->select('SELECT * FROM _ACCOUNTS WHERE atype="bounce" AND modid=?', array($this->_moid));
    while($account=$rs->fetch()) {
      $shortoid=str_replace(':','_',$account['KOID']);
      if(!$lck=\Seolan\Library\Lock::getLock('checkBounce'.$shortoid)) continue;

      $cwsDebug = new \Cws\CwsDebug();
      //$cwsDebug->setDebugVerbose();
      //$cwsDebug->setEchoMode();
      
      $checker = new \Cws\MailBounceHandler\Handler($cwsDebug);
      $checker->setDeleteProcessMode();
      //$checker->setNeutralProcessMode();
      $checker->setImapMailboxService(); // default
      $checker->setMailboxHost($account['url']); // default 'localhost'
      $checker->setMailboxPort(993); // default const MAILBOX_PORT_IMAP
      $checker->setMailboxUsername($account['login']);
      $checker->setMailboxPassword($account['passwd']);
      $checker->setMailboxSecurity(\Cws\MailBounceHandler\Handler::MAILBOX_SECURITY_SSL); // default const MAILBOX_SECURITY_NOTLS
      $checker->setMailboxCertValidate(); // default const MAILBOX_CERT_NOVALIDATE
      $checker->setPurge(true); // default const MAILBOX_CERT_NOVALIDATE
      if ($checker->openImapRemote() === false) {
	$error = $checker->getError();
        $report='Email box "'.$account['name'].'" can\'t be open. ';
        $GLOBALS['XUSER']->sendMail2User('!!ERROR!! '.TZR_SERVER_NAME.' check bounce bad email box !!ERROR!!', $report, TZR_DEBUG_ADDRESS);
	return;
      } else {
        $result=$checker->processMails();
	$counter = $result->getCounter();
	if (!$result instanceof \Cws\MailBounceHandler\Models\Result) {
	  $error = $checker->getError();
	} else {
	  $mails = $result->getMails();
	  foreach ($mails as $mail) {
	    if (!$mail instanceof \Cws\MailBounceHandler\Models\Mail) {
	      continue;
	    }
	    if($mail->getType()==\Cws\MailBounceHandler\Handler::TYPE_BOUNCE){
	      foreach($mail->getRecipients() as $recipient){
		if ($recipient instanceof \Cws\MailBounceHandler\Models\Recipient) {
		  if($recipient->isRemove()) {
		    $nbdetect=getDB()->fetchOne('SELECT nbdetect FROM _MBOUNCE WHERE email=?',
						[$recipient->getEmail()]);
		    if($nbdetect){
		      getDB()->execute('update _MBOUNCE set nbdetect=? where email=?',
				       array(++$nbdetect,$recipient->getEmail()));
		      if($nbdetect>=TZR_BOUNCE_NB_CHECK){
			$this->applyBounce($recipient->getEmail());
		      }
		    }else{
		      $xsetbounce->procInput(array('email'=>$recipient->getEmail(),
						   'nbdetect'=>1, '_local'=>true));
		    }
		  }
		}
	      }
            }
          }
        }
      }
      \Seolan\Library\Lock::releaseLock($lck);
    }
    getDB()->execute('DELETE FROM _MBOUNCE WHERE UPD<DATE_SUB(curDate(),INTERVAL 2 MONTH) AND nbdetect < ?',array(TZR_BOUNCE_NB_CHECK));
  }

  /// suppression d'une adresse de tous les modules qui pourraient l'utiliser
  function applyBounce($email) {
    static $modlist=array();
    if(empty($modlist)) $modlist=\Seolan\Core\Module\Module::modlist(array('tplentry'=>TZR_RETURN_DATA,'noauth'=>true,'withmodules'=>true, '_local'=>1));

    foreach($modlist['lines_mod'] as $i=>$mod){
      if(is_a($mod,'\Seolan\Module\MailLogs\ConnectionInterface')){
	$mod->applyBounce($email);
      }
    }
  }


  /// Recupère le nombre de fois ou un email a été detecté en bounce
  function isBounce($email){
    return getDB()->fetchOne('SELECT nbdetect FROM _MBOUNCE WHERE email=?',array($email));
  }

  /// Recupère le nombre de fois ou un email a été detecté en bounce
  function isBlacklisted($email) {
    $nb=getDB()->fetchOne('SELECT nbdetect FROM _MBOUNCE WHERE email=?',array($email));
    if($nb>=TZR_BOUNCE_NB_CHECK) {
      $this->applyBounce($email);
      return true;
    }
    return false;
  }

  /// Supprime un mail de la table des bounce
  function removeFromBounce($email){
    getDB()->execute('DELETE FROM _MBOUNCE WHERE email=?',array($email));
  }
  function _actionlist(&$my, $alfunction=true){
      parent::_actionlist($my, $alfunction);
    if($this->secure('','unBounce')){
      $o1=new \Seolan\Core\Module\Action($this,'unbounce',
					 \Seolan\Core\Labels::getSysLabel('Seolan_Module_MailLogs_MailLogs','checkmails','text'),
					 '&moid='.$this->_moid.'&tplentry=ml&&function=prepareUnBounce&template=Module/MailLogs.unbounce.html');  
    }
    $o1->menuable=true;
    $o1->group='actions';
    $my['unbounce']=$o1;
  }
}
