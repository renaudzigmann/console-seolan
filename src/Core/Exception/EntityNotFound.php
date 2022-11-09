<?php
namespace Seolan\Core\Exception;
class EntityNotFound extends \Seolan\Core\Exception\Exception {
// not commited EntityAccessException in 8.2
  
  protected $http_header = 'HTTP/1.1 404 Not Found';
  
  public function __construct($message = "Entity not found", $code = 404, Exception $previous = Null) {
    $message = 'EntityNotFoundException : '.$message ;
    
    parent::__construct($message, $code, $previous);
  }
  
}
