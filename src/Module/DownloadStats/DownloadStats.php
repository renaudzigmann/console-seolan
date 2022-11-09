<?php
namespace Seolan\Module\DownloadStats;
/// Statistiques d'utilisation téléchargements
class DownloadStats extends \Seolan\Core\Module\Module {
  // conf open flash chart
  private $colours = array( '#004040', '#008000', '#FF0000', '#0000FF', '#00FF00',
                            '#2CFFE7', '#FF5F20', '#742B0E', '#800080', '#FFFF00',
                            '#C00000', '#00C0C0', '#580000', '#007A00', '#FFBB00',
                            '#CF4812', '#78B753', '#8F6A32', '#F83496', '#A0A0A0');
  private $gridColour = '#E0E1E4';
  private $xcolor = '#cccccc';
  private $ycolor = '#ffffff';
  private $widgets = array(
            'files'   => 'Téléchargements (Mo)',
          );
  private $view_details = array (
            'files',
          );
  
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_DownloadStats_DownloadStats');
    if ($GLOBALS['LANG_USER'] == 'FR')
      setlocale( LC_TIME, 'fr_FR' );
  }

  // initialisation des proprietes
  //
  public function initOptions() {
    parent::initOptions();
  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array(
      'index'       => array('ro','rw','rwv','admin'),
      'clearStats'  => array('admin'),
      'trace'       => array('none'),
      'get_data'    => array('r', 'rw', 'rwv', 'admin'),
      'get_csv'     => array('r', 'rw', 'rwv', 'admin'),
    );
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  
  // cette fonction est appliquee pour afficher l'ensemble des methodes
  // de ce module
  //
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my);
    $moid=$this->_moid;
    $o1=new \Seolan\Core\Module\Action($this, 'index', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DownloadStats_DownloadStats','index','text'),
                          '&moid='.$moid.'&_function=index&template=Module/DownloadStats.index.html&tplentry=br','display');
//     $o1->homepageable=$o1->menuable=$o1->quicklinkable=true;
    $o1->containable=true;
    $o1->setToolbar('Seolan_Core_General','browse');
    $my['index']=$o1;
//     $my['default']='index';

    if ($this->secure('', 'clearStats')) {
      $o1 = new \Seolan\Core\Module\Action($this, 'clearStats', \Seolan\Core\Labels::getSysLabel('Seolan_Module_DownloadStats_DownloadStats','clearstats','text'),
            '&moid='.$moid.'&_function=clearStats&template=Module/DownloadStats.index.html');
      $o1->homepageable=false;
      $o1->menuable=true;
      $o1->quicklinkable = false;
      $o1->needsconfirm = \Seolan\Core\Labels::getSysLabel('Seolan_Module_DownloadStats_DownloadStats','clearconfirm','text');
      $o1->group = 'edit';
      $my['clearStats'] = $o1;
    }
  }

  /// Action principale du menu
  public function getMainAction(){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=index&template=Module/DownloadStats.index.html&tplentry=br';
  }

  // ajout d'une trace de download
  static function trace($userid, $moid, $file, $size) {
    $newkoid = \Seolan\Core\DataSource\DataSource::getNewBasicOID('_DOWNLOADS');
    getDB()->execute("insert into _DOWNLOADS values ('$newkoid', '$userid', '$moid', '$file', $size, now(), now())");
  }

  // creation de la table (depuis wizard)
  static function createTasks() {
    if (\Seolan\Core\System::tableExists('_DOWNLOADS')) {
      return;
    }
    getDB()->execute('CREATE TABLE _DOWNLOADS (
                `KOID` varchar(40) NOT NULL default "",
                `USER` varchar(40),
                `SMOID` varchar(10) default NULL,
                `file` varchar(80) default NULL,
                `size` double default 0,
                `date` date default NULL,
                `time` time default NULL,
                PRIMARY KEY  (`KOID`),
                KEY `USER` (`USER`(40)),
                KEY `SMOID` (`SMOID`),
                KEY `DATE_TIME` (`date`,`time`))');
  }

  // suppression des donnes pour un module
  //
  static function clean($moid=NULL, $user=NULL) {
    if (!\Seolan\Core\System::tableExists('_DOWNLOADS'))
      return false;
    if (!empty($moid))
      getDB()->execute('DELETE FROM _DOWNLOADS WHERE SMOID="'.$moid.'"');
  }
  // suppression de toutes les données
  //
  static function clearStats() {
    if (!\Seolan\Core\System::tableExists('_DOWNLOADS'))
      return false;
    getDB()->execute('DELETE FROM _DOWNLOADS');
  }

  function chk(&$message=NULL) {
    // suppression des donnees statistiques sur les modules supprimes
    $rs = getDB()->select('select distinct MOID,SMOID from _DOWNLOADS LEFT OUTER JOIN MODULES ON MOID=SMOID WHERE ISNULL(MOID) and NOT SMOID="";');
    if (!empty($rs)) {
      while ($ors = $rs->fetch()) {
        getDB()->execute('DELETE FROM _DOWNLOADS WHERE SMOID="'.$ors['SMOID'].'"');
        $messge .= 'Deleted stats in _DOWNLOADS table for module '.$ors['SMOID'];
      }
      $rs->closeCursor();
    }
  }
  
  /// 
  function index() {
    $GLOBALS['XSHELL']->setTemplates('Module/DownloadStats.index.html');
    $this->get_interval();
    \Seolan\Core\Shell::toScreen1('interval', $this->interval);
    $filter = array(
      'mod' => $this->mod_list(),
      'selected_mod' => '',
      'user' => $this->user_list(),
      'selected_user' => ''
    );
    foreach ($this->widgets as $widget => $title) {
      $func = "get_$widget";
      $data[] = $this->$func($title);
      $titles[] = $title;
    }
    \Seolan\Core\Shell::toScreen1('filter', $filter);
    \Seolan\Core\Shell::toScreen2('data',  'widget',  $data);
    \Seolan\Core\Shell::toScreen2('list',  'widget',  array_keys($this->widgets));
    \Seolan\Core\Shell::toScreen2('title', 'widget',  $titles);
    \Seolan\Core\Shell::toScreen2('view',  'details', $this->view_details);
  }

  function get_interval() {
    $p = new \Seolan\Core\Param(NULL, array(
              'view' => 'week',
              'ts' => time()
            ));
    // traitement période
    $ts = $p->get('ts');
    $view = $p->get('view');
    switch ($view) {
      case "week":
        $start_ts = mktime(0,0,0,date('m',$ts),1+date('d',$ts)-date('N',$ts),date('Y',$ts));
        $end_ts = $start_ts+ 6*60*60*24;
        $title = 'Semaine du ' . date('d/m/Y', $start_ts) . ' au ' . date('d/m/Y', $end_ts);
        $prev = $start_ts - 7*60*60*24;
        $next = $start_ts + 7*60*60*24;
        $start = date('Y-m-d', $start_ts);
        $end = date('Y-m-d', $next);
        break;
      case "month":
        $start_ts = mktime(0,0,0,date('m',$ts),1,date('Y',$ts));
        $title = 'Vue mensuelle '.utf8_encode( strftime('%B %Y', $start_ts));
        $prev = mktime(0,0,0,date('m',$ts)-1,1,date('Y',$ts));
        $next = mktime(0,0,0,date('m',$ts)+1,1,date('Y',$ts));
        $start = date('Y-m-d', $start_ts);
        $end_ts = $next-60*60*24;
        $end = date('Y-m-d', $end_ts);
        break;
      case "year":
        $start_ts = mktime(0,0,0,1,1,date('Y',$ts));
        $title = 'Vue annuelle '.strftime('%Y', $start_ts);
        $prev = mktime(0,0,0,1-12,1,date('Y',$ts));
        $next = mktime(0,0,0,1+12,1,date('Y',$ts));
        $start = date('Y-m-d', $start_ts);
        $end_ts = $next-60*60*24;
        $end = date('Y-m-d', $end_ts);
        break;
    }
    $this->interval = array('view' => $view, 'ts' => $ts, 'title' => $title, 'start_ts' => $start_ts, 'end_ts' => $end_ts, 'start' => $start, 'end' => $end, 'next' => $next, 'prev' => $prev);
    setSessionVar('interval', $this->interval);
    return $this->interval;
  }
  
  // filtre : liste des utilisateurs
  function user_list() {
    if (isset($this->users))
      return $this->users;
    $rs = getDB()->select('select USER as koid, fullnam as nom from _DOWNLOADS join USERS on _DOWNLOADS.USER=USERS.KOID order by nom');
    $this->users[] = 'Utilisateur';
    while ($rs && $ors = $rs->fetch())
      $this->users[$ors['koid']] = $ors['nom'];
    return $this->users;
  }
  // filtre : liste des modules
  function mod_list() {
    if (isset($this->mod))
      return $this->mod;
    $rs = getDB()->select('select distinct SMOID as koid, MODULE as nom from _DOWNLOADS join MODULES on _DOWNLOADS.SMOID=MODULES.MOID order by nom');
    $this->mod[] = 'Modules';
    while ($rs && $ors = $rs->fetch())
      $this->mod[$ors['koid']] = $ors['nom'];
    return $this->mod;
  }

  // ajax chart reload
  function get_data($ar) {
    $p = new \Seolan\Core\Param($ar);
    $this->interval = getSessionVar('interval');
    $target = $p->get('target');
    if (!empty($target)) {
      $func = "get_$target";
      echo $this->$func($this->widgets[$target]);
    }
    exit;
  }
  // ajax csv export
  function get_csv($ar) {
    $p = new \Seolan\Core\Param($ar);
    $this->interval = getSessionVar('interval');
    $target = $p->get('target');
    if (!empty($target)) {
      $func = "get_$target";
      echo $this->$func($target, 'csv');
    }
    exit;
  }
  function get_files($title, $format=null) {
    $p = new \Seolan\Core\Param();
     // traitement du filtre
    $user = $p->get('user');
    if (!empty($user))
      $where_clause .= " and _DOWNLOADS.USER='$user'";
    $byuser = $p->get('byuser');
    if (!empty($byuser) && empty($user)) { // repartition par user
      $join .= ' join USERS on _DOWNLOADS.USER=USERS.koid';
      $select_fields .= ', USERS.fullnam as user';
      $fields[] = 'user';
    }
    $mod = $p->get('mod');
    if (!empty($mod))
      $where_clause .= " and _DOWNLOADS.SMOID='$moid'";
    $bymod = $p->get('bymod');
    if (!empty($bymod) && empty($mod)) { // repartition par module
      $join .= ' join MODULES on _DOWNLOADS.SMOID=MODULES.moid';
      $select_fields .= ', MODULES.MODULE as module';
      $fields[] = 'module';
    }
    
    if (isset($fields))
      $groupby = implode(',', $fields).',';
    $query = "select round(sum(_DOWNLOADS.size)/1024/1024,2) as val, _DOWNLOADS.date as item $select_fields from _DOWNLOADS $join where '".$this->interval['start']."' <= _DOWNLOADS.date and _DOWNLOADS.date <= '".$this->interval['end']."' $where_clause group by $groupby item order by $groupby item";
    $data = $this->parse_query($query, $fields);
    if ($format == 'csv')
      return $this->evolution_CSV($data, $title, $fields);
    return $this->evolution_Chart($data, $title, $fields);
  }

  // format data to csv
  function repartition_CSV($query, $title, $fields=null) {
    $vals = '$values';
    foreach ($fields as $field)
      $vals .= '[$ors["'.$field.'"]]';
    $vals .= '[]';
    $rs = getDB()->select($query);
    while ($rs && $ors = $rs->fetch()) {
      $items[] = $ors['item'];
      $data[] = $ors['val'];
      eval("$vals = {$ors['val']};");
    }
    header('Content-Type: text/csv;charset=ISO-8859-1');
    header('Content-Disposition: attachment;filename="'.$title.'_data.csv"');
    header('Cache-Control: max-age=0');
    if (!isset($fields)) {
      echo iconv("UTF-8", "ISO-8859-1", implode(';', $items));
      echo "\n".implode(';', $data)."\n";
    } else {
      foreach ($fields as $field)
        echo ';';
      echo iconv("UTF-8", "ISO-8859-1", $title);
      $this->render_CSV_lines($values);
    }
    exit;
  }

  // format data to csv
  function parse_query($query, $fields=null) {
    $vals = '$values';
    foreach ($fields as $field)
      $vals .= '[$ors["'.$field.'"]]';
    $init_vals = $vals;
    $vals .= '[$ors["item"]]';
    // init à 0 pour l'interval
    for ($ts = $this->interval['start_ts'], $i=0; $ts <= $this->interval['end_ts']; $ts += 60*60*24,$i++) {
      $emtpy_set[date('Y-m-d', $ts)] = 0;
      $items[] = date('d/m/y', $ts);
      $timestamps[] = $ts;
    }
    $rs = getDB()->select($query);
    while ($rs && $ors = $rs->fetch()) {
      eval("if(!is_array($init_vals)) $init_vals = \$emtpy_set;");
      eval("$vals = {$ors['val']};");
    }
    return array('items' => $items, 'values' => $values, 'timestamps' => $timestamps);
  }
  
  // output a csv file from data
  function evolution_CSV(&$data, $title, $fields) {
    header('Content-Type: text/csv;charset=ISO-8859-1');
    header('Content-Disposition: attachment;filename="'.$title.'_data.csv"');
    header('Cache-Control: max-age=0');
    foreach ($fields as $field)
      echo ';';
    echo iconv("UTF-8", "ISO-8859-1", implode(';', $data['items']));
    if (!isset($fields))
      echo "\n".implode(';', $data['values'])."\n";
    else
      $this->render_CSV_lines($data['values']);
    exit;
  }
  
  // output a csv line
  function render_CSV_lines(&$values, $prefix='') {
    foreach ($values as $key => $val) {
      if (is_array($val)) {
        echo "\n".iconv("UTF-8", "ISO-8859-1", $prefix.$key).";";
        $this->render_CSV_lines($val, $prefix.';')."";
      } else
        echo "$val;";
    }
  }

  // bar repartition chart
  function repartition_Chart($query, $title, $fields=null) {
    \Seolan\Core\System::loadVendor('open-flash-chart2/php-ofc-library/open-flash-chart.php');

    // the chart
    $chart = new \open_flash_chart();
    $chart->set_title( new \title( $title ) );
    // initialiser à 5 valeurs pour éviter les déformations
    $labels = array_fill(0, 5, '');
    $bars = array_fill(0, 5, null);
    $i = 0;
    $rs = getDB()->select($query);
    while ($rs && $ors = $rs->fetch()) {
      $val = (int)$ors['val'];
      $bar = new \bar_value($val);
      $text = '';
      foreach ($fields as $field) {
          $text .= $ors[$field].' ';
      }
      $bar->set_tooltip( $text.'<br>#val#' );
      $bar->set_colour( $this->colours[$i % count($this->colours)] );
      $data[$i] = $bar;
      $labels[$i] = $text;
      $maxdata = $maxdata > $val ? $maxdata : $val;
      $i++;
    }

    $bar = new \bar_cylinder();
    $bar->set_values( $data );

    $x_labels = new \x_axis_labels();
    $x_labels->set_labels( $labels );
    $x_labels->rotate( -20 );

    $x_axis = new \x_axis();
    $x_axis->set_labels($x_labels);
    $x_axis->set_3d( 2 );
    $x_axis->set_grid_colour( $this->gridColour );
    $x_axis->set_colour( $this->xcolor );

    $y_axis = new \y_axis();
    $y_axis->set_range(0, max(10,ceil($maxdata/10)*10), 10);
    $y_axis->set_grid_colour( $this->gridColour );
    $y_axis->set_colour( $this->ycolor );

    $chart->add_element( $bar );
    $chart->set_bg_colour( '#FFFFFF' );
    $chart->set_x_axis($x_axis);
    $chart->set_y_axis($y_axis);

    return $chart->toPrettyString();
  }

  // line evolution chart
  function evolution_Chart($data, $title, $fields) {
    \Seolan\Core\System::loadVendor('open-flash-chart2/php-ofc-library/open-flash-chart.php');

    // the chart
    $chart = new \open_flash_chart();
    $chart->set_title( new \Title($title) );
    $chart->set_bg_colour( '#FFFFFF' );
    // Tooltip
    $tooltip = new \tooltip();
    $tooltip->set_shadow( true );
    $tooltip->set_stroke( 1 );
    $chart->set_tooltip($tooltip);

    // x axis (step by hand, x_axis->steps is broken)
    foreach ($data['timestamps'] as $i => $ts) {
      if ($this->interval['view'] == 'week'
        || ($this->interval['view'] == 'month' && !($i%7)))
        $labels[] = strftime('%a %e', $ts);
      elseif ($this->interval['view'] == 'year'
        && (date('d', $ts) == '01' || date('d', $ts) == '15')) {
        $labels[] = utf8_encode(strftime('%d %b', $ts));
        $angle = -40;
      } else
        $labels[] = '';
    }
    $x_labels = new \x_axis_labels();
    $x_labels->set_labels( $labels );
    $x_labels->rotate( $angle );
    $x_axis = new \x_axis();
    $x_axis->set_labels($x_labels);
    $x_axis->set_grid_colour( $this->gridColour );
    $x_axis->set_colour( $this->xcolor );
//     $x_axis->steps(7);
    $chart->set_x_axis( $x_axis );
    $this->add_lines($data['values'], $chart, $maxdata);

    // y axis
    $y_axis = new \y_axis();
    $y_axis->set_range(0, max(10,ceil($maxdata/10)*10), 10);
    $y_axis->set_grid_colour( $this->gridColour );
    $y_axis->set_colour( $this->ycolor );
    $chart->set_y_axis( $y_axis );

    return $chart->toPrettyString();
  }
  // add lines to evolution chart
  function add_lines(&$values, &$chart, &$maxdata, $i=0, $prefix=null) {
    if (!is_array(current($values))) {
      foreach ($values as $k => $value) {
        $dot = new \hollow_dot($value);
        $dot->size(3)->halo_size(0);
        $dot->tooltip("$prefix $value");
        $line_values[] = $dot;
        $maxdata = $maxdata > $value ? $maxdata : $value;
      }
      $line = new \line();
      $line->set_colour( $this->colours[$i % count($this->colours)] );
      $line->set_values( $line_values );
//       $line->set_key( $prefix, 10 );
      $chart->add_element( $line );
    } else
      foreach ($values as $key => $vals)
        $this->add_lines($vals, $chart, $maxdata, $i++, "$prefix $key<br>");
  }

}
?>
