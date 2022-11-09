<?php
/// Classe encapsulant les fonctions d'envoi de mail

namespace Seolan\Library;

class Mail extends \PHPMailer {
  public $loid;               // KOID du log lié au mail
  public $urlCounter=false;   // Active le compteur de clic sur lien
  public $DefaultCharSet;     // Charset par defaut des mails
  public $prevBody;           // Body du précédent mail envoyé avec cet objet (permet de faire des comparaisons pour eviter 
                              // certains traitements)
  public $autoAgregate=true;  // Défini si l'agregation se fait à chaque envoi (definir à false et lancer l'agragation manuellement à la 
                              // fin des envoi allège le tout
  public $nbdest=0;
  public $nberr=0;
  public $logActive=true;
  public $_data=NULL;
  public $_link=NULL;         // Lien libre vers un objet, customer, user ...
  protected static $xsetl=NULL;
  protected static $xsetld=NULL;
  protected static $boPrettyMailLayout='Core.boPrettyMail.html';
  protected static $boPrettyMailCss='Core.boPrettyMail.css';
  protected static $_testmode=null;
  
  public static function objectFactory($interactive=true,$logActive=true,$encoding="base64") {
    $class = get_called_class();
    return new $class($interactive,$logActive,$encoding);
  }
  
  function __construct($interactive=true,$logActive=true,$encoding="base64") {
    if(self::$xsetl==NULL){
      self::$xsetl=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGS');
      self::$xsetld=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=_MLOGSD');
    }
    $this->_mtype='direct';
    $this->From="noreply@xsalto.com";
    $this->logActive=$logActive;
    if($interactive) $this->Host=\Seolan\Core\Ini::get('i_smtp_ip');
    else $this->Host=\Seolan\Core\Ini::get('smtp_ip');
    $this->WordWrap='80';
    $this->Mailer="smtp";
    $this->IsHTML(true);
    $this->CharSet=$this->DefaultCharSet="UTF-8";
    $this->Encoding=$encoding;
    $this->SMTPSecure=TZR_SMTP_SECURE;
    $this->SMTPAutoTLS=false;
  }
  
  /// Recupération des destinataires
  public function getAllRecipients(){
    return ['to'=>$this->to, 'cc'=>$this->cc, 'bcc'=>$this->bcc];
  }
  /// Recupération des destinataires sous forme de chaîne
  public function getAllRecipientsString(){
    $ret = [];
    foreach(['to','cc','bcc'] as $p){
      foreach($this->$p as $mail){
	if (isset($mail[1]) && !empty($mail[1]))
	  $ret[] = "{$mail[1]}<{$mail[0]}>";
	else
	  $ret[] = $mail[0];
      }
    }
    return implode(',', $ret);
  }
  /**
   * envoi d'un mail avec formattage
   * @param $subject : le sujet 
   * @param $message
   * @param $mail : voir extractAddressess
   * @param $from : voir extractAddresses , le premier poste seulement si plusieurs
   * @param $attachement : paramètres pour string attachment ou file attachment
   * @param $options :
   * -> sign : ajout au body et sujet les prefixe/suffixes/signature console
   * -> footer : base de mail spécifique, remplace la signature par defaut
   * -> template : null | default | template de fond mail 
   * -> rubrique : null | [moid,oid,template]  : page de fond de mail et son gabarit
   * -> tags : null || données de remplacement éventuel des balises incluses dans le message, le gabarit, etc
   * -> text : pas de fond de mail, mail texte ?
   * (> forceBO : envoi d'un mail type BO depuis le FO (ex : notification de demande de contact)
   * -> reply-to (format des adresses)
   */
  public function  sendPrettyMail($subject,$message,$email=null,$from=null,$options=[], $attachments=null){
    $archive=true;
    if (isset($options['archive']))
      $archive = $options['archive'];
    $this->logActive=$archive;
    $this->_modid=@$options['moid'];
    if (isset($options['mtype']))
      $this->_mtype=$options['mtype'];
    $sign = (isset($options['sign']) && !empty(@$options['sign']));
    $footer = null;

    if ($from != null){
      if(is_array($from) && isset($from['name'])){
	$this->From=$from['mail'];
	$this->FromName=$from['name'];
      }elseif(is_array($from)){
	$this->From=$from[0];
	$this->FromName=$from[1];
      }else{
	$froms = $this->extractAddresses($from);
	if (isset($froms[0]['mail'])){
	  $this->From=$froms[0]['mail'];
	  $this->FromName=$froms[0]['name'];
	} else {
	  $this->From=$froms[0];
	  $this->FromName='';
	}
      }
    }
    if (isset($options['reply-to'])){
      foreach($this->extractAddresses($options['reply-to']) as $rt){
	if (is_array($rt) && isset($rt['mail']))
	  $this->addReplyTo($rt['mail'], $rt['name']);
	else 
	  $this->addReplyTo($rt);
      }
    }

    if ($sign)
      $subject = $this->getTZRSubject($subject);
    if(preg_match("/^<(!DOCTYPE )?html>/",trim($message))) {
      $this->IsHTML(true);
      if($sign) 
	$message = $this->setTZRSign($message);
      $this->Subject = $subject;
      $this->Body = $message;
    } elseif(isset($options['text'])) {
      $this->IsHTML(false);
      if($sign) 
	$message = $this->setTZRSign(wordwrap($message, 65));
      else 
	$message = wordwrap($message, 65);
      $this->Subject = $subject;
      $this->Body = $message;
    } else {

      $this->IsHTML(true);
      if (empty($options['footer'])){
	$footer = $this->getFooter();
      } else {
	$footer = $options['footer'];
      }
      if (empty($options['subtitle'])){
	$subtitle = $this->getSubtitle();
      } else {
	$subtitle = $options['subtitle'];
      }
      $subject = $this->getSubject($subject, $options);

      $messageLayout = $this->getPrettyMailLayout(['subject'=>$subject,
						   'subtitle'=>$subtitle,
						   'message'=>$message,
						   'footer'=>$footer],
						  $options);
      if (empty($messageLayout)){
	\Seolan\Core\Logs::notice(__METHOD__,"unable to parse mail layout");
	$messageLayout = '{message}';
      }
      $subject = $this->replaceTags($subject, @$options['tags']);
      $footer = $this->replaceTags($footer, @$options['tags']);
      $message = $this->replaceTags($message, @$options['tags']);
      
      $mailBody = $this->replaceTags($messageLayout, @$options['tags'], 
				     ['subject'=>$subject,
				      'footer'=>$footer, 
				      'subtitle'=>$subtitle,
				      'message'=>$message]);

      $this->Subject = $subject;
      $this->Body = $mailBody;
    }

    if($attachments!=null && !empty($attachments['filename'])){
      $filename=$attachments['filename'];
      $filetitle=$attachments['title'];
      if(!is_array($filename)) 
	$filename=[$filename];
      if(!is_array($filetitle)) 
	$filetitle=[$filetitle];
      foreach($filename as $i=>$fn){
	$this->AddAttachment($filename[$i], $filetitle[$i]);
      }
    }
    if($attachments!=null && !empty($attachments['string'])){
      $this->AddStringAttachment($attachments['string'], $attachments['title'], "base64", $attachments['mime']);
    }

    if ($email != null) // cas ou on positionne les addresses directement
      $dest = $this->extractAddresses($email);

    if ($email==null){ // envoi direct si il existe des destinataires

      if (count($this->getAllRecipients())>0){
	$this->Send();
	if($archive) {
	  $dests = $this->getAllRecipientsString();
	  \Seolan\Core\Logs::update("mail sent", '',"To: $dests\nSubject: {$this->Subject}\n".$this->Body);
	}
      } else {
	$this->ErrorInfo .= 'No recipient';
      }
    } else {
      foreach($dest as $i=>$mail){
	$this->ClearAllRecipients();
	if(is_array($mail))
	  $this->AddAddress($mail['mail'],$mail['name']);
	else
	  $this->AddAddress($mail);
	$this->Send();
	if($archive){
	  $dests = $this->getAllRecipientsString();
	  \Seolan\Core\Logs::update("mail sent", '',"To: $dests\nSubject: {$this->Subject}\n".$this->Body);
	}
      }
    }
  }
  /**
   * retourne la mise en page à utiliser pour l'envoi d'un mail
   */
  protected function getPrettyMailLayout($mldata, $params){
    $r = null;
    if ((\Seolan\Core\Shell::admini_mode() && !isset($params['page']))
	|| isset($params['forcebo'])
	){
      $custom_styles = null;
      if (defined('TZR_USER_CSS_PATH') && file_exists(TZR_USER_CSS_PATH.'styles-mails.css'))
	$custom_styles = TZR_USER_CSS_PATH.'styles-mails.css';
      $r = [static::$boPrettyMailLayout, static::$boPrettyMailCss, $custom_styles];
    } else {
      if (isset($params['page'])){
	list($alias, $template, $moid) = $params['page'];
	$url = $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName().'/csx/scripts/index.php?'.http_build_query(['alias'=>$alias,
				  'tplentry'=>'it',
				  'function'=>'viewpage',
				  'moid'=>$moid,
				  '_cachepolicy'=>'nocache',
				  '_charset'=>'utf-8',
				  'template'=>$template,
				  'prettyMail'=>1]);
	$r = file_get_contents($url);
	\Seolan\Core\Logs::debug(__METHOD__."\n\turl : {$url}\n\tresponse headers !".implode("\n\t", $http_response_header));
      } elseif(isset($params['template'])){
	$r = [$params['template'], null, null];
      } 
    }
    if (is_array($r)){ // template
      list($template, $styles, $custom_styles) = $r;
      $xt = new \Seolan\Core\Template($template);
      if (isset($params['tpldata']))
	$tpldata = $params['tpldata'];
      else 
	$tpldata = [];

      $tpldata = array_merge(['ml'=>['custom_styles'=>$custom_styles,
				     'styles'=>$styles]], 
			     $tpldata);
      $tpldata['ml']['base'] = $GLOBALS['HOME_ROOT_URL'];
      $tpldata['ml']['subject'] = $mldata['subject'];
      $tpldata['ml']['message'] = $mldata['message'];
      $tpldata['ml']['subtitle'] = $mldata['subtitle'];
      $tpldata['ml']['footer'] = $mldata['footer'];
      $rawdata = [];
      $contents = $xt->parse($tpldata,$rawdata);
    } else { // page donnée
      $contents = $r;
    }
    return $contents;
  }
  /**
   * remplacement des basiles éventuelles {nomduchamp} par les valeurs du champ
   */
  protected function replaceTags($content, $tags=[], $mailsTags=[]){
    if (isset($options['html']))
      $fieldformat = 'html';
    else
      $fieldformat = 'text';
    static $previousOid=NULL;
    static $fields=NULL;
    static $xset=NULL;
    static $previousTable=NULL;
    $oid = $tags['oid']??NULL;
    if($previousOid!=$oid && \Seolan\Core\Kernel::isAKoid($oid)) {
      $table = \Seolan\Core\Kernel::getTable($oid);
      unset($previousOid);
      unset($fields);
      $previousOid=$oid;
      if ($table != $previousTable){
	$xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
	$previousTable = $table;
      }
      $fields = $xset->rDisplay($oid);
    }

    $siteurl = $GLOBALS['HOME_ROOT_URL'];  // ? societe_url de local.ini ?
    preg_match('@https?://([a-zA-Z.]+)@', $siteurl, $res);

    $fields_names = ['s0'=>'{sitename}','s1'=>'{siteurl}'];

    $fields_values = ['s0'=>$res[1],
		      's1'=>$siteurl];

    if (!empty($mailsTags)){
      foreach($mailsTags as $tagname=>$tagvalue){
	$fields_names["$tagname"] = '{'.$tagname.'}';
	$fields_values["$tagname"] = $tagvalue;
      }      
    }

    if (!empty($tags)){
      $nb=0;
      foreach($tags as $tagname=>$tagvalue){
	$nb++;
	$fields_names["$tagname $nb"] = '{'.$tagname.'}';
	$fields_values["$tagname $nb"] = $tagvalue;
      }      
    }
    if ($oid != null){
      $fields_names['a']='{oid}';
      $fields_names['b']='%3Coid%3E';
      $fields_names['c']='&lt;oid&gt;';
      $fields_values['a']=$fields_values['b']=$fields_values['sc']=$oid;
    }
    if (isset($tags['moid'])){
      $fields_names['d']='{moid}';
      $fields_names['e']='%3Cmoid%3E';
      $fields_values['d']=$fields_values['e']=$tags['moid'];
    }

    if($oid != null && is_array($fields)){
      foreach($fields['fields_object'] as $field) {
	$nb++;
	$fn = $field->fielddef->field;
	$fields_names["$fn $nb"]='{'.$fn.'}';
	$fields_values["$fn $nb"]=$field->$fieldformat;
      }
    }

    $text=str_replace($fields_names, $fields_values, $content);
    return $text;
  }
  /**
   * Extraction des nom / adresse d'un tableau d'émetteurs ou de destinataires
   * sont acceptés les chaines au format "addresse", "nom<adresse>[, ...]", les tableaux de ces chaines
   * ,un tableau associatif ['name'=>'nom','mail'=>'address'], ['name'=>'nom','email'=>'address']  et les tableaux de tableaux associatifs 
   * name/mail
   */
  protected function extractAddresses($mails){
    $result = [];
    if(!is_array($mails)){
      $parsedlist = $this->parseAddresses($mails);
      foreach($parsedlist as $parsed){
	if (empty($parsed['name']))
	  $result[] = $parsed['address'];
	else 
	  $result[] = ['name'=>$parsed['name'], 'mail'=>$parsed['address']];
      }
    } elseif(is_array($mails)){
      if (isset($mails['name'])){
	if (isset($mails['email'])) // name=>'', 'email'=>'' 
	  $result[] = ['name'=>$mails['name'], 'mail'=>$mails['email']];
	else // name=>'', mail=>'' 
	  $result[] = $mails;
      } else {
	foreach($mails as $mail){
	  if (is_array($mail))
	    $result[] = $mail;
	  elseif (is_string($mail)){
	    $parsed = $this->extractAddresses($mail);
	    if (count($parsed)== 1)
	      $result[] = $parsed[0];
	  } else {
	    \Seolan\Core\Logs::debug(__METHOD__,"$mail unknown format");
	  }
	}
      }
    }
    return $result;
  }
  /// Envoi d'un mail avec un template
  public function sendMailWithTemplate($template,$tpldata, $rawdata=[],$css2inline=false, $normalizebo=false) {
    $xt=new \Seolan\Core\Template($template);
    $labels=$GLOBALS['XSHELL']->labels->get_labels(array('selectors'=>array('global'),'local'=>true));
    $xt->set_glob(array('labels'=>&$labels));
    $content=$xt->parse($tpldata,$rawdata,NULL);
    // a tester et migrer (ccs2inline dans Vendor ? etc )
    if($ccs2inline){
	  include_once('add-ons/css2inline/css2inline.php');
	  if(class_exists('CSSToInlineStyles')){
	    $cssToInlineStyles = new CSSToInlineStyles($content);
	    $cssToInlineStyles->setUseInlineStylesBlock(true);
	    $content = $cssToInlineStyles->convert();
	  }
    }
    if ($normalizebo){
      $content = static::normalizeBoLinks($content);
    }
    $this->Body=$content;
    return $this->Send();
  }
  /// Transformations des liens BO pour accès direct
  public static function normalizeBoLinks($content,$fragment=true,$charset='utf-8'){
    $content=preg_replace('/('.session_name().'=[^&]+)/','',$content);
    $dom=new \DOMDocument();
    $dom->preserveWhiteSpace=false;
    $dom->validateOnParse=true;
    if ($fragment){
      $content=<<<EOD
	<html><head><meta content="text/html; charset=$charset" http-equiv="Content-Type"></head><body>$content</body></html>
EOD;
    } 
    $dom->loadHTML($content);
    $xpath=new \DOMXpath($dom);
    $as=$xpath->query('//a[contains(@class, "cv8-ajaxlink")]');
    foreach($as as $a){
      $a->setAttribute('href',$a->getAttribute('href').'&_direct=1');
    }
    if (!$fragment)
      return $dom->saveHTML();

    $bodyElements = $dom->getElementsByTagName('body');
    return preg_replace(['@^<body>@','@</body>$@'], '', $dom->saveHTML($bodyElements[0]));

  }
  /// Envoi du mail
  public function Send() {
    // nb de destinataires avant suppression des npai
    $destinataires=count($this->to)+count($this->cc)+count($this->bcc);
    // menage dans les destinataires pour vérifier les blacklist
    $this->checkBounce('to');
    $this->checkBounce('cc');
    $this->checkBounce('bcc');
    // nb de destinataires après suppression des npai
    $destinatairesAfterCheckBounce=count($this->to)+count($this->cc)+count($this->bcc);
    
    if($this->logActive){
      if(empty($this->loid)) $this->initLog();
      $add=count($this->to);
      $add+=count($this->cc);
      $add+=count($this->bcc);
      $this->nbdest+=$add;
    }

    // on évite de générer un message de log avec "Pas de destinataire" 
    // lorsqu'il s'agit d'un nettoyage lié au NAPI, mais on compte les erreurs totales
    if($destinataires>0 && $destinatairesAfterCheckBounce<=0){
      if($this->logActive)
	$this->nberr += $destinataires;
      if($this->autoAgregate) 
	$this->agregateLog();
      return false;
    }

    // redirection éventuelles en mode tests
    if (static::testMode()){
      $this->processRedirections();
    }
    
    $charset=\Seolan\Core\Ini::get('mail_charset');

    if(!empty($charset) && $this->CharSet!=$charset) {
      \Seolan\Core\Logs::notice(__METHOD__,"convert charsets : '{$this->CharSet}' '$charset'");
      foreach($this->to as $i=>&$tab) convert_charset($tab[1], $this->DefaultCharSet, $charset);
      foreach($this->cc as $i=>&$tab) convert_charset($tab[1], $this->DefaultCharSet, $charset);
      foreach($this->bcc as $i=>&$tab) convert_charset($tab[1], $this->DefaultCharSet, $charset);
      convert_charset($this->FromName, $this->DefaultCharSet, $charset);
      convert_charset($this->Subject, $this->DefaultCharSet, $charset);
      convert_charset($this->Body, $this->DefaultCharSet, $charset);
      convert_charset($this->AltBody, $this->DefaultCharSet, $charset);
      $this->Body=str_replace('charset='.$this->DefaultCharSet,'charset='.$charset,$this->Body);
      $this->CharSet=$charset;
    } 
    if($this->getIsHTML()){
      if($this->Body!=$this->prevBody){
	if(!empty($this->loid)){
          if (preg_match('/base href="([^"]+)"/', $this->Body, $matches)) {
            $counterurl = $matches[1] . (substr($matches[1], -1) == '/' ? '' : '/') . 'csx/scripts/counter8.php?';
          } else {
            $counterurl=str_replace('admin.php','counter8.php',$GLOBALS['TZR_SESSION_MANAGER']::admin_url(true));
          }
	  if(strpos($this->Body,'_src='.$this->loid.'&_dst=open')===false){
	    $dststring='';
	    if(count($this->to)<=2) {
	      foreach($this->to as $d) {
		if(is_array($d)) {
		  if(empty($dststring)) $dststring=$d[0];
		  else $dststring.=','.$d[0];
		}
	      }
	    }
	    $this->Body=preg_replace('/(<body.*>)/U','$1<img src="'.$counterurl.'_src='.$this->loid.'&_dst=open&_dstemail='.$dststring.'" width="0" height="0" alt="" />',
				     $this->Body,1);
	  }
	}
	$this->checkLinks();
	$this->checkImages();
	if($this->urlCounter) $this->activeUrlCounter();
      }
      if(empty($this->AltBody))
	$this->AltBody=$this->plainTextBody();
    }
    $this->prevBody=$this->Body;

    $ret=parent::Send();

    if($this->logActive){
      if($ret==false){
	$this->nberr+=$add;
	$this->addDetailLog(array('sstatus'=>'error','errmess'=>$this->ErrorInfo));
      }
      if($this->autoAgregate) 
	$this->agregateLog();
    }
    return $ret;
  }
  protected function plainTextBody(){
    return (new \Docxpresso\HTML2TEXT\HTML2TEXT($this->Body,
						['titles'=>'uppercase','cellSeparator'=>'']))->plainText();
  }

  // Mode test ?
  protected static function testMode(){
    if (static::$_testmode === null){
      static::$_testmode = \Seolan\Core\Module\Module::singletonFactory(XMODMAILLOGS_TOID)->testmode(true); // indépendament de tout IP
    }
    return static::$_testmode;
  }
  // traitement des redirections
  protected function processRedirections(){
    \Seolan\Core\Module\Module::singletonFactory(XMODMAILLOGS_TOID)->processMailRedirections($this);
  }
  // Purge les bounces de la liste des destinataires fournis en paramètre
  function checkBounce($source){
    $maillogs = \Seolan\Core\Module\Module::singletonFactory(XMODMAILLOGS_TOID);
    foreach($this->$source as $i=>&$email){
      $blacklisted=$maillogs->isBlacklisted($email[0]);
      if($blacklisted){
        unset($this->all_recipients[strtolower($email[0])]);
        unset($this->{$source}[$i]);
      }
    }
  }

  /// Initialise une entree log dans la table _MLOGS et rend son oid
  function initLog($ar=NULL){
    if(empty($ar['data'])) $ar['data']=$this->_data;
    if(!empty($ar['data']) && is_array($ar['data'])){
      $data=$ar['data'];
      $ar['data']='';
      \Seolan\Core\System::array2xml($data,$ar['data']);
    }
    if(empty($ar['subject'])) $ar['subject']=$this->Subject;
    if(empty($ar['sender'])) $ar['sender']=$this->From;
    if(empty($ar['modid'])) @$ar['modid']=$this->_modid;
    if(empty($ar['mtype'])) @$ar['mtype']=$this->_mtype;
    if(empty($ar['html'])) $ar['html']=$this->getIsHTML();
    $this->setBodyParms($ar);
    if(empty($ar['size'])) $ar['size']=round(strlen($this->Body)/1024,2);
    if(empty($ar['datep'])) $ar['datep']=date('Y-m-d H:i:s');
    if ($this->_mtype != 'mailing') {
      $ar['dest'] = implode(',', array_map(function($e){return $e[0];}, $this->to));
    }
    $ar['link'] = $this->_link;
    $ar['_nolog']=true;
    $ar['_options']['local']=true;
    if (!empty($ar['data'])){
      $ar['options']['data']['raw']=1;
    }
    $ar['tplentry']=TZR_RETURN_DATA;
    $r=self::$xsetl->procInput($ar);
    $this->loid=$r['oid'];
    return $r['oid'];
  }
  /// Encodage des arguments liés au body
  protected function setBodyParms(&$ar){
    if ($this->getIsHTML() || isset($ar['html'])){
      if(empty($ar['body'])){
	$ar['bodyfile']=$this->makeBodyFile($this->Body);
      } else {
	if (empty($ar['size']))
	  $ar['size'] = round(strlen($ar['body'])/1024,2);
	$ar['bodyfile']=$this->makeBodyFile($ar['body']);
	$ar['body'] = null;
      }
    } else {
      if(empty($ar['body'])) $ar['body']=$this->Body;
    }
  }
  /// Stockage du body dans un fichier temporaire, pour insertion en base
  protected function makeBodyFile($body){
    $file = TZR_TMP_DIR.uniqid('eml').'.html';
    file_put_contents($file, $body);
    return ['tmp_name'=>$file,
	    'type'=>'text/html',
	    'name'=>'message contents '.date('Y-m-d h:i:s'),
	    'title'=>'message contents '.date('Y-m-d h:i:s')
	    ];
  }
  /// Lecture du body stocké dans le fichier
  protected function bodyFromFile($fieldValue){
    return file_get_contents($fieldValue->filename);
  }
  /// Edite un log
  function editLog($ar=NULL){
    if(empty($ar['oid'])) $ar['oid']=$this->loid;
    $ar['_nolog']=true;
    $ar['_options']['local']=true;
    $ar['tplentry']=TZR_RETURN_DATA;
    $r=self::$xsetl->procEdit($ar);
    return $r['oid'];
  }

  /// Ajoute une fiche détail
  function addDetailLog($ar=NULL){
    if(empty($this->loid)){
      \Seolan\Core\Logs::critical(get_class($this), get_class($this).'::add report not initialized');
      return;
    }
    if(empty($ar['subject'])) $ar['subject']=$this->Subject;
    $this->setBodyParms($ar);

    if(empty($ar['mails'])){
      $mails=array();
      foreach($this->to as $i=>$tab) $mails[]=$tab[0];
      foreach($this->cc as $i=>$tab) $mails[]=$tab[0];
      // ne pas prendre les non visibles (<- en ré envoi, voir sendQueued)
      //foreach($this->bcc as $i=>$tab) $mails[]=$tab[0];
      $ar['mails']=implode(',',$mails);
    }
    if(empty($ar['files'])){
      $files=array('tmp_name'=>array(),'type'=>array(),'name'=>array(),'title'=>array());
      foreach($this->attachment as &$file){
	if($file[6]=='attachment' && $file[5]==false){
	  $files['tmp_name'][]=$file[0];
	  $files['type'][]=$file[4];
	  $files['name'][]=$file[2];
	  $files['title'][]='';
	}
      }
      $ar['files']=&$files;
      $ar['options']['files']['del']=false;
    }
    $ar['_nolog']=true;
    $ar['_options']['local']=true;
    $ar['tplentry']=TZR_RETURN_DATA;
    $ar['mlogh']=$this->loid;
    $r=self::$xsetld->procInput($ar);
    return $r['oid'];
  }

  /// Agrege les logs
  function agregateLog() {
    getDB()->execute('update _MLOGS set nbdest=?, nberr=? where KOID=?', array($this->nbdest,$this->nberr,$this->loid));
  }

  /// Active le compteur de clic sur des liens
  public function activeUrlCounter(){
    $loid=$this->loid;
    if (preg_match('/base href="([^"]+)"/', $this->Body, $matches)) {
      $siteurl = $matches[1] . (substr($matches[1], -1) == '/' ? '' : '/') . 'csx/scripts/counter8.php?';
    } else {
      $siteurl=str_replace('admin.php','counter8.php',\Seolan\Core\Session::admin_url(true));
    }
    $dststring='';
    if(count($this->to)<=2) {
      foreach($this->to as $d) {
	if(is_array($d)) {
	  if(empty($dststring)) $dststring=$d[0];
	  else $dststring.=','.$d[0];
	}
      }
    }      
    if($this->loid && \Seolan\Core\System::tableExists('_LINKS')) {
      $this->Body = preg_replace_callback('/<a(.*)href="([^"]+)"/Ui',function($m) use ($loid,$siteurl,$dststring){
	return '<a'.$m[1].'href="'.$siteurl.'&_dstemail='.$dststring.'&_src='.$loid.'&_dst='.urlencode(str_replace('&amp;','&',$m[2])).'"';
      },$this->Body);
    }
  }

  /// Verfie tous les chemins de fichier pour les mettre en absolu
  public function checkLinks(){
    $domain=$GLOBALS['TZR_SESSION_MANAGER']::makeDomainName();
    $this->Body=preg_replace('/(href|src)="(\/[^"]+)"/Us','$1="'.$domain.'$2"',$this->Body);
  }

  /// remplace les image base 64 en attachment cid
  public function checkImages() {
    $matches = [];
    $imageid = 0;
    preg_match_all('/<img src="(data:image\/(jpeg|gif|png);base64,(.*))"/U', $this->Body, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $cidname = 'cidimg' . $imageid++;
      $this->addStringEmbeddedImage(base64_decode($match[3]), $cidname, '', 'base64', "image/$match[2]");
      $this->Body = str_replace($match[1], 'cid:' . $cidname, $this->Body);
    }
  }

  /// Retourne 1 si le mail est de type html et 0 sinon
  public function getIsHTML(){
    if($this->ContentType!='text/plain') return 1;
    else return 0;
  }
  public function getSubject($subject, $options=null){
    if (isset($options['subjectPrefix']) && $options['subjectPrefix'] === false )
      return $subject;
    else
      return \Seolan\Core\Ini::get('societe').' - '.$subject;
  }
  public function getSubtitle(){
    $url = \Seolan\Core\Ini::get('societe_url');
    return $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_subtitle','text').$GLOBALS['XSHELL']->labels->getSysLabel('Seolan_Core_General','i18ncolon', 'text').'<a href="'.$url.'">'.\Seolan\Core\Ini::get('societe').' - '.$url.'</a>';
  }
  public function getFooter(){
    $url = \Seolan\Core\Ini::get('societe_url');
    return '<a href="'.$url.'">'.\Seolan\Core\Ini::get('societe').' - '.$url.'</a>';
  }
  /// Definit le sujet du mail en ajoutant le prefixe TZR
  public function setTZRSubject($subject){
    $this->Subject=$GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_prefix','mail').' '.$subject;
  }
  public function getTZRSubject($subject){
    return $GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_prefix','mail').' '.$subject;
  }
  /// Definit le corps du mail en ajoutant la signature TZR
  public function setTZRBody($body){
    if($this->getIsHTML()) $this->Body=$body.'<br><br>'.sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_html_signature','mail'),$GLOBALS['HOME_ROOT_URL']);
    else $this->Body=$body."\n".sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_signature','mail'),$GLOBALS['HOME_ROOT_URL']);;
  }
  public function setTZRSign($body){
    if($this->getIsHTML()) 
      return $body.'<br><br>'.sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_html_signature','mail'),$GLOBALS['HOME_ROOT_URL']);
    else 
      return $body."\n".sprintf($GLOBALS['XSHELL']->labels->getCustomSysLabel('Seolan_Core_General','mail_signature','mail'),$GLOBALS['HOME_ROOT_URL']);;
  }

  /// Traite la file d'attente
  static function sendQueuedMails(){
    $xmail=new \Seolan\Library\Mail();
    $xmail->autoAgregate=false;
    $xmail->logActive=false;
    // Renvoie tous les mails en erreur (3 tentatives max)
    $rs=getDB()->select('select * from _MLOGSD where sstatus="error" and reex<3');
    while($rs && ($ors=$rs->fetch())){
      $d=self::$xsetld->display(array('oid'=>$ors['KOID'],'tplentry'=>TZR_RETURN_DATA,'selectedfields'=>array('files','bodyfile')));
      $ors2=getDB()->fetchRow('select * from _MLOGS where KOID=?',array($ors['mlogh']));
      $xmail->ClearAllRecipients();
      $xmail->Subject=$ors['subject'];
      $xmail->From=$ors2['sender'];
      $mails=explode(',',$ors['mails']);
      foreach($mails as $mail) $xmail->AddAddress($mail);
      if($ors2['html']==1){
	$xmail->IsHTML(true);
	$xmail->Body = $xmail->bodyFromFile($d['obodyfile']);
      } else {
	$xmail->Body=$ors['body'];
      }
      foreach($d['ofiles']->catalog as $i=>&$file){
	$xmail->AddAttachment($file->filename,$file->originalname);
      }
      $ret=$xmail->Send();
      if($ret){
	getDB()->execute('delete from _MLOGSD where KOID=?', array($ors['KOID']));
	getDB()->execute('update _MLOGS set nberr=nberr-1 where KOID=?',
			 array($ors['mlogh']));
      }else{
	getDB()->execute('update _MLOGSD set reex=reex+1 where KOID=?', array($ors['KOID']));
      }
    }
  }
  
  public function getAllRecipient () {
    return $this->all_recipients;
  }
}
?>
