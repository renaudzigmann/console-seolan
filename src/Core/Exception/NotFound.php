<?php
namespace Seolan\Core\Exception;

/**
 * Class NotFoundException
 * Exception à utiliser lorsque l'objet de la demande n'a pu être trouvé.
 */
class NotFound extends \Seolan\Core\Exception\Exception {
  public function __construct($message = "Critiqual error", $code = 500, Exception $previous = Null) {
    $message = 'NotFound : '.$message ;
    parent::__construct($message, $code, $previous);
  }
}