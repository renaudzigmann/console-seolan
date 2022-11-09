<?php
// Version 2.3.1
// Modifications :
// mettre en comm les includes de init,draw_functions,/overlapping_events
// ajouter $mArray_begin et $mArray_end
// commenter tout le if ($phpiCal_config->save_parsed_cals == 'yes')
// $cal_filelist=array($filename)
// commenter include(__DIR__.'/parse/parse_tzs.php');
// commenter tous le if (isset($master_array) && is_array($master_array) && $phpiCal_config->save_parsed_cals == 'yes')
// commenter toute la fin
// remplacer count acev 52
// SUM,LOC DESCR : $data=addslashes($this->unescapeICSText($data));
// + parese/end_vevent.php
// Ajouter toutes les global
// COmmenter $master_array['calendar_name']    = $calendar_name;
// remplacer les unset par des =null (unset foirreux sur global)
// Ajouter gestion alarm/trigger et ajouter trigger dans les champs a effacer
// Modifier traitement attendee et ajouter csattendee dans les champs a effacer
// AJouty last-modified + $modified dans champs a effacer
// date_functions.php : ajouter ...([0-9]{0,2}) (l254) et $regs[6] (l267)à extractDateTime, return '' à match_tz
// ajout checkEvent + ajout appel UID,LAST-MODIFIED + ajout->checked, ->gotonext

//include_once(__DIR__.'/init.inc.php');
require_once(__DIR__.'/date_functions.php');
//include_once(__DIR__.'/draw_functions.php');
//include_once(__DIR__.'/parse/overlapping_events.php');
include_once(__DIR__.'/parse/recur_functions.php');

function ical_parser($module, $filename) {
  global $week_start_day,$daysofweek_lang, $daysofweekshort_lang, $daysofweekreallyshort_lang, $monthsofyear_lang, $monthsofyear_lang, $monthsofyearshort_lang,$tz_array, $summary,$cpath, $timeFormat, $dateFormat_week,$tz_array, $phpiCal_config, $calendar_tz,$count, $mArray_begin, $mArray_end, $except_dates, $start_date, $start_date_unixtime,$end_range_unixtime,$until_unixtime, $day_offset, $current_view, $recur_data,$bymonth, $byweekno, $bymonthday, $year, $start_unixtime, $freq_type,$byweekno, $year, $freq_type, $wkst, $wkst3char,$byyearday, $year,$bymonthday, $year,$freq_type, $byday, $bymonth,$byweekno, $wkst3char, $year, $month, $start_unixtime, $summary, $bymonth, $byyearday,$byweekno,$byyearday,$bymonthday,$byday,$bysetpos;
  //include_once(__DIR__.'/timezones.php'); // A mettre après la declaration des globales

  $timeFormat = 'H:i';
  $timeFormat_small = 'g:i';

  // For date formatting, see note below
  $dateFormat_day = '%A %e %B';
  $dateFormat_week = '%e %B';
  $dateFormat_week_list = '%a %e %b';
  $dateFormat_week_jump = '%e %b';
  $dateFormat_month = '%B %Y';
  $dateFormat_month_list = '%A %e %B';
  $dateFormat_year = '%Y';
  $mArray_begin = mktime (0,0,0,1,1,1970);
  $mArray_end = mktime (0,0,0,12,31,2037);
  $uids=array();

  // reading the file if it's allowed
  $filecomplete=false;
  $parse_file = true;
  if ($parse_file) {	
    $overlap_array = array ();
    $uid_counter = 0;
  }
  $cal_filelist=array($filename);
  $calnumber = 1;

  foreach ($cal_filelist as $cal_key=>$filename) {
  
    // Find the real name of the calendar.
    if ($parse_file) {	
    
      // Let's see if we're doing a webcal
      $is_webcal = FALSE;
      if (substr($filename, 0, 7) == 'http://' || substr($filename, 0, 8) == 'https://' || substr($filename, 0, 9) == 'webcal://') {
	$is_webcal = TRUE;
	$cal_webcalPrefix = str_replace('http://','webcal://',$filename);
	$cal_httpPrefix = str_replace('webcal://','http://',$filename);
	$cal_httpsPrefix = str_replace('webcal://','https://',$filename);
	$cal_httpsPrefix = str_replace('http://','https://',$cal_httpsPrefix);
	$filename = $cal_httpPrefix;
	$master_array['-4'][$calnumber]['webcal'] = 'yes';
	$actual_mtime = time();
      } else {
	$actual_mtime = @filemtime($filename);
      }
    
      $ifile = @fopen($filename, "r");
      if ($ifile == FALSE) exit(error($lang['l_error_cantopen'], $filename));
      $nextline = fgets($ifile, 1024);
      if (trim($nextline) != 'BEGIN:VCALENDAR') exit(error($lang['l_error_invalidcal'], $filename));
    
      // Set a value so we can check to make sure $master_array contains valid data
      $master_array['-1'] = 'valid cal file';
    
      // Set default calendar name - can be overridden by X-WR-CALNAME
      $calendar_name = $cal_filename;
      //$master_array['calendar_name'] 	= $calendar_name;
    
      // read file in line by line
      // XXX end line is skipped because of the 1-line readahead
      $module->tmp->gotonext=false;
      while (!feof($ifile)) {
	$line = $nextline;
	$nextline = fgets($ifile, 1024);
	$nextline = preg_replace("@[\r\n]@", "", $nextline);
	// handle continuation lines that start with either a space or a tab (MS Outlook)
	while (isset($nextline[0]) && ($nextline[0] == " " || $nextline[0] == "\t")) { 
	  $line = $line . substr($nextline, 1);
	  $nextline = fgets($ifile, 1024);
	  $nextline = preg_replace("@[\r\n]@", "", $nextline);
	}
	$line = str_replace('\n',"\n",$line); // ? il ya un unescape ensuite qui le gère aussi
	$line = trim(stripslashes($line));
	if($module->tmp->gotonext && $line!='BEGIN:VEVENT' && $line!='END:VCALENDAR') continue;
	switch ($line) {
	case 'END:VCALENDAR':
	  $filecomplete=true;
	  break;
	case 'BEGIN:VFREEBUSY':
	case 'BEGIN:VEVENT':
	  // each of these vars were being set to an empty string
	  $start_time=$end_time=$start_date=$end_date=
	    $allday_start=$allday_end=$start=$end=$the_duration=
	    $beginning=$start_of_vevent=
	    $valarm_description=$start_unixtime=$end_unixtime=$display_end_tmp=$end_time_tmp1=
	    $recurrence_id=$uid=$rrule=$until_check=
	    $until=$byweek=$byweekno=
	    $byminute=$byhour=$bysecond=$csattendee=$trigger=$modified=$module->tmp->gotonext=$module->tmp->tzrchecked=$module->tmp->tzrevent=null;
	
	  $interval = 1;				
	  $sequence = 0;				
	  $summary = '';
	  $description = '';
	  $status = '';
	  $class = '';
	  $location = '';
	  $url = '';
	  $geo = '';
	  $type = '';
	  $other = '';
	  $wkst = 'MO';
	  $vtodo_categories = '';
	
	  $except_dates 	= array();
	  $except_times 	= array();
	  $rrule_array 	= array();
	  $byday  	 	= array();
	  $bymonth	 	= array();
	  $bymonthday 	= array();
	  $byyearday  	= array();
	  $bysetpos   	= array();
	  $first_duration = TRUE;
	  $count 			= 52;
	  $valarm_set 	= FALSE;
	  $attendee		= array();
	  $organizer		= array();
	
	  break;
	case 'END:VFREEBUSY':
	case 'END:VEVENT':
	  include __DIR__."/parse/end_vevent.php";
	  break;
	case 'END:VTODO':
	  if (($vtodo_priority == '') && ($status == 'COMPLETED')) {
	    $vtodo_sort = 11;
	  } elseif ($vtodo_priority == '') { 
	    $vtodo_sort = 10;
	  } else {
	    $vtodo_sort = $vtodo_priority;
	  }
	
	  // CLASS support
	  if (isset($class)) {
	    if ($class == 'PRIVATE') {
	      $summary = '**PRIVATE**';
	      $description = '**PRIVATE**';
	    } elseif ($class == 'CONFIDENTIAL') {
	      $summary = '**CONFIDENTIAL**';
	      $description = '**CONFIDENTIAL**';
	    }
	  }
	
	  $master_array['-2']["$vtodo_sort"]["$uid"] = array (
	    'start_date' => $start_date, 
	    'start_time' => $start_time, 
	    'vtodo_text' => $summary, 
	    'due_date'=> $due_date, 
	    'due_time'=> $due_time, 
	    'completed_date' => $completed_date, 
	    'completed_time' => $completed_time, 
	    'priority' => $vtodo_priority, 
	    'status' => $status, 
	    'class' => $class, 
	    'categories' => $vtodo_categories, 
	    'description' => $description, 
	    'calname' => $actual_calname,
	    'geo' => $geo,
	    'url' => $url
	  );
	  $start_date= $start_time= $due_date= $due_time= $completed_date= $completed_time= $vtodo_priority= $status= $class= $vtodo_categories= $summary= $description=null;
	  $vtodo_set = FALSE;		     	
	  break;
	
	case 'BEGIN:VTODO':
	  $vtodo_set = TRUE;
	  $summary = '';
	  $due_date = '';
	  $due_time = '';
	  $completed_date = '';
	  $completed_time = '';
	  $vtodo_priority = '';
	  $vtodo_categories = '';
	  $status = '';
	  $class = '';
	  $description = '';
	  break;
	case 'BEGIN:VALARM':
	  $valarm_set = TRUE;
	  break;
	case 'END:VALARM':
	  $valarm_set = FALSE;
	  break;
	
	default: // les cas PROP:valeur
	  unset ($field, $data, $prop_pos, $property);
	  if (preg_match ("/([^:]+):(.*)/s", $line, $line)){
	    // /s : dot all pour prendre les \n, voir plus haut le replace est déjà fait
	    $field = $line[1];
	    $data = $line[2];
	    $property = strtoupper($field);
	    $prop_pos = strpos($property,';');
	    if ($prop_pos !== false) $property = substr($property,0,$prop_pos);
	  
	    switch ($property) {
	    
	      // Start VTODO Parsing
	      //
	    case 'DUE':
	      $datetime = extractDateTime($data, $property, $field);
	      $due_date = $datetime[1];
	      $due_time = $datetime[2];
	      break;
	    
	    case 'COMPLETED':
	      $datetime = extractDateTime($data, $property, $field);
	      $completed_date = $datetime[1];
	      $completed_time = $datetime[2];
	      break;
	    
	    case 'PRIORITY':
	      $vtodo_priority = "$data";
	      break;
	    
	    case 'STATUS':
	      $status = "$data";
	      break;
	    
	    case 'GEO':
	      $geo = "$data";
	      break;
	    
	    case 'CLASS':
	      $class = "$data";
	      break;
	    
	    case 'CATEGORIES':
	      $vtodo_categories = $module->unescapeICSText($data);
	      break;
	      //
	      // End VTODO Parsing				
	    
	    case 'DTSTART':
	      $datetime = extractDateTime($data, $property, $field);
	      $start_unixtime = $datetime[0];
	      $start_date = $datetime[1];
	      $start_time = $datetime[2];
	      $allday_start = $datetime[3];
	      $start_tz = $datetime[4];
	      preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{0,2})([0-9]{0,2})/', $data, $regs);
	      $vevent_start_date = $regs[1] . $regs[2] . $regs[3];
	      $day_offset = dayCompare($start_date, $vevent_start_date);
	      break;
	    
	    case 'DTEND':
	      $datetime = extractDateTime($data, $property, $field); 
	      $end_unixtime = $datetime[0];
	      $end_date = $datetime[1];
	      $end_time = $datetime[2];
	      $allday_end = $datetime[3];
	      break;
	    
	    case 'EXDATE':
	      $data = explode(",", $data);
	      foreach ($data as $exdata) {
		$exdata = str_replace('T', '', $exdata);
		$exdata = str_replace('Z', '', $exdata);
		preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{0,2})([0-9]{0,2})/', $exdata, $regs);
		$except_dates[] = $regs[1] . $regs[2] . $regs[3];
		// Added for Evolution, since they dont think they need to tell me which time to exclude.
		if ($regs[4] == '' && isset($start_time) && $start_time != '') { 
		  $except_times[] = $start_time;
		} else {
		  $except_times[] = $regs[4] . $regs[5];
		}
	      }
	      break;
							
	    case 'SUMMARY':
	      $data=$module->unescapeICSText($data);
	      if ($valarm_set == FALSE) { 
		$summary = $data;
	      } else {
		$valarm_summary = $data;
	      }
	      break;
							
	    case 'DESCRIPTION':
	      $data=$module->unescapeICSText($data);
	      if ($valarm_set == FALSE) { 
		$description = $data;
	      } else {
		$valarm_description = $data;
	      }
	      break;
							
	    case 'RECURRENCE-ID':
	      $parts = explode(';', $field);
	      foreach($parts as $part) {
		$eachval = explode('=',$part);
		if ($eachval[0] == 'RECURRENCE-ID') {
		  // do nothing
		} elseif ($eachval[0] == 'TZID') {
		  $recurrence_id['tzid'] = $eachval[1];
		} elseif ($eachval[0] == 'RANGE') {
		  $recurrence_id['range'] = $eachval[1];
		} elseif ($eachval[0] == 'VALUE') {
		  $recurrence_id['value'] = $eachval[1];
		} else {
		  $recurrence_id[] = $eachval[1];
		}
	      }
	      unset($parts, $part, $eachval);
							
	      $data = str_replace('T', '', $data);
	      $data = str_replace('Z', '', $data);
	      preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{0,2})([0-9]{0,2})/', $data, $regs);
	      $recurrence_id['date'] = $regs[1] . $regs[2] . $regs[3];
	      $recurrence_id['time'] = $regs[4] . $regs[5];
				
	      $recur_unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]);
				
	      if (isset($recurrence_id['tzid'])) {
		$offset_tmp = chooseOffset($recur_unixtime, $recurrence_id['tzid']); 
	      } elseif (isset($calendar_tz)) {
		$offset_tmp = chooseOffset($recur_unixtime, $calendar_tz);
	      } else {
		$offset_tmp = chooseOffset($recur_unixtime);
	      }
	      $recur_unixtime = calcTime($offset_tmp, @$server_offset_tmp, $recur_unixtime);
	      $recurrence_id['date'] = date('Ymd', $recur_unixtime);
	      $recurrence_id['time'] = date('Hi', $recur_unixtime);
	      $recurrence_d = date('Ymd', $recur_unixtime);
	      $recurrence_t = date('Hi', $recur_unixtime);
	      unset($server_offset_tmp);
	      break;
							
	    case 'SEQUENCE':
	      $sequence = $data;
	      break;
	    case 'UID':
	      if(!$valarm_set){
		$uid = $data;
		$uids[]=$uid;
		checkEvent($module,$uid,$modified);
		break;
	      }
	    case 'X-WR-CALNAME':
	      $actual_calname = $data;
	      $master_array['calendar_name'] = $actual_calname;
	      $cal_displaynames[$cal_key] = $actual_calname;
	      break;
	    case 'X-WR-TIMEZONE':
	      $calendar_tz = $data;
	      $master_array['calendar_tz'] = $calendar_tz;
	      break;
	    case 'DURATION':
	      if (($first_duration == TRUE) && (!stristr($field, '=DURATION'))) {
		preg_match ('/^P([0-9]{1,2}[W])?([0-9]{1,3}[D])?([T]{0,1})?([0-9]{1,2}[H])?([0-9]{1,2}[M])?([0-9]{1,2}[S])?/', $data, $duration); 
		$weeks 			= str_replace('W', '', $duration[1]); 
		$days 			= str_replace('D', '', $duration[2]); 
		$hours 			= str_replace('H', '', $duration[4]); 
		$minutes 		= str_replace('M', '', $duration[5]); 
		$seconds 		= str_replace('S', '', $duration[6]); 
		$the_duration 	= ($weeks * 60 * 60 * 24 * 7) + ($days * 60 * 60 * 24) + ($hours * 60 * 60) + ($minutes * 60) + ($seconds);
		$first_duration = FALSE;
	      }	
	      break;
	    case 'RRULE':
	      $data = str_replace ('RRULE:', '', $data);
	      $rrule = explode (';', $data);
	      foreach ($rrule as $recur) {
		preg_match ('/(.*)=(.*)/', $recur, $regs);
		$rrule_array[$regs[1]] = $regs[2];
	      }
	      break;
	    case 'ATTENDEE':
	      $aname=preg_replace ("#.*;CN=([^;]*).*#", "\\1", $field);
	      preg_match('#\[CS\] (.+) \((.+)\)#',$aname,$cn);
	      if(count($cn)==3){
		$csattendee[]=array('name'=>trim($cn[2]),'own'=>trim($cn[1]));
	      }else{
		$attendee[] = array ('name'    => $aname, 
				     'email'   => preg_replace ("/.*mailto:(.*).*/", "\\1", $data), 
				     'RSVP'    => preg_replace ("/.*RSVP=([^;]*).*/", "\\1", $field),
				     'PARSTAT' => preg_replace ("/.*PARTSTAT=([^;]*).*/", "\\1", $field),
				     'ROLE'    => preg_replace ("/.*ROLE=([^;]*).*/", "\\1", $field));
	      }
	      break;
	    case 'ORGANIZER':
	      $field 		 = str_replace("ORGANIZER;CN=", "", $field);
	      $data 		 = str_replace ("mailto:", "", $data);
	      $organizer[] = array ('name' => stripslashes($field), 'email' => stripslashes($data));
	      break;
	    case 'LOCATION':
	      $data=$module->unescapeICSText($data);
	      $location = $data;
	      break;
	    case 'URL':
	      $url = $data;
	      break;
	    case 'TRIGGER':
	      $data=str_ireplace('-', '', $data);
	      preg_match ('/^P([0-9]{1,2}[W])?([0-9]{1,2}[D])?([T]{0,1})?([0-9]{1,2}[H])?([0-9]{1,2}[M])?/', $data, $duration); 
	      $alw       = str_ireplace('W', '', $duration[1]); 
	      $ald       = str_ireplace('D', '', $duration[2]); 
	      $alh       = str_ireplace('H', '', $duration[4]); 
	      $alm     = str_ireplace('M', '', $duration[5]); 
	      $trigger   = ($alw * 60 * 24 * 7) + ($ald * 60 * 24) + ($alh * 60) + ($alm);
	      break;
	    case 'LAST-MODIFIED':
	      $tmp = extractDateTime($data, $property, $field);
	      $modified = date('Y-m-d H:i:s',strtotime('GMT',$tmp[0]));
	      checkEvent($module,$uid,$modified);
	      break;
	    default:
	      if(strpos(':',$data) > 1) $other .= $data;
	    }
	  }
	}
      }
    }
    if (!isset($master_array['-3'][$calnumber])) $master_array['-3'][$calnumber] = $actual_calname;
    if (!isset($master_array['-4'][$calnumber]['mtime'])) $master_array['-4'][$calnumber]['mtime'] = $actual_mtime;
    if (!isset($master_array['-4'][$calnumber]['filename'])) $master_array['-4'][$calnumber]['filename'] = $filename;
    if (!isset($master_array['-4'][$calnumber]['webcal'])) $master_array['-4'][$calnumber]['webcal'] = 'no';
    $calnumber = $calnumber + 1;
  }
  if($filecomplete){
    $events_tab = [];
    if ($parse_file) {	
      // Sort the array by absolute date.
      if (isset($master_array) && is_array($master_array)) { 
	ksort($master_array);
	reset($master_array);
		
	// sort the sub (day) arrays so the times are in order
	foreach (array_keys($master_array) as $k) {
	  if (isset($master_array[$k]) && is_array($master_array[$k])) {
	    ksort($master_array[$k]);
	    reset($master_array[$k]);
	  }
	}
      }	

      /*
      // write the new master array to the file
      if (isset($master_array) && is_array($master_array) && $phpiCal_config->save_parsed_cals == 'yes') {
      $write_me = serialize($master_array);
      $fd = @fopen($parsedcal, 'w');
      if ($fd == FALSE) exit(error($lang['l_error_cache'], $filename));
      @fwrite($fd, $write_me);
      @fclose($fd);
      @touch($parsedcal, $realcal_mtime);
      }
      */
    }
    /*
    // Set a calender name for all calenders combined
    if ($cal == $phpiCal_config->ALL_CALENDARS_COMBINED) {
    $calendar_name = $all_cal_comb_lang;
    }
    example of how to customize the display name sort order
    if(in_array('US Holidays',$cal_displaynames)){
    unset($cal_displaynames[array_search('US Holidays',$cal_displaynames)]);
    array_unshift($cal_displaynames, 'US Holidays');
    }
    $cal_displayname = urldecode(implode(', ', $cal_displaynames)); #reset this with the correct names
    $template_started = getmicrotime();
    */

    $xsetcat=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$module->tcatevt);
    $xsetlink=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$module->tlinks);
    $tzruids=array();
    foreach($module->categories as $oid=>$cat)
      $tzrcategories[$cat['name']]=$oid;

    foreach($master_array as $day=>&$dayevs){
      if($day<1) continue;
      foreach($dayevs as $hour=>&$hourevs){
	foreach($hourevs as $uid=>&$ev){
	  $ar=array();
	  if(!empty($ev['tzrgotonext'])) continue;
	  // Vérification de la timezone
	  \Seolan\Core\Logs::debug("importEVT creation events '{$ev['timezone']}'");
	  if(empty($ev['timezone'])){
	    $ev['timezone']='GMT';
	  }elseif(strtotime($ev['timezone'])===false){
	    \Seolan\Core\Logs::debug("importEVT creation events force timezone diary");
	    $ev['timezone']=$module->diary['tz'];
	  }
	  // Heures
	  if($hour=='-1'){
	    $ar['begin']=date('Y-m-d 00:00:00',$ev['start_unixtime']);
	    $ar['end']=date('Y-m-d 23:59:00',$ev['end_unixtime']-1);
	    $ar['allday']=1;
	  }else{
	    $mystart=strtotime($ev['timezone'],$ev['start_unixtime']);
	    $ar['begin']=gmdate('Y-m-d H:i:s',$mystart);
	    $ar['end']=gmdate('Y-m-d H:i:s',strtotime($ev['timezone'],$ev['end_unixtime']));
	    $ar['allday']=0;
	  }
	  $mystartformatted = date('Y-m-d H:i:s',$mystart);
	  \Seolan\Core\Logs::debug("importEVT creation events 2 {$ev['event_text']} {$ar['begin']} <= {$ev['timezone']} {$mystartformatted}");

	  // Catégorie (non vide)
	  $ar['cat'] = null;
	  $catname = trim($ev['categories']);
	  if(!empty($catname) && !isset($tzrcategories[$catname])) {
	    $newcatoid = $module->createCategory($catname);
	    $tzrcategories[$catname]=$newcatoid;
	  }
	  if (!empty($catname))
	    $ar['cat']=$tzrcategories[$ev['categories']];

	  $ar['cat']=$tzrcategories[$ev['categories']];

	  // Titres, descr, lieu
	  $ar['text']=$ev['event_text'];
	  $ar['descr']=$ev['description'];
	  $ar['place'] = $module->processICSLocation($ev['location']);
	  
	  // Visibilité
	  if($ev['class']=="PRIVATE") $ar['visib']="PR";
	  elseif($ev['class']=="PUBLIC") $ar['visib']="PU";
	  elseif($ev['class']=="CONFIDENTIAL") $ar['visib']="OC";
	  elseif(!empty($module->diary['defvisi'])) $ar['visib']=$module->diary['defvisi'];
	  else $ev['visib']='PR';
	  // UIDI
	  $ar['UIDI']=$uid;
	  // KOID Source
	  if(empty($tzruids[$uid])) $ar['KOIDS']=null;
	  else $ar['KOIDS']=$tzruids[$uid];
	  $ar['KOIDD']=$module->diary['KOID'];
	  // Repetition : dans le cas d'une regle non gérée par la console, on enregistre la regle directement
	  $tmp=implode(';',$ev['rrule']);
	  if(!preg_match('/^(RRULE|FREQ|DAILY|WEEKLY|MONTHLY|YEARLY|UNTIL|EXDATE|VALUE|COUNT|DATE|INTERVAL=1|[;:=0-9 TZ-])*$/i',$tmp)){
	    $ar['rrule']=$tmp;
	    $ar['repet']=$ar['end_rep'];
	  }elseif(!empty($ev['recur']['FREQ'])){
	    $ar['rrule']='';
	    $ar['repet']=strtoupper($ev['recur']['FREQ']);
	    if($ev['recur']['UNTIL']) $ar['end_rep']=$ev['recur']['UNTIL'];
	    else{
	      if(empty($ev['recur']['COUNT'])) $ev['recur']['COUNT']=52;
	      $ar['end_rep']=date('Ymd',strtotime($day.' +'.$ev['recur']['COUNT'].' '.$ev['recur']['FREQ_TYPE']));
	    }
	  }
	  $ar['except']=implode(";",$ev['exdate']); // A verifier
	  // Rappel
	  $ar['recall']=$ev['alarm'];
	  $ar['isrecall']=0;
	  // Invités
	  $atts=array();
	  foreach($ev['attendee'] as $att) $atts[]=$att['email'];
	  $ar['attext']=implode("\r\n",$atts);
	  $tlinks=array();
	  if(!is_array($ev['csattendee'])) $ev['csattendee']=[];
	  foreach($ev['csattendee'] as $z=>$csatt){
	    $ors=getDB()->fetchRow("select ag.KOID from {$module->tagenda} as ag left outer join USERS as u on u.KOID=ag.OWN ".
				   "where TRIM(REPLACE(ag.name,\":\",\"-\"))=? and ".
				   "TRIM(REPLACE(u.fullnam,\":\",\"-\"))=?", [$csatt['name'], $csatt['own']]);
	    if($ors){
	      $tlinks[]=$ors['KOID'];
	    }
	  }

	  // Date de modif
	  if(!empty($ev['modified']))
	    $ar['UPD']=$ev['modified'];

	  // Effacement ancien si trouvé par checkEvent
	  if(!empty($ev['tzrevent']) && empty($ar['KOIDS'])){
	    $module->delEvt(array('koid'=>$ev['tzrevent']['KOID'],'noalert'=>true));
	    $ar['newoid']=$ev['tzrevent']['KOID'];
	  }
	  // Insertion
	  $ret=$module->xsetevt->procInput($ar);
	  $events_tab[] = $ret['oid'];
	  $xsetlink->procInput(['KOIDE'=>$ret['oid'],'KOIDD'=>$module->diary['KOID'],'UPD'=>$modified,'_options'=>['local'=>true]]);
	  foreach($tlinks as $doid){
	    $xsetlink->procInput(['KOIDE'=>$ret['oid'],'KOIDD'=>$doid,'UPD'=>$modified,'_options'=>['local'=>true]]);
	  }
	  if(empty($tzruids[$uid]))
            $tzruids[$uid]=$ret['oid'];
	}
      }
    }
    if($module->synchro && !$module->caldav_request){
      // suppression des evenements qui ont été supprimés
        $rs=getDB()->fetchAll('SELECT e.* FROM '.$module->tlinks.' as l left outer join '.$module->tevt.' as e on e.KOID=l.KOIDE '.
			    'WHERE l.KOIDD="'.$module->diary['KOID'].'" and (e.KOIDS is null or e.KOIDS="")');
      foreach($rs as $l){
          if(!empty($l['KOID']) && !in_array($l['KOID'],$uids) && !in_array($l['UIDI'],$uids) && 
	   in_array($module->diary['KOID'],$module->getAuthorizedDiaries('rwv'))){
	  $module->delEvt(array('koid'=>$l['KOID'],'noalert'=>true,'_options'=>array('local'=>true)));
	}
      }
    }
  }
  // renvoie soit un tableau avec les différents koid des évènements qui ont été modifiés soit NULL
  return $events_tab;
}
/// @note l'uid est soit un uid soit un koid, on recherche sur les 3 cas
function checkEvent($mod,$uid,$modified,$setmodified=NULL){
  if(!empty($uid) && (!empty($modified) || $setmodifieed)){
    $mod->tmp->tzrchecked=true;
    $tzrevent=getDB()->fetchRow("SELECT e.* FROM {$mod->tevt} as e left outer join {$mod->tlinks} as l on e.KOID=l.KOIDE ".
                                "where (e.UIDI=? || e.KOID=? || e.KOID=?) AND l.KOIDD=?",
				[$uid,
				 $uid,
				 "{$mod->tevt}:$uid", // workaround, en attendant de clarifier koid, uidi
				 $mod->diary['KOID']]);
    if($tzrevent) {
      $mod->tmp->tzrevent=$tzrevent;
      if(!(($tzrevent['UPD'] < $modified || $setmodified && empty($modified)) && $mod->diary['KOID']==$tzrevent['KOIDD'] && ($tzrevent['visib']=='PU' || in_array($mod->diary['KOID'],$mod->getAuthorizedDiaries('rwv'))))){
	$mod->tmp->gotonext=true;
      }
    }
  }
}

