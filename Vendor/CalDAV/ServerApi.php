<?php


/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ServerApi
 *
 * @author blegal
 */
//require_once('Request.php');
//require_once('ParsedBody.php');
require_once('Response.php');
require_once('Request.php');

class SApi {
    
    // catch the http request
    
    public function getRequest($xmod)
    {
        $request = new Request($_SERVER,$_REQUEST,$xmod);        
        return $request;
        
    }
    
    // Send a http response from a cadldav request
    
    
    public function sendResponse($response)
    {
      if (headers_sent($filename, $fileline)){
	$headers = headers_list();
	\Seolan\Core\Logs::notice(__METHOD__," header already sent : {$filename} {$fileline} ".tzr_var_dump($headers));
      } else {
	\Seolan\Core\Logs::debug(__METHOD__." setting headers");
      }
      $txt='';
      if(isset($response->headers)){
            
	foreach ($response->headers as $header){
	  header($header);
	  $txt.=$header;
	}
      }
      
      \Seolan\Core\Logs::debug(__METHOD__." synchro calendar global response body : ".strlen($response->body));
      file_put_contents('php://output', $response->body);
	
    }
    
}
