<?php
include_once('date_functions.php');
include_once('timezones.php');

$user=\Seolan\Core\User::get_current_user_uid();
$xsetev=&XSetDesc::objectFactory($this->tevt);
$xsetcat=&XSetDesc::objectFactory($this->tcatevt);
$xsetlink=&XSetDesc::objectFactory($this->tlinks);
$this_year=2035;
$uids=array();
$maxrepet=370;

// Creer le tableau des categories
foreach($this->categories as $oid=>$cat) $categories[$cat['name']]=array('koid'=>$oid);

$ifile = @fopen($filename, "r");
if($ifile==FALSE) $error=true;
$nextline = fgets($ifile);
if(trim($nextline)!='BEGIN:VCALENDAR') $error=true;

// On lit tout le contenu du fichier pour le placer dans un tableau
if(!$error) {
  $master_array['-1'] = 'valid cal file';
  $master_array['calendar_name']   = 'calendar';
  
  while (!feof($ifile)) {
    $line = $nextline;
    $nextline = fgets($ifile);
    $nextline = ereg_replace("[\r\n]", "", $nextline);
    while (substr($nextline, 0, 1) == " ") {
      $line = $line . substr($nextline, 1);
      $nextline = fgets($ifile);
      $nextline = ereg_replace("[\r\n]", "", $nextline);
    }
    $line = $tab_line[] = trim($line);
    
    /*Traitement des timezone
    switch ($line) {
    case 'BEGIN:VTIMEZONE':
      unset ($tz_id,$tz_from,$tz_to);
      $v_time_zone=true;
      break;
    case 'END:VTIMEZONE':
      if ($v_time_zone && isset($tz_id) && isset($tz_from) && isset($tz_to)) {  
	$GLOBALS['tz_array'][$tz_id]=array(
					   preg_replace('/(\d){2}(\d+){2}/i',' $1 hour $2 minute',$tz_from),
					   preg_replace('/(\d){2}(\d+){2}/i',' $1 hour $2 minute',$tz_to)
					   );
      }
      $v_time_zone=false;
      break;
    default:
      unset ($field, $data, $prop_pos, $property);
      if (ereg ("([^:]+):(.*)", $line, $line) && $v_time_zone) {
	$field = $line[1];
	$data = $line[2];
	$property = $field;
	$prop_pos = strpos($property,';');
	if ($prop_pos !== false) $property = substr($property,0,$prop_pos);
	$property = strtoupper($property);
	
	switch ($property) {
	case 'TZID':
	  if(array_key_exists($data, $GLOBALS['tz_array'])) {
	    $v_time_zone=false;
	  }else {
	    $tz_id=$data;
	  }
	  break;
	case 'TZOFFSETFROM':
	  $tz_from=$data;
	  break;
	case 'TZOFFSETTO':
	  $tz_to=$data;
	  break;                
	}
      }
      break;
    }*/
  }
      
  // On analyse chaque ligne
  $this->tmp->gotonext=false;
  foreach($tab_line as $line){
    if($this->tmp->gotonext && $line!='BEGIN:VEVENT') continue;
    switch ($line) {
    case 'BEGIN:VEVENT':
      unset (
	     $start_time, $end_time, $start_date, $end_date, $summary, 
	     $allday_start, $allday_end, $start, $end, $the_duration, 
	     $beginning, $rrule_array, $start_of_vevent, $description, $url, 
	     $valarm_description, $start_unixtime, $end_unixtime, $display_end_tmp, $end_time_tmp1, 
	     $recurrence_id, $uid, $class, $location, $rrule, $abs_until, $until_check,
	     $until, $bymonth, $byday, $bymonthday, $byweek, $byweekno, 
	     $byminute, $byhour, $bysecond, $byyearday, $bysetpos, $wkst,
	     $interval, $number, $modified, $this->tmp->tzrevent,$this->tmp->gotonext, $this->tmp->tzrchecked
	     );
      $ma=array();
      $except_dates   = array();
      $except_times   = array();
      $bymonth     = array();
      $bymonthday   = array();
      $first_duration = TRUE;
      $count       = 1000000;
      $valarm_set   = FALSE;
      $trigger    = 0;
      $csattendee = array();
      $attendee    = "";
      $organizer    = array();
      $except=null;
      break;
      
    case 'END:VEVENT':
      if (!isset($url)) $url = '';
      if (!isset($type)) $type = '';
	
      // Handle DURATION
      if (!isset($end_unixtime) && isset($the_duration)) {
	$end_unixtime   = $start_unixtime + $the_duration;
	$end_time   = date ('Hi', $end_unixtime);
      }
	
      // make sure we have some value for $uid
      if (!isset($uid)) {
	$uid = $uid_counter;
	$uid_counter++;
	$uid_valid = false;
      } else {
	$uid_valid = true;
      }
	
      if ($uid_valid && isset($processed[$uid]) && isset($recurrence_id['date'])) {
	$old_start_date = $processed[$uid][0];
	$old_start_time = $processed[$uid][1];
	if ($recurrence_id['value'] == 'DATE') $old_start_time = '-1';
	$start_date_tmp = $recurrence_id['date'];
	if (!isset($start_date)) $start_date = $old_start_date;
	if (!isset($start_time)) $start_time = $master_array[$old_start_date][$old_start_time][$uid]['event_start'];
	if (!isset($start_unixtime)) $start_unixtime = $master_array[$old_start_date][$old_start_time][$uid]['start_unixtime'];
	if (!isset($end_unixtime)) $end_unixtime = $master_array[$old_start_date][$old_start_time][$uid]['end_unixtime'];
	if (!isset($end_time)) $end_time = $master_array[$old_start_date][$old_start_time][$uid]['event_end'];
	if (!isset($summary)) $summary = $master_array[$old_start_date][$old_start_time][$uid]['event_text'];
	if (!isset($length)) $length = $master_array[$old_start_date][$old_start_time][$uid]['event_length'];
	if (!isset($description)) $description = $master_array[$old_start_date][$old_start_time][$uid]['description'];
	if (!isset($location)) $location = $master_array[$old_start_date][$old_start_time][$uid]['location'];
	if (!isset($organizer)) $organizer = $master_array[$old_start_date][$old_start_time][$uid]['organizer'];
	if (!isset($status)) $status = $master_array[$old_start_date][$old_start_time][$uid]['status'];
	if (!isset($attendee)) $attendee = $master_array[$old_start_date][$old_start_time][$uid]['attendee'];
	if (!isset($url)) $url = $master_array[$old_start_date][$old_start_time][$uid]['url'];
	//removeOverlap($start_date_tmp, $old_start_time, $uid);
	if (isset($master_array[$start_date_tmp][$old_start_time][$uid])) {
	  unset($master_array[$start_date_tmp][$old_start_time][$uid]);  // SJBO added $uid twice here
	  if (sizeof($master_array[$start_date_tmp][$old_start_time]) == 0) {
	    unset($master_array[$start_date_tmp][$old_start_time]);
	  }
	}
	  
	$write_processed = false;
      } else {
	$write_processed = true;
      }
	
      if (!isset($summary))     $summary = '';
      if (!isset($description))   $description = '';
      if (!isset($status))     $status = '';
      if (!isset($class))     $class = '';
      if (!isset($location))     $location = '';
	
      $mArray_begin = mktime (0,0,0,12,21,($this_year - 1));
      $mArray_end = mktime (0,0,0,1,12,($this_year + 1));
	
      if (isset($start_time) && isset($end_time)) {
	// Mozilla style all-day events or just really long events
	if (($end_time - $start_time) > 2345) {
	  $allday_start = $start_date;
	  $allday_end = ($start_date + 1);
	}
      }
      if (isset($start_unixtime,$end_unixtime) && date('Ymd',$start_unixtime) != date('Ymd',$end_unixtime)) {
	$spans_day = true;
	$bleed_check = (($start_unixtime - $end_unixtime) < (60*60*24)) ? '-1' : '0';
      } else {
	$spans_day = false;
	$bleed_check = 0;
      }
      if (isset($start_time) && $start_time != '') {
	preg_match ('/([0-9]{2})([0-9]{2})/', $start_time, $time);
	preg_match ('/([0-9]{2})([0-9]{2})/', $end_time, $time2);
	if (isset($start_unixtime) && isset($end_unixtime)) {
	  $length = $end_unixtime - $start_unixtime;
	} else {
	  $length = ($time2[1]*60+$time2[2]) - ($time[1]*60+$time[2]);
	}
	  
	//$drawKey = drawEventTimes($start_time, $end_time);
	preg_match ('/([0-9]{2})([0-9]{2})/', $drawKey['draw_start'], $time3);
	$hour = $time3[1];
	$minute = $time3[2];
      }
	
      // RECURRENCE-ID Support
      if (isset($recurrence_d)) {
	$recurrence_delete["$recurrence_d"]["$recurrence_t"] = $uid;
      }
	
      // handle single changes in recurring events
      // Maybe this is no longer need since done at bottom of parser? - CL 11/20/02
      if ($uid_valid && $write_processed) {
	if (!isset($hour)) $hour = 00;
	if (!isset($minute)) $minute = 00;
	$processed[$uid] = array($start_date,($hour.$minute), $type);
      }
	
      // Handling of the all day events
      if ((isset($allday_start) && $allday_start != '')) {
	$start = strtotime($allday_start);
	if ($spans_day) {
	  $allday_end = date('Ymd',$end_unixtime);
	}
	if (isset($allday_end)) {
	  $end = strtotime("-1 day $allday_end");
	} else {
	  $end = $start;
	}
	  
	// Changed for 1.0, basically write out the entire event if it starts while the array is written.
	if (($start < $mArray_end) && ($start <= $end)) {
	  $ma[]= array (
			'type' => 'Evenement Jour Entier',
			'UIDI' => $uid,
			'begin' => date('Ymd000000',$start),
			'end' => date('Ymd235900',$end),
			'text' => $summary, 
			'descr' => $description, 
			'place' => $location, 
			//'organizer' => serialize($organizer), 
			'attext' => $attendee, 
			//'calnumber' => $calnumber, 
			//'url' => $url, 
			//'status' => $status, 
			'visib' => "$class",
			'cat' => $category,
			'KOIDD' => $this->diary['KOID'],
			'KOIDS' => &$koids,
			'allday' => 1,
			'isrecall' => 0,
			'recall' => $trigger,
			'repet' => &$repetfreq,
			'end_rep' => &$repetuntil,
			'tplentry'=>TZR_RETURN_DATA);
	  
	  if (!$write_processed) $master_array[($start_date)]['-1'][$uid]['exception'] = true;
	}
      }
          
      // Handling regular events
      if ((isset($start_time) && $start_time != '') && (!isset($allday_start) || $allday_start == '')) {
	if (($end_time >= $bleed_time) && ($bleed_check == '-1')) {
	  $start_tmp = strtotime(date('Ymd',$start_unixtime));
	  $end_date_tmp = date('Ymd',$end_unixtime);
	  
	  $ma[]= array (
			'type' => 'Evenement Plusieurs Jours',
			'UIDI' => $uid,
			'begin' => date('YmdHis',$start_unixtime),
			'end' => date('YmdHis',$end_unixtime),
			'text' => $summary, 
			'descr' => $description, 
			'place' => $location, 
			//'organizer' => serialize($organizer), 
			'attext' => $attendee, 
			//'calnumber' => $calnumber, 
			//'url' => $url, 
			//'status' => $status, 
			'visib' => "$class",
			'cat' => $category,
			'KOIDD' => $this->diary['KOID'],
			'KOIDS' => &$koids,
			'allday' => 0,
			'isrecall' => 0,
			'recall' => $trigger,
			'repet' => &$repetfreq,
			'end_rep' => &$repetuntil,
			'tplentry'=>TZR_RETURN_DATA);
	  
	  if (!$write_processed) $master_array[$start_date][($hour.$minute)][$uid]['exception'] = true;
	} else {
	  if ($bleed_check == '-1') {
	    $display_end_tmp = $end_time;
	    $end_time_tmp1 = '2400';  
	  }
	  
	  if (!isset($end_time_tmp1)) $end_time_tmp1 = $end_time;
	  
	  // This if statement should prevent writing of an excluded date if its the first recurrance - CL
	  $ma[]= array (
			'type' => 'Evenement Un Jour',
			'UIDI' => $uid,
			'begin' => date('YmdHi00',$start_unixtime),
			'end' => date('YmdHi00',$end_unixtime),
			'text' => $summary, 
			'descr' => $description, 
			'place' => $location, 
			//'organizer' => serialize($organizer), 
			'attext' => $attendee, 
			//'calnumber' => $calnumber, 
			//'url' => $url, 
			//'status' => $status, 
			'visib' => "$class",
			'cat' => $category,
			'KOIDD' => $this->diary['KOID'],
			'KOIDS' => NULL,
			'allday' => 0,
			'isrecall' => 0,
			'recall' => $trigger,
			'repet' => &$repetfreq,
			'end_rep' => &$repetuntil,
			'tplentry'=>TZR_RETURN_DATA);
	  
	  if (!$write_processed) $master_array[($start_date)][($hour.$minute)][$uid]['exception'] = true;
	}
      }

      // Handling of the recurring events, RRULE
      if (isset($rrule_array) && is_array($rrule_array)) {
	if (isset($allday_start) && $allday_start != '') {
	  $hour = '-';
	  $minute = '1';
	  $rrule_array['START_DAY'] = $allday_start;
	  $rrule_array['END_DAY'] = $allday_end;
	  $rrule_array['END'] = 'end';
	  $recur_start = $allday_start;
	  $start_date = $allday_start;
	  if (isset($allday_end)) {
	    $diff_allday_days = dayCompare($allday_end, $allday_start);
	  }else {
	    $diff_allday_days = 1;
	  }
	}else {
	  $rrule_array['START_DATE'] = $start_date;
	  $rrule_array['START_TIME'] = $start_time;
	  $rrule_array['END_TIME'] = $end_time;
	  $rrule_array['END'] = 'end';
	}
	$start_date_time = strtotime($start_date);
	$start_range_time = strtotime('19800101');
	$end_range_time = strtotime('20301231');
   
	foreach ($rrule_array as $key => $val) {
	  switch($key) {
	  case 'FREQ':
	    switch ($val) {
	    case 'YEARLY':    $freq_type = 'year';  break;
	    case 'MONTHLY':    $freq_type = 'month';  break;
	    case 'WEEKLY':    $freq_type = 'week';  break;
	    case 'DAILY':    $freq_type = 'day';    break;
	    case 'HOURLY':    $freq_type = 'hour';  break;
	    case 'MINUTELY':  $freq_type = 'minute';  break;
	    case 'SECONDLY':  $freq_type = 'second';  break;
	    }
	    $repetfreq=$val;
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = strtolower($val);
	    break;
	  case 'COUNT':
	    $count = $val;
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $count;
	    break;
	  case 'UNTIL':
	    $until = str_ireplace('T', '', $val);
	    $until = str_ireplace('Z', '', $until);
	    if (strlen($until) == 8) $until = $until.'235959';
	    $abs_until = $until;
	    ereg ('([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})', $until, $regs);
	    $until = mktime($regs[4],$regs[5],$regs[6],$regs[2],$regs[3],$regs[1]);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = localizeDate($dateFormat_week,$until);
	    break;
	  case 'INTERVAL':
	    if ($val > 0){
	      $number = $val;
	      $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $number;
	    }
	    break;
	  case 'BYSECOND':
	    $bysecond = $val;
	    $bysecond = split (',', $bysecond);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $bysecond;
	    break;
	  case 'BYMINUTE':
	    $byminute = $val;
	    $byminute = split (',', $byminute);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $byminute;
	    break;
	  case 'BYHOUR':
	    $byhour = $val;
	    $byhour = split (',', $byhour);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $byhour;
	    break;
	  case 'BYDAY':
	    $byday = $val;
	    $byday = split (',', $byday);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $byday;
	    break;
	  case 'BYMONTHDAY':
	    $bymonthday = $val;
	    $bymonthday = split (',', $bymonthday);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $bymonthday;
	    break;          
	  case 'BYYEARDAY':
	    $byyearday = $val;
	    $byyearday = split (',', $byyearday);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $byyearday;
	    break;
	  case 'BYWEEKNO':
	    $byweekno = $val;
	    $byweekno = split (',', $byweekno);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $byweekno;
	    break;
	  case 'BYMONTH':
	    $bymonth = $val;
	    $bymonth = split (',', $bymonth);
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $bymonth;
	    break;
	  case 'BYSETPOS':
	    $bysetpos = $val;
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $bysetpos;
	    break;
	  case 'WKST':
	    $wkst = $val;
	    $master_array[($start_date)][($hour.$minute)][$uid]['recur'][$key] = $wkst;
	    break;
	  case 'END':
	    $recur = $master_array[($start_date)][($hour.$minute)][$uid]['recur'];
	    
	    // Modify the COUNT based on BYDAY
	    if ((is_array($byday)) && (isset($count))) {
	      $blah = sizeof($byday);
	      $count = ($count / $blah);
	      unset ($blah);
	    }
	    if (!isset($number)) $number = 1;

	    // if $until isn't set yet, we set it to the end of our range we're looking at
	    if (!isset($until)) $until = $end_range_time;
	    if (!isset($abs_until)) $abs_until = date('YmdHis', $end_range_time);
	    $end_date_time = $until;
	    $start_range_time_tmp = $start_range_time;
	    $end_range_time_tmp = $end_range_time;
              
	    // If the $end_range_time is less than the $start_date_time, or $start_range_time is greater
	    // than $end_date_time, we may as well forget the whole thing
	    // It doesn't do us any good to spend time adding data we aren't even looking at
	    // this will prevent the year view from taking way longer than it needs to
	    if ($end_range_time_tmp >= $start_date_time && $start_range_time_tmp <= $end_date_time) {
                
	      // if the beginning of our range is less than the start of the item, we may as well set it equal to it
	      if ($start_range_time_tmp < $start_date_time){
		$start_range_time_tmp = $start_date_time;
	      }  
	      if ($end_range_time_tmp > $end_date_time) $end_range_time_tmp = $end_date_time;
	      
	      // initialize the time we will increment
	      $next_range_time = $start_range_time_tmp;
	      
	      // FIXME: This is a hack to fix repetitions with $interval > 1 
	      if ($count > 1 && $number > 1) $count = 1 + ($count - 1) * $number; 
	      
	      $count_to = 0;
	      // start at the $start_range and go until we hit the end of our range.
	      if(!isset($wkst)) $wkst='SU';
	      $wkst3char = two2threeCharDays($wkst);
	      
	      if($count!=1000000) {
		$repetuntil=date('Y-m-d',strtotime("+".($count-1)." $freq_type",$start_range_time_tmp));
	      }else {
		$repetuntil=date('Y-m-d',$end_range_time_tmp);
	      }

	      $actrepet=0;
	      while (($next_range_time >= $start_range_time_tmp) && ($next_range_time <= $end_range_time_tmp) && ($count_to != $count)
		     && ($actrepet < $maxrepet)) {
		$func = $freq_type.'Compare';
		$diff = $func(date('Ymd',$next_range_time), $start_date);
		if ($diff < $count) {
		  if ($diff % $number == 0) {
		    $interval = $number;
		    switch ($rrule_array['FREQ']) {
		    case 'DAILY':
		      $next_date_time = $next_range_time;
		      $recur_data[] = $next_date_time;
		      break;
		    case 'WEEKLY':
		      // Populate $byday with the default day if it's not set.
		      if (!isset($byday)) {
			$byday[] = strtoupper(substr(date('D', $start_date_time), 0, 2));
		      }
		      if (is_array($byday)) {
			foreach($byday as $day) {
			  $day = two2threeCharDays($day);  
#need to find the first day of the appropriate week.
#dateOfweek uses weekstartday as a global variable. This has to be changed to $wkst, 
#but then needs to be reset for other functions
			  $week_start_day_tmp = $week_start_day;
			  $week_start_day = $wkst3char;
			  
			  $the_sunday = dateOfWeek(date("Ymd",$next_range_time), $wkst3char);

			  $next_date_time = strtotime($day,strtotime($the_sunday)) + (12 * 60 * 60);
			  $week_start_day = $week_start_day_tmp; 
			  #reset $next_range_time to first instance in this week.
			  if ($next_date_time < $next_range_time){ 
			    $next_range_time = $next_date_time; 
			  }
			  // Since this renders events from $next_range_time to $next_range_time + 1 week, I need to handle intervals
			  // as well. This checks to see if $next_date_time is after $day_start (i.e., "next week"), and thus
			  // if we need to add $interval weeks to $next_date_time.
			  if ($next_date_time > strtotime($week_start_day, $next_range_time) && $interval > 1) {
			    //$next_date_time = strtotime('+'.($interval - 1).' '.$freq_type, $next_date_time);
			  }
			  if($next_range_time != $start_range_time_tmp)
			    $recur_data[] = $next_date_time;
			}
		      }
		      break;
		    case 'MONTHLY':
		      if (empty($bymonth)) $bymonth = array(1,2,3,4,5,6,7,8,9,10,11,12);
		      $next_range_time = strtotime(date('Y-m-01', $next_range_time));
		      $next_date_time = $next_date_time;
		      
		      if(!isset($bysetpos)&&empty($bymonthday)&&!isset($byday))
			$bymonthday[]=date('j',$start_range_time_tmp);
		      
		      if (isset($bysetpos)){
			/* bysetpos code from dustinbutler
                              start on day 1 or last day. 
                              if day matches any BYDAY the count is incremented. 
                              SETPOS = 4, need 4th match 
                              SETPOS = -1, need 1st match 
			*/ 
			$year = date('Y', $next_range_time); 
			$month = date('m', $next_range_time); 
			if ($bysetpos > 0) { 
			  $next_day = '+1 day'; 
			  $day = 1; 
			} else { 
			  $next_day = '-1 day'; 
			  $day = $totalDays[$month]; 
			} 
			$day = mktime(0, 0, 0, $month, $day, $year); 
			$countMatch = 0; 
			while ($countMatch != abs($bysetpos)) { 
			  /* Does this day match a BYDAY value? */ 
			  $thisDay = $day; 
			  $textDay = strtoupper(substr(date('D', $thisDay), 0, 2)); 
			  if (in_array($textDay, $byday)) { 
			    $countMatch++; 
			  } 
			  $day = strtotime($next_day, $thisDay); 
			} 
			$recur_data[] = $thisDay; 
		      }elseif ((isset($bymonthday)) && (!isset($byday))) {
			foreach($bymonthday as $day) {
			  if ($day < 0) $day = ((date('t', $next_range_time)) + ($day)) + 1;
			  $year = date('Y', $next_range_time);
			  $month = date('m', $next_range_time);
			  if (checkdate($month,$day,$year)) {
			    $next_date_time = mktime(0,0,0,$month,$day,$year);
			    $recur_data[] = $next_date_time;
			  }
			}
		      } elseif (is_array($byday)) {
			foreach($byday as $day) {
			  ereg ('([-\+]{0,1})?([0-9]{1})?([A-Z]{2})', $day, $byday_arr);
			  //Added for 2.0 when no modifier is set
			  if ($byday_arr[2] != '') {
			    $nth = $byday_arr[2]-1;
			  } else {
			    $nth = 0;
			  }
			  $on_day = two2threeCharDays($byday_arr[3]);
			  $on_day_num = two2threeCharDays($byday_arr[3],false);
			  if ((isset($byday_arr[1])) && ($byday_arr[1] == '-')) {
			    $last_day_tmp = date('t',$next_range_time);
			    $next_range_time = strtotime(date('Y-m-'.$last_day_tmp, $next_range_time));
			    $last_tmp = (date('w',$next_range_time) == $on_day_num) ? '' : 'last ';
			    $next_date_time = strtotime($last_tmp.$on_day.' -'.$nth.' week', $next_range_time);
			    $month = date('m', $next_date_time);
			    if (in_array($month, $bymonth)) {
			      $recur_data[] = $next_date_time;
			    }
			    //reset next_range_time to start of month
			    $next_range_time = strtotime(date('Y-m-'.'1', $next_range_time));
			    
			  } elseif (isset($bymonthday) && (!empty($bymonthday))) {
			    // This supports MONTHLY where BYDAY and BYMONTH are both set
			    foreach($bymonthday as $day) {
			      $year   = date('Y', $next_range_time);
			      $month   = date('m', $next_range_time);
			      if (checkdate($month,$day,$year)) {
				$next_date_time = mktime(0,0,0,$month,$day,$year);
				$daday = strtolower(strftime("%a", $next_date_time));
				if ($daday == $on_day && in_array($month, $bymonth)) {
				  $recur_data[] = $next_date_time;
				}
			      }
			    }
			  } elseif ((isset($byday_arr[1])) && ($byday_arr[1] != '-')) {
			    $next_date_time = strtotime($on_day.' +'.$nth.' week', $next_range_time);
			    $month = date('m', $next_date_time);
			    if (in_array($month, $bymonth)) {
			      $recur_data[] = $next_date_time;
			    }
			  }
			  $next_date = date('Ymd', $next_date_time);
			}
		      }
		      break;
		    case 'YEARLY':
		      if ((!isset($bymonth)) || (sizeof($bymonth) == 0)) {
			$m = date('m', $start_date_time);
			$bymonth = array("$m");
		      }  
		      
		      foreach($bymonth as $month) {
			// Make sure the month & year used is within the start/end_range.
			if ($month < date('m', $next_range_time)) {
			  $year = date('Y', strtotime('+1 years', $next_range_time));
			} else {
			  $year = date('Y', $next_range_time);
			}
			if (isset($bysetpos)){
			  /* bysetpos code from dustinbutler
                                start on day 1 or last day. 
                                if day matches any BYDAY the count is incremented. 
                                SETPOS = 4, need 4th match 
                                SETPOS = -1, need 1st match 
			  */ 
			  if ($bysetpos > 0) { 
			    $next_day = '+1 day'; 
			    $day = 1; 
			  } else { 
			    $next_day = '-1 day'; 
			    $day = date("t",$month); 
			  } 
			  $day = mktime(12, 0, 0, $month, $day, $year); 
			  $countMatch = 0; 
			  while ($countMatch != abs($bysetpos)) { 
			    /* Does this day match a BYDAY value? */ 
			    $thisDay = $day;
			    $textDay = strtoupper(substr(date('D', $thisDay), 0, 2)); 
			    if (in_array($textDay, $byday)) { 
			      $countMatch++; 
			    } 
			    $day = strtotime($next_day, $thisDay); 
			  } 
			  $recur_data[] = $thisDay;                               
			}
			if ((isset($byday)) && (is_array($byday))) {
			  $checkdate_time = mktime(0,0,0,$month,1,$year);
			  foreach($byday as $day) {
			    ereg ('([-\+]{0,1})?([0-9]{1})?([A-Z]{2})', $day, $byday_arr);
			    if ($byday_arr[2] != '') {
			      $nth = $byday_arr[2]-1;
			    } else {
			      $nth = 0;
			    }
			    $on_day = two2threeCharDays($byday_arr[3]);
			    $on_day_num = two2threeCharDays($byday_arr[3],false);
			    if ($byday_arr[1] == '-') {
			      $last_day_tmp = date('t',$checkdate_time);
			      $checkdate_time = strtotime(date('Y-m-'.$last_day_tmp, $checkdate_time));
			      $last_tmp = (date('w',$checkdate_time) == $on_day_num) ? '' : 'last ';
			      $next_date_time = strtotime($last_tmp.$on_day.' -'.$nth.' week', $checkdate_time);
			    } else {                              
			      $next_date_time = strtotime($on_day.' +'.$nth.' week', $checkdate_time);
			    }
			  }
			} else {
			  $day   = date('d', $start_date_time);
			  $next_date_time = mktime(0,0,0,$month,$day,$year);
			  //echo date('Ymd',$next_date_time).$summary.'<br>';
			}
			$recur_data[] = $next_date_time;
		      }
		      if (isset($byyearday)) {
			foreach ($byyearday as $yearday) {
			  ereg ('([-\+]{0,1})?([0-9]{1,3})', $yearday, $byyearday_arr);
			  if ($byyearday_arr[1] == '-') {
			    $ydtime = mktime(0,0,0,12,31,$this_year);
			    $yearnum = $byyearday_arr[2] - 1;
			    $next_date_time = strtotime('-'.$yearnum.' days', $ydtime);
			  } else {
			    $ydtime = mktime(0,0,0,1,1,$this_year);
			    $yearnum = $byyearday_arr[2] - 1;
			    $next_date_time = strtotime('+'.$yearnum.' days', $ydtime);
			  }
			  $recur_data[] = $next_date_time;
			}
		      } 
		      break;
		    default:
		      // anything else we need to end the loop
		      $next_range_time = $end_range_time_tmp + 100;
		      $count_to = $count;
		    }
		  } else {
		    $interval = 1;
		  }
		  $next_range_time = strtotime('+'.$interval.' '.$freq_type, $next_range_time);
		} else {
		  // end the loop because we aren't going to write this event anyway
		  $count_to = $count;
		}
		// use the same code to write the data instead of always changing it 5 times            
		if (isset($recur_data) && is_array($recur_data)) {
		  $recur_data_hour = @substr($start_time,0,2);
		  $recur_data_minute = @substr($start_time,2,2);
		  foreach($recur_data as $recur_data_time) {
		    $recur_data_year = date('Y', $recur_data_time);
		    $recur_data_month = date('m', $recur_data_time);
		    $recur_data_day = date('d', $recur_data_time);
		    $recur_data_date = $recur_data_year.$recur_data_month.$recur_data_day;
		    
		    if (($recur_data_time > $start_date_time) && ($recur_data_time <= $end_date_time) && ($count_to != $count)
			&& ($actrepet < $maxrepet)) {
		      if (isset($allday_start) && $allday_start != '') {
			$start_time2 = $recur_data_time;
			$end_time2 = strtotime('+'.$diff_allday_days.' days', $recur_data_time);
			
			$ma[]= array (
				      'type' => 'Repetition Jour Entier',
				      'UIDI' => $uid,
				      'begin' => date('Ymd000000 ',$start_time2),
				      'end' => date('Ymd',strtotime('-1 day',$end_time2)).'235900',
				      'text' => $summary, 
				      'descr' => $description, 
				      'place' => $location, 
				      //'organizer' => serialize($organizer), 
				      'attext' => $attendee, 
				      //'calnumber' => $calnumber, 
				      //'url' => $url, 
				      //'status' => $status, 
				      'visib' => "$class",
				      'cat' => $category,
				      'KOIDD' => $this->diary['KOID'],
				      'KOIDS' => &$koids,
				      'allday' => 1,
				      'multid' => $multid,
				      'isrecall' => 0,
				      'recall' => $trigger,
				      'repet' => &$repetfreq,
				      'end_rep' => &$repetuntil,
				      'tplentry'=>TZR_RETURN_DATA);  
		      }else {
			$start_unixtime_tmp=mktime($recur_data_hour,$recur_data_minute,0,
						   $recur_data_month,$recur_data_day,$recur_data_year);
			$end_unixtime_tmp = $start_unixtime_tmp + $length;
			if (($end_time >= $bleed_time) && ($bleed_check == '-1')) {
			  $ma[]= array (
					'type' => 'Repetition Plusieurs Jours',
					'UIDI' => $uid,
					'begin' => date('YmdHi00',$start_unixtime_tmp),
					'end' => date('YmdHi00',$end_unixtime_tmp),
					'text' => $summary, 
					'descr' => $description, 
					'place' => $location, 
					//'organizer' => serialize($organizer), 
					'attext' => $attendee, 
					//'calnumber' => $calnumber, 
					//'url' => $url, 
					//'status' => $status, 
					'visib' => "$class",
					'cat' => $category,
					'KOIDD' => $this->diary['KOID'],
					'KOIDS' => &$koids,
					'allday' => 0,
					'multid' => $multid,
					'isrecall' => 0,
					'recall' => $trigger,
					'repet' => &$repetfreq,
					'end_rep' => &$repetuntil,
					'tplentry'=>TZR_RETURN_DATA);                                  
			  
			  if (isset($display_end_tmp)) {
			    $master_array[$start_date_tmp][$time_tmp][$uid]['display_end'] = $display_end_tmp;
			  }
			} else {
			  if ($bleed_check == '-1') {
			    $display_end_tmp = $end_time;
			    $end_time_tmp1 = '2400';
			    
			  }
			  if (!isset($end_time_tmp1)) $end_time_tmp1 = $end_time;
			  
			  // Let's double check the until to not write past it
			  $until_check = $recur_data_date.$hour.$minute.'00';
			  if ($abs_until > $until_check) {
			    $ma[]= array (
					  'type' => 'Repetition Un Jour',
					  'UIDI' => $uid,
					  'begin' => $recur_data_date.$start_time.'00',
					  'end' => $recur_data_date.$end_time_tmp1.'00',
					  'text' => $summary, 
					  'descr' => $description, 
					  'place' => $location, 
					  //'organizer' => serialize($organizer), 
					  'attext' => $attendee, 
					  //'calnumber' => $calnumber, 
					  //'url' => $url, 
					  //'status' => $status, 
					  'visib' => "$class",
					  'cat' => $category,
					  'KOIDD' => $this->diary['KOID'],
					  'KOIDS' => &$koids,
					  'allday' => 0,
					  'isrecall' => 0,
					  'recall' => $trigger,
					  'repet' => &$repetfreq,
					  'end_rep' => &$repetuntil,
					  'tplentry'=>TZR_RETURN_DATA);                                
			    
			    if (isset($display_end_tmp)) {
			      $master_array[($recur_data_date)][($hour.$minute)][$uid]['display_end'] = $display_end_tmp;
			    }
			  }
			}
		      }
		    }
		  }
		  unset($recur_data);
		}
		$actrepet++;
	      }
	      if($actrepet==$maxrepet) $repetuntil=date('Y-m-d',strtotime($recur_data_date));
	    }
	  }
	}
      }else {
	$repetfreq='NO';
	$repetuntil=null;
      }
      
      // Clear event data now that it's been saved.
      unset($start_time, $start_time_tmp, $end_time, $end_time_tmp, $start_unixtime, $start_unixtime_tmp, $end_unixtime, $end_unixtime_tmp,
	    $summary, $length, $description, $status, $class, $location, $organizer, $attendee);
      
      $koids=NULL;
      if(empty($this->tmp->tzrchecked)) checkEvent($this,$uid,$modified,true);
      if(!$this->tmp->gotonext){
	if(!empty($except_dates)) {
	  foreach($except_dates as $tmped) {
	    $except.=$tmped.';';
	  }
	  $except=substr($except,0,(strlen($except)-1));
	  $ev['except']=$except;
	}
	if(!empty($this->tmp->tzrevent)){
	  $this->delEvt(array('koid'=>$this->tmp->tzrevent['KOID'],'noalert'=>true));
	  $ma[0]['newoid']=$this->tmp->tzrevent['KOID'];
	}
	$tlinks=array();
	foreach($csattendee as $z=>$csatt){
	  $rs=getDB()->select('select ag.KOID from '.$this->tagenda.' as ag left outer join USERS as u on u.KOID=ag.OWN '.
			   'where ag.name=? and u.fullnam=?', array($csatt['name'],$csatt['own']));
	  if($rs->RecordCount()>0){
	    $ors=$rs->fetchRow();
	    $tlinks[]=$ors['KOID'];
	  }
	}
	unset($csattendee);
	foreach($ma as $num=>$ev){
	  if(!in_array(date('Ymd',strtotime($ev['begin'])),$except_dates) || $num==0){
	    $r=$xsetev->desc['allday']->post_edit($ev['allday']);
	    if(empty($ev['visib'])){
	      if(!empty($this->diary['defvisi'])) $ev['visib']=$this->diary['defvisi'];
	      else $ev['visib']='PR';
	    }
	    if(empty($modified)) $modified=null;
	    $ev['UPD']=$modified;
	    $tmp=implode(';',$rrule);
	    // Dasn le cas d'une regle non gérée par la console, on enregistre la regle directement
	    if(!preg_match('/^(RRULE|FREQ|DAILY|WEEKLY|MONTHLY|YEARLY|UNTIL|EXDATE|VALUE|COUNT|DATE|INTERVAL=1|[;:=0-9 -])*$/i',$tmp)){
	      $ev['rrule']=$tmp;
	      unset($ev['repet'],$ev['end_rep'],$ev['except']);
	    }
	    $ret=$xsetev->procInput($ev);
	    $xsetlink->procInput(array('KOIDE'=>$ret['oid'],'KOIDD'=>$this->diary['KOID'],'UPD'=>$modified));
	    foreach($tlinks as $doid) $xsetlink->procInput(array('KOIDE'=>$ret['oid'],'KOIDD'=>$doid,'UPD'=>$modified));
	    if($num==0) $koids=$ret['oid'];
	  }
	}
      }
      break;
    case 'END:VTODO':
      if ((!$vtodo_priority) && ($status == 'COMPLETED')) {
	$vtodo_sort = 11;
      } elseif (!$vtodo_priority) { 
	$vtodo_sort = 10;
      } else {
	$vtodo_sort = $vtodo_priority;
      }
      
      $master_array['-2']["$vtodo_sort"]["$uid"] = array ('start_date' => $start_date, 'start_time' => $start_time, 'vtodo_text' => $summary, 'due_date'=> $due_date, 'due_time'=> $due_time, 'completed_date' => $completed_date, 'completed_time' => $completed_time, 'priority' => $vtodo_priority, 'status' => $status, 'class' => $class, 'cat' => $vtodo_category, 'description' => $description);
      unset ($start_date, $start_time, $due_date, $due_time, $completed_date, $completed_time, $vtodo_priority, $status, $class, $vtodo_category, $summary, $description);
      $vtodo_set = FALSE;
      break;
          
    case 'BEGIN:VTODO':
      $vtodo_set = TRUE;
      break;
    case 'BEGIN:VALARM':
      $valarm_set = TRUE;
      break;
    case 'END:VALARM':
      $valarm_set = FALSE;
      break;
      
    default:
      unset ($field, $data, $prop_pos, $property);
      if (ereg ("([^:]+):(.*)", $line, $line)){
	$field = $line[1];
	$data = $line[2];
	
	$property = $field;
	$prop_pos = strpos($property,';');
	if ($prop_pos !== false) $property = substr($property,0,$prop_pos);
	$property = strtoupper($property);
	
	switch ($property) {
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
	  
	case 'CLASS':
	  $class = getClass($data);
	  break;
	  
	case 'CATEGORIES':
	  if(!isset($categories[$data])) {
	    $ret=$xsetcat->procInput(array('name'=>$data,
					   'time'=>60,
					   'visib'=>'PR',
					   'recall'=>0,
					   'commun'=>2,
					   'allday'=>0,
					   'tplentry'=>TZR_RETURN_DATA));
	    $categories[$data]=$ret['oid'];
	  }
	  $category = $categories[$data];
	  break;
	  //
	  // End VTODO Parsing
	  
	case 'DTSTART':
	  $datetime = extractDateTime($data, $property, $field);
	  $start_unixtime = $datetime[0];
	  $start_date = $datetime[1];
	  $start_time = $datetime[2];
	  $allday_start = $datetime[3];
	  break;
	  
	case 'DTEND':
	  $datetime = extractDateTime($data, $property, $field);
	  $end_unixtime = $datetime[0];
	  $end_date = $datetime[1];
	  $end_time = $datetime[2];
	  $allday_end = $datetime[3];
	  break;
	  
	case 'EXDATE':
	  $data = split(",", $data);
	  foreach ($data as $exdata) {
	    $exdata = str_ireplace('T', '', $exdata);
	    $exdata = str_ireplace('Z', '', $exdata);
	    preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{0,2})([0-9]{0,2})/', $exdata, $regs);
	    $except_dates[] = $regs[1] . $regs[2] . $regs[3];
	    // Added for Evolution, since they dont think they need to tell me which time to exclude.
	    if (($regs[4] == '') && ($start_time != '')) {
	      $except_times[] = $start_time;
	    } else {
	      $except_times[] = $regs[4] . $regs[5];
	    }
	  }
	  break;
	  
	case 'SUMMARY':
	  $data=addslashes($this->unescapeICSText($data));
	  if ($valarm_set == FALSE) { 
	    $summary = $data;
	  } else {
	    $valarm_summary = $data;
	  }
	  break;
	  
	case 'DESCRIPTION':
	  $data=addslashes($this->unescapeICSText($data));
	  if ($valarm_set == FALSE) { 
	    $description = $data;
	  } else {
	    $valarm_description = $data;
	  }
	  break;
	  
	case 'RECURRENCE-ID':
	  $parts = explode(';', $field);
	  foreach($parts as $part) {
	    $eachval = split('=',$part);
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
	  
	  $data = str_ireplace('T', '', $data);
	  $data = str_ireplace('Z', '', $data);
	  ereg ('([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{0,2})([0-9]{0,2})', $data, $regs);
	  $recurrence_id['date'] = $regs[1] . $regs[2] . $regs[3];
	  $recurrence_id['time'] = $regs[4] . $regs[5];
	  
	  $recur_unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]);
	  
	  $dlst = date('I', $recur_unixtime);
	  $server_offset_tmp = chooseOffset($recur_unixtime);
	  if (isset($recurrence_id['tzid'])) {
	    $tz_tmp = $recurrence_id['tzid'];
	    $offset_tmp = $tz_array[$tz_tmp][$dlst];
	  } elseif (isset($calendar_tz)) {
	    $offset_tmp = $tz_array[$calendar_tz][$dlst];
	  } else {
	    $offset_tmp = $server_offset_tmp;
	  }
	  $recur_unixtime = calcTime($offset_tmp, $server_offset_tmp, $recur_unixtime);
	  $recurrence_id['date'] = date('Ymd', $recur_unixtime);
	  $recurrence_id['time'] = date('Hi', $recur_unixtime);
	  $recurrence_d = date('Ymd', $recur_unixtime);
	  $recurrence_t = date('Hi', $recur_unixtime);
	  unset($server_offset_tmp);
	  break;
	  
	case 'UID':
	  $uid = $data;
	  $uids[]=$uid;
	  checkEvent($this,$uid,$modified);
	  break;
	  
	case 'X-WR-TIMEZONE':
	  $calendar_tz = $data;
	  $master_array['calendar_tz'] = $calendar_tz;
	  break;
	  
	case 'DURATION':
	  if (($first_duration == TRUE) && (!stristr($field, '=DURATION'))) {
	    ereg ('^P([0-9]{1,2}[W])?([0-9]{1,2}[D])?([T]{0,1})?([0-9]{1,2}[H])?([0-9]{1,2}[M])?([0-9]{1,2}[S])?', $data, $duration); 
	    $weeks       = str_ireplace('W', '', $duration[1]); 
	    $days       = str_ireplace('D', '', $duration[2]); 
	    $hours       = str_ireplace('H', '', $duration[4]); 
	    $minutes     = str_ireplace('M', '', $duration[5]); 
	    $seconds     = str_ireplace('S', '', $duration[6]); 
	    $the_duration   = ($weeks * 60 * 60 * 24 * 7) + ($days * 60 * 60 * 24) + ($hours * 60 * 60) + ($minutes * 60) + ($seconds);
	    $first_duration = FALSE;
	  }  
	  break;
	  
	case 'RRULE':
	  $data = str_ireplace ('RRULE:', '', $data);
	  if(!empty($data)){
	    $rrule = split (';', $data);
	    foreach ($rrule as $recur) {
	      ereg ('(.*)=(.*)', $recur, $regs);
	      $rrule_array[$regs[1]] = $regs[2];
	    }
	  }
	  break;
	  
	case 'ATTENDEE':
	  $field=str_ireplace("ATTENDEE;CN=", "", $field);
	  preg_match('#\[CS\] (.+) \((.+)\);#',$field.';',$cn);
	  if(count($cn)==3){
	    $csattendee[]=array('name'=>$cn[2],'own'=>$cn[1]);
	  }else{
	    preg_match('#((mailto)|(MAILTO)):([a-z0-9]+(\.[_a-z0-9-]+)*@[a-z0-9._-]{2,}\.[a-z]{2,4})#',$data,$data);
	    if(!empty($data[4])) $attendee.= stripslashes($data[4])."\r\n";
	  }
	  break;
	  
	case 'ORGANIZER':
	  $field      = str_ireplace("ORGANIZER;CN=", "", $field);
	  $data      = str_ireplace ("mailto:", "", $data);
	  $organizer[] = array ('name' => stripslashes($field), 'email' => stripslashes($data));
	  break;
	  
	case 'LOCATION':
	  $data = addslashes($this->unescapeICSText($data));
	  //	  $data = stripslashes($data);
	  $location = $data;
	  break;
	  
	case 'URL':
	  $url = $data;
	  break;
	  
	case 'LAST-MODIFIED':
	  $tmp = extractDateTime($data, $property, $field);
	  $modified = date('Y-m-d H:i:s',strtotime('GMT',$tmp[0]));
	  checkEvent($this,$uid,$modified);
	  break;
	  
	  case 'TRIGGER':
	    $data=str_ireplace('-', '', $data);
	  ereg ('^P([0-9]{1,2}[W])?([0-9]{1,2}[D])?([T]{0,1})?([0-9]{1,2}[H])?([0-9]{1,2}[M])?', $data, $duration); 
	  $weeks       = str_ireplace('W', '', $duration[1]); 
	  $days       = str_ireplace('D', '', $duration[2]); 
	  $hours       = str_ireplace('H', '', $duration[4]); 
	  $minutes     = str_ireplace('M', '', $duration[5]); 
	  $trigger   = ($weeks * 60 * 24 * 7) + ($days * 60 * 24) + ($hours * 60) + ($minutes);
	  break;
	}
      }
      }
  }

  // Si on est dans une synchonisation distante, on suppression les evenements qui ne sont pas dans le fichier à importer
  if($this->synchro){
    $rs=getDB()->select('SELECT * FROM '.$this->tevt.' WHERE KOIDD=?',array($this->diary['KOID']));
    while($rs && ($line=$rs->fetch())){
      if(!in_array($line['KOID'],$uids) && !in_array($line['UIDI'],$uids) && (in_array($this->diary['KOID'],$this->getAuthorizedDiaries('rwv')) || $line['visib']=='PU')){
	$this->delEvt(array('koid'=>$line['KOID'],'noalert'=>true));
      }
    }
  }
}
//RZ MARCHE PAS updateQuery('UNLOCK TABLES');

function checkEvent(&$mod,$uid,$modified,$setmodified){
  if(!empty($uid) && !empty($modified)){
    $mod->tmp->tzrchecked=true;
    $tzrevent=getDB()->fetchRow("SELECT e.* FROM {$mod->tevt} as e left outer join {$mod->tlinks} as l on e.KOID=l.KOIDE ".
		     "where (e.UIDI='$uid' || e.KOID='$uid') AND l.KOIDD='{$mod->diary['KOID']}'");
    if($tzrevent) {
      $mod->tmp->tzrevent=$tzrevent;
      if(!(($tzrevent['UPD'] < $modified || $setmodified && empty($modified)) && $mod->diary['KOID']==$tzrevent['KOIDD'] && ($tzrevent['visib']=='PU' || in_array($mod->diary['KOID'],$mod->getAuthorizedDiaries('rwv'))))){
	$mod->tmp->gotonext=true;
      }
    }
  }
}

function getClass($class) {
  if($class=="PRIVATE") {
    return "PR";
  }else if($class=="PUBLIC") {
    return "PU";
  }else {
    return "OC";
  }
}
?>
