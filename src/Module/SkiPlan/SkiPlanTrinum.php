<?php
namespace Seolan\Module\SkiPlan;
//11/2020 : ancien Format "Trinum" utilisé sur https://www.valberg.com 
// pistes uniquement :
//- http://valberg.infonet-online.fr/xml/ouvertures.xml
//( meteo traité en local - http://valberg.infonet-online.fr/xml/meteo.xml)

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
// le module
//
class SkiPlanTrinum extends \Seolan\Core\Module\Module {

    public $url = NULL;
    public $tbpistes = NULL;
    public $tbrems = NULL;
    public $tbSecteurs = NULL;
    public $tbetats = NULL;
    public $tbliaisons = NULL;
    public $modulePistes = NULL;
    public $shareflux = false;
    public $xmlRootElem = 'SKIPLAN';
// oid des etats en table des etats console
    public $oidEtats = NULL; // charge
    public $langue = array("FR", "GB");

    protected function runInit() {
        if ($this->xp == NULL)
            $this->xp = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbpistes); // les pistes
        if ($this->xr == NULL)
            $this->xr = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbrems); // les remontees
    }

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
        $this->_options->setOpt('XML Root Element', 'xmlRootElem', 'text', ['size' => 20], 'STATION', 'Skiplan');
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
        $g['chargementStations'] = array('rw', 'rwv', 'admin');
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

        if ($this->secure('', 'chargementDesPistes')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'chargementDesPistes', 'Mise à jour de l\'ouverture des pistes depuis le flux XML', '&moid=' . $this->_moid . '&_function=chargementDesPistes&template=Core.message.html&tplentry=br', 'edit');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['chargementDesPistes'] = $o1;
        }

        if ($this->secure('', 'inLineLoadStations')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'inLineLoadStations', 'Mise à jour des Stations et de leur informations depuis le flux XML', '&moid=' . $this->_moid . '&_function=inLineLoadStations&template=Core.message.html&tplentry=br', 'edit');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['inLineLoadStations'] = $o1;
        }
        if ($this->secure('', 'updateSchema')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'updateSchema', 'Mise à jour du Schema des Données', '&moid=' . $this->_moid . '&_function=updateSchema&template=Core.message.html&tplentry=br');
            $o1->group = 'more';
            $o1->menuable = true;
            $my['updateSchema'] = $o1;
        }

        ///NOTICE: Init Only
        if ($this->secure('', 'chargementStations')) {
            $o1 = new \Seolan\Core\Module\Action($this, 'chargementStations', 'Charger les Stations depuis le flux XML', '&moid=' . $this->_moid . '&_function=chargementStations&template=Core.message.html&tplentry=br', 'edit');
            $o1->group = 'edit';
            $o1->menuable = true;
            $my['chargementStations'] = $o1;
        }
    } // _actionlist()


    public function getMainAction() {
      return $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'moid=' . $this->_moid . '&function=getInfos&tplentry=br&template=Core/Module.infos.html';
    }
    function getInfos() {
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
      $cmd = '/bin/rm -rf ' . TZR_VAR2_DIR .'cache/*';
      \Seolan\Core\Logs::notice(__METHOD__ . ' : ' . $cmd);
      system($cmd);
    }
    protected function updateSchema() {
      \Seolan\Core\Logs::critical("Ugrade XModSkiplan START");
      ModSkiPlanWd::updateSchema();
      \Seolan\Core\Logs::critical("Ugrade XModSkiplan DONE");
    }

    // recup des meteo via scheduler
    //
    function cronLoadAll(&$scheduler, &$o, &$more) {
        $msg = $this->_loadAll();
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }
    function cronLoadMeteo(&$scheduler, &$o, &$more) {
        $buffer = null;
        $msg = $this->loadMeteo($buffer);
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }

    function cronLoadPistes(&$scheduler, &$o, &$more) {
        $buffer = null; $msg = '';
        $this->_insertPistes($buffer, false, $msg);
        $scheduler->setStatusJob($o->KOID, 'finished', $msg);
    }

    protected function getParmStation($xpath) {
        $parametresNodeList = $xpath->query('//STATION');
        if ($parametresNodeList->length != 1) {
            \Seolan\Core\Logs::critical($this->classname, "impossible de lire l'element ");
        } // if
        else {
            $paramNode = $parametresNodeList->item(0);
            $Id_station = $paramNode->getAttribute('nom');
            if (!empty($Id_station) && (!empty($this->tbStation)))
              return $this->_recupLienVerObjet($this->tbStation, "nom", $Id_station);
            else
              return $Id_station;
        } // else

    } // protected function getParmStation

    protected function getCacheXml() {
      $cacheFile = TZR_VAR2_DIR . '/XModSkiPlan/lastimport_' . $this->_moid . '.xml';
      return file_get_contents($cacheFile);
    }
    protected function getXml(&$msg='') {
      //check du cache
      mkdir(TZR_VAR2_DIR . '/XModSkiPlan/');
      $cacheFile = TZR_VAR2_DIR . '/XModSkiPlan/lastimport_' . $this->_moid . '.xml';
      $errorFile = TZR_VAR2_DIR . '/XModSkiPlan/error_' . $this->_moid . '.xml';
      $urlErrorFile = TZR_VAR2_DIR . '/XModSkiPlan/urlError_' . $this->_moid . '.xml';
      $sendAlert = false;

      // open de l'url
      $buffer = file_get_contents($this->url);

      if ($buffer === false) {
        $msg = "SKIPLAN : Erreur d\'accès au flux ({$this->url})";
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

      $rootNode = $xpath->query("//{$this->xmlRootElem}");
      if (!$rootNode || !$rootNode->length) {
        $msg = "SKIPLAN : Erreur d'accès au flux, pas de root element {$this->xmlRootElem}, query //{$this->xmlRootElem} ({$this->url})";
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
      if ($prevImport !== false && $buffer == $prevImport) {
        $msg = ' réception flux OK - pas de changement depuis ' . date("Y-m-d H:i:s.", filemtime($cacheFile));
        setSessionVar('message', $msg);
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
        if (!$buffer) {
          $msg =  __METHOD__ . ': Flux OK';
          $buffer = $this->getXml($msg);
          if (!$buffer && $forceReload) {
            $buffer = $this->getCacheXml();
            if ($buffer) setSessionVar('message', $msg . " Forced Reload");
          }
        } 
        if (!$buffer) {
          return $msg;
        }
        $doc = new \DOMDocument();
        $doc->loadXML($buffer);

        $xpath = new \DOMXPath($doc);

        $parmStation = $this->getParmStation($xpath);
        $xm = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbmeteo);

        if($doc) {
            $bulletins = [];
            $zones = [];

            // Chargement des bulletins
            foreach(['JOUR', 'LENDEMAIN', 'SEMAINE'] as $typeBulletin) {
                $bulletin = $xpath->query('//STATION/METEO/BULLETINS/'.$typeBulletin.'/LANGUE');
                if($bulletin->length > 0) {
                    foreach($bulletin as $bulletinNode) {
                        if(!empty(trim($bulletinNode->textContent))) {
                            $bulletins[$typeBulletin][$bulletinNode->attributes->getNamedItem('val')->textContent] = $bulletinNode->textContent;
                        }
                    } // foreach
                } // if
            } // foreach

            // Formattage des bulletins FR
            $bulletinFR = new Meteo([
                'type' => 'bulletin',
                'tbMeteo' => $this->tbmeteo,
                'lang' => 'FR',
                'station' => $parmStation,
            ]);
            $bulletinFR->datas['Bjour'] = (isset($bulletins['JOUR'])) ? $bulletins['JOUR']['fr'] : '';
            $bulletinFR->datas['texte'] = $bulletinFR->datas['Bjour'];
            $bulletinFR->datas['Blendemain'] = (isset($bulletins['LENDEMAIN'])) ? $bulletins['LENDEMAIN']['fr'] : '';
            $bulletinFR->datas['Bsemaine'] = (isset($bulletins['SEMAINE'])) ? $bulletins['SEMAINE']['fr'] : '';
            $bulletinFR->setDDeb(date('Y-m-d', time()).' 00:00:00');
            $bulletinFR->setDFin(date('Y-m-d', time()+24*3600*6).' 23:59:59');
            // Formattage des bulletins EN
            $bulletinEN = new Meteo([
                'type' => 'bulletin',
                'tbMeteo' => $this->tbmeteo,
                'lang' => 'GB',
                'station' => $parmStation,
            ]);
            $bulletinEN->datas['Bjour'] = (isset($bulletins['JOUR'])) ? $bulletins['JOUR']['en'] : '';
            $bulletinEN->datas['texte'] = $bulletinEN->datas['Bjour'];
            $bulletinEN->datas['Blendemain'] = (isset($bulletins['LENDEMAIN'])) ? $bulletins['LENDEMAIN']['en'] : '';
            $bulletinEN->datas['Bsemaine'] = (isset($bulletins['SEMAINE'])) ? $bulletins['SEMAINE']['en'] : '';
            $bulletinEN->setDDeb(date('Y-m-d', time()).' 00:00:00');
            $bulletinEN->setDFin(date('Y-m-d', time()+24*3600*6).' 23:59:59');

            // On vide la table météo avec toutes nouvelles insertions
            getDB()->execute("delete from $this->tbmeteo where station = ?", [$parmStation]);
            

            // Insertions bulletins FR puis EN
            $this->insertMeteo($bulletinFR, $xm);
            $this->insertMeteo($bulletinEN, $xm);

            // Chargement des zones
            $zonesNodeList = $xpath->query('//STATION/METEO/PARAMETRES/ZONE');
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
                    $ar['station'] = $parmStation;
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
                                $datetime = date('Y-m-d H:i:s', strtotime($zoneChildNode->textContent));
                                $ar['lcneige'] = $datetime;
                            break;
                            case 'VENT' :
                                $ar['fvent'] = $zoneChildNode->textContent;
                            break;
                            default:
                            break;
                        } // switch
                    } // foreach

                    $this->fixedProcInput($this->tbmeteo, $ar);
                } // foreach
            } // if
        } // if

        return $msg;
    } // loadMeteo


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
//
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

    // lire le premier plan
    protected function getPlan(&$xset) {
        $b = $xset->browse(array('tplentry' => '*return*', 'pagesize' => 1, 'first' => 0));
        return $b['lines_oid'][0];
    } // getPlan

    // table des états. on attends code et KOID
    // code est prefdefinie cf en haut
    //
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


    /**
    * En cas d'insertion défini PUBLISH à 1, en cas d'édition ne change pas PUBLISH
    */
    protected function fixedProcInput($table, &$ar) {
        $sql = "SELECT COUNT(*) FROM $table WHERE ";
        // Construction du where
        $where = '';
        $args = [];

        foreach($ar['_unique'] as $field) {
            if ($field=='station' && empty($ar[$field]))
              continue;
            $where .= "$field = ? AND ";
            $args[] = isset($ar[$field]) ? $ar[$field] : null;
        }
        $where .= "1=1";

        \Seolan\Core\Logs::critical(__METHOD__." sql=$sql");
        \Seolan\Core\Logs::critical(__METHOD__." sql where=$where");
        \Seolan\Core\Logs::critical(__METHOD__." sql args=".print_s($args,3,true,false));
        $sql = $sql.$where;
        $requete = getDb()->select($sql, $args);

        $exists = $requete->fetch();
        
        if((int)$exists['COUNT(*)'] != 0) 
          $ar['_updateifexists'] = 1;
        
        //if((int)$exists['COUNT(*)'] == 0)
            $ar['PUBLISH'] = 1;
        

        $xds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($table);
        return $xds->procInput($ar);
    } // protected function fixedProcInput

    /**
     * vérifie si le nom d'une station exist deja, via une requette
     * sql directement sur la base, le nom de la base estt récupérer grace à:
     * $this->runInit(); et est strocké dans $this->tbpistes
     *
     * @param type $idreprésente l id de la station voulue
     * @return boolean retourne true si le nom existe deja false si nom
     */
    protected function _verificationExistanceStation($ent_id) {
        //on prepare la requete ? => :$nomvar
        $sql = "select ent_id from " . $this->tbStation . " where ent_id=?";
        $idStation = getDB()->fetchOne($sql, [$ent_id]);
        if (!$idStation) {
          \Seolan\Core\Logs::critical(__METHOD__ . "Station $ent_id n'existe pas.");
          return false;
            //throw new Exception('select pour vérifier le nom de la station non aboutie');
        }
        return true;
        
        /*
        while ($id = $idStation->fetch()) {
            $ret = $id['ent_id'];
        }
        //initialisation de la valeur de retour
        isset($ret) ? $ret = true : $ret = false;

        return $ret;
        */
    } // _verificationExistanceStation

    public function inlineLoadMeteo($ar = NULL) {
        $buffer = null;
        $this->loadMeteo($buffer,true);
        \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    } // inlineLoadMeteo

    /**
     * Insère les remontées trouvés dans les secteurs, dans la table remontées listes
     *
     * @param DOMNodeList $secteurs liste des secteurs
     */
    protected function _insertRemonte($secteurs) {
        if (count($secteurs)) {
          $Id_station = $secteurs{0}->getAttribute('ent_id');
          if (!empty($Id_station) && !empty($this->tbStation))
            $stationOid = $this->_recupLienVerObjet($this->tbStation, "ent_id", $Id_station);
          else
	           $stationOid = $secteurs{0}->parentNode->parentNode->getAttribute('nom');
	        \Seolan\Core\Logs::critical(__METHOD__." oidStation =$stationOid");
          if ($stationOid && !empty($Id_station))
            getDB()->execute("UPDATE {$this->tbrems} set PUBLISH=2 where station='$stationOid' ");
          elseif ($stationOid) {
            getDB()->execute("UPDATE {$this->tbrems} r join {$this->tbSecteurs} s on r.secteur=s.koid set r.PUBLISH=2 where s.station='$stationOid' ");
            $stationOid = null;
          }
        }
        foreach ($secteurs as $secteur) {
            $remontees = $secteur->getElementsByTagName("REMONTEE");
            if(!empty($this->tbSecteurs))
              $secteurOid = $this->_recupLienVerObjet($this->tbSecteurs, "secteur", $secteur->getAttribute('nom'), null, null); //, $stationOid, 'station'
            else
              $secteurOid = null;
            foreach ($remontees as $remonte) {
                \Seolan\Core\Logs::critical(__METHOD__." remontee nom=".$remonte->getAttribute('nom')." secteur=".$secteur->getAttribute('nom')." secteuroid=$secteurOid");
                $ar['_unique'] = ['nom', 'secteur', 'PUBLISH'];
                $ar['_updateifexists'] = true;
                $ar['_nolog'] = true;
                
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

                $ret = $this->fixedProcInput($this->tbrems, $ar);
            } // foreach
        } // foreach
    } // _insertRemonte

    /**
     * fonction executent le chargement des pistes a partir d'un flux xml dans une table existente
     * le chargement des secteur est effectué ici car la table station à été créé apres
     *
     */
    protected function _insertPistes(&$buffer=null, $forceReload=false, &$msg='') {
        if (!$buffer) {
          $msg =  __METHOD__ . ': Flux OK';
          $buffer = $this->getXml($msg);
          if (!$buffer && $forceReload) {
            \Seolan\Core\Logs::critical(__METHOD__." Force Reload");
            $buffer = $this->getCacheXml();
            if ($buffer) setSessionVar('message', $msg . " Forced Reload");
            if ($buffer) \Seolan\Core\Logs::critical(__METHOD__." Got buffer");
          }
        } 
        if (!$buffer) {
          return false;
        }

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
          $Id_station = $listeDesSecteurs{0}->getAttribute('ent_id');
          \Seolan\Core\Logs::critical(__METHOD__." idStation =$Id_station");
          if (!empty($Id_station) && !empty($this->tbStation))
            $stationOid = $this->_recupLienVerObjet($this->tbStation, "ent_id", $Id_station);
          else
	          $stationOid = $listeDesSecteurs{0}->parentNode->parentNode->getAttribute('nom');
	        \Seolan\Core\Logs::critical(__METHOD__." oidStation =$stationOid");
          if ($stationOid && !empty($Id_station)) {
            getDB()->execute("UPDATE {$this->tbpistes} set PUBLISH=2 where station='$stationOid' ");
            if (!empty($this->tbliaisons))
              getDB()->execute("UPDATE {$this->tbliaisons} set PUBLISH=2 where station='$stationOid' ");
          } elseif ($stationOid) {
            getDB()->execute("UPDATE {$this->tbpistes} r join {$this->tbSecteurs} s on r.secteur=s.koid set r.PUBLISH=2 where s.station='$stationOid' ");
            if (!empty($this->tbliaisons))
              getDB()->execute("UPDATE {$this->tbliaisons} r join {$this->tbSecteurs} s on r.secteur=s.koid set r.PUBLISH=2 where s.station='$stationOid' ");
            $stationOid = null;
          }
        }
        foreach ($listeDesSecteurs as $secteur) {
            $nomSecteur = $secteur->getAttribute('nom');
            $listes[] = $listeDesPistes = $secteur->getElementsByTagName("PISTE");
            $listeDesLiaisons = $secteur->getElementsByTagName("Liaison");

            //** Insertion des Pistes
            foreach ($listes as $liste) {
                foreach ($liste as $tag) {
                    // construction du tableau corespondant a une ligne de la table
                    $valeurPourUpdate[] = array(
                        1,
                        $tag->getAttribute('nom'),
                        $tag->getAttribute('nom'),
                        $nomSecteur,
                        $tag->getAttribute('type'),
                        $tag->getAttribute('type'),
                        $tag->getAttribute('etat'),
                        $secteur->getAttribute('ent_id'),
                        $tag->getAttribute('entretien_num'),
                        $tag->getAttribute('pistequaliteneige'),
                    );
                }
            } // foreach listes
            $listes = array();

            //** Insertions des liaisons
            $ent_id = $secteur->getAttribute('ent_id');
            // On parcours les listes des liaisons de chaque secteur
            foreach($listeDesLiaisons as $liaison) {
                $nomLiaison = $liaison->attributes->getNamedItem('nom')->textContent;

                $ar = [];
                $ar['_unique'] = ['nom'];
                $ar['_updateifexists'] = true;
                $ar['_nolog'] = true;

                $ar['nom']     = $nomLiaison;
                $ar['nskpl']   = rewriteToAscii($liaison->attributes->getNamedItem('nom')->textContent);
                $ar['secteur'] = $this->_recupLienVerObjet($this->tbSecteurs, "secteur", $nomSecteur);
                $ar['etat']    = $this->_recupLienVerObjet($this->tbEtats, "code", $liaison->attributes->getNamedItem('etat')->textContent);
                $ar['ent_id']  = $ent_id;
                $ar['station'] = $ar['ent_id']? $this->_recupLienVerObjet($this->tbStation, "ent_id", $ar['ent_id']) : null;
                $ar['PUBLISH'] = 1;

                $ret = $this->fixedProcInput($this->tbliaisons, $ar);
            } // foreach
            $secteurNum++;
        } // foreach secteurs

        //insertion des variables en base grace au tableau précédement construit
        foreach ($valeurPourUpdate as $update) {
            \Seolan\Core\Logs::critical(__METHOD__." piste nom={$update[1]} secteur={$update[3]} secteuroid=".$this->_recupLienVerObjet($this->tbSecteurs, "secteur", $update[3]));
            $ar = [];
            $ar['_unique'] = ['nom', 'secteur', 'PUBLISH'];
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
            //$ar['LANG_DATA'] = TZR_DEFAULT_LANG;
            
            //on recupère le module dans le quel on doit faire l'insertion
            $ret = $this->fixedProcInput($this->tbpistes, $ar);
        } // foreach

        return true;
    } // _insertPistes

    /**
     * inserre les secteurs si ils n'existe pas,
     * on ne vérifi pas si les secteurs inssérés xistent deja
     *
     *
     * @param type $listeDesSecteurs liste de tout les secteur récupérer dans le xml
     */
    protected function _insertSecteur($listeDesSecteurs = null) {
        if (empty($this->tbSecteurs))
          return;

        if (count($listeDesSecteurs)) {
          $Id_station = $listeDesSecteurs{0}->getAttribute('ent_id');
          \Seolan\Core\Logs::critical(__METHOD__." idStation =$Id_station");
          if (!empty($Id_station) && !empty($this->tbStation))
            $stationOid = $this->_recupLienVerObjet($this->tbStation, "ent_id", $Id_station);
          else
	          $stationOid = $listeDesSecteurs{0}->parentNode->parentNode->getAttribute('nom');
	        \Seolan\Core\Logs::critical(__METHOD__." oidStation =$stationOid");
          if (!empty($stationOid))
            getDB()->execute("UPDATE {$this->tbSecteurs} set PUBLISH=2 where station='$stationOid' ");
        }
        foreach ($listeDesSecteurs as $secteur) {
            $ar = [];
            $ar['_unique'] = ['secteur', 'station', 'PUBLISH'];
            $ar['_updateifexists'] = true;
            $ar['_nolog'] = true;

            $ar['secteur'] = $secteur->getAttribute('nom');
            $ar['Id_station'] = $secteur->getAttribute('ent_id');
            $ar['id_secteur'] = $secteur->getAttribute('sct_id');
            $ar['station'] = $stationOid;
            $ar['PUBLISH'] = 1;
            //$ar['LANG_DATA'] = TZR_DEFAULT_LANG;

            $ret = $this->fixedProcInput($this->tbSecteurs, $ar);
        } // foreach
    } // function _insertSecteur

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
        if (empty($table))
          return null;
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

        \Seolan\Core\Logs::critical(__METHOD__." sql=$sql");
        \Seolan\Core\Logs::critical(__METHOD__." sql cond=".print_s($sqlCondValues,3,true,false));
        $idObjet = getDB()->select($sql, $sqlCondValues);
        if (!$idObjet)
            throw new Exception("l'objet voulue n'a pas été trouvé");

        while ($KOID = $idObjet->fetch()) {
            $ret = $KOID['KOID'];
        }
        //initialisation de la valeur de retour
        isset($ret) ? $ret = $ret : $ret = false;

        return $ret;
    } // _recupLienVerObjet

    /**
     * vérifie si le nom d'une piste exist deja pour une piste, via une requette
     * sql directement sur la base, le nom de la base estt récupérer grace à:
     * $this->runInit(); et est strocké dans $this->tbpistes
     *
     * @param type $name représente le nom de la piste voulue
     * @return boolean retourne KOID si le nom existe deja false si nom
     */
    protected function _verificationExistancePiste($nom) {
        $sql = "select KOID from " . $this->tbpistes . " where nom=?";
        $nomPiste = getDB()->select($sql, [$nom]);

        if (!$nomPiste)
            throw new Exception('select pour vérifier le nom de la piste non aboutie');

        $pistes = $nomPiste->fetchAll();

        return (count($pistes)) ? $pistes[0]['KOID'] : false;
    } // protected function _verificationExistancePiste

    /**
     * Chargement des pistes en base a partir des pistes fournies par le xml
     * si le code erreur 777 est retourné cela signifie que le secteur n'a pas
     * été trouvé, on renvoie donc une fois avec l'insertion des secteurs
     *
     * @param type $ar variaple propre à la console
     */
    public function chargementDesPistes($ar = NULL) {
        $this->runInit();
        \Seolan\Core\Logs::critical("chargementDesPistes");
        try {
            $buffer = null;
            \Seolan\Core\Logs::critical(__METHOD__." call _insertPistes");
            if ($this->_insertPistes($buffer,true)) {
              \Seolan\Core\Shell::setNextData('message', " fin de la mise à jour des Pistes ");
            } else {
              \Seolan\Core\Logs::critical(__METHOD__ . " Erreur de mise à jour des Pistes ");
            }
        } catch (Exception $ex) {
            \Seolan\Core\Shell::setNextData('message',$ex->getMessage());
        }
        \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    } // public function chargementDesPistes

    
    /**
     * recupère les date d'ouverture de la sation pour lete hiver, ainsi
     * que le nom et l'id de la satation
     * et renvois sous forme de tableau pour le proc_insert de station
     *
     * @param type $periode DOMNode contenant la periode
     * @return type le tableau correctement formater pour l'insert
     */
    protected function _recuperationStationOuvertureFermeture($stations) {
        $stationEtDate = array();
        foreach ($stations as $station) {
//ent_id représente l'id de la station
            $idStation = $station->getAttribute('ent_id');
            \Seolan\Core\Logs::critical(__METHOD__." idStation =$idStation");
            if (!isset($Id_station) || $Id_station=='')
              return $stationEtDate;
            
            $stationEtDate[$idStation] = $station->getAttribute('nom');
            $periodes = $station->getElementsByTagName("PERIODE");
            foreach ($periodes as $periode) {
                $eteOuHiver = $periode->getAttribute('prd_id'); // 1=ete 2=hiver
                $dateOuverture = $periode->getAttribute('debut_en');
                $dateFermeture = $periode->getAttribute('fin_en');
                if ($eteOuHiver == 1) {
                    $stationEtDate["ouverture_ete"][$idStation] = date("Y-m-d", $this->frtotime($dateOuverture));
                    $stationEtDate["fermeture_ete"][$idStation] = date("Y-m-d", $this->frtotime($dateFermeture));
                } else {
                    $stationEtDate["ouverture_hiver"][$idStation] = date("Y-m-d", $this->frtotime($dateOuverture));
                    $stationEtDate["fermeture_hiver"][$idStation] = date("Y-m-d", $this->frtotime($dateFermeture));
                }
            }
        }
        return $stationEtDate;
    } // _recuperationStationOuvertureFermeture

    protected function frtotime($date) {
        $tabDate = explode("/", $date);
        return mktime(0, 0, 0, $tabDate[1], $tabDate[0], $tabDate[2]);
    } // frtotime

    /**
     * insertion des stations à partir du fichier xml
     *
     * @param type $ar variable seolan
     */
    protected function _insertStations($ar = null) {
        $url = $this->url;
        $tag = "INDICES"; // tag du noeud xml que l'on veut charger
        $doc = new \DOMDocument();
        $ret = $doc->load($url);
        if (!$ret)
            throw new Exception('erreur chargement du xml');
        $listeDesIndices = $doc->getElementsByTagName($tag);
        $tag = "STATION";
        $stationOuvertureFermeture = $this->_recuperationStationOuvertureFermeture($doc->getElementsByTagName($tag));
        $this->_insertionInformationStation($listeDesIndices, $ar, $stationOuvertureFermeture);
        $this->_insertPistesStation($listeDesIndices, $ar);
    } // _insertStations

    /**
     * mise à jour des stations à partir du fichier xml
     *
     * @param type $ar variable seolan
     */
    protected function _updateStations(&$buffer=null, $forceReload=false) {
        $ar = null;
        if (!$buffer) {
          $msg =  __METHOD__ . ': Flux OK';
          $buffer = $this->getXml($msg);
          if (!$buffer && $forceReload) {
            $buffer = $this->getCacheXml();
            if ($buffer) setSessionVar('message', $msg . " Forced Reload");
          }
        }
        if (empty($buffer)) {
          return $msg;
        }
        $tag = "INDICES"; // tag du noeud xml que l'on veut charger
        $doc = new \DOMDocument();
        $ret = $doc->loadXML($buffer);
        if (!$ret) return $msg;
        $listeDesIndices = $doc->getElementsByTagName($tag);
        $tag = "STATION";
        $stationOuvertureFermeture = $this->_recuperationStationOuvertureFermeture($doc->getElementsByTagName($tag));
        $this->_updateInformationStation($listeDesIndices, $ar, $stationOuvertureFermeture);
        $this->_updatePistesStation($listeDesIndices, $ar, $stationOuvertureFermeture);
        return $msg;
    } // _updateStations

    /**
     * preparation du tableau $ar servant à insérer les données,
     * pour la table station
     *
     *
     * @param type $listeDesIndices listes des données, délimité par la balise indices
     * @param type $ar varaible propre à seolan
     * @param type $stationOuvertureFermeture Description tableau regroupant les horaires d'ouvertures de la station
     */
    /**
     * preparation du tableau $ar servant à insérer les données,
     * pour la table station
     *
     *
     * @param type $listeDesIndices listes des données, délimité par la balise indices
     * @param type $ar varaible propre à seolan
     * @param type $stationOuvertureFermeture Description tableau regroupant les horaires d'ouvertures de la station
     */
    protected function _insertionInformationStation($listeDesIndices, $ar, $stationOuvertureFermeture) {
        $commentaireEnAnglais = null;
        foreach ($listeDesIndices as $indices) {
            //$nomIndice = $indices->getAttribute('nom'); l'indice retourne plusieur nom de station
            $idStation = $indices->getAttribute('ent_id');
            \Seolan\Core\Logs::critical(__METHOD__." idStation =$idStation");
            if (!isset($idStation) || $idStation=='')
              return;
	          
            $tags = array("SKIABILITE", "AMBIANCE", "RETOUR_SKI",
                "ETAT_ROUTE", "ETAT_CHAUSSEE", "PRATIQUE_ACTIVITES",
                "SKI_NUIT", "SKI_AURORE", "STATION_OUVERTURE");
            foreach ($tags as $tag) {
                $attribut = "val";
                if ($tag == "SKI_NUIT" || $tag == "SKI_AURORE")
                    $attribut = "etat";
                else
                    $attribut = "val";
                $balise = $indices->getElementsByTagName($tag)->item(0);
                if($balise instanceof \DOMElement) {
                    $attribut = $this->_recupLienVerObjet($this->tbReference, "code", $balise->getAttribute($attribut), $tag);
                    $attribut == false ? $ar[$tag] = '' : $ar[$tag] = $attribut;
                }
            } // foreach
            //recuperation du commentaire fr(autre langue disponible)
            $langues = $indices->getElementsByTagName("LANGUE");
            foreach ($langues as $langue) {
                if ($langue->getAttribute('val') == "fr") {
                    $ar["Commentaire"] = $langue->nodeValue;
                } // if
                if ($langue->getAttribute('val') == "en") {
                    $commentaireEnAnglais = $langue->nodeValue;
                } // if
            } // foreach
            $KM_SKATING = $indices->getElementsByTagName("KM_SKATING")->item(0);
            if($KM_SKATING instanceof \DOMElement)
                $ar["KM_SKATING"] = $KM_SKATING->getAttribute('ouvert');

            
            $ar['nom'] = $stationOuvertureFermeture[$idStation];
            $ar['ent_id'] = $idStation;
            $ar['ouverture_ete'] = $stationOuvertureFermeture["ouverture_ete"][$idStation];
            $ar['fermeture_ete'] = $stationOuvertureFermeture["fermeture_ete"][$idStation];
            $ar['ouverture_hiver'] = $stationOuvertureFermeture["ouverture_hiver"][$idStation];
            $ar['fermeture_hiver'] = $stationOuvertureFermeture["fermeture_hiver"][$idStation];
            //on recupère le module dans le quel on doit faire l'insertion
            $mod = \Seolan\Core\Module\Module::objectFactory($this->Station);
            $oidStation = getDB()->fetchOne("select KOID from {$this->tbStation} where ent_id=? order by UPD desc limit 1", array($idStation));
            if (!$oidStation) {
              $ret = $mod->procInsert($ar);
              $ar['oid'] = $ret['oid'];
            } else {
              $ar['oid'] = $oidStation;
              $ret = $mod->procEdit($ar);
            }
            // on récupère la valeur de la langue courante
            $lang_save = \Seolan\Core\Shell::getLangData();
            //on initialise à la langue souhaitée
            $_REQUEST['LANG_DATA'] = "GB";
            //initialise la console à la langue passé
            $foo = \Seolan\Core\Shell::getLangData(NULL, true);
            //sauvegarde en la langue voulue
            $ar["Commentaire"] = $commentaireEnAnglais;
            $ar['LANG_DATA'] = $_REQUEST['LANG_DATA'];
            $mod->procEdit($ar);
            //on récupère la langue de départ
            $_REQUEST['LANG_DATA'] = $lang_save;
            //on initialise la console avec
            $foo = \Seolan\Core\Shell::getLangData(NULL, true);
        } // foreach
    } // _insertionInformationStation

    /**
     * mise a jour des donnees de la table station
     *
     *
     * @param type $listeDesIndices listes des données, délimité par la balise indices
     * @param type $ar varaible propre à seolan
     * @param type $stationOuvertureFermeture Description tableau regroupant les horaires d'ouvertures de la station
     */
    protected function _updateInformationStation($listeDesIndices, $ar, $stationOuvertureFermeture) {
        $mod = \Seolan\Core\Module\Module::objectFactory($this->Station);
        $commentaireEnAnglais = null;
        foreach ($listeDesIndices as $indices) {
            //$nomIndice = $indices->getAttribute('nom'); l'indice retourne plusieur nom de station
            $idStation = $indices->getAttribute('ent_id');
            \Seolan\Core\Logs::critical(__METHOD__." idStation =$idStation");
            if (!isset($idStation) || $idStation=='')
              return;
            
            $oidStation = getDB()->fetchOne("select KOID from {$this->tbStation} where ent_id=? order by UPD desc limit 1", array($idStation));
            if (!$oidStation) {
              $ar['ent_id'] = $idStation;
              $ret = $mod->procInsert($ar);
              $ar['oid'] = $ret['oid'];
            } else {
              $ar['oid'] = $oidStation;
            }
            //if ($this->_verificationExistanceStation($idStation)) {
                $tags = array("SKIABILITE", "AMBIANCE", "RETOUR_SKI",
                    "ETAT_ROUTE", "ETAT_CHAUSSEE", "PRATIQUE_ACTIVITES",
                    "SKI_NUIT", "SKI_AURORE", "STATION_OUVERTURE");
                foreach ($tags as $tag) {
                    $attribut = "val";
                    if ($tag == "SKI_NUIT" || $tag == "SKI_AURORE")
                        $attribut = "etat";
                    else
                        $attribut = "val";
                    $balise = $indices->getElementsByTagName($tag)->item(0);
                    if($balise !== null) {
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
                $KM_SKATING = $indices->getElementsByTagName("KM_SKATING")->item(0);
                if($KM_SKATING !== null)
                    $ar["KM_SKATING"] = $KM_SKATING->getAttribute('ouvert');

                $ar['ouverture_ete'] = $stationOuvertureFermeture["ouverture_ete"][$idStation];
                $ar['fermeture_ete'] = $stationOuvertureFermeture["fermeture_ete"][$idStation];
                $ar['ouverture_hiver'] = $stationOuvertureFermeture["ouverture_hiver"][$idStation];
                $ar['fermeture_hiver'] = $stationOuvertureFermeture["fermeture_hiver"][$idStation];
                //on recupère le module dans le quel on doit faire l'insertion
                
                //if ($oidStation && $oidStation->rowCount() >= 1) {
                    //while ($oid = $oidStation->fetch()) {
                        $ar['oid'] = $oidStation; //$oid['KOID'];
                        $ret = $mod->procEdit($ar);


                        // on récupère la valeur de la langue courante
                        $lang_save = \Seolan\Core\Shell::getLangData();
                        //on initialise à la langue souhaitée
                        $_REQUEST['LANG_DATA'] = "GB";
                        //initialise la console à la langue passé
                        $foo = \Seolan\Core\Shell::getLangData(NULL, true);
                        //sauvegarde en la langue voulue
                        $ar["Commentaire"] = $commentaireEnAnglais;
                        $ar['LANG_DATA'] = $_REQUEST['LANG_DATA'];
                        $mod->procEdit($ar);
                        //on récupère la langue de départ
                        $_REQUEST['LANG_DATA'] = $lang_save;
                        //on initialise la console avec
                        $foo = \Seolan\Core\Shell::getLangData(NULL, true);
                    //}
                //}
            //}
        } // foreach
    } // _updateInformationStation
    /**
     * recuperation de toutes les balises et de leurs attributs dans le tableau $tab
     *
     *
     * @param type $listeDesIndices liste de tout les indices récupérés dans le xml
     * @param type $ar variable de séolan
     */
    protected function _updatePistesStation($listeDesIndices = null, $ar = null) {
        $mod = \Seolan\Core\Module\Module::objectFactory($this->StationPistes);
        $tab = array();
        $sousBalises = array();
        $tags = array("SKI_ALPIN", "SKI_ALPIN_VERTES", "SKI_ALPIN_BLEUES", "SKI_ALPIN_ROUGES", "SKI_ALPIN_NOIRES",
            "SKI_NORDIQUE", "SKI_NORDIQUE_VERTES", "SKI_NORDIQUE_BLEUES", "SKI_NORDIQUE_ROUGES", "SKI_NORDIQUE_NOIRES",
            "DH_VERT", "DH_BLEU", "DH_ROUGE", "DH_NOIR", "DH_JAUNE", "DH_ND", "XC_VERT", "XC_BLEU", "XC_ROUGE", "XC_NOIR", "XC_JAUNE", "XC_ND",
            "REMONTEES", "FUNI", "TB", "TC", "TPH", "TS", "TSD", "TK", "FNT", "TR", "TLC", "ASC", "TSDB", "TMX", "TRAIN",
            "PIETONS", "RAQUETTES", "LUGE", "SNOWPARK", "LIGNE_SNOWPARK", "BOARDERCROSS");
        foreach ($listeDesIndices as $indices) {
            //appel la fct getAttribute sur l'obj $indices avec ent_id en parametres qui nous retourne l'id de la station
            $idStation = $indices->getAttribute('ent_id');
            \Seolan\Core\Logs::critical(__METHOD__." idStation =$idStation");
            if (isset($idStation) && $idStation!='')
              $oidStation = getDB()->fetchOne("select KOID from {$this->tbStation} where ent_id=? order by UPD desc limit 1", array($idStation));
            else
              $oidStation = null;
            
            if (!$oidStation) {
              $msg = "Station id=$idStation introuvable - abort";
              setSessionVar('message', $msg);
              \Seolan\Core\Logs::critical(__METHOD__ . $msg);
              return false;
            }
            foreach ($tags as $tag) {
                $balises = $indices->getElementsByTagName($tag)->item(0);
                if($balises instanceof \DOMElement) {
                    if ($tag == "SNOWPARK" || $tag == "LIGNE_SNOWPARK" || $tag == "BOARDERCROSS") {
                        $sousBalises[$tag] = $indices->getElementsByTagName($tag);
                    }
                    $tab[$tag] = array(
                        $idStation,
                        $balises->getAttribute('total'),
                        $balises->getAttribute('total_periode'),
                        $balises->getAttribute('total_periode_hpf'),
                        $balises->getAttribute('ouvertes_previsions'),
                        $balises->getAttribute('previsions'),
                        $balises->getAttribute('fermees'),
                        $balises->getAttribute('lng_total'),
                        $balises->getAttribute('lng_ouverts'),
                        $balises->getAttribute('lng_ouvertes_previsions'),
                        $balises->getAttribute('lng_periode'),
                        $balises->getAttribute('lng_periode_hpf'),
                        $balises->getAttribute('lng_previsions'),
                        $balises->getAttribute('lng_fermees'),
                        $tag,
                        $balises->getAttribute('ouvertes'),
                    );
                }
            }
        }
        //insertion des variables en base grace au tableau précédement construit
        foreach ($tab as $tag => $update) {
            $ar['total'] = $update[1];
            $ar['total_periode'] = $update[2];
            $ar['total_periode_hpf'] = $update[3];
            $ar['ouvertes_previsions'] = $update[4];
            $ar['previsions'] = $update[5];
            $ar['fermees'] = $update[6];
            $ar['ouvertes'] = $update[15];
            $ar['lng_total'] = $update[7];
            $ar['lng_ouverts'] = $update[8];
            $ar['lng_ouvertes_previsions'] = $update[9];
            $ar['lng_periode'] = $update[10];
            $ar['lng_periode_hpf'] = $update[11];
            $ar['lng_previsions'] = $update[12];
            $ar['lng_fermees'] = $update[13];
            $ar['Station'] = $oidStation;
            $ar['libelle'] = $tag; //$update[14];
            //$this->_recupLienVerObjet($this->tbStation, "ent_id", $update[0]);
            //$update[14];
            //
            //on recupère le module dans lequel on doit faire l'insertion
            $oidItem = getDB()->fetchOne("select distinct KOID from " . $this->tbStationPistes . " where libelle=? and Station=? order by UPD desc limit 1", array($tag, $oidStation));
            if (!$oidItem) {
              $ar['PUBLISH'] = 1;
              $ret = $mod->procInsert($ar);
              $ar['oid'] = $ret['oid'];
            } else {
              $ar['oid'] = $oidItem;
              $ret = $mod->procEdit($ar);
            }
            $this->_updateSousBalise($sousBalises[$tag], $oidStation, $ar['oid']);
            
        } // foreach
        

    } // _updatePistesStation

    /**
     * recuperation de toutes les balises et de leurs attributs dans le tableau $tab
     *
     *
     * @param type $listeDesIndices liste de tout les indices récupérés dans le xml
     * @param type $ar variable de séolan
     */
    protected function _insertPistesStation($listeDesIndices = null, $ar = null) {
        $tab = array();
        $sousBalises = array();
	$oidStations = array();
        $tags = array("SKI_ALPIN", "SKI_ALPIN_VERTES", "SKI_ALPIN_BLEUES", "SKI_ALPIN_ROUGES", "SKI_ALPIN_NOIRES",
            "SKI_NORDIQUE", "SKI_NORDIQUE_VERTES", "SKI_NORDIQUE_BLEUES", "SKI_NORDIQUE_ROUGES", "SKI_NORDIQUE_NOIRES",
            "DH_VERT", "DH_BLEU", "DH_ROUGE", "DH_NOIR", "DH_JAUNE", "DH_ND", "XC_VERT", "XC_BLEU", "XC_ROUGE", "XC_NOIR", "XC_JAUNE", "XC_ND",
            "REMONTEES", "FUNI", "TB", "TC", "TPH", "TS", "TSD", "TK", "FNT", "TR", "TLC", "ASC", "TSDB", "TMX", "TRAIN",
            "PIETONS", "RAQUETTES", "LUGE", "SNOWPARK", "LIGNE_SNOWPARK", "BOARDERCROSS");
        foreach ($listeDesIndices as $indices) {
            //appel la fct getAttribute sur l'obj $indices avec ent_id en parametres qui nous retourne l'id de la station
            $idStation = $indices->getAttribute('ent_id');
            \Seolan\Core\Logs::critical(__METHOD__." idStation =$idStation");
            if (!empty($idStation) && !empty($this->tbStation))
	           $oidStation = $this->_recupLienVerObjet($this->tbStation, "ent_id", $idStation);
	          else
	           $oidStation = null;
            foreach ($tags as $tag) {
                $balises = $indices->getElementsByTagName($tag)->item(0);
                if($balises instanceof \DOMElement) {
                    if ($tag == "SNOWPARK" || $tag == "LIGNE_SNOWPARK" || $tag == "BOARDERCROSS") {
                        $sousBalises[] = $indices->getElementsByTagName($tag);
			$oidStations[] = $oidStation;
                    }
                    $tab[] = array(
                        $idStation,
                        $balises->getAttribute('total'),
                        $balises->getAttribute('total_periode'),
                        $balises->getAttribute('total_periode_hpf'),
                        $balises->getAttribute('ouvertes_previsions'),
                        $balises->getAttribute('previsions'),
                        $balises->getAttribute('fermees'),
                        $balises->getAttribute('lng_total'),
                        $balises->getAttribute('lng_ouverts'),
                        $balises->getAttribute('lng_ouvertes_previsions'),
                        $balises->getAttribute('lng_periode'),
                        $balises->getAttribute('lng_periode_hpf'),
                        $balises->getAttribute('lng_previsions'),
                        $balises->getAttribute('lng_fermees'),
                        $tag,
                        $balises->getAttribute('ouvertes'),
			$oidStation,
                    );
                } // if instanceof DOMElement
            }
        }
        //insertion des variables en base grace au tableau précédement construit
        foreach ($tab as $update) {
            $ar = [];
            $ar['_unique'] = ['libelle'];
            $ar['_updateifexists'] = true;
            $ar['_nolog'] = true;

            $ar['total'] = $update[1];
            $ar['total_periode'] = $update[2];
            $ar['total_periode_hpf'] = $update[3];
            $ar['ouvertes_previsions'] = $update[4];
            $ar['previsions'] = $update[5];
            $ar['fermees'] = $update[6];
            $ar['ouvertes'] = $update[6];
            $ar['lng_total'] = $update[7];
            $ar['lng_ouverts'] = $update[8];
            $ar['lng_ouvertes_previsions'] = $update[9];
            $ar['lng_periode'] = $update[10];
            $ar['lng_periode_hpf'] = $update[11];
            $ar['lng_previsions'] = $update[12];
            $ar['lng_fermees'] = $update[13];
            $ar['Station'] = $update[16];
            $ar['libelle'] = $update[14];
            //$ar['LANG_DATA'] = TZR_DEFAULT_LANG;

            //on recupère le module dans lequel on doit faire l'insertion
            $ret = $this->fixedProcInput($this->tbStationPistes, $ar);
        } // foreach

        for ($i = 0; $i < count($sousBalises); $i++) {
	    $this->_insertSousBalise($sousBalises[$i], $oidStations[$i]);
	} // for
    }

    /**
     * recuperation des balises modules (sous balises de /indices/snowpark) et de leurs attributs dans le tableau $sousBalises
     * BOARDERCROSS est une balise parente autant q'une sous balise : on est kéni !!
     *
     * @param type $listeDesSousBalise liste de toutes les sous balises de SNOWPARK, LIGNE_SNOWPARK et BOARDERCROSS récupérés dans le xml
     * @param type $ar variable de séolan
     */
    protected function _insertSousBalise($listeDesSousBalise = null, $oidStation) {
        $sousBalises = array();
        $tags = array("BIGAIR", "BOX", "CHILLZONE", "HALFPIPE", "HIP", "AIRBAG", "KICKER",
            "QUATERPIPE", "RAIL", "STEPUP", "WATERSLIDE", "WHOOPS", "BOARDERCROSS", "VIDEOZONE", "SPEEDZONE");
        foreach ($listeDesSousBalise as $sousBalise) {
            foreach ($tags as $tag) {
                if ($sousBalise->hasChildNodes()) {
                    $balises = $sousBalise->getElementsByTagName($tag)->item(0);
                    $sousBalises[] = array(
                        $listeDesSousBalise->item(0)->nodeName,
                        $balises->getAttribute('total'),
                        $balises->getAttribute('total_periode'),
                        $balises->getAttribute('total_periode_hpf'),
                        $balises->getAttribute('ouvertes_previsions'),
                        $balises->getAttribute('ouvertes'),
                        $balises->getAttribute('previsions'),
                        $balises->getAttribute('fermees'),
                        $tag,
                    );
                } // if
            } // foreach
        } // foreach

        //insertion des variables en base grace au tableau précédement construit
        foreach ($sousBalises as $update) {
            $ar = [];
            $ar['_unique'] = ['libelle', 'piste_park'];
            $ar['_updateifexists'] = true;
            $ar['_nolog'] = true;

            $ar['total'] = $update[1];
            $ar['total_periode'] = $update[2];
            $ar['total_periode_hpf'] = $update[3];
            $ar['ouvertes_previsions'] = $update[4];
            $ar['ouvertes'] = $update[5];
            $ar['previsions'] = $update[6];
            $ar['fermees'] = $update[7];
            $ar['piste_park'] = $this->_recupLienVerObjet($this->tbStationPistes, "libelle", $update[0]);
            $ar['station'] = $oidStation;
            $ar['libelle'] = $update[8];

            $ret = $this->fixedProcInput($this->tbModPistes, $ar);
        } // foreach
    } // _insertSousBalise

    /**
     * recuperation des balises modules (sous balises de /indices/snowpark) et de leurs attributs dans le tableau $sousBalises
     * BOARDERCROSS est une balise parente autant q'une sous balise : on est kéni !!
     *
     * @param type $listeDesSousBalise liste de toutes les sous balises de SNOWPARK, LIGNE_SNOWPARK et BOARDERCROSS récupérés dans le xml
     * @param type $ar variable de séolan
     */
    protected function _updateSousBalise($listeDesSousBalise=null, $oidStation=null, $oidPistePark=null) {
        $mod = \Seolan\Core\Module\Module::objectFactory($this->modulePistes);
        $sousBalises = array();
        $tags = array("BIGAIR", "BOX", "CHILLZONE", "HALFPIPE", "HIP", "AIRBAG", "KICKER",
            "QUATERPIPE", "RAIL", "STEPUP", "WATERSLIDE", "WHOOPS", "BOARDERCROSS", "VIDEOZONE", "SPEEDZONE");
        foreach ($listeDesSousBalise as $sousBalise) {
            foreach ($tags as $tag) {
                if ($sousBalise->hasChildNodes()) {
                    $balises = $sousBalise->getElementsByTagName($tag)->item(0);
                    $sousBalises[] = array(
                        $listeDesSousBalise->item(0)->nodeName,
                        $balises->getAttribute('total'),
                        $balises->getAttribute('total_periode'),
                        $balises->getAttribute('total_periode_hpf'),
                        $balises->getAttribute('ouvertes_previsions'),
                        $balises->getAttribute('ouvertes'),
                        $balises->getAttribute('previsions'),
                        $balises->getAttribute('fermees'),
                        $tag,
                    );
                }
            }
        }
        //insertion des variables en base grace au tableau précédement construit
        foreach ($sousBalises as $update) {
            $ar['total'] = $update[1];
            $ar['total_periode'] = $update[2];
            $ar['total_periode_hpf'] = $update[3];
            $ar['ouvertes_previsions'] = $update[4];
            $ar['ouvertes'] = $update[5];
            $ar['previsions'] = $update[6];
            $ar['fermees'] = $update[7];
            $ar['station'] = $oidStation;
            $ar['piste_park'] = $oidPistePark;
            
            //$piste_park = $this->_recupLienVerObjet($this->tbStationPistes, "libelle", $update[0]);
            //$update[8];
            //on recupère le module dans lequel on doit faire l'insertion
            $oidItem = getDB()->fetchOne("select KOID from " . $this->tbModPistes . " where libelle=? AND piste_park=? And station=? order by UPD desc limit 1", array($update[8], $oidPistePark, $oidStation));
            if (!$oidItem) {
              $ar['libelle'] = $update[8];
              $ar['PUBLISH'] = 1;
              $ret = $mod->procInsert($ar);
              $ar['oid'] = $ret['oid'];
            } else {
              $ar['oid'] = $oidItem;
              $ret = $mod->procEdit($ar);
            }
        }
    } // _updateSousBalise

    /**
     * Update des station deja en base à partir du xml
     * @param type $ar variable de seolan
     */
    public function inLineLoadStations($ar = NULL) {
        //$this->runInit();
        try {
            $buffer = null;
            if ($this->_updateStations($buffer,true)) {
              \Seolan\Core\Logs::critical(__METHOD__ . " Fin de la mise à jour des stations et recap des équipements associés ");
            } else {
              \Seolan\Core\Logs::critical(__METHOD__ . " Erreur de mise à jour des stations et recap des équipements associés ");
            }
        } catch (Exception $ex) {
            \Seolan\Core\Logs::critical(__METHOD__ . ' ' . $ex->getMessage());
        }
        \Seolan\Core\Shell::setNext(\Seolan\Core\Shell::get_back_url());
    } // inlineLoadStations

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
        if (is_array($ar['cond']['station']) &&  count($ar['cond']['station']))
          $cond['Station'] = $ar['cond']['station'];
        elseif (is_array($ar['station']) && count($ar['station']))
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
            'cond' => [ 'secteur' => ['=', $publishedSecteur], 'PUBLISH' => ['=', 1] ],
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('nom', 'etat', 'secteur',  'niveau'),//'type',, 'station'
            'options' => [
              'niveau' => ['target_fields' => ['libelle', 'stylesheet', 'ordre']],
              'station' => ['target_fields' => ['nom']]
            ],
            'order' => 'secteur[secteur], niveau[ordre], nom',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $remontees = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbrems)->browse([
            'cond' => [ 'secteur' => ['=', $publishedSecteur], 'PUBLISH' => ['=', 1] ],
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('nom', 'etat', 'secteur', 'type'),
            'order' => 'secteur[secteur]',
            '_mode' => 'both',
            'pagesize' => -1
        ]);
        
        if (!empty($this->tbStationPistes)) {
          $cond['libelle'] = ['=', ['SKI_ALPIN','REMONTEES']];
          $recap  = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbStationPistes)->browse([
              'cond' => $cond,
              'tplentry' => TZR_RETURN_DATA,
              'selectedfields' => array('libelle', 'ouvertes_previsions', 'total', 'total_periode'),
              'order' => 'FIELD(libelle, \'SKI_ALPIN\',\'REMONTEES\')',
              '_mode' => 'both',
              'pagesize' => -1
          ]);
        }
        if (!empty($this->tbliaisons)) {
          $liaisons = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbliaisons)->browse([
              'cond' => [ 'secteur' => ['=', $publishedSecteur] ],
              'tplentry' => TZR_RETURN_DATA,
              'selectedfields' => ['nom', 'nskpl', 'secteur', 'etat', 'ent_id', 'station'],
              'order' => 'secteur[secteur]',
              '_mode' => 'both',
              'pagesize' => -1,
          ]);
        }

        $legenderemontees = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbReomnteesTypes)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet'),
            'order' => 'libelle',
            '_mode' => 'both',
            'pagesize' => -1
        ]);
        $legendelvl = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbNiveaux)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet', 'style'),
            'order' => 'ordre',
            '_mode' => 'both',
            'pagesize' => -1
        ]);

        $legendeetat = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->tbEtats)->browse([
            'tplentry' => TZR_RETURN_DATA,
            'selectedfields' => array('libelle', 'stylesheet', 'style'),
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

        return [
            'recapOn' => $recapOn,
            'smenuOn' => $smenuOn,
            
            'pistes' => $pistes,
            'remontees' => $remontees,
            'liaisons' => $liaisons,
            'recap' => $recap,
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

  /**
    * Chargement des stations en base à partir des station présente dans le xml
    *
    *
    * @param type $ar variable de seolan
    */
    public function chargementStations($ar = NULL) {

        $this->runInit();
        try {
            $ret = $this->_insertStations($ar);
            $ret['message'] = $ret['message'] . " fin du chargement des stations, vérifier les données chargées avant utilisation";
            $_REQUEST['message'] = $ret['message'];
        } catch (Exception $ex) {
            var_dump($ex->getMessage());
        }
    } // chargementStations

}

