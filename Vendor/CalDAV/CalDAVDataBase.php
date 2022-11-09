<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CalDAVDataBase
 *
 * @author blegal
 */
class CalDAVDataBase {
    //put your code here
    // Vcollection Request
    static function get_user_email($user) {
      $user = \Seolan\Core\User::get_user();
      return (isset($user)?$user->email():'');
    }
    
    static function get_user_display_name($user) {
      $user = \Seolan\Core\User::get_user();
      return (isset($user)?$user->fullname():'');
    }
    
    static function check_calendar_koid($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $query = 'SELECT KOID FROM '.$xmod->tagenda.' WHERE KOID = ?';
        $checked_calendar_koid = getDB()->fetchCol($query, array($xmod->tagenda.':'.$calendar_koid));
        if($checked_calendar_koid) {
            return $checked_calendar_koid[0];
        }
        return null;
    }
    
    static function get_user_calendars_koid($user, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $query = 'SELECT a.KOID FROM '.$xmod->tagenda.' AS a, USERS AS u  WHERE u.alias = ? AND u.KOID = a.OWN';
        return getDB()->fetchCol($query, array($user));
        
    }    
    
  /**
   * Vevent Request
   * @param string $event_uid : un koid ou un uidi
   * @param string $calendar_koid : l'agenda qui contient l'evt
   * @note : 
   * recherche du koid dans le cas d'evt initiés par un client qui a fourni un uid / uidi
   * les evts initiés en BO n'ont pas d'uidi en base en request caldav on passe la 2me partie du koid
   */
  static function get_event_koid_from_uid($event_uid,$calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
    $koid = getDB()->fetchCol("SELECT e.KOID FROM {$xmod->tevt} e, {$xmod->tlinks} l WHERE l.KOIDE=e.KOID AND e.UIDI=? AND l.KOIDD=?"
			      ,[$event_uid, $xmod->tagenda.':'.$calendar_koid]);
    if($koid) {
      $koid = str_replace($xmod->tevt.':', '', $koid[0]);
      return $koid;
    }
    return $event_uid;
  }
  /// @note : RR 20220602, workaround, lorsque l'uidi est un koid, il faut vérifier qu'il existe bien
  //  -> il existe de tels evts, pour lesquels l'oid utilisé comme uidi n'existe pas
  static function get_event_uid($event_koid, \Seolan\Module\Calendar\Calendar $xmod) {
        
    $uidi = getDB()->fetchOne("SELECT UIDI FROM {$xmod->tevt} WHERE KOID = ?",
			      [$xmod->tevt.':'.$event_koid]);
    
    if (\Seolan\Core\Kernel::isAKoid($uidi)
	&& \Seolan\Core\Kernel::getTable($uidi) == $xmod->tevt
	&& !getDB()->fetchExists("select koid from {$xmod->tevt} where koid=?",[$uidi])){
      \Seolan\Core\Logs::debug(__METHOD__." uidi as unknown koid {$event_koid} {$uidi}");
      return '';
    }
    
    return str_replace($xmod->tevt.':', '', $uidi??'');
    
  }
  /**
   * date de mise à jour d'un event identifié par son koid et agenda
   * agenda : directement ou indirectement via le lien de rattachement 
   * (cas des evts partagés entre plusieurs agenda)
   * @note : RR 20220602
     -> le etag est obligatoire dans les réponses aux requêtes caldav
   * l'absence génére une erreur dans THDB)
   * -> il existe des evts (legacy?) qui n'ont pas de rattachement 
   * 
   */  
  static function get_event_etag($event_koid,$calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
    
    $etag = getDB()->fetchOne("SELECT e.UPD FROM {$xmod->tevt} e, {$xmod->tlinks} l WHERE (e.KOID = ? OR e.UIDI = ?) AND l.KOIDE=e.KOID AND l.KOIDD=?",
			      [$xmod->tevt.':'.$event_koid, $event_koid, $xmod->tagenda.':'.$calendar_koid]);

    // event non rattaché (est-ce normal ?) et sans tenir compte de l'agenda
    if (empty($etag)){
      $evt = getDB()->fetchRow("SELECT e.UPD, e.KOIDD, e.KOID, ifnull(a.name, 'agenda inconnu') as agenda FROM {$xmod->tevt} e left outer join {$xmod->tagenda} a on a.koid=e.koidd WHERE (e.KOID = ? OR e.UIDI=?)",
			       [$xmod->tevt.':'.$event_koid,
				$event_koid]);
      if (empty($evt)){
	\Seolan\Core\Logs::debug(__METHOD__." koid : '{$event_koid}' calendar : '{$calendar_koid}' not found with link and uidi-evt not found");
	$etag = null;
      } else {
	\Seolan\Core\Logs::debug(__METHOD__." koid : '{$event_koid}' calendar : '{$calendar_koid}' not found with link, found direct evt :  {$evt['KOID']} {$evt['KOIDD']} {$evt['agenda']}");
	$etag = $evt['UPD'];
      }
    }
    return $etag??null;
    
  }
    
  // Calendar function
  static function get_calendar_display_name($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
    
    $query = 'SELECT name FROM '.$xmod->tagenda.' WHERE KOID = ?';
    $name = getDB()->fetchCol($query, array($xmod->tagenda.':'.$calendar_koid));
    return $name[0];
  }
    
    static function get_calendar_sync_token($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $query = 'SELECT synctoken FROM '.$xmod->tagenda.' WHERE KOID = ?';
        $sync_token = getDB()->fetchCol($query, array($xmod->tagenda.':'.$calendar_koid));
        if($sync_token) {
            return $sync_token[0];
        }
        $query = 'UPDATE '.$xmod->tagenda.' SET synctoken = 1 WHERE KOID = ?';
        getDB()->execute($query, array($xmod->tagenda.':'.$calendar_koid));
        return 1;
    }

    /**
     * return all events directly or not registered for the calendar (diary)
     */
    static function get_calendar_events($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod, $time_filter=null) {

      //$query = "SELECT e.KOID FROM {$xmod->tevt} e, {$xmod->tlinks} l WHERE e.KOID=l.KOIDE and l.KOIDD=?";
      $query = "SELECT e.KOID, e.visib, e.text FROM {$xmod->tevt} e, {$xmod->tlinks} l WHERE e.KOID=l.KOIDE and l.KOIDD=?";
      if(@$time_filter['end'] && @$time_filter['start']) {
        $query .= " AND ((e.begin <= '" . $time_filter['end'] . "' AND e.end > '" . $time_filter['start'] . "') OR rrule IS NOT NULL)";
      }
      $eventOids = [];
      $nbtot = 0;
      $authorizedDiaries = $xmod->getAuthorizedDiaries('rw');
      foreach(getDB()->select($query,[$xmod->tagenda.':'.$calendar_koid]) as $event){
	$nbtot++;
	if(($event['visib']=='PR')
	   && !in_array($xmod->diary['KOID'],$authorizedDiaries))
	continue;
	$summary = trim($event['text']);
	if (empty($summary))
	  continue;
	$eventOids[] = $event['KOID'];
      }
      $returned = count($eventOids);
      \Seolan\Core\Logs::debug(__METHOD__." synchro calendar {$xmod->tagenda}:{$calendar_koid} {$nbtot} {$returned} $query : ".implode(',', $eventOids));
      return $eventOids;
      
      //return  getDB()->fetchCol($query,[$xmod->tagenda.':'.$calendar_koid]);
    }
    
    static function get_calendar_color($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $query = 'SELECT color FROM '.$xmod->tagenda.' WHERE KOID = ?';
        $color = getDB()->fetchCol($query, array($xmod->tagenda.':'. $calendar_koid));
        if($color) {
            return $color[0];
        }
        return null;
    }
    
    static function set_calendar_color($calendar_koid,$hexa_color, \Seolan\Module\Calendar\Calendar $xmod) {
        
        $query = 'UPDATE '.$xmod->tagenda.' SET color = ? WHERE KOID = ?';
        getDB()->execute($query, array($hexa_color, $xmod->tagenda.':' . $calendar_koid));
    }
    
    static function update_calendar_sync_token($calendar_koid, \Seolan\Module\Calendar\Calendar $xmod) {
      \Seolan\Core\Logs::debug('CalDAVDataBase::update_calendar_sync_token('.$calendar_koid.')');
        $query = 'UPDATE '.$xmod->tagenda.' SET synctoken = synctoken+1 WHERE KOID = ?';
        getDB()->execute($query, array($calendar_koid));
        
    }
    
    static function update_calendar_propertie($propertie_name,$new_propertie_value,$calendar_koid, \Seolan\Module\Calendar\Calendard $xmod) {

      \Seolan\Core\Logs::debug('CalDAVDataBase::update_calendar_properties');
        $field_name = [
            "displayname" => "name",
            "calendar-color" => "color"
        ];
        
        $query = 'UPDATE '.$xmod->tagenda.' SET '. $field_name[$propertie_name].' = ? WHERE KOID = ? ';
        getDB()->execute($query, array($new_propertie_value, $xmod->tagenda.':'.$calendar_koid));               

    }
    
}
