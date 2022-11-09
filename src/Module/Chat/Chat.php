<?php
namespace Seolan\Module\Chat;
class Chat extends \Seolan\Module\Table\Table {
  public $prefs;            /* Preferences du module */
  public $enable_chat;
  public $hiddenusers;
  public $data_directory;
  public $groupUser;
  private $chat;
  static $prefix = 'WaterCoolerChat';
  private $cname;
  private $cname64;

  function __construct($ar=NULL) {
    $ar['moid']=self::getMoid(XMODCHAT_TOID);
    parent::__construct($ar);
  }

  /**
   * Fonction d'initialisation du module de chat. Cette fonction appel la fonction de login, si l'utilisateur n'est pas enregistré alors un compte est créé dans le chat.
   * @param addUser Authoriser l'ajout d'utilisateur
   */
  protected function initChat($addUser=true) {
    try{
      $this->chat = new \WcChat();
      $this->chat->initIncPath();

      $this->xset=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$this->table);
      $this->login();
      if($addUser && !$this->userExists()) {
        $this->createUser();
        //$this->chat->init();
      }
    } catch (Exception $e) {
      \Seolan\Core\Logs::debug("Unable to initialise the chat ".$e->getMessage());
      return false;
    }
    return true;
  }

  /**
   * Fonction de création du compte utilisateur dans le chat.
   */
  public function createUser() {
    $this->login();
    \Seolan\Core\Logs::debug("Insert user ".$this->cname." in : ".\WcChat::$dataDir);
    if(!file_exists(\WcChat::$dataDir.'users.txt')) {
      file_put_contents(\WcChat::$dataDir.'users.txt','');
    }
    file_put_contents(\WcChat::$dataDir.'users.txt',PHP_EOL.$this->cname64.'|fHx8MHwwfA==|'.time().'|'.time().'|1', FILE_APPEND | LOCK_EX);
  }

  /**
   * Fonction de vérification de l'existance d'un utilisateur dans le chat
   */
  protected function userExists() {
    $file = \WcChat::$dataDir.'users.txt';
    if(file_exists($file)) {
      $lines = file($file);
      foreach ($lines as $line_num => $line) {
        list($name, $data, $firstJoin, $lastAct, $status) = explode('|', $line);
        if($name == $this->cname64) {
            return true;
        }
      }
    }
    return false;
  }

  /**
   * Fonction d'authentification dans le chat: création/update du cookie utilisé par WcChat
   */
  protected function login() {
    $this->cname = \Seolan\Core\User::get_user()->_cur[TZR_CHAT_USER_FIELD];// \Seolan\Core\User::get_current_user_uid();
    $this->cname64 = base64_encode($this->cname);
    // On regarde si 'utilisateur n'a pas changé d'alias)
    $this->checkUserAlias(\Seolan\Core\User::get_current_user_uid());
    // Création du cookie et de la variable de session pour se logguer automatiquement au chat
    setcookie(
      \Seolan\Module\Chat\Chat::$prefix . '_cname',
      $this->cname,
      time() + (86400 * \WcChat::$cookieExpire),
      '/'
    );
    $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_cname'] = $this->cname;
  }

  /**
   * Initialisation des propriétés
   * Cette fonction va ajouter une option d'activation du webchat pour les utilisateurs (activé par défaut)
   * TODO : Mettre les droits
   */
  public function initOptions() {
    parent::initOptions();
    if(!empty($GLOBALS['XUSER']) && !empty($this->xset->desc['BO']) && empty($this->fieldssec['BO'])){
      $rwv=$this->secure('',':rwv');
      if(!$rwv) $this->fieldssec['BO']='ro';
    }
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Chat_Chat');
    if(!$this->group){
      $this->group=\Seolan\Core\Labels::getSysLabel('Seolan_Core_General',"systemproperties","text");
    }
    if(!$this->modulename){
      $this->modulename=\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"modulename","text");
    }

    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"hiddenusers","text"), 'hiddenusers', 'object',['multivalued' => true, 'type'=>'multiplelist', 'table'=>'USERS', 'compulsory' => false], null);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"group_user", 'text'),'groupUser', 'object', ['multivalued' => false, 'type'=>'list', 'table'=>'GRP', 'compulsory' => true], NULL);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat',"data_directory", 'text'),'data_directory', 'text');
    $this->_options->setRO("data_directory");

    $this->getPrefs();
    if(empty($this->enable_chat) && !empty($this->prefs['enable_chat'])) {
      $this->enable_chat=$this->prefs['enable_chat'];
    }

    if($this->enable_chat == 1) {
      $r = array('enable_chat'=>true);
      \Seolan\Core\Shell::toScreen1('chat',$r);
    }

    \Seolan\Core\System::loadVendor('WaterCooler-Chat/wcchat.class.php');

    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','modulename');

  }

  /// securite des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['newMessages']=array('none','ro','rw','rwv','admin');
    $g['setAllRead']=array('none','ro','rw','rwv','admin');
    if(isset($g[$function])) return $g[$function];
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Ajoute les actions du browse à une ligne donnée
  function browseActionsForLine(&$r,&$i,&$oid,&$oidlvl,&$noeditoids){
    if($this->enable_chat){
      $this->browseActionStartChat($r,$i,$oid,$oidlvl);
    }
  }

  function browseActionStartChat(&$r,&$i,&$oid,&$oidlvl,$usersel=false){
    $this->browseActionForLine('startChat',$r,$i,$oid,$oidlvl,$usersel);
  }

  function browseActionStartChatText(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','startchat','text');
  }
  function browseActionStartChatIco(){
    return \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','startchat');
  }
  function browseActionStartChatLvl(){
    return $this->secGroups('edit');
  }
  function browseActionStartChatHtmlAttributes(&$url,&$text,&$icon){
    return 'class="cv8-ajaxlink"';
  }
  function browseActionStartChatUrl($usersel){
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$this->_moid.'&oid=<oid>&tplentry=br&function=display&template=Module/Chat.display.html';
  }

  /**
   * Intérroge le chat et compte le nombre de nouveaux messages non lus en dehors de la conversation active.
   */
  public function newMessages($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());

    if (!$this->initChat() or \Seolan\Core\User::get_current_user_uid() == TZR_USERID_NOBODY) {
      header('HTTP/1.1 403 Forbidden');
      die();
    }

    $msgs = $this->arrayNewMessages();
    $tplentry=$p->get('tplentry');
    $r = array('total'=>array_sum(array_values($msgs)), 'users'=>$msgs);
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /**
   * Contrôle si l'alias de l'utilisateur a changé, si oui il est remplacé dans tous les fichiers du chat
   */
  public function checkUserAlias($user) {
    $params = \Seolan\Core\DbIni::get(\Seolan\Module\Chat\Chat::$prefix.'_'.$user)[0];
      if(!isset($params['alias'])) {
          $params['alias'] = $this->cname64;
      }
      if($params['alias'] != $this->cname64) {
          foreach(glob(\WcChat::$roomDir . '*.txt') as $file) {
              preg_match('/^(\w+_)?(.+).txt$/', basename($file), $matches, PREG_OFFSET_CAPTURE);

              $room_name64 = $matches[2][0];
              $room_name = base64_decode($room_name64);

              if(strpos($room_name, $params['alias']) !== FALSE ) {
                  // changer l'alias dans tous les fichiers
                  $str=file_get_contents($file);
                  $str=str_replace($params['alias'], $this->cname64,$str);
                  file_put_contents($file, $str);

                  //renomer le fichier
                  $new_room = explode('_',str_replace($params['alias'], $this->cname64, $room_name));
                  rsort($new_room);
                  $unwanted_room = implode('_', $new_room);
                  sort($new_room);
                  $new_room = implode('_', $new_room);
                  $infos = pathinfo($file);
                  $new_file = $infos['dirname'].'/'.$matches[1][0].base64_encode($new_room).'.txt';
                  rename($file, $new_file);
                  @unlink($infos['dirname'].'/'.$matches[1][0].base64_encode($unwanted_room).'.txt');
                  $params['rooms'][$new_room] = $params['rooms'][$room_name];
                  unset($params['rooms'][$room_name]);
              }
          }

          $str=file_get_contents(\WcChat::$dataDir.'users.txt');
          $str=str_replace($params['alias'], $this->cname64,$str);
          file_put_contents(\WcChat::$dataDir.'users.txt', $str);
          $params['alias'] = $this->cname64;
      }
      \Seolan\Core\DbIni::set(\Seolan\Module\Chat\Chat::$prefix.'_'.$user, $params);
  }

  /**
   * Met à jour l'activité de l'utilisateur pour un canal de messagerie en base de donnée
   */
  static public function updateUserRooms($user, $room_name, $force = true) {
      $params = \Seolan\Core\DbIni::get(\Seolan\Module\Chat\Chat::$prefix.'_'.$user)[0];
      if(!isset($params['rooms'])) {
          $params['rooms'] = array();
      }
      if($force or !isset($params['rooms'][$room_name])) {
          $params['rooms'][$room_name] = time();
          \Seolan\Core\DbIni::set(\Seolan\Module\Chat\Chat::$prefix.'_'.$user, $params);
      }
  }

  /**
   * Met tous les messages de l'utilisateur en lu.
   */
  public function setAllRead($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);

    if (!$this->initChat() or \Seolan\Core\User::get_current_user_uid() == TZR_USERID_NOBODY) {
      header('HTTP/1.1 403 Forbidden');
      die();
    }

    $params = \Seolan\Core\DbIni::get(\Seolan\Module\Chat\Chat::$prefix.'_'.\Seolan\Core\User::get_current_user_uid())[0];

    if(!isset($params['rooms'])) {
      $params['rooms'] = array();
    }
    foreach($params['rooms'] as $room_name) {
        $params['rooms'][$room_name] = $time;
    }
    foreach(glob(\WcChat::$roomDir . '*.txt') as $file) {
      $room_name = base64_decode(str_replace(array(\WcChat::$roomDir, '.txt'), '', $file ));
      if(
        $room_name != $activeChatRoom &&
        strpos(basename($file), 'def_') === FALSE &&
        strpos(basename($file), 'topic_') === FALSE &&
        strpos(basename($file), 'updated_') === FALSE &&
        strpos(basename($file), 'hidden_') === FALSE &&
        strpos($room_name, 'pm_') !== FALSE
      ) {
        $participants = explode('_', $room_name);
        unset($participants[0]);
        $users = array_diff( $participants, [$this->cname64] );
        if (count($users) != 1) {
          continue;
        }
        $params['rooms'][$room_name] = time();
      }
    }
    \Seolan\Core\DbIni::set(\Seolan\Module\Chat\Chat::$prefix.'_'.\Seolan\Core\User::get_current_user_uid(), $params);

    return $this->browse($ar);
  }

  /**
   * Fonction ajax pour obtenir le nombre de messages non lus
   */
  protected function arrayNewMessages() {
    $result = array();
    $activeChatRoom = urldecode($_REQUEST['activeChatRoom']);

    $opts = new \Seolan\Library\Opts();
    $params = \Seolan\Core\DbIni::get(\Seolan\Module\Chat\Chat::$prefix.'_'.\Seolan\Core\User::get_current_user_uid())[0];

    $user_rooms = $params['rooms'];

    $usersModule=\Seolan\Core\Module\Module::singletonFactory(XMODUSER2_TOID);
    $userList = $usersModule->getActiveUsers(TZR_CHAT_USER_FIELD);

    foreach(glob(\WcChat::$roomDir . '*.txt') as $file) {
      $room_name = base64_decode(str_replace(array(\WcChat::$roomDir, '.txt'), '', $file ));
      if(
        $room_name != $activeChatRoom &&
        strpos(basename($file), 'def_') === FALSE &&
        strpos(basename($file), 'topic_') === FALSE &&
        strpos(basename($file), 'updated_') === FALSE &&
        strpos(basename($file), 'hidden_') === FALSE &&
        strpos($room_name, 'pm_') !== FALSE
      ) {
        $participants = explode('_', $room_name);
        unset($participants[0]);
        $users = array_diff( $participants, [$this->cname64] );
        if (count($users) != 1) {
          continue;
        }

        $user = end($users);

        if($user_rooms[$room_name])
            $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_lastread_' . $room_name] = $user_rooms[$room_name];
        else
            $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_lastread_' . $room_name] = 0;

        $alias = base64_decode($user);
        if(!in_array($alias, $userList))
          continue;
        $result[$alias] = 0;
        $lines = file($file);
        foreach ($lines as $line_num => $line) {
          list($time, $user, $msg) = explode('|', $line);
          if ($time > $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_lastread_' . $room_name] and $user != $this->cname64) {
            $result[$alias]++;
          }
        }
      }
    }
    return $result;
  }

  /**
   * Fonction pour obtenir la dernière date d'activité sur le chat
   */
  protected function lastActivities() {
    $result = array();
    $file = \WcChat::$dataDir.'users.txt';
    if(file_exists($file)) {
      $lines = file($file);
      foreach ($lines as $line_num => $line) {
        list($name, $data, $firstJoin, $lastAct, $status) = explode('|', $line);
        $alias = base64_decode($name);
        $result[$alias] = $lastAct;
      }
    }
    return $result;
  }

  function display($ar=NULL){
    // On suppose pour l'instant que tous les membres affichés dans browse sont connectés et ont acceptés de discuter en chat.
    $p = new \Seolan\Core\Param($ar, array());
    if (!$this->initChat()) {
      return NULL;
    }
    $oid = $p->get('oid');
    $tplentry=$p->get('tplentry');

    if(!$oid) {
      $alias = urldecode($p->get('alias'));
      $oid=getDB()->fetchOne('SELECT KOID FROM USERS WHERE '.TZR_CHAT_USER_FIELD.'=?',array($alias));
    }
    if(!$oid) {
      return NULL;
    }
    \Seolan\Core\Logs::debug("Start chat with ".$oid);
    $r = array();
    if (!$_GET['mode']) {

      $user=getDB()->fetchRow('SELECT * FROM USERS WHERE KOID=?',array($oid));
      if(!empty($user)) {
        $participants = array($this->cname, $user[TZR_CHAT_USER_FIELD]);
        sort($participants);
        $room_name = 'pm_' . base64_encode($participants[0]) . '_' . base64_encode($participants[1]);
        $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_current_room'] = $room_name;
        setcookie(
          \Seolan\Module\Chat\Chat::$prefix.'_current_room',
          $room_name,
          time() + (86400 * \WcChat::$cookieExpire),
          '/'
        );
        $_SESSION[\Seolan\Module\Chat\Chat::$prefix . '_lastread_' . $room_name] = time();
        // création du fichier de la discution
        $room_filename = \WcChat::$dataDir.'rooms/'.base64_encode($room_name).'.txt';
        if (!file_exists($room_filename)) {
          file_put_contents($room_filename,'');
        }
        $js = '<input type="hidden" id="activeChatRoom" value="'.$room_name.'">'.
          '<script type="text/javascript">(function() {  '.
            'var stateoverlay=TZR.setOverlay(jQuery("#cv8-module-container-0"));'.
            'wc_join_chat("'.TZR_SHARE_SCRIPTS.'/chat-ajax.php?query=/ajax.php&", "join", 2000);'.
            'wc_refresh_msg("'.TZR_SHARE_SCRIPTS.'/chat-ajax.php?query=/ajax.php&", "ALL", 10000, 100, "/scripts/chat-ajax.php?query=/themes/xsalto/", "WaterCoolerChat");'.
            'TZR.unsetOverlay(stateoverlay);'.
          '} )()</script>';
        $r['chat'] = ($this->chat->printIndex().$js);
      }
    } else {
      $r['chat'] = $this->chat->ajax();
    }

    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  /// Parcourir les utilisateurs qui ont acceptés le chat
  function browse($ar=NULL) {
    if (!$this->initChat()) {
      return NULL;
    }
    $p=new \Seolan\Core\Param($ar,array('order'=>0),"all",
    array('pagesize'=>array(FILTER_VALIDATE_INT,array()),
    'order'=>array(FILTER_CALLBACK,array('options'=>'containsNoSQLKeyword'))));

    $ar['selectedfields'] = array('alias', 'fullnam', 'newMessages');
    if($this->enable_chat){
      $this->hiddenusers[] = \Seolan\Core\User::get_current_user_uid();

      // On ne veux que les utilisateurs qui ont le chat activé
      $s = serialize(array('enable_chat'=>"1"));
      preg_match('/\{([^\}]*)\}/',$s, $matches);
      $acc = \Seolan\Core\User::getModuleAccess($this, true);
      $users = array();
      for($i = 0; $i < count($acc[0]['lines_oid']); $i++) {
        if (in_array('list', $acc[0]['lines_sec'][$i][\Seolan\Core\Shell::getLangUser()][0])) {
          $users[] = $acc[0]['lines_oid'][$i];
        }
      }
      // contrôle de OPTS dans la requête pour optimiser un peu de ressources
      $q=$this->xset->select_query(array('where' => 'KOID in (SELECT user FROM OPTS where specs like '.getDB()->quote('%'.$matches[1].'%').' ) AND KOID in ("'.implode('","', $users).'") AND KOID not in ("'.implode('","', $this->hiddenusers).'")', 'order'=> 'fullnam'));
      $ar['select']=$q;
    } else {
      $ar['select']=NULL;
    }
    $r= parent::browse($ar);
    $tplentry=$p->get('tplentry');

    // Ajout du champ "nouveaux messages"
    $fieldNewMessages = new \Seolan\Field\Real\Real();
    $fieldNewMessages->field = 'newMessages';
    $fieldNewMessages->listbox = true;
    $fieldNewMessages->published = '1';
    $fieldNewMessages->browsable = '1';
    $fieldNewMessages->decimal = 0;
    $fieldNewMessages->readonly = true;
    $fieldNewMessages->label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','new_messages','text');

    $r['header_fields'][] = $fieldNewMessages;
    $r['selectedfields'][] = 'newMessages';
    $r['_fieldssec']['newMessages'] = 'ro';
    $r['fieldlist']['']['newMessages'] = array(
      "selected"=>true,
      "label"=> \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','new_messages','text'),
      "browsable"=>'0',
      "order"=>'20');

    // Ajout du hcamps "date derniière activité"
    $fieldLastActivity = new \Seolan\Field\DateTime\DateTime();
    $fieldLastActivity->field = 'lastActivity';
    $fieldLastActivity->listbox = true;
    $fieldLastActivity->published = '1';
    $fieldLastActivity->browsable = '1';
    $fieldLastActivity->readonly = true;
    $fieldLastActivity->label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','last_activity','text');

    $r['header_fields'][] = $fieldLastActivity;
    $r['selectedfields'][] = 'lastActivity';
    $r['_fieldssec']['lastActivity'] = 'ro';
    $r['fieldlist']['']['lastActivity'] = array(
      "selected"=>true,
      "label"=> \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','last_activity','text'),
      "browsable"=>'1',
      "order"=>'20');

    // Ajout du champs "Status"
    $fieldActivity = new \Seolan\Field\Color\Color();
    $fieldActivity->field = 'activity';
    $fieldActivity->listbox = true;
    $fieldActivity->published = '1';
    $fieldActivity->browsable = '1';
    $fieldActivity->readonly = true;
    $fieldActivity->label = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','activity','text');

    $r['header_fields'][] = $fieldActivity;
    $r['selectedfields'][] = 'activity';
    $r['_fieldssec']['activity'] = 'ro';
    $r['fieldlist']['']['activity'] = array(
      "selected"=>true,
      "label"=> \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','activity','text'),
      "browsable"=>'1',
      "order"=>'20');

    // récupération des valeurs
    $newMessages = $this->arrayNewMessages();
    $lastActivities = $this->lastActivities();

    $now = time();
    for($i = 0; $i < count($r['lines_oalias']); $i++) {
      $name = $r['lines_oalias'][$i]->raw;
      $nb = $newMessages[$name] ?? 0;
      $r['lines_onewMessages'][$i] = new \Seolan\Core\Field\Value();
      $r['lines_onewMessages'][$i]->raw = $nb;
      $r['lines_onewMessages'][$i] = $fieldNewMessages->my_browse_deferred($r['lines_onewMessages'][$i]);
      // Ajout d'une class pour les users avec un message en attente
      if ($nb) {
        $r['lines_trclass'][$i] = "row-new-message";
      }

      $date = $lastActivities[$name] ? date("Y-m-d H:i:s", $lastActivities[$name]):'';
      $r['lines_olastActivity'][$i] = new \Seolan\Core\Field\Value();
      $r['lines_olastActivity'][$i]->raw = $date;
      $r['lines_olastActivity'][$i] = $fieldLastActivity->my_browse_deferred($r['lines_olastActivity'][$i]);

      // status <5 min = vert, <15min = orange, sinon rouge
      $nbMinutes = ($now - $lastActivities[$name]) / 60;
      $color = 'red';
      if ($nbMinutes < 5)
        $color = 'green';
      else if ($nbMinutes < 15)
        $color = 'orange';
      $r['lines_oactivity'][$i] = new \Seolan\Core\Field\Value();
      $r['lines_oactivity'][$i]->raw = $color;
      $r['lines_oactivity'][$i] = $fieldActivity->my_browse_deferred($r['lines_oactivity'][$i]);
    }

    // tri des lignes...
    $order = $p->get('order');
    if ($order) {
      list($order_field, $order_direction)=explode(' ', $order);

      $raw = array();
      $col = 'lines_o' . $order_field;
      foreach($r[$col] as $k=>$v) {
        $raw[] = $v->raw;
      }

      if($order_direction == 'ASC')
        asort($raw);
      else
        arsort($raw);

      $raw = array_keys($raw);
      foreach($raw as $k=>$i) {
        if($k == $i)
          unset($raw[$k]);
      }

      $keys = preg_grep ('/^(lines|actions|objects)(_(\w+))?/i', array_keys($r));

      foreach($keys as $key) {
        $tmp = $r[$key];
        foreach($raw as $k=>$i) {
          if( isset($tmp[$i])) {
            $r[$key][$k] = $tmp[$i];
          } else {
            unset($r[$key][$k]);
          }
        }
      }
    }
    return \Seolan\Core\Shell::toScreen1($tplentry,$r);
  }

  function browseActionChat(&$url,&$text,&$icon, $linecontext){
    return 'class="cv8-ajaxlink cv8-dispaction"';
  }

  /// Recupere les preferences du module pour une édition
  function getParamPrefs(){
    $desc['enable_chat']=\Seolan\Core\Field\Field::objectFactory((object)array('FIELD'=>'enable_chat','FTYPE'=>'\Seolan\Field\Boolean\Boolean','MULTIVALUED'=>0,
    'COMPULSORY'=>false,'LABEL'=>\Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','enable_chat','text'),
    'TARGET'=>$this->tagenda));
    $desc['enable_chat']->default=true;
    return array('desc'=>$desc);
  }

  /// suppression du module
  function delete($ar=NULL) {
    // désactiver la suppression de la table
    $p=new \Seolan\Core\Param($ar, array());
    $p->set('withtable', false);

    parent::delete($p->getArray());
    if ($this->initChat(false)) {
      // suppression des fichiers de données
      \Seolan\Core\Logs::notice('rm '.\WcChat::$dataDir);
      if(file_exists(\WcChat::$dataDir) && is_dir(\WcChat::$dataDir)) {
        \Seolan\Library\Dir::unlink(\WcChat::$dataDir);
      }
      getDB()->execute('DELETE FROM OPTS where modid = ? and dtype = ? ', array($this->_moid, \Seolan\Module\Chat\Chat::$prefix));
    }
  }

  /// Listes des actions générales du module
  protected function _actionlist(&$my,$alfunction=true) {
    $r = parent::_actionlist($my, $alfunction);
    unset($my['deletewithtable']);
    unset($my['insert']);
    unset($my['del']);
    unset($my['edit']);
    unset($my['editselection']);
    $uniqid = \Seolan\Core\Shell::uniqid();
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Chat_Chat');
    $o1=new \Seolan\Core\Module\Action(
      $this,
      'setallread',
      \Seolan\Core\Labels::getSysLabel('Seolan_Module_Chat_Chat','setallread','text'),
      'javascript:TZR.Table.applyfunction("'.$uniqid.'","setAllRead","",{},false,false);',
      'menu');
    $o1->order=2;
    $o1->setToolbar('Seolan_Module_Chat_Chat','setallread');
    $my['setallread']=$o1;

    return $r;
  }

  function procEditProperties($ar) {
    $oldGroup = $this->groupUser;
    $r = parent::procEditProperties($ar);
    getDB()->execute('UPDATE ACL4  set AGRP = ? WHERE AGRP = ? AND AMOID = ? ', array($this->groupUser, $oldGroup, $this->_moid));
    \Seolan\Core\Logs::secEvent(__METHOD__,"Set rules for chat module {$this->_moid} on group {$this->_module->groupUser}", $this->_moid);
    return $r;
  }
}
