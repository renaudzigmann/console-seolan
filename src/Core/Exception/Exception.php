<?php
namespace Seolan\Core\Exception;
/**
 * Class Seolan\Core\Exception\Exception
 * Exception à utiliser lors d'une exception propre à la logique Seolan.
 * Attraper une SeolanException au lieu d'une Exception permet de n'attraper que des exceptions Seolan et de ne plus
 * attraper les exception PHP Standarts (notamment les exceptions SPL (http://php.net/manual/fr/spl.exceptions.php).
 */
class Exception extends \Exception {
  protected $http_header = '';
  
  public function __construct($message = "Critiqual error", $code = 500, \Exception $previous = Null) {
    parent::__construct($message, $code, $previous);
  }
  
  public function getHttpHeader() {
   $header = get_header_for_code($this->getCode());
   $this->http_header = $header;
   return $this->http_header;
  }
  
}

