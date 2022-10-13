<?php
namespace Seolan\Module\TarteAuCitron;

use \Seolan\Core\Labels, \Seolan\Core\Shell, \Seolan\Core\Param, Seolan\Core\Logs;

/**
 * Tarte au citron - RGPD cookie manager
 * Permet à l'utilisateur du site de gérer lui même les cookies via une popin
 * Plus d'info : https://opt-out.ferank.eu/fr/install/
 * 
 * Quick notes : 
 * - Installation standard via le wizard
 * - Utilise une seule table "TARTEAUCITRON" qui contient la config des différents services (Analytics, AddThis, Youtube, ...)
 * - Intégration : 
 *    1. dans Corail::index() faire un $xmodtarteaucitron->sendScriptsToTemplate();
 *    2. dans le fichier templates/index.html mettre "<%$tarteaucitron_header%>" avant le </head> et "<%$tarteaucitron_footer%>" avant le </body>
 */
class TarteAuCitron extends \Seolan\Module\Table\Table {

  public static $upgrades = [
    '20210323' => '',
    '20220401' => '',
    '20220426' => ''
  ];

  // Liste des propriétés de base qui sont réutilisées dans le script du header
  public $privacyUrl;
  public $hashtag;
  public $cookieName;
  public $orientation;
  public $showAlertSmall;
  public $cookieslist;
  public $adblocker;
  public $AcceptAllCta;
  public $DenyAllCta;
  public $highPrivacy;
  public $handleBrowserDNTRequest;
  public $removeCredit;
  public $moreInfoLink;
  public $useExternalCss;
  public $cookieDomain;
  public $readmoreLink;
  public $mandatory;

  /**
   * Liste des Services (Google Analytics, Addthis, Youtube, ...)
   * - local : contient les informations de base des différents services (classes situées dans le dossier "Services")
   * - db : contient la liste des services enregistrés en base (configuration personnalisée)
   */
  public $services = ['local' => [], 'db' => []];

  function __construct($ar=NULL) {
    $ar['moid'] = self::getMoid(XMODTARTEAUCITRON_TOID);
    parent::__construct($ar);
  } 

  /**
   * Initialisation des propriétés
   */
  public function initOptions() {
    parent::initOptions();
    Labels::loadLabels('Seolan_Module_TarteAuCitron_TarteAuCitron');
    $this->group=Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"group","text");
    $this->modulename=Labels::getSysLabel('Seolan_Module_TarteAuCitron_TarteAuCitron',"modulename","text");
  }

  /**
   * Suppression du module
   */
  public function delete($ar=NULL){

    // Suppression du module des services
    if(!empty($this->ssmod1) && \Seolan\Core\Module\Module::moduleExists($this->ssmod1)){
      $xmod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->ssmod1]);
      $xmod->delete();
    }

    // Suppression du module des services personnalisés
    if(!empty($this->ssmod2) && \Seolan\Core\Module\Module::moduleExists($this->ssmod2)){
      $xmod = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->ssmod2]);
      $xmod->delete();
    }
    
    // Suppression du module
    return parent::delete($ar);
  }

  /**
   * Envoie les scripts (header/footer) au template
   * A utiliser dans la méthode Corail::run() 
   * 
   * @param string $appOid L'OID de l'application
   * @param string $prefix Le prefix de la variable smarty
   * @return void
   */
  public function sendScriptsToTemplate($appOid, $prefix='tarteaucitron'){
    $ret = $this->browse([
      'tplentry'=>TZR_RETURN_DATA,
      'selectedfields'=>['KOID'],
      'cond'=>['application'=>['=', $appOid]],
      '_options'=>array('local'=>1)
    ]);
    $scriptHeader = $scriptFooter = '';
    if(is_array($ret['lines_oid']) && count($ret['lines_oid']) == 1){
      $scriptHeader = $this->_getScriptHeader($ret['lines_oid'][0]);
      $scriptFooter = $this->_getScriptFooter($ret['lines_oid'][0]);
    }
    Shell::toScreen2($prefix, 'header', $scriptHeader);
    Shell::toScreen2($prefix, 'footer', $scriptFooter);
  }

  /**
   * Affiche le code d'intégration dans le haut de la page (setSessionVar) pour un service en particulier
   * Utilisé dans les méthodes edit() et display()
   * 
   * @param array $ret le tableau renvoyé par la méthode parent edit() ou display() de la classe Service
   * @return void
   */
  public function displayCodeIntegration($ret){
    $this->_loadServices();
    $classname = $this->services['local'][$ret['oservice']->raw]['classname'];
    if (!empty($classname) && class_exists($classname)) {
      $class = new $classname();
      if($ret['omainparam']->raw) $class->setFieldValue('mainparam', $ret['omainparam']->raw);
      if($ret['oextraparams']->raw) $class->setFieldValue('extraparams', $ret['oextraparams']->raw);
      $installInfo = $class->getInstallInfo();
    }
    $message = \getSessionVar('message');
    if(!empty($message)) $message .= '<br /><br />';
    $message .= '<h3>Code d\'intégration</h3>'.(!empty($installInfo) ? 'Ajoutez cette partie à l\'endroit où le service doit s\'afficher : '.$installInfo : 'Rien à effectuer en particulier');
    \setSessionVar('message', $message);
  }


  /**
   * Forcer la saisie dynamiquement de certains champs + afficher un message d'aide relatif à la saisie des champs
   * Utilisé dans la méthodes Service->edit()
   */
  public function setDynamicCompulsoryFieldsAndShowMessage($o, $service){
    $this->_loadServices();
   
    $fields = $this->services['local'][$service]['fields'];
    $compulsoryFields = '';
    if(isset($fields['mainparam']) && isset($fields['extraparams'])){
      $o->xset->desc['mainparam']->compulsory = true;
      $compulsoryFields = 'le champ <strong>'.$o->xset->desc['mainparam']->label.'</strong> <u>DOIT</u> être saisi et le champ <strong>'.$o->xset->desc['extraparams']->label.'</strong> peut contenir des paramètres de configuration spécifique à ce service (non obligatoire)';
    }
    elseif(isset($fields['mainparam'])){
      $o->xset->desc['mainparam']->compulsory = true;
      $compulsoryFields = 'le champ <strong>'.$o->xset->desc['mainparam']->label.'</strong> <u>DOIT</u> être saisi';
    }
    elseif(isset($fields['extraparams'])){
      $compulsoryFields = 'le champ <strong>'.$o->xset->desc['extraparams']->label.'</strong> peut être saisi';
    }

    $message = \getSessionVar('message');
    if(!empty($message)) $message .= '<br /><br />';
    $message .= '<h3>Paramétrage</h3>';
    if(!empty($compulsoryFields))
      $message .= "Attention, pour fonctionner correctement :  $compulsoryFields.";
    else
      $message .= 'Pas de paramétrage spécifique à indiquer pour les champs <strong>'.$o->xset->desc['mainparam']->label.'</strong> et <strong>'.$o->xset->desc['extraparams']->label.'</strong>.';
    \setSessionVar('message', $message);
  }

  /**
   * Génération du script javascript à placer dans le header
   * 
   * @param array $oid L'oid de la config
   * @return string Le script javascript
   */
  private function _getScriptHeader($oid){
    // Pour toutes les valeurs pour le header
    $ret = $this->display([
      'tplentry'=>TZR_RETURN_DATA, 
      'selectedfields'=>'all', 
      'oid' => $oid,
      '_options'=>['local'=>1,'error'=>'return'],
    ]);
    if(is_array($ret)){
      $script = '<script src="'.TZR_WWW_CSX.'VendorJS/node_modules/tarteaucitronjs/tarteaucitron.js"></script>'. "\n";
      $script .= '<script>tarteaucitron.init({'. "\n";
      foreach(array_keys($this->xset->desc) as $prop){
        if(in_array($prop,['UPD', 'application'])) continue;
        $value = $ret['o' . $prop]->raw;
        // Boolean
        if(is_a($this->xset->desc[$prop], 'Seolan\Field\Boolean\Boolean'))
          $value = $value == 1 ? 'true' : 'false';
        // privacyUrl = alias de la page des cookies
        elseif($prop == 'privacyUrl' && strpos($value, '/') === false) {
          $value = '/index.php?alias=' . $value;
          $value = '"'.$value.'"';
        }
        // readmoreLink : KOID d'un libellé
        elseif($prop == 'readmoreLink'){
          // $value vaut le KOID d'un libellé (titre : CSX_TARTEAUCITRON_readmoreLink)
          if(!empty($value) && \Seolan\Core\Kernel::isAKoid($value))
            $value = getDb()->fetchOne('SELECT LABEL FROM LABELS WHERE KOID=? AND LANG=?',[$value, \Seolan\Core\Shell::getLangUser()]);
          else
            $value = '';
          $value = '"'.htmlspecialchars($value).'"';
        }
        // Sinon c'est un string standard
        else
          $value = '"'.$value.'"';
        $script .= '"'.$prop.'": '.$value.',' . "\n";
      }
      $script .= '});'."\n";

      // On va regarder s'il y a des services personnalisés
      $xmodservicespesonnalises = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->ssmod2]);
      $ret = $xmodservicespesonnalises->xset->browse([
        'selectedfields'=>'all',
        '_options'=>array('local'=>1),
        'tplentry'=>TZR_RETURN_DATA,
        'cond'=> (!Shell::admini_mode() ? ['configuration' => ['=',$oid]] : [])
      ]);
      foreach($ret['lines_oid'] as $k => $oid){
        $key = $ret['lines_ospkey'][$k]->raw;
        $type = $ret['lines_otype'][$k]->raw;
        $name = $ret['lines_oname'][$k]->raw;
        $needConsent = $ret['lines_oneedConsent'][$k]->raw == '1' ? 'true' : 'false';
        $cookies = $ret['lines_ocookies'][$k]->raw;
        $cookies = preg_split("/[^A-Za-z_-]+/", $cookies, null, PREG_SPLIT_NO_EMPTY);
        $cookies = count($cookies) ? "'cookies': ['".implode("','", $cookies)."']," : "";
        $required = $needConsent === 'false' ? '"required":true,' : '';
        $readmoreLink = $ret['lines_oreadmoreLink'][$k]->raw;
        $readmoreLink = strpos($readmoreLink, '/') === false ? "/index.php?alias=$readmoreLink" : $readmoreLink;
        $readmoreLink = !empty($readmoreLink) ? "'readmoreLink': '$readmoreLink'," : "";
        $uri = '';
        if (isset($ret['lines_ouri'])) {
          $uri = $ret['lines_ouri'][$k]->raw;
          $uri = strpos($uri, '/') === false ? "/index.php?alias=$uri" : $uri;
          $uri = !empty($uri) ? "'uri': '$uri'," : "";
        }
        $js = $ret['lines_ojs'][$k]->raw;
        $fallback = $ret['lines_ofallback'][$k]->raw;
        $script .= "
        tarteaucitron.services.$key = {
          'key': '$key',
          'type': '$type',
          'name': '$name',
          'needConsent': $needConsent,
          $required
          $cookies
          $readmoreLink
          $uri
          'js': function() {
            'use strict';
            $js
          },
          'fallback': function() {
            'use strict';
            $fallback
          }
        }
        \n";
      }
      $script .= "</script>\n";
      return $script;
    }
    return '';
  }

  /**
   * Génération du javascript à placer dans le footer
   * 
   * @param array $oid L'oid de la config
   * @return string Le javascript pour le footer
   */
  private function _getScriptFooter($oid){
    $script = '<script>' . "\n";
    $this->_getServicesFromDb($oid);

    // 1. Vérifier s'il y a des scripts spécifiques à mettre AVANT la mise en execution de TarteAuCitron 
    // (par exemple "initMap" (checkInitMap) pour Google Maps)
    foreach($this->services['db'] as $service){
      if(!empty($service['specificFooterScript']))
        $script .= '/* '.$service['service'].' */ ' . $service['specificFooterScript'] . "\n";
    }
    
    // 2. Initialiser les services ainsi que leurs paramétrages spécifiques
    foreach($this->services['db'] as $service){
      $script .= $service['script'] . "\n";
    }

    // 3. On va regarder s'il y a des services personnalisés
    $xmodservicespesonnalises = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->ssmod2]);
    $ret = $xmodservicespesonnalises->xset->browse([
      'selectedfields'=>['type','spkey','needConsent'],
      '_options'=>array('local'=>1),
      'tplentry'=>TZR_RETURN_DATA,
      'cond'=> (!Shell::admini_mode() ? ['configuration' => ['=',$oid]] : [])
    ]);
    foreach($ret['lines_oid'] as $k => $oid){
      $key = $ret['lines_ospkey'][$k]->raw;
      if($ret['lines_oneedConsent'][$k]->raw == '2')
        $script .= 'tarteaucitron.services.'.$key.'.needConsent=false;'. "\n";
      $script .= '(tarteaucitron.job = tarteaucitron.job || []).push("'.$key.'");'. "\n";
    }

    $script .= '</script>';
    return $script;
  }

  /**
   * Remplit l'entrée "local" du tableau "$services" en fonction de la liste des services présents dans le dossier "Services"
   * Scanne juste les classes situées dans le dossier "Services"
   * 
   * @uses TarteAuCitron::services
   * @return void
   */
  private function _loadServices(){
    if(empty($this->services['local'])){
      $dir = __DIR__ . '/Services/';
      if (is_dir($dir) && $dh = opendir($dir)){
        while (($file = readdir($dh)) !== false){
          if($file != '.' && $file != '..'){
            require($dir . $file);
            $classname = 'Seolan\Module\TarteAuCitron\Services\\' . str_replace('.php', '', $file);
            if(class_exists($classname)){
              $class = new $classname();
              if(!empty($class->get('name'))){
                $this->services['local'][$class->get('name')] = [
                  'name' => $class->get('name'),
                  'title' => $class->get('title'),
                  'fields' => $class->get('fields'),
                  'image' => TZR_WWW_CSX.'src/Module/TarteAuCitron/public/images/'.$class->get('image'),
                  'classname' => $classname,
                ];
              }
            }
          }
        }
        closedir($dh);
      }
      ksort($this->services['local']);
    }
  }

  /**
   * Remplit l'entrée "db" du tableau "$services". 
   * Récupère les valeurs sauvegardées en base de données puis construit les entrées correspondantes pour chaque service (notamment "script" et "installinfo")
   * Utilisé sur le FO notamment pour obtenir le script pour chacun des services ajoutés
   * 
   * @uses TarteAuCitron::services
   * @return void
   */
  private function _getServicesFromDb($oid){
    Logs::notice(__METHOD__ . ' (start)');

    $this->_loadServices();

    // Pour le footer
    $xmodservices = \Seolan\Core\Module\Module::objectFactory(['moid'=>$this->ssmod1]);
    $ret = $xmodservices->xset->browse([
      'selectedfields'=>'all',
      '_options'=>array('local'=>1),
      'tplentry'=>TZR_RETURN_DATA,
      'cond'=> (!Shell::admini_mode() ? ['configuration' => ['=',$oid]] : [])
    ]);
    foreach($ret['lines_oid'] as $k => $oid){
      $service = $ret['lines_oservice'][$k]->raw;
      $mainparam = $ret['lines_omainparam'][$k]->raw;
      $extraparams = $ret['lines_oextraparams'][$k]->raw;
      $needConsent = $ret['lines_oneedConsent'][$k]->raw;

      // Construction de l'objet et affectation des valeurs
      $classname = $this->services['local'][$service]['classname'];
      if (!empty($classname)) {
        $class = new $classname();
        if($mainparam) $class->setFieldValue('mainparam', $mainparam);
        if($extraparams) $class->setFieldValue('extraparams', $extraparams);
        $class->setFieldValue('needConsent', $needConsent == '1');
        
        // Remplissage du tableau avec les valeurs générées par l'objet (script et installinfo notamment)
        $this->services['db'][$oid] = [
          'service' =>  $service,
          'name' =>     $class->get('name'),
          'title' =>    $class->get('title'),
          'specificFooterScript' => $class->getSpecificFooterScript(),
          'script' =>   $class->getScript(),
          'installInfo' => $class->getInstallInfo(),
        ];
        Logs::notice(__METHOD__ . ' : '.$class->get('title')." (mainparam=$mainparam)");
      }
    }
    Logs::notice(__METHOD__ . ' (end)');
  }
}