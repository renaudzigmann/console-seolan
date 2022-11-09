<?php
namespace Seolan\Module\MultiTable;
  use Seolan\Core\Param;
  use Seolan\Core\Shell;

  /**
   * xmodtable "avec variantes", ou "multi" tables
   * lien vers fiche parente (= 1 sous fiche gérée différement ?)
   * -> champ type, obligatoire par programme (voir insert)
   * -> datasources associées à chaque type
   * -> listes sont inchangées : sur datasource de base
   * -> edit : en fonction du type, ajouter les champs
   * -> display : idem edit
   * -> insert : saisir le type, puis proposer champs de base et champs du type
   * -> repair translation : lancer aussi pour les datasources des types
   * @note : le champ type est obligatoire
   * @note : le nom d'un champ de type ne peut pas être identique au nom d'un champ de base
   * -> delete : effacer les données associées au type
   * -> repercuter et dupliquer le type
   * -> archives : @todo à voir : remonter les informations des types
   * @note : types et sous types doivent avoir les mêms prop. de traduction
   * C'est forcé par programme (patch 
   * @todo droits sur les champs des types
   * @todo TESTER : dans les langues
   * - lg simple + trad-auto :ok
   * - lg mode trad + trad-auto :ok
   * @todo voir les checks, whatsnew du module qui doivent prendre en compte les types
   * @todo recherche : devrait permettre de recherche dans les sous types
   * @todo/à voir import : quel type, proposer un xsetall comportant les champs de tous les types
   */
class MultiTable extends \Seolan\Module\Table\Table{
  static protected $excludedFields = ['UPD', 'OWN', 'CREAD', 'OWN', 'PUBLISH'];
  // un desc comportant tous les champs 
  protected $alldescs, $allorddescs = null;
  // ens de chaînes, lien
  public $nbTypes = 3;
  public $typeField = null;
  //  public $typeLabel = null;
  // $typevalue$i, typedatasourcename$i
  // les datasources par valeur de clée
  protected $typeDescs = [];
  protected $typeDataSources = [];
  protected $typesors = [];
  protected $originalFieldsMap = [];
  static public $upgrades = [];
  
  function __construct($ar){
    parent::__construct($ar);
    $this->initTypes();
    $this->initDescs();
  }

  /**
   * browse
   * -> protection du champ type
   */
  function browse($ar){
    $ar['fieldssec'] = array($this->typeField=>'ro');
    return parent::browse($ar);
  }
  /**
   * procQuery
   * -> protection du champ type
   */
  function procQuery($ar){
    $ar['fieldssec'] = array($this->typeField=>'ro');
    return parent::procQuery($ar);
  }
  /**
   * delete
   * -> repercuter le delete
   * -> gérer cas mulitple del
   */
  function del($ar){
    
    $p = new \Seolan\Core\Param($ar, array());
    $oid = $p->get('oid');
    // comme on efface les types avant
    $oid=\Seolan\Core\Kernel::getSelectedOids($p,true,false);
    if(is_array($oid)){
      foreach($oid as $toid){
        $ar['oid']=$toid;
        $ar['_selectedok']=$ar['_selected']='';
        $this->del($ar);
      }
      return true;
    }
    // avant d'effacer !
    $typedesc = $this->getTypeDesc(array('oid'=>$oid));
    if (!empty($typedesc)){ // type connu
      $typeOid = $this->getTypeOid($typedesc, $oid);
      if (!empty($typeOid) && $this->objectExists($typedesc, $typeOid)){
	$resType = $typedesc->datasource->del(array('oid'=>$typeOid, '_options'=>array('local'=>true)));
      }
    }
    // la base 
    return parent::del($ar);
  }
  /**
   * duplication
   * -> dupliquer le type si il existe
   * -> valeur du champ + type
   */
  function procEditDup($ar){
    $p = new \Seolan\Core\Param($ar, array());
    $dupOid = $p->get('oid');
    
    $dd = $this->xset->rdisplay($dupOid);

    $resDup = parent::procEditDup($ar);

    $typedesc = $this->getTypeDesc(array('display'=>$dd));
    if (empty($typedesc) || !$resDup['duplicated']){
      return $resDup;
    }

    // Création des données du nouveau type si changement de type
    $oldType = $dd['o'.$this->typeField]->raw;
    $newType = $p->get($this->typeField);
    if($oldType && $newType && $oldType != $newType) {
      $newdd = $this->xset->rdisplay($resDup['oid']);
      $newtypedesc = $this->getTypeDesc(array('display'=>$newdd));
      $inputval = array(
        $newtypedesc->linkname => $resDup['oid'],
      );
      $newtypedesc->datasource->procInput($inputval);
      return $resDup;
    }

    // recherche des donnees du type et duplication
    $dupTypeOid = $this->getTypeOid($typedesc, $dupOid);
    if (!empty($dupTypeOid)){
      $inputval = [
        $typedesc->linkname => $resDup['oid'],
        'oid' => $dupTypeOid,
      ];
      if($typedesc->datasource && count($typedesc->datasource->orddesc)) {
        $fields = implode(', ', array_diff($typedesc->datasource->orddesc, ['UPD', $typedesc->linkname]));
        $table = $typedesc->datasource->getTable();
        if($fields) {
          $dupvalues = getDB()->fetchAll("select $fields from $table where KOID=? and LANG=?", array($dupTypeOid, TZR_DEFAULT_LANG));
          foreach($dupvalues[0] as $field => $dupvalue) {
            $inputval[$field] = $dupvalue;
          }
        }
      }
      
      $typedesc->datasource->procEditDup($inputval);
    }
    
    return $resDup;
    
  }
  /**
   * display
   * -> completer avec les champs du type si il existe
   */
  function display($ar=null){
    $p = new \Seolan\Core\Param($ar, array());
    $ar['tplentry'] = TZR_RETURN_DATA;
    
    $res = parent::display($ar);

    $typedesc = $this->getTypeDesc(array('display'=>$res));

    if (!empty($typedesc)){ // type connu
      $typeOid = $this->getTypeOid($typedesc, $res['oid']);
      // display / message incomplete object ?
      if (!empty($typeOid) && $this->objectExists($typedesc, $typeOid)){
	$resType = $typedesc->datasource->display(array('oid'=>$typeOid, 'tplentry'=>TZR_RETURN_DATA));
      } else {
	$resType = false;
	\Seolan\Core\Logs::critical(get_class($this), '::display display on incomplete object');
      }
      if ($resType){
	$this->mergeTypeFields($res, $resType, $typedesc);
      }
    }
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $res);

  }
  public function displayJSon($ar) {
    $json = parent::displayJSon($ar) ;
    $fieldAlias = \Seolan\Core\Json::getFieldAlias($this->_moid,$this->typeField,$this->base);
    $typeFieldValue = $json['attributes'][$fieldAlias];
    if (empty($typeFieldValue)) {
      if (strpos($json['relationships'][$fieldAlias]['data']->id, ':') === false) {
        $typeFieldValue = $json['relationships'][$fieldAlias]['data']->type.':'.$json['relationships'][$fieldAlias]['data']->id;
      } else {
        $typeFieldValue = $json['relationships'][$fieldAlias]['data']->id;
      }
      
      if ($typeFieldValue === ':') {
        $typeFieldValue = null;
      }
    }
    $oid = \Seolan\Core\Json::getFullOidMoid($this->_moid,$json['id']);
    if($typeFieldValue && $oid){
      $typedesc = $this->getTypeDesc([], $typeFieldValue);    
      if (!empty($typedesc)){ // type connu
        $typeOid = $this->getTypeOid($typedesc, $oid);
        // display / message incomplete object ?
        if (!empty($typeOid) && $this->objectExists($typedesc, $typeOid)){
          $except = ['UPD',$typedesc->linkname];
          $fields = $typedesc->datasource->getFieldsList(NULL,false,false,false,false,false,false,null,'AND',$except);

          $jsonType = $typedesc->datasource->getJSon(array('oid'=>$typeOid, 
                                                           'selectedfields'=>$fields,
                                                           'tplentry'=>TZR_RETURN_DATA));
          if ($jsonType){
            $json['attributes'] = array_merge_recursive ($json['attributes'],$jsonType['attributes']);
            $json['relationships'] = array_merge_recursive ($json['relationships'],$jsonType['relationships']);
          }
        }
      }
    }
    return $json;
  }
  /**
   * mise à jour
   * -> repercuter les mise à jour sur le type si defini
   */
  function procEdit($ar){
    $p=new \Seolan\Core\Param($ar,array());
    $oid = $p->get('oid');
    $resUpdate = parent::procEdit($ar);
    if (!is_array($oid)) {
      $typedesc = $this->getTypeDesc(array('oid'=>$resUpdate['oid']));
      if (!empty($typedesc)){ // type connu
        $typeOid = $this->getTypeOid($typedesc, $resUpdate['oid']);
        // a voir, recuperer les champs et valeurs à passer plus précisement ($resUpdate['inputs ?)
        if (!empty($typeOid) && $this->objectExists($typedesc, $typeOid)){
          $langs = $this->getAuthorizedLangs($p->get('_langs'), $oid, 'procEdit');
          $resType = $typedesc->datasource->procEdit(array_merge([$ar,$typedesc->linkname=>$resUpdate['oid'],
                                                                  '_langs'=>$langs,
                                                                  'oid'=>$typeOid,
                                                                  'tplentry'=>TZR_RETURN_DATA])
                                                    );
        } else {
          $resType = $typedesc->datasource->procInput(array_merge($ar,[$typedesc->linkname=>$resUpdate['oid'],
                                                                  'tplentry'=>TZR_RETURN_DATA])
                                                     );
        }
        // maj des champs systemes
        $this->synchronizeUpd($typedesc, $resUpdate['oid']);
      } else {
        \Seolan\Core\Shell::setNextData('message', 'unknown type '.$resUpdate['inputs'][$this->typeField]->raw);
      }
    }
    return $resUpdate;
  }
  /**
   * édition
   * -> protection du champ type
   * -> ajout des champs du type
   */
  function edit($ar){
    $p = new \Seolan\Core\Param($ar, array());
    // on ne peut pas pour le moment changer la variante en 'live'
    $ar['fieldssec'] = array($this->typeField=>'ro');
    $ar['tplentry'] = TZR_RETURN_DATA;
    $resEdit = parent::edit($ar);
    $resEdit = $this->editType($resEdit);
    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $resEdit);
  }
  /**
   * ajout des champs d'un type pour un formulaire d'édition
   */
  protected function editType($resEdit){
    $typedesc = $this->getTypeDesc(['edit'=>$resEdit]);
    if (!empty($typedesc)){
      $typeOid = $this->getTypeOid($typedesc, $resEdit['oid']);
      if (!empty($typeOid) && $this->objectExists($typedesc, $typeOid)){
	$resType = $typedesc->datasource->edit(['oid'=>$typeOid, 'tplentry'=>TZR_RETURN_DATA,'fmoid'=>$this->_moid]);
      } else {
	$resType = $typedesc->datasource->input(['tplentry'=>TZR_RETURN_DATA,'fmoid'=>$this->_moid]);
	$resType['newoid'] = $typeOid;
      }
      // fusionne les champs type / fiche principale
      $this->mergeTypeFields($resEdit, $resType, $typedesc);
    } else { // 
      \Seolan\Core\Logs::critical(__METHOD__,'invalid object type : '.$this->typeField->field.' '.$resEdit['o'+$this->typeField->field]->raw);
    }
    return $resEdit;
  }
  /**
   * 1 - restreindre la saisie au type et le rendre obligatoire
   * 2 - faire un formulaire avec le xset de base et celui du type
   * le champ type étant protégé
   */
  function insert($ar){
    $p = new \Seolan\Core\Param($ar, []);
    if($_REQUEST['function'] === 'editDup') {
      return parent::insert($ar);
    }
    $tplentry = $p->get('tplentry');
    if (!$p->is_set($this->typeField)){
      $this->xset->getField($this->typeField)->compulsory = true;
      $this->xset->getField($this->typeField)->fgroup = null;
      $ar['selectedfields']=[$this->typeField];
      \Seolan\Core\shell::toScreen2('','_function', 'insert');
      if ($tplentry != TZR_RETURN_DATA)
	\Seolan\Core\Shell::alert('Merci de préciser le type de fiche que vous souhaitez créer.', 'info');
      return parent::insert($ar);
    } else {
      $this->xset->getField($this->typeField)->compulsory = true;
      //$this->xset->getField($this->typeField)->readonly = true;
      //$this->xset->getField($this->typeField)->hidden = true;
      $typeValue = $p->get($this->typeField);
      $typedesc = $this->getTypeDesc([], $typeValue);
      if (empty($typedesc)){
	\Seolan\Core\Logs::critical(__METHOD__, "Invalid type $typeValue");
	if ($tplentry != TZR_RETURN_DATA)
	  \Seolan\Core\Shell::setNext('back');
	return;
      }
      \Seolan\Core\Shell::setNext('');
      \Seolan\Core\Shell::changeTemplate('Module/Table.new.html');
      $ar['options'][$this->typeField]['value']=$typeValue;
      $ar['tplentry'] = TZR_RETURN_DATA; 
      $resInsert = parent::insert($ar);
      // ajout formulaire d'insertion pour les données du type (intable du typeValue)
      $resInsert = $this->insertType($resInsert, $typedesc);
      // remplacementdu champ (ro, hidden pas satifaisants) 
      // dans 'o'..., fields_objects, et _groups
      $opt = [];
      $rotype = $this->xset->getField($this->typeField)->display($typeValue, $opt);
      $rotype->html.='<input type="hidden" value="'.htmlspecialchars($typeValue).'" name="'.$this->typeField.'">';
      $resInsert['o'.$this->typeField] = $rotype;
      foreach($resInsert['fields_object'] as $i=>$fv){
	if ($fv->field == $this->typeField){
	  $resInsert['fields_object'][$i] = &$rotype;
	  break;
	}
      }
      foreach($resInsert['_groups'] as $grp=>$fields){
	foreach($fields as $i=>$fv){
	  if ($fv->field == $this->typeField){
	    $resInsert['_groups'][$grp][$i] = &$rotype;
	    break 2;
	  }
	}
      }
      return \Seolan\Core\Shell::toScreen1($tplentry,$resInsert);
    }
  }
  /**
   * ajout des champs d'un type pour un formulaire d'insertion
   */
  protected function insertType($resInsert, $typedesc){
    $resType = $typedesc->datasource->input(['tplentry'=>TZR_RETURN_DATA,'fmoid'=>$this->_moid]);
    // fusionne les champs type / fiche principale
    $this->mergeTypeFields($resInsert, $resType, $typedesc);
    return $resInsert;
  }
  /**
   * ajout d'un fiche
   * -> vérifier que le type est bien renseigné
   * -> insertion de base et insertion des données du type
   * @note : il n'y a pas possibilité de chevauchement des noms de champs
   */
  function procInsert($ar){
    $p = new \Seolan\Core\Param($ar, []);
    
    $resInsert = parent::procInsert($ar);

    if (!empty($resInsert['oid'])){
      // création des données du type
      $typedesc = $this->getTypeDesc(array('oid'=>$resInsert['oid']));
      if (!empty($typedesc)){ // type connu
	 $typedesc->datasource->procInput(array_merge($ar,[$typedesc->linkname=>$resInsert['oid'],
                                                           'tplentry'=>TZR_RETURN_DATA])
        );
      } else {
	\Seolan\Core\Logs::critical(__METHOD__,"invalid line type {$resInsert['oid']}");
      }
    }

    return $resInsert;

  }
  /**
   * oid des données du type
   * -> premier oid correspondant
   */
  protected function getTypeOid(\stdClass $typedesc, string $oid=null){
    if (property_exists($typedesc,'datasource') && !isset($this->typesors[$oid])){
      $this->typesors[$oid] = getDb()->select('select KOID from '.$typedesc->datasource->getTable().' where '.$typedesc->linkname.'=?', 
					      [$oid])->fetch(\PDO::FETCH_COLUMN);
    }
    return $this->typesors[$oid];
  }
  /// champ type et sous types
  function initOptions(){
    parent::initOptions();
    // surcharge de certaines options
    $this->_options->delOpt('archive');
    $this->_options->setOpt(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Field_Field','archive'),
			    'archive',
			    'boolean',
			    array('read-only'=>1),
			    null,
			    \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','tracking')
			    );
    // options spécfiques : les variantes
    $group = 'Ensemble de fiches avec variantes';
    $this->_options->setOpt('Champ déterminant la variante',
			    'typeField',
			    'field',
			    array('table'=>$this->base,'compulsory'=>false),
			    null, 
			    $group);
    $this->_options->setOpt('Nombre de variantes à paraméter',
			    'nbTypes',
			    'text',
			    null,
			    null,
			    $group);
    $this->_options->set($this,'nbTypes',\Seolan\Core\Module\Module::$_mcache[$this->_moid]['MPARAM']['nbTypes']);
    for($i=1;$i<=$this->nbTypes;$i++) {
      $this->_options->setOpt('Valeur du champ pour la variante '.$i, 
			      'typevalue'.$i,
			      'text',NULL,
			      '',
			      $group);
      $this->_options->setOpt('Source de donnée pour la variante  '.$i, 
			      'typedatasourcename'.$i,
			      'table', 
			      array('emptyok'=>true),
			      '', 
			      $group);
      $this->_options->setOpt('Nom du champ lien pour la table de la variante '.$i, 
			      'linkname'.$i,
			      'text',NULL,
			      '',
			      $group);
    }
  
    //Ajout de l'import avancé en option
    $this->_options->setOpt('Activer l\'import avancé',
                            'advancedImport',
                            'boolean',NULL,
                            2,
                            $group);
    $this->_options->setOpt('Module historique d\'import',
                            'modHistoryAdvancedImport',
                            'module',NULL,
                            '',
                            $group);
  }
  /// Liste des tables utilisé par le module
  public function usedTables() {
    return array_merge(array($this->table), array_keys($this->typeDataSources));
  }
  /// Liste des boid utilisés par le module
  function usedBoids() {
    return array_merge(array($this->xset->getBoid()), array_values($this->typeDataSources));
  }
  /**
   *  initialisation des types et check de la configuraiton
   */
  protected function initTypes(){

    $translatable = $this->xset->getTranslatable();
    $autotranslate = $this->xset->getAutoTranslate();
    $toLog = $this->xset->toLog();

    for($i=1;$i<=$this->nbTypes;$i++) {
      $typevalue = 'typevalue'.$i;
      $dsname = 'typedatasourcename'.$i;
      $linkname = 'linkname'.$i;
      
      if (empty($this->$typevalue) || empty($this->$dsname)){
	continue;
      }
      $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->$dsname);
      if (empty($this->$linkname)){
	$links1= $ds->getXLinkDefs(NULL, $this->table);
	if(!empty($links1)) {
	  $this->$linkname=array_values($links1)[0];
	}
	if (empty($this->$linkname)){
	  \Seolan\Core\Logs::critical(__METHOD__, $i.' unable to find link from '.$ds->getTable());
	  continue;
	}
      } else {
	if (!$ds->fieldExists($this->$linkname)){
	  \Seolan\Core\Logs::critical(get_class($this), '::initTpes, '.$i.' field '.$this->linkname.' does not exits in '.$this->dsname);
	  continue;
	}
      }
      if ($ds->getTranslatable() != $translatable){
	\Seolan\Core\Logs::critical(__METHOD__, ' not coherent translation property '.$ds->getTable()." updated to $translatable");
	$this->correctTable($ds, $translatable, $autotranslate);
      }
      if ($ds->getTranslatable() && ($ds->getAutoTranslate() != $autotranslate)){
	\Seolan\Core\Logs::critical(__METHOD__, ' not coherent autotranslate property '.$ds->getTable()." updated to $autotranslate");
	$this->correctTable($ds, $translatable, $autotranslate);
      }
      if ($ds->toLog() != $toLog){
	\Seolan\Core\Logs::critical(__METHOD__, ' not coherent log property '.$ds->getTable().' update required ');
      }

      $this->typeDataSources[$this->$dsname] = $ds->getBoid();
      
      $this->typeDescs[$this->$typevalue] = (Object) array('datasource'=>$ds, 
							   'linkname'=>$this->$linkname,
							   'label'=>$ds->getLabel()
      );
    }
  }
  /**
   * définition de champ "d'origine"
   * -> retourne le field def du ds d'origine
   * -> ou le champ si c'est un champ de la table de base
   */
  protected function getPairField($field){
    if (!isset($this->originalFieldsMap[$field->field])
      && !$this->xset->fieldExists($field->field))
    throw new \Exception("Invalid field query : {$field->field}/{$field->_mttCloneFieldName} does not exists in {$field->table}");
    if ($this->xset->fieldExists($field->field))
      return $field;
    return $this->originalFieldsMap[$field->field];
  }
  /**
   * correction des prop. de traduction d'une table de type
   */
  protected function correctTable($ds, $translatable, $autotranslate){
    if (!$this->secure('','procEditProperties'))
      return;
    getDB()->execute('update BASEBASE set TRANSLATABLE=?, AUTO_TRANSLATE=? where BOID=?', [$translatable,$autotranslate,$ds->getBoid()]);
    \Seolan\Library\ProcessCache::deleteFromMemcached('datasource-'.$ds->getBoid());
    \Seolan\Core\Datasource\Datasource::clearCache();
  }
  //
  /**
   * fusion des champs des différents types dans les même listes
   * -> orddesc et desc 
   * -> champs des types : table_field
   */
  protected function initDescs(){
    $this->alldescs = $this->xset->desc;
    $this->allorddescs = $this->xset->orddesc;
    $general = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','general');
    foreach($this->typeDescs as $typeValue=>$typeDesc){
      foreach($typeDesc->datasource->orddesc as $fn){
	if (!in_array($fn, static::$excludedFields) && $fn != $typeDesc->linkname){
	  // dans les liens il faut le nom d'origine
	  if ($typeDesc->datasource->desc[$fn] instanceof \Seolan\Field\Link\Link)
	    $fd = \Seolan\Core\Field\Field::objectFactory2($typeDesc->datasource->desc[$fn]->table, $fn, '\Seolan\Module\MultiTable\Field\Link');
	  elseif($typeDesc->datasource->desc[$fn])
	    $fd = clone $typeDesc->datasource->desc[$fn];
	  else
            $fd = new \Seolan\Field\ShortText\ShortText();

	  // perfectible mais on a besoin du nom d'origine : voir Module/Table::addfilestoexport
	  $fd->_mttCloneFieldName = $fd->field; 
	  $tfn = $fd->field = $fd->table.'_'.$fd->field;
	  $fd->fgroup = $typeDesc->label.', '.(isset($fd->group)?$fd->fgroup:$general);
	  $this->alldescs[$tfn] = $fd;
	  $this->allorddescs[] = $tfn;
	  // correspondances original / clone
	  $this->originalFieldsMap[$tfn] = $typeDesc->datasource->desc[$fn];
	}
      }
    }
  }
  /**
   * export :
   * construction d'une select de jointure avec les champs de la table de base et les champs du type concerné
   * export std pour chaque type en forcant le dictionnaire (alldescs, allorddesc) pour être en phase avec le select
   */
  function export($ar=null){
    $p = new \Seolan\Core\Param($ar, ['_ajax'=>false, 'fname'=>'Export '.$this->getLabel().' '.date('YMDhis')]);

    $target = $p->get('_target');
    if ((!empty($target) && ($target != $this->_moid)) || $ar['nozip']) {
      return parent::export($ar);
    }

    $langs = $p->get('langs');
    $_ajax = $p->get('_ajax');
    $recordCount = $p->get('_recordcount');
    $selectedfields = $p->get('selectedfields');
    $optionsfields = $p->get('optionsfields');
    $select = $p->get('select');
    if (!isset($select) || empty($select)){
      $select = $this->getContextQuery($ar);
    }
    if (preg_match('/WHERE(.*)$/i', $select, $res)){
      $where = $res[1];
    } else {
      $where = '';
    }
    if ($p->is_set('order')){
      $orders = $p->get('order');
      if (!is_array($orders)){
	$orders = [$orders];
      } 
      foreach($orders as &$forder){
	$ofd = $this->alldescs[$forder];
	$forder = $ofd->field;
      }
    } else {
      $orders = ['UPD'];
    }
    // les champs de tri sont obligatoirement sélectionnés
    $selectedfields = array_merge($selectedfields, $orders);
    /* traitement des sélections de sous champs ('fn#subfn') */
    $decodedselectedfields = [];   // champ et tableau des sous champs
    foreach($selectedfields as $fk){
      if (strpos($fk, '#') !== false){
	list($fnpart, $subfnpart) = explode('#', $fk);
	$decodedselectedfields[$fnpart][] = $fk;
      } else {
	$decodedselectedfields[$fk][] = $fk;
      }
    }
    $selectedfields = array_keys($decodedselectedfields);

    // le checkorder field laisse passer des requetes fausses
    unset($_REQUEST['order']);

    // tables concernées et champs concernés 
    $selfieldstypes = [];
    $orderfields = [];
    foreach($this->alldescs as $fd){
      if (isset($decodedselectedfields[$fd->field])){
	if (!isset($selfieldstypes[$fd->table])){
	  $selfieldstypes[$fd->table]=[];
	}
	$selfieldstypes[$fd->table][] = $fd->field;
      }
      if (in_array($fd->field, $orders)){
	if (!isset($orders[$fd->table])){
	  $orderfields[$fd->table]=[];
	}
	// on ne . pas les champs de tri : 'as'
	$orderfields[$fd->table][] = $fd->field;
      }
    }

    // export des champs de la table principale jointe aux tables des <> types
    $qfields = [];
    $selects = [];
    $table = $this->xset->getTable();
    $commonBrowseSelectedfields = [];
    $commonBrowseOptionsfields = [];
    $browseSelectedfields = [];
    $browseOptionsfields = [];

    // champs table de base
    foreach($selfieldstypes[$table] as $fn){
      // sql 
      $qfields[] = $table.'.'.$fn.' as '.$fn ;
      // datasource : champs sous champs et options
      foreach($decodedselectedfields[$fn] as $rawfn){
	$commonBrowseSelectedfields[]=$rawfn;
	if(isset($optionsfields[$rawfn])){
	  $commonBrowseOptionsfields[$rawfn] = $optionsfields[$rawfn];
	}
      }
    }
    if (empty($qfields)){
      $qfields[] = $table.'.KOID';
    }
    if (!isset($orderfields[$table])){
      $orderfields[$table] = [];
    }

    //select des types selectionnés
    $selectedType = getDB()->fetchCol('SELECT DISTINCT '.$this->typeField.' FROM '.$table.' WHERE '.$where);

    $fieldsMultiTable = false;

    // champs tables des types
    foreach($this->typeDescs as $typevalue=>$typedesc){
      if(!in_array($typevalue,$selectedType))
        continue;
      $tqfields = [];
      $ttable = $typedesc->datasource->getTable();
      $browseSelectedfields[$typevalue] = array_merge($commonBrowseSelectedfields, []);
      $browseOptionsfields[$typevalue] = array_merge($commonBrowseOptionsfields, []);
      if (!isset($orderfields[$ttable])){
	$orderfields[$ttable] = [];
      }
      // filtre de type sur la table principale
      $wheretype = empty($where)?'':' AND '."{$this->table}.{$this->typeField}=".getDB()->quote($typevalue);
      // types pour lequels des champs sont selectionnés
      if (!empty($selfieldstypes[$ttable])){
        $fieldsMultiTable = true;
	foreach($selfieldstypes[$ttable] as $fn){
	  // sql
	  $tqfields[] =  str_replace($ttable.'_', $ttable.'.', $fn).' as '.$fn;
	  // datasource : champs sous champs et options des champs
	  foreach($decodedselectedfields[$fn] as $rawfn){
	    $browseSelectedfields[$typevalue][]=$rawfn;
	    if (isset($optionsfields[$rawfn]))
	      $browseOptionsfields[$typevalue][$rawfn] = $optionsfields[$rawfn];
	  }
	}
	if (empty($orderfields[$ttable]) && empty($orderfields[$table])){
	  $orderfields[$ttable] = [$selfieldstypes[$ttable][0]];
	}
	$qorder = implode(',', array_merge($orderfields[$table], $orderfields[$ttable]));
	// jointure à gauche pour avoir les lignes table principale même si donnée existe pour la langue dans le sous-type ? sinon INNER mais pas bon non pls
	$selects[$typevalue] = "SELECT $table.KOID, ".implode(',', array_merge($qfields, $tqfields))." FROM $table LEFT OUTER JOIN $ttable on $table.KOID=$ttable.{$typedesc->linkname} AND $table.LANG=$ttable.LANG WHERE $where $wheretype ORDER BY ".$qorder;
      } else {
        $qorder = implode(',', array_merge($orderfields[$table], $orderfields[$ttable]));
	// jointure à gauche pour avoir les lignes table principale même si donnée existe pour la langue dans le sous-type ? sinon INNER mais pas bon non pls
        $selects[$typevalue] = "SELECT $table.KOID, ".implode(',', array_merge($qfields, $tqfields))." FROM $table LEFT OUTER JOIN $ttable on $table.KOID=$ttable.{$typedesc->linkname} AND $table.LANG=$ttable.LANG WHERE $where /* type */ $wheretype ORDER BY ".$qorder;
      }
    }
    // Si on a sélectionné aucun sous champ multitable pas besoin de plusieurs fichiers donc on fait un export normal
    if(!$fieldsMultiTable) {
      return parent::export($ar);
    }

    // export sur les types à partir du dictionnaire consolidé (base + types)
    $files = [];
    $olddesc = $this->xset->desc;
    $oldorddesc = $this->xset->orddesc;
    $this->xset->desc = $this->alldescs;
    $this->xset->orddesc = $this->allorddescs;
    $basename = rewritetoascii($p->get('fname'));

    foreach($this->typeDescs as $typevalue=>$typedesc){
      // types pour lequels des champs sont selectionnés
      if (isset($selects[$typevalue])){
        // on ne passe pas ftpserver, ftplogin, ftppassword
        $paramsExport = ['tplentry'=>TZR_RETURN_DATA,
					      'showfieldsgroup'=>0,
					      '_recordcount'=>$recordCount,
					      'fromfunction'=>$p->get('fromfunction'),
					      '_linkedfield'=>$p->get('_linkedfield'),
					      '_target'=>$p->get('_target'),
					      'order'=>$p->get('order'),
					      'fmt'=>$p->get('fmt'),
					      'csvfsep'=>$p->get('csvfsep'),
					      'csvtextsep'=>$p->get('csvtextsep'),
					      'csvcharset'=>$p->get('csvcharset'),
					      'selectedfields'=>$browseSelectedfields[$typevalue],
					      'optionsfields'=>$browseOptionsfields[$typevalue],
					      'select'=>$selects[$typevalue],
					      'exportfiles'=>$p->get('exportfiles'),
					      'fname'=>$basename.' '.$typedesc->label,
					      '_keepStatusFile'=>true,
					      '_ajax'=>$_ajax,
					      'statusFileId'=>$p->get('statusFileId'),
					      'oidisvisible'=>$p->get('oidisvisible'),
					      '_options'=>['local'=>true],
					      'nozip'=>true
        ];
        if(is_array($langs) && count($langs) > 0) {
          // On fait un export par langue
          $files[$typevalue] = [];
          foreach($langs as $lang) {
            $paramsExport['select'] = preg_replace('/'.$this->table.'\.LANG="[A-Z]{2}"\s+AND/', $this->table.'.LANG="'.$lang.'" AND', $selects[$typevalue]);
            $paramsExport['langs'] = array($lang);
            $oldfile = parent::export($paramsExport);
            $pathinfo = pathinfo($oldfile);
            $newfile = $pathinfo['dirname'] . '/' . $lang . '_' . $pathinfo['basename'];
            rename($oldfile, $newfile);
            $files[$typevalue][] = $newfile;
          }
        }
        else {
          $files[$typevalue] = parent::export($paramsExport);
        }
      }
    }

    $this->xset->desc = $olddesc;
    $this->xset->orddesc = $oldorddesc;
    
    $filename = $p->get('fname');
    $dir = $this->getExportDir($ar);
    $zipname = 'data.zip';
    $mime = 'application/zip';
    $tozip = [];
    foreach($files as $file){
      if(is_array($file)) {
        foreach($file as $file2) {
          exec('mv '.$file2.' '.$dir);
          $tozip[] = basename($file2);
        }
      }
      else {
        exec('mv '.$file.' '.$dir);
        $tozip[] = basename($file);
      }
    }

    \Seolan\Core\Logs::debug(__METHOD__.'::'.$filename.' (cd ' . $dir . ';zip -rm ../' . $zipname . ' . )2>&1 > /dev/null');

    if(count($tozip)==1 && (int)$p->get('exportfiles') !== 1) {
      $zipname = $dir.$tozip[0];
      $fmt = $p->get('fmt');
      $filename .= ($fmt == 'xl07') ? '.xlsx' : ".$fmt";
      $mime = $this->_exportMimeType(array('fmt'=>$fmt));
    }
    else {
      $filename .= '.zip';
      exec('(cd ' . $dir . ';zip -rm ../' . $zipname . ' . )2>&1 > /dev/null');
    }

    if ($_ajax){
      \Seolan\Core\Logs::debug(__METHOD__.'::ajaxmode status file ');
      $dlzipname = str_replace(TZR_TMP_DIR, '', $zipname);
      $this->writeStatusFile(array(
        'done' => 1,
        'url' => TZR_DOWNLOADER.'?tmp=1&del=1&mime='.$mime.'&filename='.$dlzipname.'&originalname='.$filename
      ), $ar);
      fclose($this->statusFile);
    } else {
      \Seolan\Core\Logs::debug(__METHOD__.'::download mode ');

      register_shutdown_function('unlink',$dir.'/'.$zipname);
      ob_clean();
      $size=filesize($dir.'/'.$zipname);
      header('Content-type: '.$mime);
      header('Content-Disposition: attachment; filename='.$filename);
      header('Content-Length: '.$size);
      
      readfile($dir.'/'.$zipname);
    }

  }
  
  /**
   * export, print : selection de champs dans les différents types
   */
  function prePrintBrowse($ar=null, $unsetLinkfield = true){
    $p = new \Seolan\Core\Param($ar, []);
    $ar2 = $ar??[];
    $ar2['tplentry']=TZR_RETURN_DATA;
    $r = parent::prePrintBrowse($ar2, $unsetLinkfield);
    
    // compteurs par types
    if(is_array($r['_selected'])) {
      $countQuery = 'select '.$this->typeField.' as type, count(distinct KOID) as nb from '.$this->table.' where LANG=? and KOID in ("'.implode('","',array_keys($r['_selected'])).'") group by 1';
    }elseif($p->get('fromfunction')=='browseSelection') {
      // a voir versus getContextQuery
      $selection=getSessionVar('selection');
      $r['_selected']=$selection[$this->_moid];
      $countQuery = 'select '.$this->typeField.' as type, count(distinct KOID) as nb from '.$this->table.' where LANG=? and KOID in ("'.implode('","',array_keys($r['_selected'])).'") group by 1';
    }else{
      $context=$this->getContextQuery($ar,false);
      $ar['select']=$context['query'];
      $countQuery = str_replace($this->table.'.*', $this->typeField.' as type, count(distinct KOID) as nb ', $ar['select']).' group by 1 ' ;
    }
    $r['record_countDetails'] = getDb()->select($countQuery, [\Seolan\Core\Shell::getLangData()])->fetchAll();
    $colon = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','i18ncolon');
    foreach($r['record_countDetails'] as &$detail){
      $detail['html'] = $this->typeDescs[$detail['type']]->label.$colon.$detail['nb'];
      if($this->typeDescs[$detail['type']]->datasource) {
        $detail['tablename'] = $this->typeDescs[$detail['type']]->datasource->getTable();
      }
    }
    // champs des types sélectionnés
    $r['header_fields'] = [];
    foreach($this->allorddescs as $fn){
      if($this->alldescs[$fn]->table == $this->table || in_array($this->alldescs[$fn]->table, array_column($r['record_countDetails'], 'tablename'))) {
        //Récupération de la stucture des sous tables liées
        if (is_a($this->alldescs[$fn], \Seolan\Field\Link\Link::class) && $this->alldescs[$fn]->target !== '%') {
          $targetfields = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($this->alldescs[$fn]->target);
          $this->alldescs[$fn]->targetfields = $targetfields->desc;
        }
        
        $r['header_fields'][] = $this->alldescs[$fn];
      }
    }

    return \Seolan\Core\Shell::toScreen1($p->get('tplentry'), $r);
  }

  function filledReporting_browse($ar) {
    $p = new Param($ar);
    $field = $p->get('field');

    if($this->xset->desc[$field]) {
      return parent::filledReporting_browse($ar);
    }

    $lang = $p->get('lang');
    if($lang) {
      Shell::setLang($lang);
    }

    $fieldObj = $this->alldescs[$field];
    $table = $this->table;
    $subtable = substr($field, 0, strpos($field, '_'));
    $subfield = substr($field, strpos($field, '_') + 1);
    foreach($this->typeDescs as $typeValue => $typeDesc) {
      if($typeDesc->datasource && $typeDesc->datasource->getTable() == $subtable) {
        $linkname = $typeDesc->linkname;
        break;
      }
    }

    if($fieldObj && $this->typeDataSources[$subtable] && $linkname) {
      $query = $this->_getSession('reporting_query') ?: array();
      $select = $this->getContextQuery($query);
      $search = "/^select $table\.\* from $table where/i";
      $replace = "select $table.KOID from $table left join $subtable on $table.KOID = $subtable.$linkname and $table.LANG = $subtable.LANG where ($subtable.$subfield is null or $subtable.$subfield = '') and ";
      $select = preg_replace($search, $replace, $select);
      $query['oids'] = getDB()->fetchCol($select);
      $query['oids'] = array_combine($query['oids'], $query['oids']);
      $this->_setSession('query', $query);
    }

    $GLOBALS['XSHELL']->_function = $query['fromfunction'] = $_REQUEST['fromfunction'] = 'procQuery';
    return $this->procQuery($query);
  }

  function filledReporting_getCount($field, $query) {
    if($this->xset->desc[$field]) {
      return parent::filledReporting_getCount($field, $query);
    }

    $fieldObj = $this->alldescs[$field];
    $table = $this->table;
    $subtable = substr($field, 0, strpos($field, '_'));
    $subfield = substr($field, strpos($field, '_') + 1);
    foreach($this->typeDescs as $typeValue => $typeDesc) {
      if($typeDesc->datasource && $typeDesc->datasource->getTable() == $subtable) {
        $linkname = $typeDesc->linkname;
        break;
      }
    }

    if($fieldObj && $this->typeDataSources[$subtable] && $linkname) {
      $select = $this->getContextQuery($query);
      $search = "/^select $table\.\* from $table where/i";
      $replace = "select count(1) from $table left join $subtable on $table.KOID = $subtable.$linkname and $table.LANG = $subtable.LANG where ($subtable.$subfield is not null and $subtable.$subfield != '') and ";
      $select = preg_replace($search, $replace, $select);

      return getDB()->fetchOne($select);
    }

    return 0;
  }

  /**
   * quel datasource en fonction du champ type à partir de différentes sources
   */
  protected function getTypeDesc(array $ar, ?string $typeFieldValue=null):?\stdClass{
    if (!isset($ar['ors'])){
      if (isset($ar['edit'])){
	$ar['ors'] = array($this->typeField=>$ar['edit']['o'.$this->typeField]->raw);
      }
      if (isset($ar['display'])){
	$ar['ors'] = array($this->typeField=>$ar['display']['o'.$this->typeField]->raw);
      }
      if (isset($ar['oid'])){
	$ar['ors'] = getDb()->select('select '.$this->typeField.' from '.$this->xset->getTable().' where KOID=? and LANG=?', array($ar['oid'], \Seolan\Core\Shell::getLangData()))->fetch();
      }
      if ($typeFieldValue != null){
	$ar['ors'] = array($this->typeField=>$typeFieldValue);
      } 
    }
    if (!isset($this->typeDescs[$ar['ors'][$this->typeField]])){
      \Seolan\Core\Logs::critical(get_class($this),'::getTypeDesc unknown type '.$ar['ors'][$this->typeField]);
      return null;
    }
    return $this->typeDescs[$ar['ors'][$this->typeField]];
  }
  function getTypeFieldGroup($oidObject){
    $groupt = $oidObject['o'.$this->typeField]->title ?? $oidObject['o'.$this->typeField]->text ;
    if(empty($groupt))
      $groupt = $oidObject['o'.$this->typeField]->raw;
    return $groupt;
  }

  /**
   * ajout des champs du type aux champs de base
   * -> génération systématique des groupes
   * @param array $res : résultat type edit/display à alimenter
   * @param array $resType : résultat type edit/display qui alimente
   * @param stdClass $typedesc : la descripition du type en cours de traitement
   */

  function mergeTypeFields(array &$res, array $resType, \stdClass $typedesc){
    $groupg = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','general');
    // title n'est pas commun a tout les champs
    $groupt = $this->getTypeFieldGroup($res);
    $resType['_groups'] = null;
    if (empty($res['_groups']) || !isset($res['_groups'])){
      $res['_groups'] =  array();
      foreach($res['fields_object'] as $fo){
	if (in_array($fo->field, static::$excludedFields) || 
	    $fo->sys
	){
	  continue;
	}
	if (empty($fo->fielddef->fgroup)){
	  $fo->fielddef->fgroup = $groupt;
	}
	if (!isset($res['_groups'][$fo->fielddef->fgroup])){
	  $res['_groups'][$fo->fielddef->fgroup] = array();
	}
	$res['_groups'][$fo->fielddef->fgroup][] = $fo;
      }
    }
    foreach($resType['fields_object'] as $fo){
      // les champs qui ne doivent pas être repris
      if (in_array($fo->field, static::$excludedFields) 
	  || $fo->field == $this->typeField
	  || $fo->field == $typedesc->linkname || 
	  $this->xset->fieldExists($fo->field) ||
	  $fo->sys
	  ){
	continue;
      }
      if (empty($fo->fielddef->fgroup)){
	$fo->fielddef->fgroup = $groupt;
      } else {
	$fo->fielddef->fgroup = $groupt.' : '.$fo->fielddef->fgroup;
      }
      if (!isset($res['_groups'][$fo->fielddef->fgroup])){
	$res['_groups'][$fo->fielddef->fgroup] = array();
      }
      $res['_groups'][$fo->fielddef->fgroup][] = $fo;
      $res['fields_object'][] = $fo;
      $res['o'.$fo->field] = $resType['o'.$fo->field];
    }
    ksort($res['_groups']);
  }
  /**
   * journal
   * -> ajout des modifications des sous-types
   */
  public function &journal($ar) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $oid=$p->get('oid');
    $ar['tplentry'] = TZR_RETURN_DATA;
    $rp = parent::journal($ar);

    $typedesc = $this->getTypeDesc(array('oid'=>$oid));
    if (!empty($typedesc)){ // type connu
      $typeOid = $this->getTypeOid($typedesc, $oid);
      $rt=\Seolan\Core\Logs::getJournal($typeOid,array('etype'=>array('=',array('create','update','rule'))),NULL,NULL,$typedesc->datasource, null);
      $tabs = array('lines_oid', 'lines_oUPD', 'lines__lang', 'lines_journal', 'lines_fields', 'lines_elabel');
      foreach($tabs as $tn){
        $rp[$tn] = array_merge((array)$rp[$tn], (array)$rt[$tn]);
      }
      // dates de mises à jour
      $lines_upd = array();
      foreach($rp['lines_oUPD'] as $i=>$oupd){
        $lines_upd[$i] = $oupd->raw;
      }
      array_multisort($lines_upd, SORT_DESC, $rp['lines_oid'], $rp['lines_oUPD'], $rp['lines__lang'], $rp['lines_journal'], $rp['lines_fields'], $rp['lines_elabel']);
    } 

    return \Seolan\Core\Shell::toScreen1($tplentry, $rp);
  }
  /**
   * génération de la documentaion, selon les droits d'un utilisateur
   */
  function getDocumentationData(){
    $doc1=$this->xset->getDocumentationData($this->getFieldsSec([]));
    $doc=array();
    $doc['template']='Module/MultiTable.documentation.md';
    $doc['more']='';
    
    if(\Seolan\Core\Json::hasInterfaceConfig() && ($alias=\Seolan\Core\Json::getModuleAlias($this->_moid))) {
      $doc['more'].='L\'alias pour ce module est : '.$alias.". Obtenir les détails d\'un objet de ce module : \n".
	"```\nGET /".$alias."/53x1ml04oit6?sessionid=v94o7fvn5tc9g5hshb95m43vb2\n```\n";
    }

    $doc['data']['sources']['main']=$doc1;
    $doc['data']['sources']['types']=array();
    foreach($this->typeDescs as $typeValue=>$typeDesc){
      $doc['data']['sources']['types'][$typeValue]=$typeDesc->datasource->getDocumentationData();
    }
    return $doc;
  }

  /**
   * synchro des champs systèmes des types 
   */
  function synchronizeUpd($typedesc, $oid){
    getDb()->execute('update '.$this->xset->getTable().' m, '.$typedesc->datasource->getTable().' s set m.upd=s.upd where m.lang=s.lang and m.koid=s.'.$typedesc->linkname.' and m.upd<s.upd and m.koid=?', array($oid));
  }
  /**
   * recherche si la sous fiche type existe
   * - se fait dans la langue de base, que la sous fiche soit traduisible ou pas
   */
  function objectExists($typedesc, $typeOid){
    return $typedesc->datasource->objectExists($typeOid, TZR_DEFAULT_LANG);
  }
  /// Retourne l'instance qui va être associée au serveur soap 
  protected function _SOAPHandler(){
    return new \Seolan\Module\MultiTable\SoapServerHandler($this);
  }
  function _SOAPWSDLTypes(&$wsdl){
    $fields=array(array('minOccurs'=>1,'maxOccurs'=>1,'name'=>'oid','type'=>'xsd:string'));
      \Seolan\Core\Logs::debug("_SOAPWSDLTypes ");

    foreach($this->alldescs as $n=>&$f){
      \Seolan\Core\Logs::debug("_SOAPWSDLTypes field ".$f->field);

      $type=$f->getSoapType();
      $fields[]=array('minOccurs'=>0,'maxOccurs'=>1,'name'=>$f->field,'type'=>$type['name']);
      if(!empty($type['descr'])) $this->_SOAPAddTypes($wsdl,$type['descr']);
    }
    $this->_SOAPAddTypes($wsdl,array('browseParam'=>array(array('name'=>'filter','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string'),
							  array('name'=>'fields','minOccurs'=>0,'maxOccurs'=>1,'type'=>'xsd:string')),
                                     'displayParam'=>array(array('name'=>'oid','minOccurs'=>1,'maxOccurs'=>1,'type'=>'xsd:string')),
				     'displayResult'=>$fields,
				     'browseResult'=>array(array('name'=>'line','minOccurs'=>0,'maxOccurs'=>'unbounded','type'=>'tns:displayResult'))));
    return;
  }

  function tablesToTrack() {
    if($this->trackchanges) {
      $totrack = array($this->table);
      foreach($this->typeDescs as $typeValue=>$typeDesc){
        $totrack[] = $typeDesc->datasource->getTable();
      }
      return $totrack;
    }
  }

  public function chk(&$message=NULL) {
    $ret = parent::chk($message);

    // Crée un index sur les champs liés de la multitable
    foreach($this->typeDescs as $typeValue=>$typeDesc){
      $ttable = $typeDesc->datasource->getTable();
      $tfield = $typeDesc->linkname;
      if(!getDB()->count("SHOW INDEX FROM $ttable where Column_name=?", [$tfield])) {
        getDB()->execute("ALTER TABLE $ttable ADD INDEX {$tfield}({$tfield}(40))");
      }
    }

    return $ret;
  }
  
  function secGroups($function, $group=NULL) {
    $g = [];
    $g['advancedImport'] = ['rw','rwv','admin'];
    
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    
    return parent::secGroups($function,$group);
  }
  
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my, $alfunction);
    
    if($this->secure('','advancedImport') && (int)$this->advancedImport === 1){
      $o1=new \Seolan\Core\Module\Action($this,'advancedImport', \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','advancedImport'),
                                         \Seolan\Core\Session::complete_self().'&moid='.$this->_moid.'&function=advancedImport&template=Module/MultiTable.advanced-import.html', 'edit');
      $o1->menuable=true;
      unset( $my['import']);
      $my['advancedimport'] = $o1;
    }
  }
  
  /* fonction import avancee  */
  // -> default, on fait rien
  // -> impstep 1 : on analyse le fichier
  // -> impstep 2 : on importe après traitement du formulaire
  //
  function advancedImport($ar){
    
    $p = new \Seolan\Core\Param($ar, ['impstep'=>0,
                                      /* step 1 */
                                      'guesscols'=>'1',
                                      'csvtextsep'=>'"',
                                      'csvfieldsep'=>';',
                                      'csvcharset'=>'UTF-8',
                                      'csvlinesep'=>null, /* calculé si pas fourni */
                                      /* step2 */
                                      'mode'=>'check',
                                      'linestoskip'=>NULL,
                                      'updateifexists'=>NULL,
                                      'updateonly'=>NULL, /* pas implementé encore */
                                      'clearbefore'=>NULL,
                                      'ilangdata'=>NULL]
    );
    $impstep = $p->get('impstep');
    $r = [];
    $file=$_FILES['file']['tmp_name'];
    //list des champ du module (Table ou multitable)
    if(isset($this->alldescs)){
      $descs = $this->alldescs;
    }else{
      $descs = $this->xset->desc;
    }
    if (file_exists($file))
      $impstep = 1;
    ///////////////////
    if ($impstep == 1){
      $svspec = [];
      $guesscols = $p->get('guesscols');
      if(file_exists($file)) {
	$specs = null;
        $mimeClass = \Seolan\Library\MimeTypes::getInstance();
        $mime = $mimeClass->getValidMime($_FILES['file']['type'],$_FILES['file']['file'],$_FILES['file']['name']);
        $format = $mimeClass->get_extension($mime);
        $svspec['guesscols'] = $guesscols;
        $svspec['filename'] = $_FILES['file']['name'];
        $r['fileinfo']['name'] = "{$_FILES['file']['name']}, $mime";
        if($format=='csv'){
          $svspec['format'] = 'csv';
          $fsep = $p->get('csvfieldsep');
          $tsep = $p->get('csvtextsep');
          $lsep = $p->get('csvlinesep');
          if(empty($lsep)){
	    \Seolan\Core\System::loadHelper('get_ua.php');
	    $ua = get_ua_info($_SERVER['HTTP_USER_AGENT'],0);
            if($ua['os'] != 'Linux'){
              $lsep = "\r\n";
            }else{
              $lsep = "\n";
            }
          }
          $charset = $p->get('csvcharset');
          $svspec['csvcharset'] = $charset;
          $svspec['csvlinesep'] = $lsep;
          $svspec['csvfieldsep'] = $fsep;
          $svspec['csvtextsep'] = $tsep;
          $specs =(Object)
	  ['general'=>(Object)['format'=>$format,
			       'fieldsinheader'=>false,
			       'quote'=>$tsep,
			       'separator'=>$fsep,
			       'endofline'=>$lsep
	  ]];
          $data=file_get_contents($file);
          convert_charset($data,$charset,TZR_INTERNAL_CHARSET);
          $data=_getCSVData($data, $specs);
        } elseif($format=='xls') {
	  //pseudo spec
	  $specs = (Object)['general'=>null,'catalog'=>null];
          $svspec['format'] = 'xl';
          $data=_getXLSData($file,'Excel5');
        }elseif($format=='xlsx'){
	  //pseudo spec
	  $specs = (Object)['general'=>null,'catalog'=>null];
          $svspec['format'] = 'xl07';
          $data=_getXLSData($file,'Excel2007');
        } else {
          $r['message'] .= "{$_FILES['file']['name']} [$mime] [$format] unknown format";
        }
        // les premières lignes du fichier
        $r['fileinfo']['count'] = count($data);
        $m = min(count($data), 4);
        $r['sample_lines'] = $m+1;
        $nbcols = 0;
	$nbsamplecols = 0;
        for($i=0; $i<$m; $i++){
          $row = $data[$i];
	  $nbsamplecols = max($nbsamplecols, count($row));
          foreach($row as $j=>$v){
            if ($i == 0){
              $nbcols += 1;
            }
            $r['colname'][$j] = \PHPExcel_Cell::stringFromColumnIndex($j);
            $r['sample'][$j][$i] = @htmlentities($v,ENT_COMPAT,TZR_INTERNAL_CHARSET);
            $r['truncsample'][$j][$i] = mb_substr($v,0,20,TZR_INTERNAL_CHARSET);
          }
        }
	$r['nbsamplelines'] = $m;
	$r['nbsamplecols'] = $nbsamplecols;
        // liste des champs, dont liens + tableaux de recherche
        $ffields = [];
        $flabels = [];
        $r['fields'] = [];
        $r['links'] = [];
        $r['compulsoryfields'] = [];
        $r['translatablefields'] = [];
        
        foreach($descs as $fn=>$fd){
          if ($fd->translatable)
            $r['translatablefields'][] = $fd->label;
          if ($fd->compulsory)
            $r['compulsoryfields'][] = $fd->label;
          $tfields = array();
          if ($fd->ftype == '\Seolan\Field\Link\Link' && \Seolan\Core\System::tableExists($fd->target) && !isset($r['links'][$fn])){
            $r['links'][$fn] = array();
            $xl = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$fd->target);
            foreach($xl->desc as $fn1=>$fd1){
              if ($fd1->sys || $fd1->ftype == '\Seolan\Field\Link\Link')
                continue;
              $r['links'][$fn][] = (object)array('field'=>$fn1, 'label'=>$fd1->label, 'ftype'=>$fd1->ftype);
            }
          }
          $r['fields'][] = (object)array('field'=>$fn, 'label'=>$fd->fgroup.' - ' .$fd->label, 'ftype'=>$fd->ftype, 'group'=>$fd->fgroup,'multivalued'=>$fd->multivalued);
          $flabels[] = $fd->label;
          $ffields[] = $fn;
        }
        // associations supposées
        $r['foundcols'] = [];
        if ($guesscols){
          for($c=0; $c<$nbcols; $c++){
            $v = html_entity_decode($r['sample'][$c][0]);
            $f = array_search($v, $ffields);
            if ($f !== false){
              $r['foundcols'][] = $f;
            } else{
              $f = array_search($v, $flabels);
              if ($f !== false){
                $r['foundcols'][] = $f;
              } else {
                $r['foundcols'][] = -1;
              }
            }
          }
        }
        // langue dans le cas de table traduisible
        if ($this->xset->isTranslatable()){
          $r['langs'] = \Seolan\Core\Lang::getCodes();
          $r['translatable'] = true;
          $r['langdef'] =\Seolan\Core\Shell::getLangData();
        } else {
          $r['translatable'] = false;
          $r['langs'] = [];
        }
        // sauvegarde du fichier pour import reel
        $filesv = TZR_TMP_DIR.uniqid().'importedfile';
        move_uploaded_file($file, $filesv);
        $this->_setSession('imported_file_name', $filesv);
        $this->_setSession('imported_file_spec', $svspec);
        @unlink($file);
      }
    } // impstep1


    ///////////////////
    if ($impstep == 2){
      $r['message'] = "step 2";
      // recup des infos etapes prec
      $file = $this->_getSession('imported_file_name');
      $svspec = $this->_getSession('imported_file_spec');
      if (file_exists($file)){
        $clearbefore = false;
        $updateifexists = '';
        if ($p->is_set('updateifexists'))
          $updateifexists = true;
        if ($p->is_set('clearbefore'))
          $clearbefore = true;
        if ($p->is_set('linestoskip'))
          $linestoskip = $p->get('linestoskip');
        
        // lecture du form de conf de l'import
        $tzrfields = $p->get('tzrfield');
        $importcols = $p->get('importcol');
        $srcfields = $p->get('srcfield');
        $srcfieldscreate = $p->get('srcfieldcreate');
        $tzrfieldsoperator = $p->get('tzrfieldoperator');
        $keyfields = $p->get('keyfield');
        if ($p->is_set('ilangdata'))
          $ilangdata = $p->get('ilangdata');
        else
          $ilangdata = '';
        
        $keynames = [];
        $keys = [];
        $headers = [];
	$catalog = (Object)['fields'=>[]];
        foreach($tzrfields as $i=>$selfield){
	  if (empty($selfield))
	    continue;
          if (isset($importcols[$i]) && $importcols[$i] == 'import'){
            if (isset($srcfields[$i])){
              if (isset($srcfieldscreate[$i]))
                $create = true;
              else
                $create = false;
              if ( isset($tzrfieldsoperator[$i]) && $tzrfieldsoperator[$i] != '')
                $operator = $tzrfieldsoperator[$i];
              else
                $operator = null;
              
	      $srcParms = ['srcField'=>$srcfields[$i],
			   'create'=>$create,
			   'operator'=>$operator,
			   'filter'=>null];
            } else {

              if( $descs[$selfield]->ftype == '\Seolan\Field\StringSet\StringSet' ){
		$srcParms = ['srcField'=>'raw',
			     'create'=>$create,
			     'operator'=>null,
			     'filter'=>null];
              }else{
                $srcParms = [];
              }

            }
	    $iskey = false;
            if (isset($keyfields[$i])){
              $iskey = true;
              $keynames[] = $selfield;
            }
            // champs non traduisibles
            if ((count($ilangdata) && $ilangdata[0] != TZR_DEFAULT_LANG ) && $descs[$selfield]->translatable != 1 && !$iskey){
              $headers[$i] = 'N/A';
            } else {
              $catalog->fields[] = (Object)(['name'=>$selfield.$i, 'tzr'=>$selfield] + $srcParms);
              $headers[$i] = $selfield.$i;
            }
          } else {
            $headers[$i] = 'N/A';
          }
        }
        
        // colonne KOID
        if ($p->is_set('oidfield')){
          $koid = true;
          list($foo, $row) = explode(':', $p->get('oidfield'));
	  $row = (int)$row;
          if (isset($headers[$row]) && $headers[$row] != 'N/A'){
            $genkoid = $tzrfields[$row]; // genkoid doit être un champ de l'input pas un header
          } else {
            $headers[$row] = 'KOID';
            $catalog->fields[] = (Object)['tzr'=>'KOID'];
            $genkoid = null;
          }
        } else {
          $koid = false;
        }
        // update if exists + cles
        if (($updateifexists || $updateonly) && count($keyfields) == 0 && $koid == false){
          $r['message'] = 'Could not import in update mode with no key field or system identifier selected ';
          return \Seolan\Core\Shell::toScreen1('br', $r);
        } else {
          foreach($keynames as $key){
	    $keys[] = $key;
          }
        }
        // contruction des specs et de l'entete
	$langs = null;
        if (count($ilangdata) ){
          if(count($ilangdata)==1 && $ilangdata[0] != TZR_DEFAULT_LANG){
	    $langs[] = $ilangdata[0];
          }elseif(count($ilangdata)>1 ){
            $langs=$ilangdata;
          }
        }
        $specs = (Object)[
	  'general'=>(Object)[
	    'format'=>$svspec['format'],
	    'charset'=>$svspec['csvcharset'],
	    'fieldsinheader'=>true,
	    'langs'=>$langs,
	    'koid'=>$genkoid,
	    'keys'=>$keys,
	    'quote'=>$svspec['csvtextsep'],
	    'separator'=>$svspec['csvfieldsep'],
	    'endofline'=>$svspec['csvlinesep'],
	    'strategy'=>(Object)['clearbefore'=>$clearbefore,
				 'updateifexists'=>$updateifexists]
	  ],
	  'catalog'=>$catalog
	];

        if($specs->general->format=='csv'){
          $data=file_get_contents($file);
          $charset=$specs->general->charset;
          if($charset != '' && $charset != TZR_INTERNAL_CHARSET) 
	    convert_charset($data,$charset,TZR_INTERNAL_CHARSET);
          $data=_getCSVData($data,$specs);
        }elseif($specs->general->format=='xl'){
          $data=_getXLSData($file,'Excel5');
        }elseif($specs->general->format=='xl07'){
          $data=_getXLSData($file, 'Excel2007');
        }
        // gestion des entetes : on ajoute ou on remplace
	if ($linestoskip>0){
          // completer
          $foo = array_shift($data);
        }
        array_unshift($data, $headers);
        $mode = $p->get('mode');
        if ($mode == 'real'){
          if(!empty($data)) 
	    $found = $this->_doImportData($data, $specs);
          $r['imported'] = 1;
          $r['message'] = $svspec['filename'].' imported';
	  
          $this->insertHistoryLog($file,$svspec['filename'],'ADV MANUAL '.$this->getLabel().' ('.$this->_moid.')',json_encode($specs));
          @unlink($file);
        } else {
          \Seolan\Core\Shell::changeTemplate('Module/MultiTable.check-advanced-import.html');
          $r['specs'] = json_encode($specs, JSON_PRETTY_PRINT);
          if(!empty($data)) 
	    $found = $this->_doImportData($data, $specs, true);
        }
      } else {
        $r['message'] = "file $file not found";
      }
      
    } // impstep2
    return \Seolan\Core\Shell::toScreen1('br', $r);

  }
  
  protected function _doImportData(&$data, &$specs, $checkonly=false) {
    if ($this->advancedImport) {
      return $this->_doImportDataAdvanced($data, $specs, $checkonly);
    }
    
    return parent::_doImportData($data, $specs);
  }
  
  protected function _doImportDataAdvanced(&$data, &$specs, $checkonly=false) {
    if ($checkonly){
      $cplmess = '';
    } else {
      $cplmess = '';
    }
  
    //multitable or not !
    if(isset($this->alldescs)){
      $descs = $this->alldescs;
    }else{
      $descs = $this->xset->desc;
    }
  
    $unique=array();
    $found=false;
    $koid=$specs->general->koid;
    $updateifexists=$specs->general->strategy->updateifexists;
    if(!empty($updateifexists) && $updateifexists) 
      $updateifexists=true;
    else 
      $updateifexists=false;
  
    $unique=[];
    foreach((array)$specs->general->keys as $i=>$kname){
      if(!empty($kname)) $unique[]=$kname;
    }
    if(!empty($unique)) 
      $unique[]='LANG';
    // Vide la table si demandé
    $clearbefore=$specs->general->strategy->clearbefore;
    if(!empty($clearbefore) && $clearbefore) {
      \Seolan\Core\Logs::notice('_import_data',$cplmess.'clearing before importing');
      $deleterequest=$specs->general->strategy->clearrequest;
      if (!$checkonly){
        if(!empty($deleterequest)) 
	  $this->xset->clear($deleterequest);
        else 
	  $this->xset->clear();
      }
    }
  
    // Creation des entetes
    $head=array();
    $fieldsinheader = $specs->general->fieldsinheader;
    if(!empty($fieldsinheader) && $fieldsinheader) 
      $fieldsinheader=true;
    else 
      $fieldsinheader=false;
    if($fieldsinheader){
      $line=$data[0];
      foreach($line as $i=>$field){
        $head[$field]=$i;
      }
      unset($data[0]);
    }else{
      $j=0;
      foreach($specs->catalog->fields as $i=>$field){
        $head[$field->tzr]=$j;
        $j++;
      }
    }
    // Supprime les lignes à ne pas importer
    $linestoskip=$specs->general->linestoskip;
    if(!empty($linestoskip)) {
      for($i=0;$i<$linestoskip;$i++) unset($data[$i]);
    }
    $message='<dl>';
    $tot=$ok=$nok=$update=0;
    $incompletelines=$emptylines=array();
    foreach($data as $line=>&$tuple) {
      $tot++;
      $message.='<dt><strong>- Line '.($line+1).'</strong></dt>';
      if(count($tuple)<count($head)){
        $message.='<dd>Incomplete line</dd>';
        $incompletelines[]=$line+1;
        continue;
      }
      $isempty=true;
      foreach($tuple as $i=>$value){
        if(!empty($value)){
          $isempty=false;
          break;
        }
      }
      if($isempty){
        $message.='<dd>Empty line</dd>';
        $emptylines[]=$linet+1;
        continue;
      }
      $input=['_langs'=>[],'_unique'=>$unique];
      $refsql= [];
      // Specifie la langue par defaut de l'entrée
      if($specs->general->lang) {
	$input['_langs'][0]=$specs->general->lang;
      } elseif($specs->general->langs){ // on enregistre dans plusieurs langue
        $langs = $specs->general->langs;
        $input['_langs'] = $langs['lang'];
      }else {
	$input['_langs'][0] = TZR_DEFAULT_LANG;
      }
    
      $multipleLine = [];//gestion du plusieur insertion par ligne du fichier excel
      $operators = [];
    
      foreach($specs->catalog->fields as $i=>$field) {
      
        $tzrfield=$field->tzr;
        $namefield=$field->name;
        $skip=$field->skip;
        $operator=$field->operator;
      
        if(!empty($skip) && $skip!=false) 
	  continue;
        if($fieldsinheader && $namefield) 
	  $value=$tuple[$head[$namefield]];
        else 
	  $value=$tuple[$head[$tzrfield]];
        // On passe les champs du catalogue qui ne sont pas dans le fichier
        if($value===NULL) 
	  continue;
        $defaultvalue=$field->default;
        if(!empty($tzrfield)) {
          if(!empty($operator)) {
	    $fd = $this->alldescs[$tzrfield];
	    if (!isset($operators[$fd->table]))
	      $operators[$fd->table] = ['+'=>[],'-'=>[]];
            $operators[$fd->table][$operator][] = $tzrfield;
          }
        
          if($tzrfield=='KOID' || $tzrfield=='LANG') 
	    $input[$tzrfield]=$value;
          else{
            $fieldDef=$descs[$tzrfield];
            if(!is_object($fieldDef)){
              $message='Error : field "'.$tzrfield.'" doesn\'t exists<br>';
              break 2;
            }
	    // l'import (au moins pour les link, stringset) doit se faire 
	    // avec le champ original (bon noms => requete correctes etc)
	    $pairField = $this->getPairField($fieldDef);
            if ($checkonly){
	      $ret = $pairField->checkImport($value,$field);
            } else {
              $ret = $pairField->import($value,$field);
            }
            //gestion des clé sur une/des colonnes données
            //TODO : utiliser les requetes parametrées
            if($tzrfield != 'LANG' AND in_array($tzrfield,$unique)){
              if(is_array($ret['value'])) $ret['value']=$ret['value'][0];
              $testval=$ret['value'];
              $refsql[$tzrfield] = "$tzrfield like '".trim($testval)."'";
            }
            $retvalue = $ret['value'];
            if(empty($retvalue) && !empty($defaultvalue)) $retvalue=$defaultvalue;
          
            if( !empty($input[$tzrfield]) ){ //on est dans le cas ou on essaye d'insérer plusieurs ligne en base pour une ligne du fichier excel
              $multipleLine[$tzrfield] = $refsql[$tzrfield] || true;
              if(!is_array($input[$tzrfield])){
                $input[$tzrfield] = (array) $input[$tzrfield];
              }
              $input[$tzrfield][] = $retvalue;
            }else {
              $input[$tzrfield] = $retvalue;
            }
            if(!empty($ret['message'])) $message.='<dd>'.$ret['message'].'</dd>';
          }
        }
	
      }
      if($multipleLine){ //on est dans le cas ou on essaye d'insérer plusieurs ligne en base pour une ligne du fichier excel
        $inputTemp = $input;
        foreach($multipleLine as $multipleField => $multipleRefsql){
          foreach($input[$multipleField] as $multipleVal){
            $inputTemp[$multipleField] = $multipleVal;
            $refsql[$multipleField] = 1;
            $resinput = $this->insertInput($inputTemp,$koid,$updateifexists,array_merge($refsql,$multipleRefsql),$operators,$checkonly);
            $message .= $resinput['message'];
            $ok += $resinput['ok'];
            $nok += $resinput['nok'];
            $update += $resinput['update'];
          }
        }
        unset($inputTemp);
      }else{
        $resinput = $this->insertInput($input,$koid,$updateifexists,$refsql,$operators,$checkonly);
      }
      $message .= $resinput['message'];
      $ok += $resinput['ok'];
      $nok += $resinput['nok'];
      $update += $resinput['update'];
      
      unset($input);
      
      ob_end_flush();
      flush();
      ob_start();

    }
  
    $message.='</dl>';
    $message='Total : '.$tot.'<br>Insert : '.$ok.'<br>Update : '.$update.'<br>Error : '.$nok.'<br>'.
             'Empty lines : '.count($emptylines).' ('.implode(', ',$emptylines).')<br>'.
             'Incomplete line : '.count($incompletelines).' ('.implode(', ',$incompletelines).')<br>'.$message;
    if ($checkonly){
      $message = '<h3>Check only mode</h3>'.$message;
    }
  
    \Seolan\Core\Shell::toScreen2('','message',$message);
    return $found;
  }
  
  function insertInput($input,$koid,$updateifexists,$refsql,$operators,$checkonly){
    $kernel = new \Seolan\Core\Kernel();
    $message = '';
    $ok = 0;
    $nok = 0;
    $update = 0;
    
    
    // Recupere un eventuel oid
    if(!empty($koid)){
      if(strpos($input[$koid],':')) $input['newoid']=$input[$koid];
      else $input['newoid']=$this->table.':'.rewriteToAscii($input[$koid]);
    }elseif(!empty($input['KOID'])){
      if(strpos($input['KOID'],':')) 
	$input['newoid']=$input['KOID'];
      else 
	$input['newoid']=$this->table.':'.rewriteToAscii($input['KOID']);
    }elseif(!empty($refsql) && $updateifexists){
      $sele = 'SELECT KOID FROM '.$this->table.' WHERE LANG = \''.TZR_DEFAULT_LANG.'\' AND '.implode(' AND ',$refsql);
      $foundKoids = getDB()->fetchAll($sele);
      if(count($foundKoids) > 1){
        $ok = false;
        $nok = 1;
        $message .= 'Multiple element found for unique key : '.var_export($refsql,1);
        unset($input);
      }elseif($foundKoids){
        $input['newoid'] = $input['KOID'] = $foundKoids[0]['KOID'];
      }
    }
    // JUG 20150908 ajout ou suppression pour les champs lien multiple
    // /!\ on ne prend pas en compte les langues ici, on doit être sur des champs lien multivalué non traduisible
    // RR 20200228 ajout boucle / table sur $operators + correspondance 'realname' + requete sur la bonne table et via le bon champ
    if($updateifexists && $operators){
      $queryfields = [];
      $realnames = [];
      $linknames = [];
      foreach($operators as $tabName=>$tabOps){
	$fieldnames  = array_merge($tabOps['+'],$tabOps['-']);
	$queryfields = [];
	foreach($fieldnames as $fn){
	  $fd = $this->alldescs[$fn];
	  $realnames[$fd->field] = $fd->_mttCloneFieldName??$fd->field; // si champ de type le nom sql est mtt, sinon mtt existe pas
	  $queryfields[] = $realnames[$fd->field];
	  // recup du champ qui fait le lien
	  if ($fd->table == $this->xset->getTable())
	    $linknames[$tabName] = 'KOID';
	  else {
	    if (!isset($linknames[$tabName])){
	      foreach($this->typeDescs as $infos){
		if ($infos->datasource->getTable() == $tabName){
		  $linknames[$tabName] = $infos->linkname;
		}
	      }
	    }
	  }
	}
	// recup des valeurs actuelles du (des champs liens de la table ce qui peut être sur la table pcple ou sur une table de type
	$sele = 'SELECT '.implode(',', $queryfields).' FROM '.$tabName.' WHERE LANG = \''.TZR_DEFAULT_LANG.'\' AND '.$linknames[$tabName].' = \''.$input['newoid'].'\'';
	$rsk = getDB()->select($sele);
	if($rsk && $rsk->rowCount()==1 && $orsk = $rsk->fetch(\PDO::FETCH_ASSOC)){
          foreach($tabOps['+'] as $fieldname ){
	    $realname = $realnames[$fieldname];
            $old = explode('||',$orsk[$realname]);
            $input[$fieldname] = array_unique(array_merge($input[$fieldname],$old));
            $input[$fieldname] = array_values(array_filter($input[$fieldname]));
          }
          foreach($tabOps['-'] as $fieldname ){
	    $realname = $realnames[$fieldname];
            $old = explode('||',$orsk[$fieldname]);
            $input[$fieldname] = array_diff($old,$input[$fieldname]);
            $input[$fieldname] = array_values(array_filter($input[$fieldname]));
          }
          unset($orsk);
	}
	unset($rsk);
      }
    }
    $input['_updateifexists']=$updateifexists;
    $input['_delayed']=false;
    $input['tplentry']=TZR_RETURN_DATA;
    
    foreach((array)$input['_langs'] as $vlangs){
      $input['LANG'] = $vlangs;
      
      if( (
          (
            !$input['newoid'] ||
            !$kernel->objectExists($input['newoid'], $input['LANG'])
          )
          ) &&
          (
            empty($input['LANG']) ||
            $input['LANG']==TZR_DEFAULT_LANG ||
            $this->xset->getTranslatable()==3
          )
      
      ){
        
        if(!$this->secure('','procInsert',$u=NULL,$input['LANG']) ||
           (
             !empty($input['LANG']) && $input['LANG']!=TZR_DEFAULT_LANG &&
             !$tghis->secure('','procInsert',$u=NULL,$input['LANG'])
           )
        ){
          $message.='<dd>Insert : Write in '.$input['LANG'].' is not allowed</dd>';
          $nok++;
        }else{
          $input['LANG_DATA']=$input['LANG'];
          if ($checkonly){
            // on recherche ....
            $r = $this->myProcInput($input);
          } else {
            $r = $this->xsetProcInput($input);
          }
          if(!empty($r['oid'])){
            if(!$found) $found=true;
            $message.='<dd>Insert : success -> '.$r['oid'].' '.$r['message'].'</dd>';
            $ok++;
          }elseif(empty($r['error'])){
            $message.='<dd>Insert : update an existing entry '.$r['oid'].' : '.$r['message'].'</dd>';
            $update++;
          }else{
            $message.='<dd>Insert : error ('.$r['message'].')</dd>';
            $nok++;
          }
        }
      }elseif(!empty($input['newoid']) ){
        if(!array_key_exists($input['LANG'],$GLOBALS['TZR_LANGUAGES'])){
          $message.='<dd>Lang '.$input['LANG'].' does not exist</dd>';
          $nok++;
        }elseif($input['LANG']!=TZR_DEFAULT_LANG && !$kernel->objectExists($input['newoid'], $input['LANG'])){
          $nok++;
          $message.='<dd>data does not exist in base lang</dd>';
        }elseif($this->secure('','procEdit',$u=NULL,$input['LANG'])){
          $input['LANG_DATA']=$input['LANG'];
          if(!empty($input['newoid']))  $input['oid']=$input['newoid'];
          if ($checkonly){
            $r = $this->myProcEdit($input);
          } else {
            $r = $this->xsetProcEdit($input);
          }
          if(count($this->keys) > 0){
            $dispName = '';
            foreach($this->keys as $keyfield)
              $dispName .= $input[$keyfield].' ';
          }else
            $dispName = $input['oid'];
          
          $message.='<dd>Update '.$dispName.' in '.$input['LANG'].'</dd>';
          $update++;
        }else{
          $message.='<dd>Update : Write in '.$input['LANG'].' is not allowed</dd>';
          $nok++;
        }
      }else{
        $message.='<dd>Translation not possible because data does not exist in base lang</dd>';
        $nok++;
      }
    }
    return array('message'=>$message,'ok'=>$ok,'nok'=>$nok,'update'=>$update);
  }

  
  protected function insertHistoryLog($file,$filename,$type,$conf){
    if ($this->modHistoryAdvancedImport) {
      //insertion dans l'historique
      $mimeClasse      = \Seolan\Library\MimeTypes::getInstance();
      $insert          = [];
      $insert['IDATE'] = date('Y/m/d H:m:s');
      $insert['ITYPE'] = $type;
      $insert['IFILE'] = [
        'tmp_name' => $file,
        'name'     => $filename,
        'size'     => filesize($file),
        'type'     => $mimeClasse->getValidMime(null, $filename, null)
      ];
  
      $insert['CONF']  = $conf;
      $insert['nolog'] = 1;
  
  
      $modhistory = \Seolan\Core\Module\Module::objectFactory($this->modHistoryAdvancedImport);
      $modhistory->procInsert($insert);
    }
  }
  
  
  public function xsetProcEdit($input){
    $r = $this->xset->procEdit($input);
    if($this->typeField && $r['oid']) {
      $this->updateDaughterForOid($r['oid'], $input);
    }
    return $r;
  }
  public function xsetProcInput($input){
    $r = $this->xset->procInput($input);
    if($this->typeField && $r['oid']) {
      $this->updateDaughterForOid($r['oid'], $input);
    }
    return $r;
  }
  
  public function updateDaughterForOid($oid,$input){
    if(!$oid)
      return;
    $updateDaughter = false;
    $oidFicheType = $this->multiTableGetDaughterForOid($oid);
    if($oidFicheType){
      $xset = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($oidFicheType);
      
      $inputDaughter = ['oid'=>$oidFicheType,'LANG'=>$input['LANG'],'LANG_DATA'=>$input['LANG_DATA']];
      $inputKeys = array_keys($input);
      
      foreach($xset->desc as $fieldName => $fieldObject){
        $key = $xset->getTable().'_'.$fieldName;
        
        foreach($inputKeys as $inputKey){
          if(preg_match('/'.$fieldName.'$/',$inputKey)){
            $inputDaughter[$fieldName]=$input[$inputKey];
            $updateDaughter = true;
          }
        }
        
      }
      if($updateDaughter){
        return $xset->procEdit($inputDaughter);
      }
    }
    return ;
  }
  
  function multiTableGetDaughterForOid($oid){
    if(!$oid)
      return false;
    
    $sel = 'SELECT '.$this->typeField.' FROM '.$this->table.' WHERE KOID = \''.$oid.'\' AND LANG =\''.TZR_DEFAULT_LANG.'\' LIMIT 1';
    $typeM3 = getDB()->fetchOne($sel);
    
    $typedesc = $this->getTypeDesc([], $typeM3);
    if (empty($typedesc))
      return false;
    $typeOid = $this->getTypeOid($typedesc, $oid);
    
    if (!empty($typeOid))
      return $typeOid;
    
    $resType = $typedesc->datasource->procInput(array($typedesc->linkname=>$oid,'tplentry'=>TZR_RETURN_DATA));
    
    return $resType['oid'];
  }
  
  // pseudo procEdit
  // -> realise tous les controles comme un procInput sans faire de création
  //
  private function myProcEdit($ar=NULL){
    global $value;
    $mymess = '';
    $table = $this->xset;//->getTable();
    $p = new \Seolan\Core\Param($ar, array('tplentry'=>$table->getTable(),'_inputs'=>array(),'fieldssec'=>array(),'options'=>array()));
    $LANG_USER = \Seolan\Core\Shell::getLangUser();
    if(!$table->isTranslatable()) $LANG_DATA=TZR_DEFAULT_LANG;
    else $LANG_DATA=\Seolan\Core\Shell::getLangData($ar['LANG_DATA']);
    $tplentry = $p->get('tplentry');
    $fieldssec = $p->get('fieldssec','local');
    $delayed = $p->get('_delayed');
    $nolog = $p->get('_nolog','local');
    $track = $table->toLog();
    $options = $p->get('options');
    // Si option est un champ
    if(is_string($options)) $options=array();
    
    $moid=$p->get('fmoid','local');
    if(!empty($delayed)) $delayed='LOW_PRIORITY ';
    else $delayed='';
    $oid=$p->get('oid');
    $editfields=$p->get('editfields');
    $editbatch=$p->get('editbatch');
    if(is_array($oid)) {
      $P1=array();
      foreach($table->desc as $f => $o) {
        if(($editfields=='all') || in_array($f,$editfields)){
          $P1[$f]=$p->get($f);
          $P1[$f.'_HID']=$p->get($f.'_HID');
        }
      }
      foreach($oid as $i => $oid1) {
        if(!$editbatch){
          $ar1=array();
          foreach($table->desc as $f => $o) {
            if(($editfields=='all') || in_array($f,$editfields)){
              if(isset($P1[$f][$i]) || isset($P1[$f.'_HID'][$i])) {
                $ar1[$f]=$P1[$f][$i];
                $ar1[$f.'_HID']=$P1[$f.'_HID'][$i];
              }
            }
          }
        }else{
          $ar1=$P1;
        }
        $ar1['editfields']=$editfields;
        $ar1['editbatch']=$editbatch;
        $ar1['fieldssec']=$fieldssec;
        $ar1['oid']=$oid1;
        $ar1['options']=$options;
        $table->procEdit($ar1);
      }
      return;
    }
    $table->checkOID($oid,$ar,'procEdit');
    
    // Si la donnée n'existe pas dans la langue voulue, on la crée
    // a condition qu'elle existe en langue de base
    if(!$table->objectExists($oid, $LANG_DATA)) {
      //$k = new \Seolan\Core\Kernel;
      //$k->data_autoTranslate($oid, $LANG_DATA);
      \Seolan\Core\Logs::notice(get_class($this).'::myProcEdit autotranslate data check only mode');
      $mymess = 'data will be translated '.$oid;
    }
    
    // on genere la donnee en affichage pour calculer les differences
    if($track && empty($nolog) || $editbatch) $disp=$table->display(array('oid'=>$oid,'lang'=>$LANG_DATA,'tplentry'=>TZR_RETURN_DATA));

    $inputvalues=array();

    // archivage de l'ancienne donnée si nécessaire
    $archive = $p->get('_archive');
    $aupd=NULL;
    if($archive) {
      if($table->checkArchiveTable()) {
        $mymess .= 'data will archived';
        $rq = "UPD='$aupd', KOID='$oid', LANG= '$LANG_DATA'";
      }
    }
    if(empty($rq)) $rq = "UPD=NULL, KOID='$oid', LANG= '$LANG_DATA'";
    $where = "KOID='$oid' and LANG= '$LANG_DATA'";
    
    $inputs=$p->get('_inputs','local');
    $trace=array();
    foreach($table->orddesc as $foo => $k) {
      // Cerification des droits sur le champ
      if(!empty($fieldssec[$k]) && $fieldssec[$k]!='rw') continue;
      // Si on est dans une edition par lot, ne traiter que les champs concernés
      if(!empty($editbatch) && !in_array($k,$editfields)) continue;
      $v=&$table->desc[$k];
      if($p->is_set($k)||$p->is_set($k.'_HID')) {
        $value=$p->get($k);
        $value_hid=$p->get($k.'_HID');
        $options[$k]['oid']=$oid;
        $options[$k][$k.'_HID']=$value_hid;
        $options[$k]['old']=@$disp['o'.$k];
        $options[$k]['_track']=$track;
        $options[$k]['fmoid']=$moid;
        $options[$k]['editbatch']=$editbatch;
        $r1=$v->post_edit($value,$options[$k],$inputs);
        $nvalue=$r1->raw;
        $inputs[$k]=$r1;
        if($track && !empty($r1->trace)) {
          $trace=array_merge($trace,$r1->trace);
        }
        
        // cas ou on garde la valeur
        if ( $nvalue != 'TZR_unchanged' ) {
          if(is_array($nvalue) || ($nvalue!=NULL)) {
            $value=$nvalue;
            if(is_array($value) && (count($value)>1))  {
              $finalval='||';
              foreach($value as $o1 => $o2)
                $finalval=$finalval.$o2.'||';
              $rq.=' ,'.$k."= ?";
              $inputvalues[]=$finalval;
            } elseif(is_array($value))  {
              $rq.=' ,'.$k."= ?";
              $inputvalues[]=array_values($values)[0];
            } else {
              $rq.=' ,'.$k."= ?";
              $inputvalues[]=$value;
            }
          }
          else {
            if(!empty($r1->forcenull)) $rq.=' ,'.$k."=NULL";
            else $rq.=' ,'.$k."= ''";
          }
        }
      }
    }
    //    preparedUpdateQuery('UPDATE '.$delayed.$table->base.' set '.$rq.' where '.$where, $inputvalues);
    
    if ( $LANG_DATA == TZR_DEFAULT_LANG ) {
      $mymess = 'date will propagated on other langs';
      //$table->propagateOnOtherLangs($oid);
    }
    
    // on met une ligne dans les logs pour dire qu'il y a eu modification de cet objet
    
    // message ok
    if($GLOBALS['XSHELL']) $label=$GLOBALS['XSHELL']->labels->get_label(array('variable'=>'update_success'));
    $result['message']=$label['update_success'];
    $result['inputs']=$inputs;
    $result['mymess'] = $mymess;
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }
  
  // pseudo procInput
  // -> realise tous les controles comme un procInput sans faire de création
  //
  private function myProcInput($ar=NULL) {
    $table = $this->xset->getTable();
    $p=new \Seolan\Core\Param($ar, ['tplentry'=>$table,'_inputs'=>array(),'options'=>[]]);
    $tplentry = $p->get('tplentry');
    $j=$p->get('_nojournal');
    $journal=empty($j);
    $moid=$p->get('fmoid','local');
    $nolog=$p->get('_nolog','local');
    $all=$p->get('_allfields');
    $fieldssec=$p->get('fieldssec','local');
    $delayed=$p->get('_delayed');
    if(!empty($delayed)) $delayed='LOW_PRIORITY ';
    else $delayed='';
    $options=$p->get('options');
    // Si option est un champ
    if(is_string($options)) $options=array();
    $unique=$p->get('_unique');
    $updateifexists = $p->get('_updateifexists');
    $unique_val = array();
    $insert = true;
    // Nouvel oid puisqu'on cree une nouvelle data en langue par defaut
    $oid=$p->get('newoid'); // permet d'imposer le KOID
    if(!empty($oid) && empty($updateifexists)) {
      $cnt=getDB()->count("select COUNT(KOID) from {$table} where KOID='$oid' limit 1");
      if($cnt) {
        \Seolan\Core\Logs::notice('ModTable::MyprocInput', $oid.' already exist');
        return array('error'=>true,'message'=>$oid.' already exist');
      }
    }
    if(empty($oid)) 
      $oid=$this->xset->getNewOID();
    else 
      $this->xset->checkOID($oid,$ar,'procInput');
    
    // traitement des langues
    $translatable = $this->xset->getTranslatable();
    if(!$this->xset->isTranslatable()) 
      $lang=TZR_DEFAULT_LANG;
    elseif($translatable==3)
      $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
    else {
      $lang=\Seolan\Core\Shell::getLangData($p->get('LANG_DATA'));
      if($lang!=TZR_DEFAULT_LANG) 
	return array('error'=>true,'message'=>'Lang error');
    }
    
    $fields='KOID,LANG';
    $values="'$oid','".$lang."'";
    $nottorepeat=array('UPD');
    if($this->xset->fieldExists('OWN')) {
      $fields.=',OWN';
      $values.=",'".\Seolan\Core\User::get_current_user_uid()."'";
      $nottorepeat[]='OWN';
    }
    if($this->xset->fieldExists('CREAD')) {
      $fields.=',CREAD';
      $values.=",'".date('Y-m-d H:i:s')."'";
      $nottorepeat[]='CREAD';
    }
    if($this->xset->fieldExists('PUBLISH') && !$p->is_set('PUBLISH')) {
      $fields.=',PUBLISH';
      $values.=",'2'";
      $nottorepeat[]='PUBLISH';
    }
    $inputs=$p->get('_inputs','local');
    $inputvalues=[];
    foreach($this->xset->desc as $k => &$v) {
      if(!empty($fieldssec[$k]) && $fieldssec[$k]!='rw') continue;
      if(($p->is_set($k) || $p->is_set($k.'_HID') || !empty($all)) && !in_array($k, $nottorepeat)) {
        $value = $p->get($k);
        $value_hid = $p->get($k.'_HID');
        // traitement en post edit dans les cas simples
        if(!is_object($v)) \Seolan\Core\Shell::quit(array('message'=>'ModTable::MyProcInput: '.$table.':'.$k.' is not a valid field'));
        $options[$k]['oid']=$oid;
        $options[$k][$k.'_HID']=$value_hid;
        $options[$k]['fmoid']=$moid;
        $r1=$v->post_edit($value,$options[$k],$inputs);
        $inputs[$k]=$r1;
        $nvalue=$r1->raw;
        $fields .= ','.$k;
        if(!empty($unique) && in_array($k, $unique)) $unique_val[]="$k like '$nvalue'";
        // cas ou on garde la valeur
        $value=$nvalue;
        if(is_array($value) && (count($value)>1))  {
          $finalval='||';
          foreach($value as $o1=>$o2)
            $finalval.=$o2.'||';
          $values.=",'".$finalval."'";
        } elseif(is_array($value))  {
          $values.=',?';
          $inputvalues[]=array_values($value)[0];
        } else {
          if(!empty($r1->forcenull)) {
            $values.=",NULL";
          } else {
            $values.=',?';
            $inputvalues[]=$value;
          }
        }
      }
    }
    $query.=$fields.') values ('.$values.')';
    // vérification que l'enregistrement n'est pas existant dans le cas où on gère l'unicité
    if(!empty($unique) || !empty($updateifexists)) {
      if(!empty($unique) && !empty($unique_val)) $rs=getDB()->select("select * from {$table} where ".implode(' and ',$unique_val));
      else $rs=getDB()->select("select * from {$table} where KOID='$oid'");
      if($rs && $ors=$rs->fetch()) {
        $rs->closeCursor();
        $insert = false;
        if(!empty($updateifexists)) {
          $oid=$ors['KOID'];
          $ar['oid']=$oid;
          $eresult['message']='Entry will be updated';
          return \Seolan\Core\Shell::toScreen1($tplentry, $eresult);
        }
      }
    }
    if(!$insert) {
      return array('error'=>true,'message'=>'Not unique value');
    }
    // préparation des retours de résultats
    $result=[];
    $result['oid']=$oid;
    $result['inputs']=$inputs;
    $result['message']='Entry will be created';
    return \Seolan\Core\Shell::toScreen1($tplentry, $result);
  }
  
  public function adminRepairTranslations($ar=null){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ar['tplentry'] = TZR_RETURN_DATA;
    $ret=$this->xset->repairTranslations($ar);
    foreach($this->typeDescs as $typeDesc){
      $typeDesc->datasource->repairTranslations();
    }
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }
}
