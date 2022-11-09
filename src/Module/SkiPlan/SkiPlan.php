<?php
namespace Seolan\Module\SkiPlan;

// recup informations skiplan tignes
// -> vers un dynplan
//    -> etat des pistes
//    -> etat des remontees
//    -> meteo
// -> vers une base meteo
//    -> parametres par zone
//    -> bulletins GB/FR
//    -> prevision
//
class SkiPlan extends \Seolan\Core\Module\Module {

    public $url = NULL;
    public $tbpistes = NULL;
    public $tbrems = NULL;
    public $tbSecteurs = NULL;
    public $tbetats = NULL;
    public $tbliaisons = NULL;
    public $modulePistes = NULL;
    public $shareflux = false;
// oid des etats en table des etats console
    public $oidEtats = NULL; // charge
    public $langue = array("FR", "GB");

    public function initOptions() {
        parent::initOptions();
        $this->_options->setOpt('URL', 'url', 'text', ['size' => 80], NULL, 'Skiplan');
        $this->_options->setOpt('Table pistes', 'tbpistes', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table liaisons', 'tbliaisons', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table remont&eacute;es', 'tbrems', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table météo', 'tbmeteo', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table météo ciel', 'tbmeteociel', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table météo avalanche', 'tbmeteoavalanche', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des secteurs', 'tbSecteurs', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des états', 'tbEtats', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des niveaux', 'tbNiveaux', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des Station', 'tbStation', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table modules de pistes', 'tbModPistes', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des pistes de la station', 'tbStationPistes', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des types de remontées', 'tbReomnteesTypes', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des références', 'tbReference', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Table des types de pistes', 'tbPistesTypes', 'table', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Station', 'station', 'text', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Station', 'Station', 'module', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('piste des Stations', 'StationPistes', 'module', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Module des Pistes', 'modulePistes', 'module', NULL, NULL, 'Skiplan');
        $this->_options->setOpt('Redistribuer le flux', 'shareflux', 'boolean', NULL, 0, 'Skiplan');
        $this->_options->setOpt('Supprimer le cache<br>après une mise à jour', 'clearCacheOnUPD', 'boolean', NULL, 0, 'Skiplan');
    }

    public function secGroups($function, $group = NULL) {
        $g = array();
	// voir action list, manque des fonctions 
        $g['loadMeteo'] = array('rw', 'rwv', 'admin');
        $g['inlineLoadMeteo'] = array('rw', 'rwv', 'admin');
        $g['lireMeteo'] = array('none', 'ro', 'rw', 'rwv', 'admin');
        $g['cronLoadAll'] = array('admin');
        $g['cronLoadMeteo'] = array('admin');
        $g['cronLoadPistes'] = array('admin');
        $g['updateSchema'] = array('admin');
        $g['_loadAll'] = array('rw', 'rwv', 'admin');
        $g['chargementDesPistes'] = array('rw', 'rwv', 'admin');
        $g['inLineLoadStations'] = array('rw', 'rwv', 'admin');
        if (isset($g[$function])) {
            if (!empty($group))
                return in_array($group, $g[$function]);
            return $g[$function];
        }
        return parent::secGroups($function, $group);
    } // secGroups

    public function _actionlist(&$my,$alfunction = true) {
        parent::_actionlist($my);

        // ajout du chargement en direct
        $myclass = $this->classname;
        $moid = $this->_moid;
        if ($this->secure('', 'inlineLoadMeteo')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'inlineLoadMeteo', 'Mise à jour de la meteo', 'class=' . $myclass . '&amp;moid=' . $moid .
                    '&amp;_function=inlineLoadMeteo&amp;template=empty.txt&tplentry=br');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['loadMeteo'] = $o1;
        }

        if ($this->secure('', 'inLineLoadStations')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'inLineLoadStations', 'Mise à jour des Stations et de leur informations depuis le flux XML', '&moid=' . $this->_moid . '&_function=inLineLoadStations&template=Core.message.html&tplentry=br', 'edit');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['inLineLoadStations'] = $o1;
        }

        if ($this->secure('', 'chargementDesPistes')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'chargementDesPistes', 'Mise à jour de l\'ouverture des pistes depuis le flux XML', '&moid=' . $this->_moid . '&_function=chargementDesPistes&template=Core.message.html&tplentry=br', 'edit');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['chargementDesPistes'] = $o1;
        }

        if ($this->secure('', 'updateSchema')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'updateSchema', 'Mise à jour du Schema des Données', '&moid=' . $this->_moid . '&_function=updateSchema&template=Core.message.html&tplentry=br');
            $o1->group = 'more';
            $o1->menuable = true;
            $my['updateSchema'] = $o1;
        }

    } // _actionlist()


    public function getMainAction() {
      return $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=getInfos&tplentry=br&template=Core/Module.infos.html';
    }
    function getInfos($ar=null) {
      $p=new \Seolan\Core\Param($ar,array('tplentry'=>TZR_RETURN_DATA));
      $tplentry=$p->get('tplentry');
      $ar['tplentry']=TZR_RETURN_DATA;
      $ret = parent::getInfos($ar);
      foreach(['tbpistes','tbliaisons','tbrems','tbmeteo','tbmeteociel','tbmeteoavalanche','tbSecteurs','tbEtats','tbNiveaux','tbStation','tbModPistes','tbStationPistes','tbReomnteesTypes','tbReference','tbPistesTypes'] as $n){
        $tb = $this->$n;
        if ($tb) {
          $nb = getDB()->fetchOne("select count(*) as nb from $tb");
          $ret['infos'][$tb] = (object)['label'=>"{$this->$n} : ", 'html'=>"$nb lignes"];
        } else {
          $ret['infos'][$tb] = (object)['label'=>"{$this->$n} : ", 'html'=>"non défini"];
        }
      }
      return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
    }
    protected function cleanCache() {
      if(\Seolan\Core\System::tableExists('_PCACHE') && ($cache=\Seolan\Core\Module\Module::singletonFactory(8018))) {
        $infotreeMoids = getDB()->fetchCol('SELECT moid from MODULES where toid=4');
        foreach ($infotreeMoids as $infotreeMoid) {
          $mod = \Seolan\Core\Module\Module::objectFactory($infotreeMoid);
          //listes des alias de rubriques contenant des sections dans laquelle on utilise des données skiplan
          $aliases = getDB()->fetchCol(
            "select alias from $mod->dyntable "
            . "join $mod->tname on $mod->tname.KOIDDST = koid "
            . "join $mod->table on $mod->table.KOID = $mod->tname.KOIDSRC "
            . "where (query like '%\"function\":\"pistes\"%' or query like '%\"function\":\"meteo\"%') "
            . 'or module="' . $this->_moid . '" group by alias');
          foreach($aliases as $alias){
            if(!empty($alias)) {
              $cache->clean($alias, "immediate");
            }
          }
        }
      }
    }
    public function updateSchema() {
      \Seolan\Core\Logs::critical("Ugrade Skiplan START");
      Wizard::updateSchema();
      \Seolan\Core\Logs::critical("Ugrade Skiplan DONE");
    }

    // recup des meteo via scheduler
    //
    function cronLoadAll(&$scheduler, &$o, &$more) {
        $msg = $this->_loadAll();
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }
    function cronLoadMeteo(&$scheduler, &$o, &$more) {
        $buffer = $this->getXml();
        $msg = $this->loadMeteo($buffer);
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }

    function cronLoadPistes(&$scheduler, &$o, &$more) {
        $buffer = $this->getXml();
        $msg = $this->_insertPistes($buffer);
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }

    protected function getXml(&$msg='', $nocache = false) {
      //check du cache
      mkdir(TZR_VAR2_DIR . '/XModSkiPlan/');
      $cacheFile = TZR_VAR2_DIR . '/XModSkiPlan/lastimport_' . $this->_moid . '.xml';
      $errorFile = TZR_VAR2_DIR . '/XModSkiPlan/error_' . $this->_moid . '.xml';
      $urlErrorFile = TZR_VAR2_DIR . '/XModSkiPlan/urlError_' . $this->_moid . '.xml';
      $sendAlert = false;

      // open de l'url
      $buffer = file_get_contents($this->url);

      if ($buffer === false) {
        $msg = "SKIPLAN : Erreur d'accès au flux ({$this->url})";
        $subject = $GLOBALS['HOME_ROOT_URL'] . $msg;
        $detail = ' impossible de lire l\'url : ' . $this->url;
        if (!file_exists($urlErrorFile))
          file_put_contents($urlErrorFile, $detail);
        $mtimeError = filemtime($urlErrorFile);
        if ((time() - $mtimeError > 24 * 3600)) { //1 jour en sec
          $sendAlert = true;
          file_put_contents($urlErrorFile, $detail);
        }
        if($sendAlert && !empty($this->reportto)) {
          $GLOBALS['XUSER']->sendMail2User($subject,$detail,$this->reportto);
          $sendAlert = false;
        }
        setSessionVar('message', $msg);
        \Seolan\Core\Logs::critical(__METHOD__ . $msg.$detail);
        return false;
      }
      $doc = new \DOMDocument();
      $doc->loadXML($buffer);
      $xpath = new \DOMXPath($doc);
      $rootNode = $xpath->query("//SKIPLAN");
      if (!$rootNode || !$rootNode->length) {
        $msg = "SKIPLAN : Erreur d'accès au flux, pas de root element SKIPLAN, query //SKIPLAN ({$this->url})";
        $subject = $GLOBALS['HOME_ROOT_URL'] . $msg;
        $detail = $this->url . ': ' . PHP_EOL . $buffer;
        $mtimeError = @filemtime($errorFile);
        if (($mtimeError && (time() - $mtimeError > 24 * 3600)) || (!$mtimeError) || file_get_contents($errorFile)!=$buffer) { //1 jour en sec
          $sendAlert = true;
          file_put_contents($errorFile, $buffer);
        }
        if($sendAlert && !empty($this->reportto)) {
          $GLOBALS['XUSER']->sendMail2User($subject,$detail,$this->reportto);
          $sendAlert = false;
        }
        setSessionVar('message', $msg);
        \Seolan\Core\Logs::critical(__METHOD__ . $msg .': '.$buffer);
        return false;
      }
      $prevImport = file_get_contents($cacheFile);
      if (!$nocache && $prevImport !== false && $buffer == $prevImport) {
        $msg = ' réception flux OK - pas de changement depuis ' . date("Y-m-d H:i:s.", filemtime($cacheFile));
        \Seolan\Core\Shell::alert($msg, 'info');
        \Seolan\Core\Logs::notice(__METHOD__ . $msg);
        return false;
      }
      file_put_contents($cacheFile, $buffer);
      if ($this->clearCacheOnUPD) $this->cleanCache();
      // sauvegarde pour partenaire externe
      if ($this->shareflux) {
        mkdir(TZR_WWW_DIR . 'skiplan/');
        file_put_contents(TZR_WWW_DIR . 'skiplan/' . rewriteToAscii($this->station) . '.xml', $buffer);
      }
      return $buffer;
    }

    public function _loadAll() {
      $msg = __METHOD__ . ': Flux OK';
      $buffer = $this->getXml($msg);
      if ($buffer) {
        if($this->loadMeteo($buffer))
          $msg .= ' - load Meteo OK';
        if($this->_insertPistes($buffer))
          $msg .= ' - load Pistes OK';
        if($this->_updateStations($buffer))
          $msg .= ' - load Stations OK';
      }
      return $msg;
    }

    public function loadMeteo(&$buffer=null, $forceReload=false) {
      $doc = new \DOMDocument();
      $doc->loadXML($buffer);
      $xpath = new \DOMXPath($doc);
      $stationsNodeList = $xpath->query('//STATION');
      foreach ($stationsNodeList as $stationNode) {
        $idStation = $stationNode->getAttribute('ent_id');
        $oidStation = $this->_recupLienVerObjet($this->tbStation, "ent_id", $idStation);
        $xm = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo);
        if($doc) {
          $bulletins = [];
          $zones = [];
          // Chargement des bulletins
          foreach(['JOUR', 'LENDEMAIN', 'SEMAINE'] as $typeBulletin) {
            $bulletin = $xpath->query('//STATION[@ent_id="'.$idStation.'"]/METEO/BULLETINS/'.$typeBulletin.'/LANGUE');
            if($bulletin->length > 0) {
              foreach($bulletin as $bulletinNode) {
                if(!empty(trim($bulletinNode->textContent))) {
                    $bulletins[$typeBulletin][$bulletinNode->getAttribute('val')] = $bulletinNode->textContent;
                }
              }
            }
          }
          // Formattage des bulletins FR
          $bulletinFR = new Meteo([
              'type' => 'bulletin',
              'tbMeteo' => $this->tbmeteo,
              'lang' => 'FR',
              'station' => $oidStation,
          ]);
          $bulletinFR->datas['Bjour'] = (isset($bulletins['JOUR'])) ? $bulletins['JOUR']['fr'] : '';
          $bulletinFR->datas['texte'] = $bulletinFR->datas['Bjour'];
          $bulletinFR->datas['Blendemain'] = (isset($bulletins['LENDEMAIN'])) ? $bulletins['LENDEMAIN']['fr'] : '';
          $bulletinFR->datas['Bsemaine'] = (isset($bulletins['SEMAINE'])) ? $bulletins['SEMAINE']['fr'] : '';
          $bulletinFR->setDDeb(date('Y-m-d', time()).' 00:00:00');
          $bulletinFR->setDFin(date('Y-m-d', time()+24*3600*6).' 23:59:59');
          $datemaj = $xpath->query('//SKIPLAN/DATEINFO_EN');
          $bulletinFR->setDMaj(date('Y-m-d h:i', strtotime($datemaj->item(0)->textContent))); //Champ non traduisible
          // Formattage des bulletins EN
          $bulletinEN = new Meteo([
              'type' => 'bulletin',
              'tbMeteo' => $this->tbmeteo,
              'lang' => 'GB',
              'station' => $oidStation,
          ]);
          $bulletinEN->datas['Bjour'] = (isset($bulletins['JOUR']) && $bulletins['JOUR']['en'] != '') ? $bulletins['JOUR']['en'] : $bulletins['JOUR']['fr'];
          $bulletinEN->datas['texte'] = $bulletinEN->datas['Bjour'];
          $bulletinEN->datas['Blendemain'] = (isset($bulletins['LENDEMAIN']) && $bulletins['LENDEMAIN']['en'] != '') ? $bulletins['LENDEMAIN']['en'] : $bulletins['LENDEMAIN']['fr'];
          $bulletinEN->datas['Bsemaine'] = (isset($bulletins['SEMAINE']) && $bulletins['SEMAINE']['en'] != '') ? $bulletins['SEMAINE']['en'] : $bulletins['SEMAINE']['fr'];
          $bulletinEN->setDDeb(date('Y-m-d', time()).' 00:00:00');
          $bulletinEN->setDFin(date('Y-m-d', time()+24*3600*6).' 23:59:59');

          // On vide la table météo avec toutes nouvelles insertions
          getDB()->execute("delete from $this->tbmeteo where station = ?", [$oidStation]);

          $this->insertMeteo($bulletinFR, $xm);
          $this->insertMeteo($bulletinEN, $xm);

          // Chargement des zones
          $zonesNodeList = $xpath->query('//STATION[@ent_id="'.$idStation.'"]/METEO/PARAMETRES/ZONE');
          if($zonesNodeList->length > 0) {
            foreach($zonesNodeList as $zoneNode ) {
              $reference = $zoneNode->attributes->getNamedItem('reference')->textContent;
              $nom = $zoneNode->attributes->getNamedItem('nom')->textContent;

              $ar = [];
              $ar['_unique'] = ['zone', 'typinf'];
              $ar['_updateifexists'] = true;
              $ar['_nolog'] = true;

              // Si le nom est différent alors la zone n'est pas une prévision pour aujourd'hui
              $datejour = date('Y-m-d', strtotime('+ ' . (int)preg_replace('/[^\d]*/', '', $nom) . ' days'));

              $ar['typinf'] =  'parametres';
              $ar['station'] = $oidStation;
              $ar['zone'] =    $nom;
              $ar['alti'] =    $zoneNode->attributes->getNamedItem('altitude')->textContent;
              $ar['skplid'] =  $reference;
              $ar['ddeb'] =    $datejour.' 00:00:00';
              $ar['dfin'] =    $datejour.' 23:59:59';
              $ar['datemaj'] = date('Y-m-d H:i:s', strtotime($zoneNode->attributes->getNamedItem('datemaj_en')->textContent));

              foreach($zoneNode->childNodes as $zoneChildNode) {
                switch($zoneChildNode->localName) {
                  case 'VALRISQUE':
                      $ar['vrisqa'] = $this->_recupLienVerObjet($this->tbmeteoavalanche, "code", $zoneChildNode->textContent);
                  break;
                  case 'LIBRISQUE':
                      $ar['lrisqa'] = $zoneChildNode->textContent;
                  break;
                  case 'RSQ_REEL':
                      $ar['vrisqar'] = ((int)$zoneChildNode->textContent == 1) ? 1 : 2;
                  break;
                  case 'TEMPERATURE' :
                      $ar['tempe'] = $zoneChildNode->textContent;
                  break;
                  case 'TEMPERATURE_APM' :
                      $ar['tempeapm'] = $zoneChildNode->textContent;
                  break;
                  case 'TEMPERATURE_RESSENTIE' :
                      $ar['temperessentie'] = $zoneChildNode->textContent;
                  break;
                  case 'CUMUL' :
                      $ar['hcneige'] = $zoneChildNode->textContent;
                  break;
                  case 'NEIGE' :
                      $ar['hneige'] = $zoneChildNode->textContent;
                  break;
                  case 'CIEL_ID' :
                      $ar['ecielid'] = $this->_recupLienVerObjet($this->tbmeteociel, "code", $zoneChildNode->textContent);
                      $ar['leciel'] = $zoneChildNode->textContent;
                  break;
                  case 'CIEL_ID_APM' :
                      $ar['ecielai'] = $this->_recupLienVerObjet($this->tbmeteociel, "code", $zoneChildNode->textContent);
                      $ar['ecielam'] = $zoneChildNode->textContent;
                  break;
                  case 'RISQUE_ORAGE' :
                      $ar['RISQUE_ORAGE'] = $this->_recupLienVerObjet($this->tbReference, "code", $zoneChildNode->textContent, "RISQUE_ORAGE");
                  break;
                  case 'QLT_ID' :
                      $ar['QLT_ID'] = $this->_recupLienVerObjet($this->tbReference, "code", $zoneChildNode->textContent, "QLT_ID");
                  break;
                  case 'DIRECTION' :
                      // Direction donne d'où vient le vent mais pas où il va
                      $trueDirection = [
                          'N' => 'S',
                          'S' => 'N',
                          'E' => 'O',
                          'O' => 'E',
                          'NE' => 'SO',
                          'NO' => 'SE',
                          'SE' => 'NO',
                          'SO' => 'NE',
                      ];

                      $ar['dvent'] = $trueDirection[$zoneChildNode->textContent];
                      $ar['DIRECTION'] = $this->_recupLienVerObjet($this->tbReference, "code", $trueDirection[$zoneChildNode->textContent], "DIRECTION");
                  break;
                  case 'VISIBILITE' :
                      $ar['VISIBILITE'] = $this->_recupLienVerObjet($this->tbReference, "code", $zoneChildNode->textContent, "VISIBILITE");
                  break;
                  case 'DERNIERE_CHUTE_EN':
                    if ($zoneChildNode->textContent) {
                      $datetime = date('Y-m-d H:i:s', strtotime($zoneChildNode->textContent));
                      $ar['lcneige'] = $datetime;
                    }
                  break;
                  case 'VENT' :
                      $ar['fvent'] = $zoneChildNode->textContent;
                  break;
                  default:
                  break;
                }
              }
              $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo);
              $xds->procInput($ar);
            }
          }
        }
      }
      return $msg;
    }

    // la table meteo est trad et auto (pour avoir les parametres en GB et FR)
    protected function insertMeteo(&$am, &$xm) {
        \Seolan\Core\Logs::notice('Meteo', 'INSERT METEO ' . $am->lang . ' ' . $am->type);
        $ar = $am->toTzr();

        $ar['_options']['local'] = true;
        $ar['tplentry'] = TZR_RETURN_DATA;
        $ar['_nolog'] = 1;
        if ($am->lang == TZR_DEFAULT_LANG) {
            // si langue par defaut : insert
            $lang_save = \Seolan\Core\Shell::getLangData();
            $_REQUEST['LANG_DATA'] = $am->lang;
            $ar['LANG_DATA'] = $_REQUEST['LANG_DATA'];
            $xm->procInput($ar);
            $_REQUEST['LANG_DATA'] = $lang_save;
            $foo = \Seolan\Core\Shell::getLangData(NULL, true);
        } else if ($xm->getTranslatable()) {
            // si langue <> update si ligne existe deja : meme type, meme date
            $s = $xm->select_query(array('cond' => array('typinf' => array('=', $am->type),
//                        'zone'=>array('=', $am->zone),
//                        'alti'=>array('=', $am->altitude),
//                        'ddeb'=>array('=', $am->datedeb),
//                        'dfin'=>array('=', $am->datefin),
                    'station' => array('=', $am->station)
            )));
            $br = $xm->browse(array('tplentry' => TZR_RETURN_DATA, 'select' => $s));
            if (count($br['lines_oid']) == 1) {
                $ar['oid'] = $br['lines_oid'][0];
                $lang_save = \Seolan\Core\Shell::getLangData(); // force recalcul
                $_REQUEST['LANG_DATA'] = $am->lang;
                $ar['LANG_DATA'] = $_REQUEST['LANG_DATA'];
                $foo = \Seolan\Core\Shell::getLangData(NULL, true); // force le recalcul ...
                $xm->procEdit($ar);
                $_REQUEST['LANG_DATA'] = $lang_save;
                $foo = \Seolan\Core\Shell::getLangData(NULL, true); // force recalcul
            } else {
                \Seolan\Core\Logs::critical('Meteo', 'unable to insert data for lang ' . $am->lang . ' (' . $s . ')');
            }
        } // else if
    } // insertMeteo

// lecture de la meteo a un moment donne
    public function lireMeteo($ar = NULL) {
        $p = new \Seolan\Core\Param($ar, array('tplentry' => 'br'));
        $tpl = $p->get('tplentry');
        $xm = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo);
        $typinfs = array('parametres', 'bulletin', 'prevision');
        foreach ($typinfs as $foo => $typinf) {
            $datas = array();
            $br = $xm->browse(array('tplentry' => TZR_RETURN_DATA, 'selectedfields' => 'KOID',
                'select' => $xm->select_query(array('cond' => array('typinf' => array('=', $typinf))))));
            foreach ($br['lines_oid'] as $i => $oid) {
                $d = $xm->display(array('tplentry' => TZR_RETURN_DATA, 'oid' => $oid));
                $datas[] = $d;
            }
            \Seolan\Core\Shell::toScreen2($tpl, $typinf, $datas);
        } // foreach
    } // lireMeteo


    // table des états. on attend code et KOID
    // code est predefinie cf en haut
    protected function loadTableEtats(&$xset) {
        \Seolan\Core\Logs::notice($this->classname, $this->classname . " loading table etats");
        $fe = $xset->getField('etat');
        $tbetats = $fe->get_target();
        $lang = \Seolan\Core\Shell::getLangData();
        $s1 = "select code, KOID from " . $tbetats . " where lang='$lang'";
        $r1 = getDB()->select($s1);
        if (!$r1) {
            \Seolan\Core\Logs::critical($this->classname, $this->classname . " erreur acces base $s1");
            die($this->classname . " erreur acces base $s1");
        }
        $l = array();
        $this->oidEtats = array();
        while ($l = $r1->fetch()) {
            $this->oidEtats[$l['code']] = $l['KOID'];
        }
        \Seolan\Core\Logs::notice($this->classname, $this->classname . " table etats loaded");
    }

    public function inlineLoadMeteo($ar = NULL) {
        $buffer = $this->getXml($msg, true);
        $this->loadMeteo($buffer);
        \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    } // inlineLoadMeteo

    /**
     * Insère les remontées trouvés dans les secteurs, dans la table remontées listes
     *
     * @param DOMNodeList $secteurs liste des secteurs
     */
    protected function _insertRemonte($secteurs) {
      $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbrems);
      foreach ($secteurs as $secteur) {
        $Id_station = $secteur->getAttribute('ent_id');
        $stationOid = $this->_recupLienVerObjet($this->tbStation, "ent_id", $Id_station);
        $secteurOid = $this->_recupLienVerObjet($this->tbSecteurs, "id_secteur", $secteur->getAttribute('sct_id'), null, null, $stationOid, 'station');
        if ($stationOid)
          getDB()->execute("UPDATE {$this->tbrems} set PUBLISH=2 where station='$stationOid' and secteur='$secteurOid' ");
        $remontees = $secteur->getElementsByTagName("REMONTEE");
        foreach ($remontees as $remonte) {
          $ar = [];
          $ar['_unique'] = ['nom', 'station'];
          $ar['_updateifexists'] = true;
          $ar['_nolog'] = true;
          $ar['PUBLISH'] = 1;
          $ar["nom"] = $remonte->getAttribute('nom');
          $ar["nskpl"] = $remonte->getAttribute('nom');
          $ar["secteur"] = $secteurOid;
          $ar["type"] = $this->_recupLienVerObjet($this->tbReomnteesTypes, "code", $remonte->getAttribute('type'));
          $ar["etat"] = $this->_recupLienVerObjet($this->tbEtats, "code", $remonte->getAttribute('etat'));
          $ar["attente"] = $remonte->getAttribute('attente');
          $ar["longueur"] = $remonte->getAttribute('longueur');
          $ar["heuredeb"] = date("H:i:s", strtotime($remonte->getAttribute('heuredeb')));
          $ar["heurefin"] = date("H:i:s", strtotime($remonte->getAttribute('heurefin')));
          $ar["heuredebmod"] = date("H:i:s", strtotime($remonte->getAttribute('heuredebmod')));
          $ar["heurefinmod"] = date("H:i:s", strtotime($remonte->getAttribute('heurefinmod')));
          $ar["heuredesc"] = date("H:i:s", strtotime($remonte->getAttribute('heuredesc')));
          $ar["heuredescmod"] = date("H:i:s", strtotime($remonte->getAttribute('heuredescmod')));
          $ar["station"] = $stationOid;
          $xds->procInput($ar);
        }
      }
    }

    /**
     * fonction executent le chargement des pistes a partir d'un flux xml dans une table existente
     * le chargement des secteur est effectué ici car la table station à été créé apres
     *
     */
    protected function _insertPistes(&$buffer=null) {
        $doc = new \DOMDocument();
        $doc->loadXML($buffer);
        $valeurPourUpdate = array();
        $listes = array();
        if (!$doc)
            throw new Exception('erreur chargement du xml');

        $listeDesSecteurs = $doc->getElementsByTagName("SECTEUR");
        $this->_insertSecteur($listeDesSecteurs);
        $this->_insertRemonte($listeDesSecteurs);

        // Insertions des pistes et liaisons
        $secteurNum = 0;
        if (count($listeDesSecteurs)) {
          $Id_station = $listeDesSecteurs[0]->getAttribute('ent_id');
          $stationOid = $this->_recupLienVerObjet($this->tbStation, "ent_id", $Id_station);
          if ($stationOid) {
            getDB()->execute("UPDATE {$this->tbpistes} set PUBLISH=2 where station='$stationOid' ");
            getDB()->execute("UPDATE {$this->tbliaisons} set PUBLISH=2 where station='$stationOid' ");
          }
        }
        foreach ($listeDesSecteurs as $secteur) {
            $nomSecteur = $secteur->getAttribute('nom');
            $listes[] = $listeDesPistes = $secteur->getElementsByTagName("PISTE");
            $listeDesLiaisons = $secteur->getElementsByTagName("Liaison");

            //-- Insertion des Pistes
            foreach ($listes as $liste) {
                foreach ($liste as $tag) {
                    // construction du tableau corespondant a une ligne de la table
                    $valeurPourUpdate[] = array(
                        1,
                        $tag->getAttribute('nom'),
                        $tag->getAttribute('nom'),
                        $nomSecteur,
                        $tag->getAttribute('type'),
                        $tag->getAttribute('niveau'),
                        $tag->getAttribute('etat'),
                        $secteur->getAttribute('ent_id'),
                        $tag->getAttribute('entretien_num'),
                        $tag->getAttribute('pistequaliteneige'),
                    );
                }
            } // foreach listes
            $listes = array();

            //-- Insertions des liaisons
            $ent_id = $secteur->getAttribute('ent_id');
            // On parcours les listes des liaisons de chaque secteur
            foreach($listeDesLiaisons as $liaison) {
                $nomLiaison = $liaison->attributes->getNamedItem('nom')->textContent;
                $ar = [];
                $ar['_unique'] = ['nom'];
                $ar['_updateifexists'] = true;
                $ar['_nolog'] = true;
                $ar['PUBLISH'] = 1;
                $ar['nom']     = $nomLiaison;
                $ar['nskpl']   = rewriteToAscii($liaison->attributes->getNamedItem('nom')->textContent);
                $ar['secteur'] = $this->_recupLienVerObjet($this->tbSecteurs, "secteur", $nomSecteur);
                $ar['etat']    = $this->_recupLienVerObjet($this->tbEtats, "code", $liaison->attributes->getNamedItem('etat')->textContent);
                $ar['ent_id']  = $ent_id;
                $ar['station'] = $this->_recupLienVerObjet($this->tbStation, "ent_id", $ar['ent_id']);
                $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbliaisons);
                $xds->procInput($ar);
            }
            $secteurNum++;
        }

        //insertion des variables en base grace au tableau précédement construit
        foreach ($valeurPourUpdate as $update) {
            $ar = [];
            $ar['_unique'] = ['nom'];
            $ar['_updateifexists'] = true;
            $ar['_nolog'] = true;
            $ar['PUBLISH'] = 1;
            $ar['nom'] = $update[1];
            $ar['nskpl'] = $update[2];
            $ar['secteur'] = $this->_recupLienVerObjet($this->tbSecteurs, "secteur", $update[3]);
            $ar['type'] = $this->_recupLienVerObjet($this->tbPistesTypes, "code", $update[4]);
            $ar['niveau'] = $this->_recupLienVerObjet($this->tbNiveaux, "code", $update[5]);
            $ar['etat'] = $this->_recupLienVerObjet($this->tbEtats, "code", $update[6]);
            $ar['ent_id'] = $update[7];
            $ar['station'] = $this->_recupLienVerObjet($this->tbStation, "ent_id", $update[7]);
            $ar['entretien_num'] = $this->_recupLienVerObjet($this->tbReference, "code", $update[8], "PISTE", "entretien_num");
            $ar['pistequaliteneige'] = $this->_recupLienVerObjet($this->tbReference, "code", $update[9], "PISTE", "pistequaliteneige");
            $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbpistes);
            $xds->procInput($ar);
        }

        if ($stationOid && \Seolan\Core\System::fieldExists($this->tbStation,'datmaj')) {
          $xpath = new \DOMXPath($doc);
          $datemaj = $xpath->query('//SKIPLAN/DATEINFO_EN');
          $datemaj = date('Y-m-d h:i', strtotime($datemaj->item(0)->textContent)); //Champ non traduisible
          getDB()->execute("UPDATE {$this->tbStation} set UPD=UPD, datmaj=? where koid=? ", [$datemaj, $stationOid]);
        }

        return true;
    }

    /**
     * @param type $listeDesSecteurs liste de tout les secteur récupérer dans le xml
     */
    protected function _insertSecteur($listeDesSecteurs = null) {
      foreach ($listeDesSecteurs as $secteur) {
        $ar = [];
        $ar['_unique'] = ['secteur', 'station'];
        $ar['_updateifexists'] = true;
        $ar['_nolog'] = true;
        $ar['secteur'] = $secteur->getAttribute('nom');
        $ar['Id_station'] = $secteur->getAttribute('ent_id');
        $ar['id_secteur'] = $secteur->getAttribute('sct_id');
        $ar['station'] = $this->_recupLienVerObjet($this->tbStation, "ent_id", $ar['Id_station']);
        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbSecteurs);
        $xds->procInput($ar);
      }
    }

    /**
     * Récupére les id des liens vers objets pour l'insertion en base,
     * un nom de la table et un attribut de la table, la balise et l'attribut
     * sont facultatif, mais si l'attribut est passé la balise doit l'etre aussi
     *
     * @param type $table nom de la table
     * @param type $champ nom du champ
     * @param type $valeur valeur du champ
     * @param type $balise nom de la balise
     * @param type $attribut nom de l'attribut
     * @return array les id associé
     */
    protected function _recupLienVerObjet($table = null, $champ = null, $val = null, $balise = null, $attribut = null, $stationOid = null, $stationField = null) {
        $sql = 'select KOID from ' . $table . ' where ' . $champ . '=?';
        $sqlCondValues = [$val];
        if ($attribut != null && $balise != null) {
            $sql .= ' AND balise=? AND attribut=?';
            $sqlCondValues = array_merge($sqlCondValues, [$balise, $attribut]);
        } else if ($balise != null && $attribut == null) {
            $sql .= ' AND balise=?';
            $sqlCondValues[] = $balise;
        }

        if ($stationOid && $stationField) {
          $sql .= " AND $stationField=?";
          $sqlCondValues[] = $stationOid;
        }

        $idObjet = getDB()->select($sql, $sqlCondValues);
        if (!$idObjet)
            throw new Exception("l'objet voulu n'a pas été trouvé");

        while ($KOID = $idObjet->fetch()) {
            $ret = $KOID['KOID'];
        }
        //initialisation de la valeur de retour
        isset($ret) ? $ret = $ret : $ret = false;

        return $ret;
    } // _recupLienVerObjet

    /**
     * Chargement des pistes en base a partir des pistes fournies par le xml
     * si le code erreur 777 est retourné cela signifie que le secteur n'a pas
     * été trouvé, on renvoie donc une fois avec l'insertion des secteurs
     *
     * @param type $ar variaple propre à la console
     */
    public function chargementDesPistes($ar = NULL) {
      $buffer = $this->getXml($msg, true);
      if ($this->_insertPistes($buffer)) {
        \Seolan\Core\Shell::alert('fin de la mise à jour des Pistes', 'info');
      } else {
        \Seolan\Core\Shell::alert('Erreur de mise à jour des Pistes');
      }
      \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    }
    /**
     * recupère les date d'ouverture de la sation pour lete hiver forme de tableau pour le proc_insert de station
     * @param type $periode DOMNode contenant la periode
     * @return type le tableau correctement formater pour l'insert
     */
    protected function _recuperationStationOuvertureFermeture($station) {
        $periodes = $station->getElementsByTagName("PERIODE");
        foreach ($periodes as $periode) {
            $eteOuHiver = $periode->getAttribute('prd_id'); // 1=ete 2=hiver <- Brian : pas si simple que ça à revoir
            $dateOuverture = $periode->getAttribute('debut_en');
            $dateFermeture = $periode->getAttribute('fin_en');
            if ($eteOuHiver == 1) {
                $stationEtDate["ouverture_ete"] = date("Y-m-d", $this->frtotime($dateOuverture));
                $stationEtDate["fermeture_ete"] = date("Y-m-d", $this->frtotime($dateFermeture));
            } else {
                $stationEtDate["ouverture_hiver"] = date("Y-m-d", $this->frtotime($dateOuverture));
                $stationEtDate["fermeture_hiver"] = date("Y-m-d", $this->frtotime($dateFermeture));
            }
        }
        return $stationEtDate;
    }

    protected function frtotime($date) {
        $tabDate = explode("/", $date);
        return mktime(0, 0, 0, $tabDate[1], $tabDate[0], $tabDate[2]);
    }

    /**
     * mise à jour des stations à partir du fichier xml
     *
     * @param type $ar variable seolan
     */
    protected function _updateStations(&$buffer=null) {
        $ar = null;
        if (empty($buffer)) {
          return false;
        }
        $doc = new \DOMDocument();
        $ret = $doc->loadXML($buffer);
        if (!$ret) return false;
        $xpath = new \DOMXPath($doc);
        $stations = $xpath->query('//STATION');
        $this->_loadInformationStation($stations, $ar);
        $this->_loadPistesStation($stations, $ar);
        return true;
    }

    /**
     * mise a jour des donnees de la table station
     *
     * @param type $ar varaible propre à seolan
     */
    protected function _loadInformationStation($stations, $ar) {
      $commentaireEnAnglais = null;
      foreach ($stations as $station) {
        $idStation = $station->getAttribute('ent_id');
        $indicesExist = $station->getElementsByTagName('INDICES')->length > 0;
        $ar['_unique'] = ['ent_id'];
        $ar['_updateifexists'] = true;
        $ar['_nolog'] = true;
        $ar['nom'] = $station->getAttribute('nom');
        $ar['ent_id'] = $idStation;
        if ($indicesExist) {
          $indices = $station->getElementsByTagName('INDICES')[0];
          $tags = array("SKIABILITE", "AMBIANCE", "RETOUR_SKI",
            "ETAT_ROUTE", "ETAT_CHAUSSEE", "PRATIQUE_ACTIVITES",
            "SKI_NUIT", "SKI_AURORE", "STATION_OUVERTURE");
          foreach ($tags as $tag) {
            $attribut = ($tag == "SKI_NUIT" || $tag == "SKI_AURORE") ? "etat" : "val";
            $balise = $indices->getElementsByTagName($tag)[0];
            if($balise instanceof \DOMElement) {
              $attribut = $this->_recupLienVerObjet($this->tbReference, "code", $balise->getAttribute($attribut), $tag);
              $attribut == false ? $ar[$tag] = '' : $ar[$tag] = $attribut;
            }
          }
          //recuperation du commentaire fr(autre langue disponible)
          $langues = $indices->getElementsByTagName("LANGUE");
          foreach ($langues as $langue) {
            if ($langue->getAttribute('val') == "fr") {
              $ar["Commentaire"] = $langue->nodeValue;
            }
            if ($langue->getAttribute('val') == "en") {
              $commentaireEnAnglais = $langue->nodeValue;
            }
          }
          $KM_SKATING = $indices->getElementsByTagName("KM_SKATING")[0];
          if($KM_SKATING instanceof \DOMElement) {
            $ar["KM_SKATING"] = $KM_SKATING->getAttribute('ouvert');
          }
        }
        $stationOuvertureFermeture = $this->_recuperationStationOuvertureFermeture($station);
        $ar['ouverture_ete'] = $stationOuvertureFermeture["ouverture_ete"];
        $ar['fermeture_ete'] = $stationOuvertureFermeture["fermeture_ete"];
        $ar['ouverture_hiver'] = $stationOuvertureFermeture["ouverture_hiver"];
        $ar['fermeture_hiver'] = $stationOuvertureFermeture["fermeture_hiver"];
        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbStation);
        $xds->procInput($ar);
        
        // ALERT pourquoi spécifier oid ? utilisé dans procedit au lieu de koid ?
        //$ar['oid'] = $oidStation; //$oid['KOID'];

        // on récupère la valeur de la langue courante
        $lang_save = \Seolan\Core\Shell::getLangData();
        //on initialise à la langue souhaitée
        $_REQUEST['LANG_DATA'] = "GB";
        //initialise la console à la langue passé
        $foo = \Seolan\Core\Shell::getLangData(NULL, true);
        //sauvegarde en la langue voulue
        $ar["Commentaire"] = $commentaireEnAnglais;
        $ar['LANG_DATA'] = $_REQUEST['LANG_DATA'];
        $xds->procInput($ar);
        //on récupère la langue de départ
        $_REQUEST['LANG_DATA'] = $lang_save;
        //on initialise la console avec
        $foo = \Seolan\Core\Shell::getLangData(NULL, true);
      }
    }

    protected function _loadPistesStation($stations, $ar) {
      $sousBalises = [];
      $tags = ["SKI_ALPIN", "SKI_ALPIN_VERTES", "SKI_ALPIN_BLEUES", "SKI_ALPIN_ROUGES", "SKI_ALPIN_NOIRES",
          "SKI_NORDIQUE", "SKI_NORDIQUE_VERTES", "SKI_NORDIQUE_BLEUES", "SKI_NORDIQUE_ROUGES", "SKI_NORDIQUE_NOIRES",
          "DH_VERT", "DH_BLEU", "DH_ROUGE", "DH_NOIR", "DH_JAUNE", "DH_ND", "XC_VERT", "XC_BLEU", "XC_ROUGE", "XC_NOIR", "XC_JAUNE", "XC_ND",
          "REMONTEES", "FUNI", "TB", "TC", "TPH", "TS", "TSD", "TK", "FNT", "TR", "TLC", "ASC", "TSDB", "TMX", "TRAIN",
          "PIETONS", "RAQUETTES", "LUGE", "SNOWPARK", "LIGNE_SNOWPARK", "BOARDERCROSS"];
      foreach ($stations as $station) {
        $idStation = $station->getAttribute('ent_id');
        $indicesExist = $station->getElementsByTagName('INDICES')->length > 0;
        $oidStation = $this->_recupLienVerObjet($this->tbStation, "ent_id", $idStation);
        $ar['_unique'] = ['libelle', 'Station'];
        $ar['_updateifexists'] = true;
        $ar['_nolog'] = true;
        $ar['Station'] = $oidStation;
        if ($indicesExist) {
          $indices = $station->getElementsByTagName('INDICES')[0];
          foreach ($tags as $tag) {
            $balises = $indices->getElementsByTagName($tag)[0];
            if($balises instanceof \DOMElement) {
              if (in_array($tag, ["SNOWPARK","LIGNE_SNOWPARK","BOARDERCROSS"])) {
                  $sousBalises[$tag] = $indices->getElementsByTagName($tag);
              }
              $pistesStationFields = ['total','total_periode','total_periode_hpf','ouvertes_previsions','previsions',
              'ouvertes','fermees','lng_total','lng_ouvertes_previsions','lng_periode','lng_periode_hpf',
              'lng_previsions','lng_fermees'];
              $ar['libelle'] = $tag;
              foreach($pistesStationFields as $pistesStationField) {
                $ar[$pistesStationField] = $balises->getAttribute($pistesStationField);
              }
              $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbStationPistes);
              $xds->procInput($ar);
              $this->_loadSousBalise($sousBalises[$tag], $oidStation);
            }
          }
        }
      }
    }

    /**
     * recuperation des balises modules (sous balises de /indices/snowpark) et de leurs attributs dans le tableau $sousBalises
     * BOARDERCROSS est une balise parente autant q'une sous balise : on est kéni !!
     *
     * @param type $listeDesSousBalise liste de toutes les sous balises de SNOWPARK, LIGNE_SNOWPARK et BOARDERCROSS récupérés dans le xml
     * @param type $ar variable de séolan
     */
    protected function _loadSousBalise($listeDesSousBalise=null, $oidStation=null) {
      $sousBalises = [];
      $tags = ["BIGAIR", "BOX", "CHILLZONE", "HALFPIPE", "HIP", "AIRBAG", "KICKER",
          "QUATERPIPE", "RAIL", "STEPUP", "WATERSLIDE", "WHOOPS", "BOARDERCROSS", "VIDEOZONE", "SPEEDZONE"];
      foreach ($listeDesSousBalise as $sousBalise) {
        foreach ($tags as $tag) {
          if ($sousBalise->hasChildNodes()) {
            $balises = $sousBalise->getElementsByTagName($tag)[0];
            $ar = [];
            $ar['_unique'] = ['libelle', 'piste_park'];
            $ar['_updateifexists'] = true;
            $ar['_nolog'] = true;
            $ar['station'] = $oidStation;
            $ar['libelle'] = $tag;
            $oidPistePark = $this->_recupLienVerObjet($this->tbStationPistes, "libelle",
              $listeDesSousBalise[0]->nodeName);
            $ar['piste_park'] = $oidPistePark;
            $sousBalisesFields = ['total','total_periode','total_periode_hpf', 'ouvertes_previsions','previsions','ouvertes','fermees'];
            foreach($sousBalisesFields as $sousBalisesField) {
              $ar[$sousBalisesField] = $balises->getAttribute($sousBalisesField);
            }
            $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbModPistes);
            $xds->procInput($ar);
          }
        }
      }
    }

    /**
     * Update des station deja en base à partir du xml
     * @param type $ar variable de seolan
     */
    public function inLineLoadStations($ar = NULL) {
      $buffer = $this->getXml($msg, true);
      if ($this->_updateStations($buffer)) {
        \Seolan\Core\Shell::alert('Fin de la mise à jour des stations et recap des équipements associés', 'info');
      } else {
        \Seolan\Core\Shell::alert('Erreur de mise à jour des stations et recap des équipements associés');
      }
      \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    }
    public function getUIFunctionList() {
        return [
            'pistes' => 'Etat des pistes',
            'meteo' => 'Météo'
        ];
    }

    public function UIEdit_pistes($ar) {
      $dsSecteurs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbSecteurs);

      $dsSecteurs->desc['recapOn'] = clone($dsSecteurs->desc['PUBLISH']);
      $dsSecteurs->desc['recapOn']->field = 'recapOn';
      $dsSecteurs->desc['recapOn']->label = 'Afficher le Récap des ouvertures<br/> (Pistes / Remontées)';
      $dsSecteurs->desc['recapOn']->published = 0;
      $dsSecteurs->desc['recapOn']->compulsory = true;
      $dsSecteurs->desc['recapOn']->queryable = 1;
      $dsSecteurs->desc['recapOn']->default = 1;
      //$dsSecteurs->desc['recapOn']->_options->id = "skiplanSecteurs:recapOn";
      $dsSecteurs->orddesc[] = 'recapOn';

      $dsSecteurs->desc['smenuOn'] = clone($dsSecteurs->desc['PUBLISH']);
      $dsSecteurs->desc['smenuOn']->field = 'smenuOn';
      $dsSecteurs->desc['smenuOn']->label = 'Afficher les liens de navigation<br/> par secteur';
      $dsSecteurs->desc['smenuOn']->published = 0;
      $dsSecteurs->desc['smenuOn']->compulsory = true;
      $dsSecteurs->desc['smenuOn']->queryable = 1;
      $dsSecteurs->desc['smenuOn']->default = 1;
      //$dsSecteurs->desc['smenuOn']->_options->id = "skiplanSecteurs:smenuOn";
      $dsSecteurs->orddesc[] = 'smenuOn';

      $ar['selectedfields'] = ['station', 'recapOn', 'smenuOn'];
      //$ar['_preparedquery']['recapOn']['value'] = 1;
      $ar['_options'] = ['local'=>true];
      $ret = $dsSecteurs->query($ar);
      return $ret;
    }

    public function UIProcEdit_pistes($ar) {
      $dsSecteurs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbSecteurs);
      
      $dsSecteurs->desc['recapOn'] = clone($dsSecteurs->desc['PUBLISH']);
      $dsSecteurs->desc['recapOn']->field = 'recapOn';
      $dsSecteurs->desc['recapOn']->label = 'Afficher le Récap des ouvertures<br/> (Pistes / Remontées)';
      $dsSecteurs->desc['recapOn']->published = 0;
      $dsSecteurs->desc['recapOn']->compulsory = true;
      $dsSecteurs->desc['recapOn']->queryable = 1;
      $dsSecteurs->desc['recapOn']->default = 1;
      //$dsSecteurs->desc['recapOn']->_options->id = "skiplanSecteurs:recapOn";
      $dsSecteurs->orddesc[] = 'recapOn';
      
      $dsSecteurs->desc['smenuOn'] = clone($dsSecteurs->desc['PUBLISH']);
      $dsSecteurs->desc['smenuOn']->field = 'smenuOn';
      $dsSecteurs->desc['smenuOn']->label = 'Afficher les liens de navigation<br/> par secteur';
      $dsSecteurs->desc['smenuOn']->published = 0;
      $dsSecteurs->desc['smenuOn']->compulsory = true;
      $dsSecteurs->desc['smenuOn']->queryable = 1;
      $dsSecteurs->desc['smenuOn']->default = 1;
      //$dsSecteurs->desc['smenuOn']->_options->id = "skiplanSecteurs:smenuOn";
      $dsSecteurs->orddesc[] = 'smenuOn';

      $ar['_FIELDS'] = ['station' => 'station', 'recapOn' => 'recapOn', 'smenuOn' => 'smenuOn'];
      return $dsSecteurs->captureQuery($ar);
    }

    public function UIView_pistes($ar) {
        $cond = [];
        if (is_array($ar['cond']['station'])) 
          $cond['Station'] = $ar['cond']['station'];
        elseif (is_array($ar['station']))
          $cond['Station'] = ['=', array_keys($ar['station'])];
        
        $recapOn = $ar['recapOn'];
        $smenuOn = $ar['smenuOn'];
        $ar = array_merge($ar, [
          'tplentry' => TZR_RETURN_DATA,
          '_mode' => 'both',
          'pagesize' => -1,
        ]);
        // On sélectionne les secteurs pour filtrer uniquement sur ceux publiés
        $secteurs = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbSecteurs)->procQuery($ar);

        $publishedSecteur = !empty($secteurs['lines_oid']) ? $secteurs['lines_oid'] : false;

        $pistes = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbpistes)->browse([
            'cond' => [ 'secteur' => ['=', $publishedSecteur] ],
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('nom', 'etat', 'secteur', 'type', 'niveau', 'station'),
            'options' => [
              'niveau' => ['target_fields' => ['libelle', 'stylesheet', 'ordre']],
              'station' => ['target_fields' => ['nom']]
            ],
            'order' => 'secteur[secteur], niveau[ordre], nom',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $remontees = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbrems)->browse([
            'cond' => [ 'secteur' => ['=', $publishedSecteur] ],
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('nom', 'etat', 'secteur', 'type'),
            'order' => 'secteur[secteur]',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $datasBySecteur = [];

        foreach ($publishedSecteur as $secteurOid) {
          $datasBySecteur[$secteurOid]['pistes'] = array_filter($pistes['lines'], fn($piste) => $piste['osecteur']->raw == $secteurOid);
          $datasBySecteur[$secteurOid]['remontees'] = array_filter($remontees['lines'], fn($remontee) => $remontee['osecteur']->raw == $secteurOid);
          $datasBySecteur[$secteurOid]['nom'] = getDB()->fetchOne("select secteur from skiplanSecteurs where LANG='FR' and koid = ?", [$secteurOid]);

        }

        $recap = [];
        $recap['totalPistesOuvertes'] = getDB()->fetchOne(
          "select count(*) from skiplanPistes "
          ."join skiplanEtats on skiplanPistes.etat=skiplanEtats.koid "
          ."join skiplanPistesTypes on skiplanPistes.type=skiplanPistesTypes.koid "
          ."where skiplanPistes.LANG='FR' and PUBLISH=1 and skiplanEtats.lang='FR' "
          ."and skiplanPistesTypes.lang='FR' and (skiplanEtats.code='O' or skiplanEtats.code='P') "
          ."and skiplanPistesTypes.code='A' and skiplanPistes.secteur in ('" . implode("','", $publishedSecteur) . "')"
        );
        $recap['totalPistes'] = getDB()->fetchOne(
          "select count(*) from skiplanPistes "
          ."join skiplanEtats on skiplanPistes.etat=skiplanEtats.koid "
          ."join skiplanPistesTypes on skiplanPistes.type=skiplanPistesTypes.koid "
          ."where skiplanPistes.LANG='FR' and PUBLISH=1 and skiplanEtats.lang='FR' "
          ."and skiplanPistesTypes.lang='FR' and skiplanPistesTypes.code='A' and skiplanPistes.secteur in ('" . implode("','", $publishedSecteur) . "')"
        );
        $recap['totalRemonteesOuvertes'] = getDB()->fetchOne(
          "select count(*) from skiplanRemontees "
          ."join skiplanEtats on skiplanRemontees.etat=skiplanEtats.koid "
          ."where skiplanRemontees.LANG='FR' and PUBLISH=1 and skiplanEtats.lang='FR' "
          ."and (skiplanEtats.code='O' or skiplanEtats.code='P') and skiplanRemontees.secteur in ('" . implode("','", $publishedSecteur) . "')"
        );
        $recap['totalRemontees'] = getDB()->fetchOne(
          "select count(*) from skiplanRemontees where LANG='FR' and PUBLISH=1 "
          ."and skiplanRemontees.secteur in ('" . implode("','", $publishedSecteur) . "')"
        );

        $liaisons = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbliaisons)->browse([
            'cond' => [ 'secteur' => ['=', $publishedSecteur] ],
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => ['nom', 'nskpl', 'secteur', 'etat', 'ent_id', 'station'],
            'order' => 'secteur[secteur]',
            '_mode' => 'both',
            'pagesize' => -1,
        ]);

        $legenderemontees = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbReomnteesTypes)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet'),
            'order' => 'libelle',
            '_mode' => 'both',
            'pagesize' => -1
        ]);
        $legendelvl = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbNiveaux)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet'),
            'order' => 'ordre',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $legendeetat = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbEtats)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet'),
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $legendepiste = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbPistesTypes)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet'),
            'order' => 'libelle',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $stationInfos = [];
        if ((is_array($cond['Station'])) && \Seolan\Core\System::fieldExists($this->tbStation,'datmaj')) {
          $condStation = ['koid' => $cond['Station']];
          $stationInfos = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbStation)->browse([
              'cond' => $condStation,
              'tplentry' => TZR_RETURN_DATA,
              'selectedfields' => 'datmaj',
              //'_mode' => 'both',
              'pagesize' => -1
          ]);
        }

        return [
            'recapOn' => $recapOn,
            'smenuOn' => $smenuOn,
            'secteurs' => $publishedSecteur,
            'pistes' => $pistes,
            'remontees' => $remontees,
            'datasBySecteur' => $datasBySecteur,
            'liaisons' => $liaisons,
            'recap' => $recap,
            'stationInfos' => $stationInfos,
            'legenderemontees' => $legenderemontees,
            'legendelvl' => $legendelvl,
            'legendeetat' => $legendeetat,
            'legendepiste' => $legendepiste
        ];
    } // UIView_pistes

    public function UIEdit_meteo($ar) {
      $ar['selectedfields'] = ['station'];
      return \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo)->query($ar);
    }

    public function UIProcEdit_meteo($ar) {
      $ar['_FIELDS'] = ['station' => 'station'];
      return \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo)->captureQuery($ar);
    }

    public function UIView_meteo($ar) {
      $ar = array_merge($ar, [
        'tplentry' => TZR_RETURN_DATA,
        'selectedfields' => 'all',
        '_mode' => 'both',
        'pagesize' => -1,
        'order' => 'skplid ASC, ddeb ASC',
        'where' => "ddeb >= curDate() OR typinf = 'bulletin'"
      ]);
      $meteo = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo)->procQuery($ar);
      return ['meteo' => $meteo];
    }
}
