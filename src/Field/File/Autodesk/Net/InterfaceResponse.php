<?php
namespace Seolan\Field\File\Autodesk\Net;

/**
 * encapsulation d'un réponse et de ces statuts
 * => todo : extends \Seolan\Core\JsonClient\Response ou corriger autodesk
 */

class InterFaceResponse {

  private const HTTP_OK_NOCONTENT = 204;
  private const HTTP_OK = 200;
  private const HTTP_CREATED = 201;
  private const HTTP_NOT_FOUND = 404;

  function __construct($status, $data, $headers){
    $this->status = $status;
    $this->data = $data;
    $this->headers = $headers;
  }
  
  public function getStatus(){
    return $this->status;
  }
  
  public function getHeaders(){
        return $this->headers;
  }  
  public function getData($modeArray = true) {
    if ($this->status  == static::HTTP_OK_NOCONTENT){
      return NULL;
    }
    if (in_array($this->status, [static::HTTP_OK, static::HTTP_CREATED]) ) {
      return json_decode($this->data, $modeArray);
    } else {
      if($this->data !== null) {
	$data = json_decode($this->data);
	$errors = [];
	if (isset($data->errors)){
	  foreach ($data->errors as $error) {
	    if (isset($error->source))
	      $errors[] = $error->status . ' ' . $error->detail . ' (' . $error->source->pointer . ')';
	    else
	      $errors[] = $error->status . ' ' . $error->detail;
	  }
	}
	if (is_object($data) && isset($data->reason)){
	  $errors[] = $data->reason;
	} elseif (is_array($data) && isset($data['reason'])){
	  $errors[] = $data['reason'];
	}
	throw new \Seolan\Core\Exception\Exception(implode(', ', $errors), $this->status);
      } else {
	throw new \Seolan\Core\Exception\Exception('Status ' . $this->status);
      }
    }  
  }
  function notFound(){
    return in_array($this->status, [static::HTTP_NOT_FOUND]);
  }
  function ok(){
    return in_array($this->status, [static::HTTP_OK]);
  }
}
