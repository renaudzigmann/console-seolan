<?php
namespace UnitTests;
use \Seolan\Library\Mail;
use \Seolan\Core\Module\Module;

/**
 * tests des envois de mails
 * -redirection en modetest
 */
class MailTests extends BaseCase {
  private static $subject = 'Mail tests unitaires redirection de mails';
  private static $body = 'Mail tests unitaires redirection de mails';
  private $methodsget = ['getToAddresses','getCcAddresses', 'getBccAddresses'];
  private $methodsadd = ['addAddress', 'addCC','addBCC'];
  private $methodstext = ['to', 'cc','bcc'];
  public function initCase($name){
    parent::initCase($name);
  }
  public static function setUpBeforeClass(){
    parent::setUpBeforeClass();
    // save d'une conf éventuelle, restaurée en clearfixtures
    static::trace("backup {$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php ");
    if (file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php")
      && !file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu")
    ){
      copy("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php",
	   "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu");
    }
  }
  /**
   * ne pas remplacer mais ajouter dans la conf ?
   */
  public function testMailRedirect(){

    $mod = Module::singletonFactory(XMODMAILLOGS_TOID);

    $confFile = "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php";
    $redirectto1 = 'rr+A@xsalto.com';
    $redirectto2 = 'rr+B@xsalto.com';
    $conf = var_export([
      'toid:'.XMODMAILLOGS_TOID=>['options'=>[
	'redirect_rules'=>[
	  'redirect_to_addresses'=>[$redirectto1, $redirectto2],
	  'granted_addresses'=>[
	    '@somme-granted-domain.org',
	    '@an-other-grandted-domain.com',
	    'auth-user1|auth-user2'
	  ]
	]
      ]
      ]
    ], true);

    $conf = "<?php\n return $conf ;\n";

    file_put_contents($confFile,$conf);

   
    $mail1 = new Mail();
    $mail1->Subject = static::$subject;
    $mail1->Body = static::$body;
    $mail1->IsHtml(false);
    $testaddr = 'johndoe@somme-granted-domain.org';
    $testname = 'John Doe';

    foreach($this->methodsadd as $i=>$method){
      $mail1->$method($this->methodstext[$i].$testaddr, $this->methodstext[$i].' '.$testname);
    }
      
    // ? replyto ?
    
    $mod->_testmode = $mod->testmode = false;

    $mod->processMailRedirections($mail1);
    
    // rien ne doit changer
    foreach($this->methodsget as $i=>$method){

      $addresses = $this->rawAddresses($mail1->$method());

      $this->assertEquals(count($addresses), 1, "nombre de destinataires inchangé");
      $this->assertEquals($addresses[0], $this->methodstext[$i].$testaddr ,"adresse destinataire inchangée");
      
    }

    // en mode tests, les adresses doivent être corrigées
    $mod->testmode = $mod->_testmode = true;
    
    $testaddr2 = 'janedoe@somme-ungranted-domain.com';
    $testname2 = 'Jane Doe';

    foreach($this->methodsadd as $i=>$method){
      $mail1->$method($this->methodstext[$i].$testaddr2, $this->methodstext[$i].' '.$testname2);
    }

    // adresses sur regexp tout domaine
    foreach(['somme-ungranted-domain.com', 'xsalto.com'] as $domain){
      $mail1->addAddress("auth-user1@{$domain}", "auth-user1@{$domain} name");
      $mail1->addAddress("auth-user2@{$domain}", "auth-user2@{$domain} name");
    }
    // vérfication de l'ajout effectif
    foreach($this->methodsget as $i=>$method){
      
      $addresses = $this->rawAddresses($mail1->$method());
      if ($this->methodstext[$i] == 'to')
	$expected = 6;
      else
	$expected = 2;
      $this->assertEquals($expected, count($addresses), "nombre de destinataires {$this->methodstext[$i]} avant redirection");
      $this->assertEquals($addresses[0], $this->methodstext[$i].$testaddr ,"adresses destinataires {$this->methodstext[$i]} avant redirection ");
      $this->assertEquals($addresses[1], $this->methodstext[$i].$testaddr2 ,"adresses destinataires  {$this->methodstext[$i]} avant redirection ");
     
    }

    // certaines adresses doivent sauter et d'autres être ajoutées
    // le sujet et le body sont modifiés pour marquer la redirection
    
    $mod->processMailRedirections($mail1);

    foreach($this->methodsget as $i=>$method){
      
      $addresses = $this->rawAddresses($mail1->$method());

      if ($this->methodstext[$i] == 'to') // on a ajoute 2 redirectto en pcpe et on en a enlevé 1
	$expected = 7;
      else
	$expected = 1;
      // 1 + les 2 redirectto
      $this->assertEquals($expected, count($addresses), "nombre de destinataires final {$expected} ".count($addresses)." {$this->methodstext[$i]}");
      $this->assertMailContainsAddress($mail1, [$this->methodstext[$i].$testaddr, $this->methodstext[$i].' '.$testname, $this->methodstext[$i], true]);
      $this->assertMailContainsAddress($mail1, [$redirectto1, null, 'to', false]);
      $this->assertMailContainsAddress($mail1, [$redirectto2, null, 'to', false]);
      
    }

    // les auth-user qui passe par la regexp |
    foreach(['somme-ungranted-domain.com', 'xsalto.com'] as $domain){
      $this->assertMailContainsAddress($mail1, ["auth-user1@{$domain}", "auth-user1@{$domain} name", 'to', true]);
      $this->assertMailContainsAddress($mail1, ["auth-user2@{$domain}", "auth-user2@{$domain} name", 'to', true]);
    }

    $this->traceMail($mail1);
    
  }
  /**
   * => on doit tout rejeter sur TZR_DEBUG_ADDRESS
   */
  public function testMailRedirectWithoutRules(){

    $mod = Module::singletonFactory(XMODMAILLOGS_TOID);
    $mod->testmode = $mod->_testmode = true;

    // on mets une conf vide : on doit tout rediriger sur TZR_DEBUG_ADDRESS
    $confFile = "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php";
    $conf = var_export(['toid:'.XMODMAILLOGS_TOID=>['options'=>[]]], true);
    
    $conf = "<?php\n return $conf ;\n";

    file_put_contents($confFile,$conf);
    
   
    $mail1 = new Mail();
    $mail1->Subject = static::$subject;
    $mail1->Body = static::$body;
    $mail1->IsHtml(false);
    $testaddr = 'johndoe@somme-granted-domain.org';
    $testname = 'John Doe';
    
    foreach($this->methodsadd as $i=>$method){
      $mail1->$method($this->methodstext[$i].$testaddr, $this->methodstext[$i].' '.$testname);
    }

    $mod->processMailRedirections($mail1);

    foreach($this->methodsget as $i=>$method){
      if ($this->methodstext[$i] == 'to') // on a ajoute TZR_DEBUG_ADDRESS en pcpe
	$expected = 1; 
      else
	$expected = 0;
      $addresses = $this->rawAddresses($mail1->$method());
      $this->assertEquals($expected, count($addresses), "nombre de {$this->methodstext[$i]} destinataires without rules ");
    }
    
  }
  protected function traceMail($mail){

    static::trace("Custom Headers");
    static::trace($mail->getCustomHeaders());
    static::trace("To");
    static::trace($mail->getToAddresses());
    static::trace("Cc");
    static::trace($mail->getCcAddresses());
    static::trace("Bcc");
    static::trace($mail->getBccAddresses());
  }
  protected function rawAddresses($addresses){
    $ret = [];
    foreach($addresses as $addr){
      $ret[] = $addr[0];
    }
    return $ret;
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    unlink("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php"); 
    if (file_exists("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu")){
      copy("{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php.back.tu",
	     "{$GLOBALS['LOCALLIBTHEZORRO']}config/modules-configuration.php");
    }
  }
}
