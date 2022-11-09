<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of IcalInserter
 *
 * @author blegal
 */
class IcalInserter {
    
    private $allday = 2;
    private $location = null;
    private $description = null;
    private $dates;
    private $exdate = '';
    private $rrule = '';
    private $summary;
    private $event_koid = null;
    private $attendee = '';
    private $trigger = 0;
    private $time_zone;
    private $categories;
    private $repet = 'NO';
    private $xmod = NULL;
    public function __construct(Request$request,$xmod) {
        $calendar = $request->parsed_calendar_ics->cal;
        $event = $calendar['VEVENT'][0];
	$this->xmod = $xmod;
        $this->set_time_zone($xmod,$calendar);
        $this->set_location($event);
        $this->set_description($event);
        $this->set_exDate($event);
        $this->summary = $event['SUMMARY'];
        $this->set_rrule($event);
        $this->set_dates($event, $this->time_zone);
        $this->set_event_koid($request);
        $this->set_attendee($event);
        $this->set_alert($calendar['VALARM']);
        $this->set_categorie($event['CATEGORIES'],$xmod);
        $this->insert($xmod, $request);         
	
    }
    
    private function set_categorie($categorie_name,$xmod) {
        
        $this->categories = $this->get_categorie($categorie_name, $xmod);
    }
    
    private function set_categories($categories,$xmod) {
        
        foreach ($categories as $categorie_name){
            
                $this->categories .= $this->get_categorie($categorie_name,$xmod);
        }
    }
    
    private function get_categorie($categorie_name,$xmod) {
        
        foreach ($xmod->categories as $existing_categorie_koid => $existing_categorie_name) {
            if ($existing_categorie_name['name'] === $categorie_name) {
                $categorie_koid = $existing_categorie_koid;
            }
        }
        if (!$categorie_koid) {
            $categorie_koid = $xmod->tcatevt.':'.uniqid();
            $upd =  DateTime::createFromFormat('YmdHis', \Seolan\Field\Timestamp\Timestamp::default_timestamp());
            $diary = $xmod->diary;
            $query = 'INSERT INTO '. $xmod->tcatevt." VALUES (?,?,?,?,?,NULL,'0','60','2','2')";
            getDB()->execute($query, array($categorie_koid,$diary['LANG'],$upd->date,$diary['OWN'],$categorie_name));      
        }
        return $categorie_koid;
    }
    
    
    
    private function set_time_zone($xmod,$calendar) {
        if (isset($calendar['VTIMEZONE']['TZID'])) {
            $this->time_zone = new DateTimeZone($calendar['VTIMEZONE']['TZID']);
        }
        else {
            
            $this->time_zone = new DateTimeZone($xmod->diary['tz']);
        }
    }
    
    private function set_alert($alarm) {
        
        
        if(isset($alarm['TRIGGER;VALUE=DURATION'])) {
            $trigger = $alarm['TRIGGER;VALUE=DURATION'];
        }
        if(isset($alarm['TRIGGER'])) {
            $trigger = $alarm['TRIGGER'];
        }
        if($trigger){
            $duration  = array();
            $trigger = str_ireplace('-', '', $trigger);
	    preg_match ('/^P([0-9]{1,2}[W])?([0-9]{1,2}[D])?([T]{0,1})?([0-9]{1,2}[H])?([0-9]{1,2}[M])?/', $trigger, $duration); 
	    $alw       = str_ireplace('W', '', $duration[1]); 
	    $ald       = str_ireplace('D', '', $duration[2]); 
	    $alh       = str_ireplace('H', '', $duration[4]); 
	    $alm       = str_ireplace('M', '', $duration[5]); 
	    $this->trigger   = ($alw * 60 * 24 * 7) + ($ald * 60 * 24) + ($alh * 60) + ($alm);
        }
    }
    
    private function set_location($event) {
        
        if (isset($event['LOCATION'])) {
           
            $this->location =  $event['LOCATION'];
        }        
    }
    
    private function set_description($event) {
        
        if (isset($event['DESCRIPTION'])) {
           
            $this->description =  $event['DESCRIPTION'];
        }             
    }
    
    private function set_exDate($event) {
        
        if( isset($event['EXDATE'])) {
             
            foreach ($event['EXDATE'] as $raw_ex_date) {
                
            $ex_full_date = $this->toTimeStamp($raw_ex_date);
            $this->exdate .= $ex_full_date['date'] . ';';
            }           
        }      
    }
    
    private function set_rrule($event) {
        
        if (isset($event['RRULE'])) {
            
            $this->rrule = $event['RRULE'];
        }
        
    }
    
    private function set_dates($event) {
        
        $this->dates['DTSTART'] = $this->toTimeStamp($event['DTSTART']);
        $this->dates['DTEND'] = $this->toTimeStamp($event['DTEND']);
    }
    
    private function set_event_koid($request) {
        
        $query = 'SELECT KOID FROM '.$this->xmod->tevt.' WHERE KOID = ? OR UIDI = ?';
        $res = getDB()->fetchCol($query, array($this->xmod->tevt.':'.$request->event_uid,$request->event_uid));
            
        if ($res) {
                
                $this->event_koid = $res[0];
                $request->method = 'UPDATE';
            }
    }
    
    private function set_attendee($event) {
       
        if(isset($event['ATTENDEE'])) {
            
            $this->attendee = $event['ATTENDEE'];
        }
        
    }

    /// @todo : suppress $xmod 
    private function insert($xmod,$request) {
               
        $ar = [
            "calendar_koid" => $this->xmod->tagenda.":".$request->calendar_koid,
            "text" => $this->summary,
            "place" => $this->location,
            "descr" =>$this->description,
            "recalltime" => '0',
            "recalltype" => '1',
            "cat" => $this->categories,
            "begin" => $this->dates['DTSTART'],
            "end" => $this->dates['DTEND'],
            "allday" => $this->allday,
            "UIDI" => $request->event_uid,
            "until" => 'NULL',
            "repetition" => 'NO',
            "koid" => $this->event_koid,
            "caldav_request" => true,
            "rrule" => $this->rrule,
            "except" => $this->exdate,
            "attext" => $this->attendee,
            "trigger" => $this->trigger,
            'local'=>true         
        ];
        $xmod->saveEvt($ar);
    }
    /// @todo : check method call and suppress $time_zone
    /// or use time_zone
    private function toTimeStamp($date,$time_zone=NULL) {
        
        $time_stamp_date = DateTime::createFromFormat('Ymd?His', $date,$this->time_zone);
        if(!$time_stamp_date) {
            
            $time_stamp_date = DateTime::createFromFormat('Ymd?His?', $date,new DateTimeZone("UTC"));
            $time_stamp_date->setTimeZone($this->time_zone);           
                if(!$time_stamp_date) {
                    
                $time_stamp_date = DateTime::createFromFormat('Ymd', $date);
                $this->allday = 1;
            }
        }
        $date =  date_format($time_stamp_date, 'Y-m-d H:i');
        list($split_date['date'],$split_date['hour']) = explode(" ", $date);
        return $split_date;
        
    }
        
        
}
