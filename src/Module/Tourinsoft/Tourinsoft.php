<?php
namespace Seolan\Module\Tourinsoft;

use Seolan\Core\Labels;
use Seolan\Core\Module\Action;
use Seolan\Core\Module\Module;
use Seolan\Core\Param;
use Seolan\Core\Shell;
use Seolan\Library\Upgrades;

class Tourinsoft extends Module {
  public $clientId;
  public $syndicId;
  public $tblPrefix;
  public $hashmd5;

  function secGroups($function, $group=NULL) {
    $g = array();
    $g['accueil'] = array('ro', 'rw', 'rwv', 'admin');
    $g['fetchStructure'] = array('ro', 'rw', 'rwv', 'admin');
    $g['fetchDatas'] = array('ro', 'rw', 'rwv', 'admin');
    $g['cronImport'] = array('admin');

    if(isset($g[$function])) {
      if(!empty($group))
        return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  public function initOptions() {
    parent::initOptions();
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"clientid"), 'clientId', 'text', array('compulsory'=>true));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"syndicid"), 'syndicId', 'text', array('compulsory'=>true,'rows'=>5,'cols'=>40));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"tblprefix"), 'tblPrefix', 'text', array('compulsory'=>true));
    $this->_options->setOpt(Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"hashmd5"), 'hashmd5', 'text', array('rows'=>5,'cols'=>40));
  }

  public function getMainAction() {
    return $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'moid='.$this->_moid.'&function=accueil&template=Core/Module.infos.html&tplentry=br';
  }

  public function accueil($ar) {
    $tplentry = 'br';
    $ret = "Accueil";
    Shell::toScreen1($tplentry,$ret);
  }

  public function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my, $alfunction);
    $myclass=get_class($this);
    $moid=$this->_moid;

    if ($this->secure('','fetchStructure')) {
      $o1 = new Action($this, 'fetchStructure', Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"fetchstructure"), 'class='.$myclass.'&moid='.$moid.'&_function=fetchStructure&template=Core/Module.infos.html&tplentry=br', 'more');
      $o1->homepageable = true;
      $o1->quicklinkable = false;
      $o1->menuable = true;
      $o1->group = 'edit';
      $my['fetchStructure'] = $o1;
    }

    if ($this->secure('','fetchDatas')) {
      $o1 = new Action($this, 'fetchDatas', Labels::getTextSysLabel('Seolan_Module_Tourinsoft_Tourinsoft',"fetchdatas"), 'class='.$myclass.'&moid='.$moid.'&_function=fetchDatas&template=Core/Module.infos.html&tplentry=br', 'more');
      $o1->homepageable = true;
      $o1->quicklinkable = false;
      $o1->menuable = true;
      $o1->group = 'edit';
      $my['fetchDatas'] = $o1;
    }

    if($this->interactive){
      $o1=new Action($this,'info',$this->getLabel(), '&moid='.$this->_moid.'&_function=accueil&template=Core/Module.infos.html&tplentry=br');
      $my['stack'][]=$o1;
    }
  }

  function delete($ar) {
    $p = new Param($ar);
    $withtable=$p->get('withtable');
    if($withtable) {
      $syndicIds = explode("\n", $this->syndicId);
      foreach($syndicIds as $syndicId) {
        $syndicId = trim($syndicId);
        $EntityList = $this->getDatas($syndicId);
        foreach($EntityList['value'] as $Entity) {
          $sqlName = $this->getSqlEntityName($Entity['url']);
          $moids = array_keys(Module::modulesUsingTable($sqlName));
          if(count($moids)) {
            $module = Module::objectFactory($moids[0]);
            $module->delete($ar);
          }
        }
      }
    }

    return parent::delete($ar);
  }

  public function cronImport(&$scheduler, &$o, &$more) {
    $this->fetchStructure();

    if($more->reimport) {
      $syndicIds = explode("\n", $this->syndicId);
      foreach($syndicIds as $syndicId) {
        $syndicId = trim($syndicId);
        $EntityList = $this->getDatas($syndicId);
        foreach($EntityList['value'] as $Entity) {
          $sqlName = $this->getSqlEntityName($Entity['url']);
          if(getDB()->fetchExists("select 1 from DICT where FIELD='hashmd5' and DTAB=?", array($sqlName))) {
            getDB()->execute("update $sqlName set hashmd5=''");
          }
        }
      }
    }

    $this->fetchDatas();

    $alerts = getSessionVar('alerts');
    $message = implode("\n", $alerts["info"]);
    if($alerts["danger"]) {
      $message .= "\n\nErreurs : \n";
      $message .= implode("\n", $alerts["danger"]);
    }

    $scheduler->setStatusJob($o->KOID, 'finished', $message);
  }

  public function fetchStructure() {
    // Il est possible d'avoir plusieur flux séparés par des saut de ligne
    $syndicIds = explode("\n", $this->syndicId);
    $hashmd5 = explode("\n", $this->hashmd5);

    // Correspondance des types tourinsoft/seolan
    $fieldTypes = array(
      'Edm.Guid' => '\Seolan\Field\ShortText\ShortText',
      'Edm.String' => '\Seolan\Field\Text\Text',
      'Edm.Int32' => '\Seolan\Field\ShortText\ShortText',
      'Edm.Int64' => '\Seolan\Field\ShortText\ShortText',
      'Edm.Double' => '\Seolan\Field\ShortText\ShortText',
      'Edm.Decimal' => '\Seolan\Field\Real\Real',
      'Edm.Boolean' => '\Seolan\Field\Boolean\Boolean',
      'Edm.Time' => '\Seolan\Field\Time\Time',
      'Edm.DateTime' => '\Seolan\Field\DateTime\DateTime'
    );

    $tablesToCreate = [];
    $uniqueFields = [];

    foreach($syndicIds as $key => $syndicId) {
      $syndicId = trim($syndicId);
      $metaDatas = $this->getDatas($syndicId, '$metadata');
      if(!$metaDatas) continue;

      // On regarde si le md5 des metadata a changé depuis le dernier import, si il n'a pas changé pas besoin de récupérer la structure
      $hash = md5(json_encode($metaDatas));
      if($hash == $hashmd5[$key]) {
        Shell::alert("Structure inchangée", "info");
        continue;
      }
      $hashmd5[$key] = $hash;

      // On récupère le nom de chaque entité, ce qui donne au final une array de la forme array("EntityType" => "Name")
      // Le Name étant utilisé dans les url des json, et le EntityType étant utilisé dans le contenu des json, c'est
      // pas tout le temps les mêmes, sinon ca serait trop simple
      $EntitySet = $metaDatas['DataServices']['Schema'][1]['EntityContainer']['EntitySet'];
      $keys = array_map(function($a) {
        return substr(strrchr($a['@attributes']['EntityType'], '.'), 1);
      }, $EntitySet);
      $values = array_map(function($a) {
        return $a['@attributes']['Name'];
      }, $EntitySet);
      $EntityNames = array_combine($keys, $values);

      // On prépare l'ajout de chaque table et des champs correspondant
      foreach($metaDatas['DataServices']['Schema'][0]['EntityType'] as $Entity) {
        $EntityName = $EntityNames[$Entity['@attributes']['Name']];
        $fields = [];
        $isThesaurus = false;
        foreach($Entity['Property'] as $i => $Property) {
          $FieldType = $Property['@attributes']['Type'];
          $MaxLength = $Property['@attributes']['MaxLength'];
          $FieldName = $this->getSqlFieldName($Property['@attributes']['Name']);

          if($FieldName == 'ThesID') {
            $isThesaurus = true;
          }

          $forder = $i + 1;
          $target = '%';
          $fcount = 60;
          $published = 0;
          $options = [];

          if($FieldName == $Entity['Key']['PropertyRef']['@attributes']['Name']) {
            $published = 1;
            $options['readonly'] = 1;
          }

          // On détermine le type du champ à ajouter
          $ftype = @$fieldTypes[$FieldType];
          if(!$ftype && strpos($FieldType, 'ListeComplexType') !== false) {
            $ftype = '\Seolan\Field\Link\Link';
            $target = $this->getSqlEntityName('Thesaurus');
            $options['filter'] = "FieldName='$FieldName'";
          }
          elseif(!$ftype && strpos($FieldType, 'MediaComplexType') !== false) {
            $ftype = '\Seolan\Field\File\File';
          }
          elseif($ftype == '\Seolan\Field\Text\Text' && $MaxLength && $MaxLength != 'Max') {
            $ftype = '\Seolan\Field\ShortText\ShortText';
            $fcount = $MaxLength;
          }

          // On ajoute une exception pour les pictos du thesaurus qui sont envoyés sous forme de texte, on récupère l'image à la place
          if($EntityName == 'Thesaurus' && $FieldName == 'ThesPicto') {
            $ftype = '\Seolan\Field\Image\Image';
          }

          // Si on a pas réussi à déterminer le type on l'ignore
          if(!$ftype) {
            Shell::alert("Champ $FieldName de l'entité $EntityName ignoré car son type $FieldType est inconnu");
            continue;
          }

          $fields[$FieldName] = [
            'field' => $FieldName,
            'label' => array(TZR_DEFAULT_LANG => $FieldName),
            'ftype' => $ftype,
            'fcount' => $fcount,
            'forder' => $forder,
            'compulsory' => 0,
            'queryable' => 1,
            'browsable' => 1,
            'translatable' => 0,
            'multi' => 0,
            'published' => $published,
            'target' => $target,
            'options' => $options,
          ];
        }

        // On ajoute la table uniquement si ce n'est une entité "Thesaurus" car
        // pour les thésaurus on met des liens vers objet à la place de sous tables
        if($EntityName && count($fields) && (!$isThesaurus || $EntityName == 'Thesaurus')) {
          $tableLabel = strpos($EntityName, '_') !== false ? substr(strrchr($EntityName, '_'), 1) : $EntityName;
          if(!$tablesToCreate[$EntityName]) {
            $tablesToCreate[$EntityName] = [
              "tableLabel" => "Tourinsoft - " . ucfirst(strtolower($tableLabel)),
              "tableName" => $this->getSqlEntityName($EntityName),
              "subModules" => [],
              "fields" => $fields
            ];
          }
          else {
            $tablesToCreate[$EntityName]['fields'] = array_merge($tablesToCreate[$EntityName]['fields'], $fields);
          }
        }
      }


      // On ajoute les liens entre les tables (sous modules, liens vers le thésaurus)
      foreach($metaDatas['DataServices']['Schema'][0]['Association'] as $Association) {
        if($Association['ReferentialConstraint']) {
          if(strpos($Association['End'][0]['@attributes']['Role'], 'Source') !== false) {
            $EntitySource = $EntityNames[substr(strrchr($Association['End'][0]['@attributes']['Type'], '.'), 1)];
            $EntityTarget = $EntityNames[substr(strrchr($Association['End'][1]['@attributes']['Type'], '.'), 1)];
          }
          else {
            $EntitySource = $EntityNames[substr(strrchr($Association['End'][1]['@attributes']['Type'], '.'), 1)];
            $EntityTarget = $EntityNames[substr(strrchr($Association['End'][0]['@attributes']['Type'], '.'), 1)];
          }
          $linkField = $Association['ReferentialConstraint']['Dependent']['PropertyRef']['@attributes']['Name'];
          if($linkField && $EntitySource && $EntityTarget) {

            // Si la table "EntitySource" existe c'est que c'est un sous module
            if($tablesToCreate[$EntitySource]['fields']) {
              foreach($tablesToCreate[$EntitySource]['fields'] as $i => $field) {
                if($field['field'] == $linkField) {
                  $tablesToCreate[$EntitySource]['fields'][$i]['ftype'] = '\Seolan\Field\Link\Link';
                  $tablesToCreate[$EntitySource]['fields'][$i]['target'] = $this->getSqlEntityName($EntityTarget);
                  $tablesToCreate[$EntitySource]['fields'][$i]['compulsory'] = 1;
                  if(!in_array($EntitySource, $tablesToCreate[$EntityTarget]['subModules'])) {
                    $tablesToCreate[$EntityTarget]['subModules'][] = $EntitySource;
                  }
                }
              }
            }
            // Sinon c'est un lien vers le thésaurus multivalué
            elseif($tablesToCreate[$EntityTarget]) {
              $FieldName = strpos($EntitySource, '_') !== false ? substr(strrchr($EntitySource, '_'), 1) : $EntitySource;
              $FieldName = $this->getSqlFieldName($FieldName);
              $tablesToCreate[$EntityTarget]['fields'][$FieldName . '_' . $linkField] = [
                'field' => $FieldName . '_' . $linkField,
                'label' => array(TZR_DEFAULT_LANG => $FieldName),
                'ftype' => '\Seolan\Field\Link\Link',
                'fcount' => 0,
                'forder' => count($tablesToCreate[$EntityTarget]['fields']) + 1,
                'compulsory' => 0,
                'queryable' => 1,
                'browsable' => 1,
                'translatable' => 0,
                'multi' => 1,
                'published' => 0,
                'target' => $this->getSqlEntityName('Thesaurus'),
                'options' => ["filter" => "FieldName='$FieldName'"],
              ];
            }

          }
        }
      }

      // On récupère les champs uniques qui identifient une ligne pour rajouter des index dessus
      foreach($metaDatas['DataServices']['Schema'][0]['EntityType'] as $Entity) {
        $EntityName = $this->getSqlEntityName($EntityNames[$Entity['@attributes']['Name']]);
        $uniqueFields[$EntityName] = $this->getSqlFieldName($Entity['Key']['PropertyRef']['@attributes']['Name']);
      }
    }

    if(count($tablesToCreate)) {
      // Maintenant qu'on a correctement rempli tablesToCreate on crée d'abord les tables/modules
      foreach($tablesToCreate as $EntityName => $table) {
        Upgrades::addTable($table['tableName'], $table['tableLabel']);
        $tablesToCreate[$EntityName]['moid'] = Upgrades::addModule(
          $table['tableName'],
          str_replace("Tourinsoft - ", '', $table['tableLabel']),
          $this->group,
          null,
          'Seolan\Module\Table\Wizard'
        );
      }

      // On crée les champs (après avoir créé tous les modules pour éviter d'avoir des erreurs sur les targets des champs liens)
      foreach($tablesToCreate as $table) {

        // On ajoute le champ "hashmd5" dans chaque table
        $table['fields'][] = [
          'field' => "hashmd5",
          'label' => array(TZR_DEFAULT_LANG => "hashmd5"),
          'ftype' => "\Seolan\Field\ShortText\ShortText",
          'fcount' => "32",
          'forder' => count($table['fields']) + 1,
          'compulsory' => 0,
          'queryable' => 0,
          'browsable' => 0,
          'translatable' => 0,
          'multi' => 0,
          'published' => 0,
          'target' => '',
          'options' => [],
        ];

        Upgrades::addFields($table['tableName'], $table['fields']);

        // On ajoute l'index
        $EntityName = $table['tableName'];
        $uniqueField = $uniqueFields[$EntityName];
        if(!getMetaKeys($EntityName, $uniqueField)) {
          getDB()->execute("CREATE INDEX $uniqueField ON $EntityName ($uniqueField(40))");
        }
      }

      // Puis on définit les sous modules une fois que les champs sont ok
      foreach($tablesToCreate as $table) {
        Upgrades::editModuleOptions($table['moid'], 'submodmax', count($table['subModules']));
        foreach($table['subModules'] as $i => $subMod) {
          Upgrades::editModuleOptions($table['moid'], 'ssmod' . ($i + 1), $tablesToCreate[$subMod]['moid']);
        }
      }

      Upgrades::editModuleOptions($this->_moid, 'hashmd5', implode("\n", $hashmd5));
    }

    return true;
  }

  function fetchDatas() {
    $infos = [];
    // On conserve les oid des target des liens dans une array
    // pour éviter de faire trop de requêtes pour rien
    $targetOids = [];

    // Il est possible d'avoir plusieur flux séparés par des saut de ligne
    $syndicIds = explode("\n", $this->syndicId);
    foreach($syndicIds as $syndicId) {
      $syndicId = trim($syndicId);
      $linksToInsert = [];

      $metaDatas = $this->getDatas($syndicId, '$metadata');

      // On récupère le nom de chaque entité, ce qui donne au final une array de la forme array("EntityType" => "Name")
      // Le Name étant utilisé dans les url des json, et le EntityType étant utilisé dans le contenu des json, c'est
      // pas tout le temps les mêmes, sinon ca serait trop simple
      $EntitySet = $metaDatas['DataServices']['Schema'][1]['EntityContainer']['EntitySet'];
      $keys = array_map(function($a) { return substr(strrchr($a['@attributes']['EntityType'], '.'), 1); }, $EntitySet);
      $values = array_map(function($a) { return $a['@attributes']['Name']; }, $EntitySet);
      $EntityNames = array_combine($keys, $values);

      // On récupère les champs uniques pour identifier si la donnée est déjà présente en base et pour les liens vers objet
      $uniqueFields = [];
      foreach($metaDatas['DataServices']['Schema'][0]['EntityType'] as $Entity) {
        $EntityName = $this->getSqlEntityName($EntityNames[$Entity['@attributes']['Name']]);
        if($EntityNames[$Entity['@attributes']['Name']] == 'Thesaurus') {
          $uniqueFields[$EntityName] = "ThesID";
        }
        else {
          $uniqueFields[$EntityName] = $this->getSqlFieldName($Entity['Key']['PropertyRef']['@attributes']['Name']);
        }
      }

      // On récupère d'abord tout ce qui n'est pas un lien, pour éviter de tomber sur
      // un lien vers une donnée qui n'a pas encore été importée
      $EntityList = $this->getDatas($syndicId);
      foreach($EntityList['value'] as $Entity) {
        $EntityName = $Entity['url'];
        $sqlName = $this->getSqlEntityName($EntityName);
        $uniqueField = $uniqueFields[$sqlName];
        $moids = array_keys(Module::modulesUsingTable($sqlName));
        if(count($moids)) {
          $module = Module::objectFactory($moids[0]);
          // Si le champ IsUsed existe dans la table on rajoute un filtre dessus qui vaut vrai pour pas
          // récupérer des données pour rien (par ex pour pas récupérer toutes les communes de france)
          $filter = fieldExists($sqlName, 'IsUsed') ? '$filter=IsUsed%20eq%20true' : '';
          $datas = $this->getDatas($syndicId, $EntityName, $filter);
          $inserted = [];
          $modified = [];
          $nochange = [];
          foreach($datas['value'] as $data) {
            // On récupère la valeur du champ qui identifie la ligne, qui peut avoir
            // un _ à la fin de son nom sql si c'est un mot clé réservé de la
            // console (voir fonction getSqlFieldName), donc on l'enlève avec le substr
            $uniqueFieldVal = $data[$uniqueField];
            if(!$uniqueFieldVal) {
              $uniqueFieldVal = $data[substr($uniqueField, 0, -1)];
            }

            $hashmd5 = md5(json_encode($data));
            $existingLine = getDB()->fetchRow("select KOID, hashmd5 from $sqlName where $uniqueField = ?", array($uniqueFieldVal));
            if($existingLine['hashmd5'] && $existingLine['hashmd5'] == $hashmd5) {
              $nochange[] = $existingLine['KOID'];
              continue;
            }

            // on remplit le tableau $toInsert avec les données qu'on va insérer
            $toInsert = [];
            foreach($data as $FieldName => $FieldVal) {
              $FieldName = $this->getSqlFieldName($FieldName);

              if(is_array($FieldVal) && array_key_exists('ThesID', $FieldVal)) {
                // Lien vers le thésaurus
                $FieldVal = $FieldVal['ThesID'];
              }
              elseif(is_array($FieldVal) && array_key_exists('Url', $FieldVal)) {
                // Champ type image
                $copyright = $FieldVal['Credit'] ? ' © '.$FieldVal['Credit'] : '';
                $title = $FieldVal['Titre'];
                $toInsert[$FieldName.'_title'] = trim($title.$copyright);
                $FieldVal = $FieldVal['Url'];
              }
              elseif($FieldVal && is_a($module->xset->desc[$FieldName], '\Seolan\Field\Time\Time')) {
                // Champ type Time, format PThhHmmM, il faut récupérer que les hh mm et compléter avec des 0
                $split = explode('H', substr($FieldVal, 2, -1));
                $FieldVal = sprintf('%02d:%02d:00', $split[0] ?: 0, $split[1] ?: 0);
              }
              elseif($FieldVal && $EntityName == 'Thesaurus' && $FieldName == 'ThesPicto') {
                // Pour un picto ils envoient l'url relative donc on ajoute le domaine pour avoir l'url complète
                $FieldVal = 'http://' . $this->clientId . '.media.tourinsoft.eu/upload/' . $FieldVal;
              }

              // Si c'est un lien vers objet, on le traite plus tard
              if(is_a($module->xset->desc[$FieldName], '\Seolan\Field\Link\Link')) {
                $source = $this->getSqlEntityName($EntityName);
                $linksToInsert[$source][$uniqueFieldVal][$FieldName] = $FieldVal;
              }
              else {
                $toInsert[$FieldName] = $FieldVal;
              }
            }

            $toInsert['hashmd5'] = $hashmd5;

            // On insère si la donnée n'existe pas, on modifie sinon
            if(!$existingLine['KOID']) {
              $toInsert['newoid'] = substr($sqlName.':'.md5($uniqueFieldVal), 0, 40);
              $ret = $module->procInsert($toInsert);
              $inserted[] = $ret['oid'];
            }
            else {
              $toInsert['oid'] = $existingLine['KOID'];
              $module->procEdit($toInsert);
              $modified[] = $toInsert['oid'];
            }
          }

          if($infos[$sqlName]) {
            $infos[$sqlName]['inserted'] = array_merge($infos[$sqlName]['inserted'], $inserted);
            $infos[$sqlName]['modified'] = array_merge($infos[$sqlName]['modified'], $modified);
            $infos[$sqlName]['nochange'] = array_merge($infos[$sqlName]['nochange'], $nochange);
          }
          else {
            $infos[$sqlName] = array(
              'inserted' => $inserted,
              'modified' => $modified,
              'nochange' => $nochange
            );
          }
        }
        else {
          // Si le module n'existe pas c'est qu'on est dans le cas d'un lien multivalué vers le thésaurus
          $linkField = strpos($EntityName, '_') !== false ? substr(strrchr($EntityName, '_'), 1) : $EntityName;
          $linkField = $this->getSqlFieldName($linkField);
          $target = $this->getSqlEntityName('Thesaurus');
          $targetId = 'ThesID';

          $fieldDict = getDB()->fetchRow('select FIELD, DTAB from DICT where FIELD like "' . $linkField . '_%" and TARGET=?', array($target));
          $linkField = $fieldDict['FIELD'];
          $source = $fieldDict['DTAB'];
          $sourceId = substr(strrchr($linkField, "_"), 1);

          $datas = $this->getDatas($syndicId, $EntityName);
          foreach($datas['value'] as $data) {
            $sourceIdVal = $data[$sourceId];
            $targetIdVal = $data[$targetId];

            if($source && $sourceIdVal && $linkField && $targetIdVal) {
              $linksToInsert[$source][$sourceIdVal][$linkField][] = $targetIdVal;
            }
          }
        }
      }

      // Puis on modifie les liens vers objets
      foreach($linksToInsert as $source => $dataEntity) {
        $sourceId = $uniqueFields[$source];
        $moids = array_keys(Module::modulesUsingTable($source));
        $module = Module::objectFactory($moids[0]);
        foreach($dataEntity as $sourceIdVal => $dataField) {
          foreach($dataField as $linkField => $targetIdVal) {
            if(!$targetIdVal) continue;

            $target = $module->xset->desc[$linkField]->target;
            if($target == $this->getSqlEntityName('Thesaurus')) {
              $targetId = 'ThesID';
            }
            else {
              $targetId = $uniqueFields[$target];
            }

            if(!$target || !$targetId) continue;

            // On récupère les KOID des target
            $targetIdValOid = '';
            if(is_array($targetIdVal)) {
              // Lien multivalué
              foreach($targetIdVal as $targetVal) {
                if(!$targetOids[$target][$targetVal]) {
                  $targetOids[$target][$targetVal] = getDB()->fetchOne("select KOID from $target where $targetId=? limit 1", array($targetVal));
                }
                $targetIdValOid .= $targetOids[$target][$targetVal] . '||';
              }
              if($targetIdValOid) {
                $targetIdValOid = "||$targetIdValOid";
              }
            }
            else {
              if(!$targetOids[$target][$targetIdVal]) {
                $targetOids[$target][$targetIdVal] = getDB()->fetchOne("select KOID from $target where $targetId=? limit 1", array($targetIdVal));
              }
              $targetIdValOid = $targetOids[$target][$targetIdVal];
            }

            if(!$targetIdValOid) continue;

            // On update la source avec les KOID des target
            getDB()->execute("update $source set $linkField=? where $sourceId=?", array($targetIdValOid, $sourceIdVal));
          }
        }
      }
    }

    // Suppression des fiches plus dans les flux
    foreach($infos as $sqlName => $info) {
      $moids = array_keys(Module::modulesUsingTable($sqlName));
      $module = Module::objectFactory($moids[0]);
      $oids = implode("','", array_merge($info['inserted'], $info['modified'], $info['nochange']));
      $deleted = getDB()->fetchCol("select KOID from $sqlName where KOID not in ('$oids')");
      $module->del(array('oid' => $deleted));
      $infos[$sqlName]['deleted'] = $deleted;
    }

    // Message sous forme de table
    $message = "<table class='table table-condensed table-bordered'><thead><th>Table</th><th>Insérées</th><th>Modifiées</th><th>Supprimées</th></thead><tbody>\n";
    foreach($infos as $sqlName => $info) {
      $inserted = count($info['inserted']) < 20 ? implode(", ", $info['inserted']) : count($info['inserted']) . ' fiches';
      $modified = count($info['modified']) < 20 ? implode(", ", $info['modified']) : count($info['modified']) . ' fiches';
      $deleted = count($info['deleted']) < 20 ? implode(", ", $info['deleted']) : count($info['deleted']) . ' fiches';
      $message .= "<tr><td>$sqlName</td><td>$inserted</td><td>$modified</td><td>$deleted</td></tr>";
    }
    $message .= "</tbody></table>";
    Shell::alert($message, "info");

    return $infos;
  }

  // On ne garde que la fin des noms des entités si il y a un _, sinon ca fait planter
  // la console. Par ex, à la place de "DECIBELLESDATAOFFRESASSOCIEES_Typedeprestation"
  // on conserve "Typedeprestation"
  function getSqlEntityName($EntityName) {
    $tableLabel = $EntityName;
    $tableName = $this->tblPrefix . $tableLabel;
    if(strpos($EntityName, '_') !== false || strlen($tableName) > 30) {
      $tableLabel = substr(strrchr($EntityName, '_'), 1);
      $tableName = substr($this->tblPrefix . $tableLabel, 0, 30);
    }

    return $tableName;
  }

  function getSqlFieldName($FieldName) {
    // Noms de champs réservés par la console, si c'est le cas on rajoute un underscore
    while(isTZRKeyword($FieldName)) {
      $FieldName .= '_';
    }
    return $FieldName;
  }

  function getDatas($syndicId='', $type='', $filter='') {
    $clientId = $this->clientId;
    $syndicId = $syndicId ?: $this->syndicId;
    $url = "http://wcf.tourinsoft.com/Syndication/3.0/$clientId/$syndicId/$type";

    // Les metadata sont les seules à ne pas pouvoir être récupérées en json
    if($type != '$metadata') {
      $url .= '?$format=json&'.$filter;
    }

    $datas = file_get_contents($url);

    if(!$datas) {
      Shell::alert("Impossible de récupérer les données depuis $url");
      return false;
    }

    if($type == '$metadata') {
      // la fonction simplexml_load_string ne fonctionne pas si il y a des "edmx:" dans les tags xml donc on les enlève
      $datas = json_encode(simplexml_load_string(str_replace('edmx:', '', $datas)));
    }

    return json_decode($datas, true);
  }
}
