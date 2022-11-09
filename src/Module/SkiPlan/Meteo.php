<?php
namespace Seolan\Module\SkiPlan;
//
// outil pour recuperer les meteo
//
class Meteo {

//equivalent nom champ de la table nom de la balise xml
    private static $parmsFields1 = array(
        'vrisqa' => 'VALRISQUE',
        'lrisqa' => 'LIBRISQUE',
        'vrisqar' => 'RSQ_REEL',
        'tempe' => 'TEMPERATURE',
        'hneige' => 'NEIGE',
        'hcneige' => 'CUMUL',
        'fvent' => 'VENT',
        'dvent' => 'DIRECTION',
        'qneige' => 'QUALITE',
        'qneigid' => 'QLT_ID',
        'visib' => 'VISIBILITE',
        'leciel' => 'CIEL',
        'ecielid' => 'CIEL_ID',
        'ecielai' => 'CIEL_ID_APM',
        'lcneige' => 'DERNIERE_CHUTE',
        'RISQUE_ORAGE' => 'RISQUE_ORAGE',
        'QLT_ID' => 'QLT_ID',
        'DIRECTION' => 'DIRECTION',
        'VISIBILITE' => 'VISIBILITE',
    );
    public $datedeb = NULL;
    public $datefin = NULL;
    public $datemaj = NULL;
    public $type = NULL;
    public $lang = NULL;
    public $zone = NULL;
    public $skplid = NULL;
    public $altitude = NULL;
    public $station = NULL;
    public $datas = array();

    function __construct($ar) {
        $this->type = $ar['type'];
        $this->lang = $ar['lang'];
        if (isset($ar['zone']))
            $this->zone = $ar['zone'];
        if (isset($ar['altitude']))
            $this->altitude = $ar['altitude'];
        if(isset($ar['skplid']))
            $this->skplid = $ar['skplid'];
        $this->station = $ar['station'];
        // preparation des tables de correspondances avalanche et ciel
        $this->xmeteo = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($ar['tbMeteo']);
        $this->loadTableAvalanche($this->xmeteo);
        $this->loadTableCiel($this->xmeteo);
    }

    public function setDDeb($val) {
        $this->datedeb = $val;
    } // setDDeb

    public function setDFin($val) {
        $this->datefin = $val;
    } // setDFin

    public function setDMaj($val) {
        $this->datemaj = $val;
    } // setDMaj

    // table des etas avalanches
    // les correspondances se trouvent dans la table
    //
    private function loadTableAvalanche(&$xset) {
        $fe = $xset->getField('vrisqa');
        $tbetats = $fe->get_target();
        $lang = \Seolan\Core\Shell::getLangData();
        $s1 = "select code, KOID from " . $tbetats . " where lang='$lang'";
        $r1 = getDB()->select($s1);
        if (!$r1) {
            \Seolan\Core\Logs::critical($this->classname, $this->classname . " erreur acces base $s1");
            die($this->classname . " erreur acces base $s1");
        }
        $l = array();
        $this->oidAvalanche = array();
        while ($l = $r1->fetch()) {
            $this->oidAvalanche[$l['code']] = $l['KOID'];
        }
    }

    // table des etas du ciel
    // les correspondances se trouvent dans la table
    //
    private function loadTableCiel(&$xset) {
        $fe = $xset->getField('ecielid');
        $tbetats = $fe->get_target();
        $lang = \Seolan\Core\Shell::getLangData();
        $s1 = "select code, KOID from " . $tbetats . " where lang='$lang'";
        $r1 = getDB()->select($s1);
        if (!$r1) {
            \Seolan\Core\Logs::critical($this->classname, $this->classname . " erreur acces base $s1");
            die($this->classname . " erreur acces base $s1");
        }
        $l = array();
        $this->oidCiel = array();
        while ($l = $r1->fetch()) {
            $this->oidCiel[$l['code']] = $l['KOID'];
        }
    }

    // faire un array
    public function toTzr() {
        $ar = array('typinf' => '', 'skplid' => '', 'ddeb' => '', 'dfin' => '', 'datmaj' => '', 'zone' => '', 'alti' => '', 'texte' => '', 'saison' => '');
        foreach (self::$parmsFields1 as $fn => $xfn) {
            $ar[$fn] = '';
        }
        if (isset($this->datedeb))
            $ar['ddeb'] = $this->datedeb;
        if (isset($this->datefin))
            $ar['dfin'] = $this->datefin;

        if(isset($this->skplid))
            $ar['skplid'] = $this->skplid;

        if ($this->zone != NULL) {
            $ar['alti'] = addslashes($this->altitude);
            $ar['zone'] = addslashes($this->zone);
        }
        if (isset($this->station)) {
            $ar['station'] = addslashes($this->station);
        }
        if (isset($this->datemaj)) {
            $ar['datmaj'] = $this->datemaj;
        }
        $ar['typinf'] = $this->type;
        if ($this->type == 'parametres') {
            foreach (self::$parmsFields1 as $fn => $xfn) {
                $ar[$fn] = addslashes($this->datas[$xfn]);
            }
        } else {
            foreach ($this->datas as $foo => $ligne) {
                $ar[$foo] .= ' ' . addslashes(trim($ligne));
            }
        }
        return $ar;
    }

}

