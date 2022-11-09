<?php
namespace Seolan\Module\Chat;

class Wizard extends \Seolan\Module\Table\Wizard {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    $this->_module->table="USERS";
    $this->_module->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    $this->_module->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"modulename","text");
    \Seolan\Core\System::loadVendor('WaterCooler-Chat/settings.php');
    $this->_module->data_directory = DATA_DIR;
    $this->_module->groupUser = "GRP:2";
  }

  function istep1() {
    parent::istep1();

    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Table_Table','table'), 'table', 'table', 			    array('validate'=>true));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"modulename"), "modulename", "text");
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"group"), "group", "text");
    //$this->_options->setRO("table");
    $this->_options->setRO("modulename");
    $this->_options->setRO("group");
    //$this->_options->delOpt("createstructure");
    $this->_options->setRO("table");
    $this->_options->setRO("createstructure");
  }

 function istep2() {
   $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"data_directory"),'data_directory', 'text');
   $this->_options->setRO("data_directory");
   $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"group_user"),'groupUser', 'object', ['multivalued' => false, 'type'=>'list', 'table'=>'GRP', 'compulsory' => true], NULL, $group);
 }

  private function checkEnvironnement($moid) {
/*
    $key = 'DATA_DIR';
    $constant_value = DATA_DIR;
    $value = $this->_module->data_directory;
    $str=file_get_contents('Vendor/WaterCooler-Chat/settings.php');
    $str=str_replace("define('{$key}', {$constant_value})", "define('{$key}', {$value})",$str);
    file_put_contents('Vendor/WaterCooler-Chat/settings.php', $str);*/

    \Seolan\Core\System::loadVendor('WaterCooler-Chat/settings.php');
    // Création des dossiers du chat
    \Seolan\Core\Logs::debug('mkdir('.DATA_DIR.'tmp/)');
    \Seolan\Library\Dir::mkdir(DATA_DIR.'tmp/');
    \Seolan\Core\Logs::debug('mkdir('.DATA_DIR.'rooms/)');
    \Seolan\Library\Dir::mkdir(DATA_DIR.'rooms/');
    \Seolan\Core\Logs::debug('mkdir('.DATA_DIR.'rooms/archived/)');
    \Seolan\Library\Dir::mkdir(DATA_DIR.'rooms/archived/');

    // création du fichier de la discution par défaut
    $room_filename = DATA_DIR.'rooms/'.base64_encode(DEFAULT_ROOM).'.txt';
    if (!file_exists($room_filename)) {
      file_put_contents($room_filename,'');
    }

    // mettre les droits pour chaque langues sur tous les utilisateurs
    $langs = array_keys($GLOBALS['TZR_LANGUAGES']);
    foreach($langs as $lang){
      $aoid = substr(md5(uniqid()), 0, 40);
      getDB()->execute("insert IGNORE into ACL4 (AOID, UPD, AGRP, AFUNCTION, ALANG, AMOID, AKOID, OK) values ('$aoid', NULL, '".$this->_module->groupUser."', 'rw', '$lang', '$moid', '', 1)");
      \Seolan\Core\Logs::secEvent(__METHOD__,"Set rules for chat module $moid on group {$this->_module->groupUser}", $moid);
    }
    // activer les préf pour tous les utilisateurs
    $users=getDB()->fetchCol('SELECT KOID FROM USERS');
    foreach($users as $user) {
      $prefs = \Seolan\Library\Opts::getOpt($user, $moid, 'pref');
      if (!isset($prefs['enable_chat'])) {
        $prefs['enable_chat'] = '1';
        \Seolan\Library\Opts::setOpt($user, $moid, 'pref', $prefs);
      }
    }
  }

  function iend($ar=NULL) {
    $moid=parent::iend();
    $this->checkEnvironnement($moid);

  }
}

?>
