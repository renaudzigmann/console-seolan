<?php
namespace Seolan\Core\Exception;

class Forbidden extends \Seolan\Core\Exception\Exception {
  
  protected $http_header = 'HTTP/1.1 403 Forbidden';
  
  public function __construct($message = "Forbidden ressource", $code = 403, Exception $previous = Null) {
    parent::__construct($message, $code, $previous);
  }
  
}
