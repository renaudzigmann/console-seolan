<?php
/**
 * Get all the needed informations from a CalDAV request
 * @todo fix $xmod versus $this->xmod in methods and methods calls
 * @author blegal
 */
class Request {
 
    // http method
    public $method;
    
    // http depth
    public $depth = 'none';
    
    // requested uri
    public $uri;
     
    //Name of the user
    public $user;
    
    // koid of the calendar if requested
    public $calendar_koid;
    
    // koid of the event if requested
    public $event_uid;
    
    // id of the xcalendar mod
    public $moid;
    
    
    public $parsed_calendar_ics;
    
    
    public $parsed_xml;
    
    private $xmod = NULL;

    public function __construct($server,$request,$xmod) {
        
        $this->xmod = $xmod;        
        $this->get_headers($server,$request);
        $this->parse_body($xmod);
        
    }
 
    //
        
    private function get_headers($server,$request) {
        
        $this->method = $server["REQUEST_METHOD"];
        $this->uri = rawurldecode($server["REQUEST_URI"]);
        $this->user = $request['login'];
        $this->calendar_koid = $request['oid'];
	if(!empty($request['uidi']))
	  $this->event_uid = str_replace(".ics", "", $request['uidi']);
        $this->moid = $request['moid'];
        $this->client = $server['HTTP_USER_AGENT'];
        
        if ( isset($server["HTTP_DEPTH"])) {
            
            $this->depth = $server["HTTP_DEPTH"];
        }
    }
    
    /** 
     * @todo : check if ForbiddenReportException is thrown. Seems not to be ? 
     * Instead Exception with message = "forbidden" is thrown
     * 
     */
    private function parse_body($xmod) {
        
        $stream_body_request = fopen('php://input','rb');
        $this->body = stream_get_contents($stream_body_request);
        \Seolan\Core\Logs::debug(get_class($this).'caldav::parse_body method is '.$this->method);
        \Seolan\Core\Logs::debug(get_class($this).'caldav::parse_body body content '.$this->body);
        if($this->body !== "" && $this->method !== 'PUT') {
            
            require_once 'exceptions/ForbiddenReportException.php';
            require_once 'XmlParser.php';
            
            try {
                $this->parsed_xml = new XmlParser($this->body);
            }
            //catch(ForbiddenReportException $e){
            catch(Exception $e){
                
                $this->method = 'FORBIDDEN';
            }
        }
        
        if($this->method === 'PUT') {
            //\Seolan\Core\Logs::debug(get_class($this).'::parse_body '.var_export($this, true));
            $query = 'SELECT KOID,KOIDD FROM '.$this->xmod->tevt.' WHERE KOID = ? OR UIDI = ?';
            $res = getDB()->fetchRow($query, array($this->xmod->tevt.':'.$this->event_uid, $this->event_uid));
            
        if ($res) {                
                if($res['KOIDD'] ===$this->xmod->tagenda.':'.$this->calendar_koid){
                    $this->method = 'UPDATE';
                }     
            }
        
        
        }
    }

    public function printRequest() {
        /* RZ TO BE DELETED
        $req = var_export($this, true);
        file_get_contents("/home/blegal/Bureau/Caldav.log", "REQUETE CLIENT : " .$req."\n",FILE_APPEND);
        file_get_contents("/home/blegal/Bureau/Caldav.log", "BODY : " .$this->body."\n",FILE_APPEND);
        */
    }    
    
}
