<?php
namespace UnitTests\Constraint;

use \PHPUnit\Framework\Constraint\Constraint;

/**
 * Vérfie que le Mail contient une adresse donnée en to, cc ou bcc
 */

class MailContainsAddress extends Constraint {
  
  private $_types = ['to'=>'getToAddresses',
		     'cc'=>'getCcAddresses',
		     'bcc'=>'getBccAddresses'];
  
  public function __construct(\Seolan\Library\Mail $mail)  {
    parent::__construct(); // !!!
    $this->mail = $mail;
  }
  // bool est un tableau avec adresse, nom, to|cc|bcc, strict
  public function matches($other): bool {
    list($email, $name, $type, $strict) = $other;
    if (!array_key_exists($type, $this->_types))
      return false;
    $method = $this->_types[$type];
    $addresses = $this->mail->$method();
    $ok = false;
    foreach($addresses as $addr){
      if ($addr[0] == $email && $strict && $addr[1] == $name){
	$ok = true;
	break;
      } elseif($addr[0] == $email && !$strict){
	$ok = true;
	break;
      }
    }
    return $ok;
  }
  public function toString(): string  {
    return "mail contains required recipient";
  }
  
}
