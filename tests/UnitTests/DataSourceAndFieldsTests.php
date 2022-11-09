<?php
/* 
liste des test dans ce fichier:

  * Datasource et champs
  -testCreateDataSource
  -testCreateDSWithAllFields
  -testProcInput
  -testProcInputDefaultValues
  -testProcEdit
  -testAllDefaultValues
  -testDeleteLine
  -testDeleteField

  * Spécifiques aux champs
  -testLinks
  -testFiles
  -testMarkdown
  -testLabel
  -testDate
  -testDatime
  -testTime
  -testBoolean
  -testText
  -testRichText
  -testNormalizeRichText

  à faire/voir:

  -les traductions
  -les autres types de champs lien (country, DependentLink, datasources) 

  à faire:
  -testMultivaluable (la base est faite à savoir faire la différence entre le champ multivalué et les champ monovalué maitenant la question est qu'est-ce qu'on veut tester)
  -testText en Mode FO
  -password (pas sure qu'on puisse faire un test la dessus)  

 */

namespace UnitTests;

use \Seolan\Core\DataSource\DataSource;
use \Seolan\Core\Field\Field;
use \Seolan\Core\Module\Module;
use \Seolan\Core\Shell;
use \Seolan\Model\DataSource\Table\Table;
use \Seolan\Field\File\File;


/**
 * todo setup : create target table for links and derived (links, thesaurus)
 * 
 */
class DataSourceAndFieldsTests extends BaseCase
{
  static $tableLinkTargetName = 'TU_TABLE_FOR_TEST_LINKS';
  static $tablename = 'TU_TABLEWITHALLFIELDS';
  static $tableprocnewsource_name = 'TU_TABLEPROCNEWSOURCE';
  static $label_table = 'Label de TABLE PROC NEW SOURCE';
  static $tableNoTradName = "TU_tableNoTrad";
  static $tableTradName = "TU_tableTradAuto";
  static $tableTradWithoutAutoTradName = "TU_tableTradWithoutAutoTrad";
  static $optionsDefaultValue = ['date delay'=>'+50 days',
				 'hour delay'=>'+50 hours',
				 'text content'=>'default value',
				 'numeric content'=>40,
				 'boolean value'=>false];
  /**
   * On crée une table et on teste :
   * -qu'elle existe dans BASEBASE
   * -que les champs qui doivent l'être soit bien renseigner dans MSGS et dans DICT
   * @group main
   */
  public function testCreateDataSource()
  {

    $dt = Table::procNewSource([
      "translatable" => "0",
      "publish" => "0",
      "auto_translate" => "0",
      'tag'=>1,
      "btab" => static::$tableprocnewsource_name,
      "bname" => [TZR_DEFAULT_LANG => static::$label_table]
    ]);

    $boid = $dt["boid"];

    $this->assertSQLTableExists(static::$tableprocnewsource_name); //On vérifie que la table existe
    $this->assertSQLColumnExists('KOID', static::$tableprocnewsource_name); //il faut vérifier que la table contient bien les colone KOID et LANG
    $this->assertSQLColumnExists('LANG', static::$tableprocnewsource_name);
    $this->assertFieldOk('OWN', static::$tableprocnewsource_name);
    $this->assertFieldOk('UPD', static::$tableprocnewsource_name);
    $this->assertFieldOk('TAG', static::$tableprocnewsource_name);

    $bparam = [];
    $json = \Seolan\Core\Options::rawToJSON($bparam, TZR_ADMINI_CHARSET); //si Bparam est tableau vide alors rawToJSON renvoi {"@comment@":"JSON encoded options","@version@":"2"}


    $this->assertSQLRowIsOk('BASEBASE', ['BOID' => $boid] /* clé primaire */, [
      'BTAB' => static::$tableprocnewsource_name, 'BNAME' => static::$label_table, 'BCLASS' => "\Seolan\Model\DataSource\Table\Table",
      'AUTO_TRANSLATE' => '0', 'TRANSLATABLE' => '0', 'NOTTOREPLI' => '0', 'LOG'  => '1',  'BPARAM' => $json
    ]);  //vérifier que la ligne existe et quel contient bien les colone et les champs demander.

    $this->assertSQLRowIsOk('AMSG', ['MOID' => $boid, 'MLANG' => TZR_DEFAULT_LANG], ['MTXT' => static::$label_table]);
  }
  /**
   * @group main
   */
  public function testCreateDSWithAllFields()
  {
    Table::procNewSource([
      "translatable" => "0",
      "publish" => "0",
      "auto_translate" => "0",
      "btab" => static::$tablename,
      "bname" => [TZR_DEFAULT_LANG => static::$tablename]
    ]);

    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $field_list = Field::$_fields;
    $field_list = array_keys($field_list);
    $allfields =  [];
    $todofields = [];
    foreach ($field_list as $field) {
      $parts = explode('\\', $field);
      $field_name = $parts[count($parts) - 1];
      // on crée 2 versions d'un même champ l'un mono valué, l'autre multivalué.
      $this->createFields($ds, $field, $field_name, $allfields, $todofields);
    }

    foreach ($allfields as $fn) {
      $this->assertFieldExists($ds, $fn);
    }

    return $allfields;
    
  }
  /**
   * Des insertions de champs dans une table
   * @group main
   * @depends testCreateDSWithAllFields
   */
  public function testProcInput($allfields)
  {
    //test des ProcInput standard sur les champs crée dans testCreatDSWithAllFields
    // corrections de certaines prop
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $insert = [];
    // on récupère des valeurs de test à insérer
    foreach ($allfields as $fname) {
      $insert[$fname] = $this->getFieldTestValue($ds, $fname);
    }

   
    $r = $ds->procInput($insert);
    $this->assertArrayHasKey('oid', $r);
    $this->assertObjectExists($r['oid']);
    //vérifier que la ligne existe avec les bonne valeur.
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $r['oid'], 'LANG' => TZR_DEFAULT_LANG], $insert); 

    //Test des procInput avec oid

    /*1 initialiser une variable avec un oid valide.
    vérifier que la ligne n'existe pas en base
    faire l'insertion avec 'newoid' et une valeur donnée pour un champ texte de la table
    vérifier que l'objet existe en base*/


    //l'oid est définie manuelement. On Impose un oid comme le code le permet et on vérifies que ça fonctionne 
    //l'oid n'existe pas et création 
    $oid=static::$tablename.":1";//initialiser une variable avec un oid valide. L'oid doit obligatoir contenir le nom de la table 
  
    //vérifier que la ligne n'existe pas en base
    $select_log = getDB()->select("SELECT * FROM ".static::$tablename." WHERE `KOID`= ?", [$oid]);
    $this->assertEquals('0', $select_log->rowCount(), "Impossible d'affirmer que la ligne n'existe pas en base. Donc l'oid manuel n'est pas bon");

    //faire l'insertion avec 'newoid' et une valeur donnée pour un champ texte de la table
    $insertWithOid=[];
    $insertWithOid['newoid']=$oid; 
    $insertWithOid['ShortTextMono']="insertWithOidText";
    $r=$ds->procInput($insertWithOid);
    
    //vérifier que l'objet existe en base
    unset($insertWithOid['newoid']);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' =>  $oid, 'LANG' => TZR_DEFAULT_LANG], $insertWithOid); //vérifier que la ligne existe avec les bonne valeur.

   /* 2 lire en base la date de mise à jour de la ligne que l'on vient de créer
      faire un procInput avec newoid=l'oid de l'étape d'avant, updateif exists, etc et une nouvelle valeur pour le champ cité au dessus
      vérifier que la ligne d'oid donnée comporte bien la nouvelle valeur du champ, que la date de mise à jour a changé*/
      $select_log = getDB()->select("SELECT * FROM ".static::$tablename." WHERE `KOID`= ?", [$oid]);
      $select_log=$select_log->fetchAll();
      $PreUpdateDate=$select_log[0]['UPD'];

          //ProcInput avec newoid définie et updateifexists. l'oid viens du procInput des champ plus haut.
    // oid existe et modification
    //faire un procInput avec newoid=l'oid de l'étape d'avant, updateif exists, etc et une nouvelle valeur pour le champ cité au dessus
    $insertWithOid=[];
    $insertWithOid['newoid']=$oid;
    $insertWithOid['_updateifexists']=true;
    $insertWithOid['ShortTextMono']="insert2_OID";
 
    sleep(1); //on s'arrête pour 2 secondes sinon a va se retrouver avec la même date de mise a jour
    $r2=$ds->procInput($insertWithOid);

    $this->assertEquals($r['oid'], $r2['oid'], "procInput with newoid et _updateifexists=true ");

    //vérifier que la ligne d'oid donnée comporte bien la nouvelle valeur du champ, que la date de mise à jour a changé
    unset($insertWithOid['newoid']);
    unset($insertWithOid['_updateifexists']);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' =>  $r2['oid'], 'LANG' => TZR_DEFAULT_LANG], $insertWithOid); //vérifier que la ligne existe avec les bonne valeur.
    
    $select_log = getDB()->select("SELECT * FROM ".static::$tablename." WHERE `KOID`= ?", [$oid]);
    $select_log=$select_log->fetchAll();
    $NowUpdateDate=$select_log[0]['UPD'];

    $this->assertNotEquals($PreUpdateDate,$NowUpdateDate, "la mise a jour de la date n' a eu lieu");


   /* 3 initialiser une nouvelle valeur d'oid
    vérifier qu'aucune ligne ne correspond en base
    faire un procInput avec newoid, updateif exists, etc et une nouvelle valeur pour le champ cité au dessus
    (exactement les mêmes paramèrtes que au 2, newoid et valeur du champ excepté).
    vérifier que la nouvelle ligne a bien été créée */
    $insertWithOid=[];
    $insertWithOid['newoid']=static::$tablename.":2";
    $insertWithOid['_updateifexists']=true;
    $insertWithOid['ShortTextMono']="insert3_OID";
 
    //vérifier qu'aucune ligne ne correspond en base
    $this->assertNotEquals($r['oid'],  $insertWithOid['newoid'], "procInput with newoid et _updateifexists=true ");
    $select_log = getDB()->select("SELECT * FROM ".static::$tablename." WHERE `KOID`= ?", [$insertWithOid['newoid']]);
    $this->assertEquals('0', $select_log->rowCount(), "Vérification que l'oid est unique "); 

    $r2=$ds->procInput($insertWithOid);

    unset($insertWithOid['newoid']);
    unset($insertWithOid['_updateifexists']);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' =>  $r2['oid'], 'LANG' => TZR_DEFAULT_LANG], $insertWithOid); //vérifier que la ligne existe avec les bonne valeur.
  
    return ['allfields' => $allfields, "oid" => $r['oid']]; //retourne la liste des champ ainsi que l'oid sur le quel le champ on été crée pour le besoin dest test suviant.
  }
  /**
   * Insertion : prise en compte des valeurs par defaut pour quelques champs
   * voir aussi testAllDefaultValues
   * @depends testProcInput
   */
  function testProcInputDefaultValues($allfieldsandoid){

    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    //    static::$tools->traceObject($ds->getField('StringSetMono'));

    $ret = $ds->procInput([]); // insertion ligne 'vide' 
    
    $totest = ['StringSet','Boolean','Real','Rating','ShortText']; // pour Link voir tests spécifiques champs liens

    foreach($totest as $fn){

      $mono = $ds->getField("{$fn}Mono");
      $ftype = $mono->ftype;
      $multi = $multidefault = null;
      
      $monodefault = $mono->default;
      if ($ftype::isMultiValuable()){
	$multi = $ds->getField("{$fn}Multi");
	$multidefault = $multi->default;
      }
      $this->assertTrue(isset($mono->default), "field {$mono->field} does not have default value");
      if ($multi){
	$this->assertTrue(isset($multi->default), "field {$multi->field} does not have default value");
      }
      if ($mono instanceof \Seolan\Field\Boolean\Boolean){ 
	if ($mono->default === false)
	  $monodefault = '2';
	else
	  $monodefault = '1';
      } 
      if ($multi){
	$this->assertSQLRowIsOk(static::$tablename,
				['KOID'=>$ret['oid'],
				 'LANG'=>TZR_DEFAULT_LANG],
				[$mono->field=>$monodefault,
				 $multi->field=>$multidefault]);
      } else {
      	$this->assertSQLRowIsOk(static::$tablename,
				['KOID'=>$ret['oid'],
				 'LANG'=>TZR_DEFAULT_LANG],
				[$mono->field=>$monodefault]);
      }

    }
    
  }
  /**
   * des update de champs
   * @depends testProcInput
   * @group main
   */
  public function testProcEdit(array $allfieldsAndOid)  {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    foreach ($allfieldsAndOid['allfields'] as $fname) {
      $update[$fname] = $this->getFieldTestValue($ds, $fname, ['date delay'=>'+4 days',
							       'hour delay'=>'+4 hours',
							       'text content'=>'update',
							       'numeric content'=>8,
							       'boolean value'=>1]);
    }
    $update["oid"] = $allfieldsAndOid['oid'];
    $r = $ds->procEdit($update);
    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $r['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);
    return $allfieldsAndOid;
  }
  
  /**
   * test spécifique pour les champs liens
   * @depends testProcEdit
   * @group main
   */
  public function testLinks(array $allfieldsAndOid) {

    list($ri, $linkVals) = $this->createTableLink();
    
    // on met à jour les champs liens avec les valeurs ajoutées dessus
  
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    // mise à jour des champs LinkMono et LinkMulti
    $fieldMono = $ds->getField('LinkMono');
    $fieldMono->set_target(static::$tableLinkTargetName);
    $fieldMulti = $ds->getField('LinkMulti');
    $fieldMulti->set_target(static::$tableLinkTargetName);

    $update['LinkMono'] = $ri[0]["oid"];
    $update['LinkMulti']= [$ri[0]["oid"], $ri[2]["oid"]]; 

    $update["oid"] = $allfieldsAndOid['oid'];

    $rU = $ds->procEdit($update);
    
    // raw values en base
    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    $filter_display['selectedfields'] = ['LinkMono', 'LinkMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    // mono 
    $this->assertEquals($fiche['oLinkMono']->text, $linkVals[0], "Vérification du ->text du champ lien");
    
    // multi ? voir si pb d'ordre ?
    $this->assertEquals("{$linkVals[0]}, {$linkVals[2]}", $fiche['oLinkMulti']->text , "Vérification du ->text du champ lien multivalué");
    $this->linkVals = $linkVals[0];
  }
  /**
   * valeur par défaut des champs liens
   * @depends testLinks
   */
  function testLinksDefaultValues(){

    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $monodefault = getDb()->fetchOne("select koid from ".static::$tableLinkTargetName." order by rand() limit 1");
    $multidefault = getDb()->fetchOne("select koid from ".static::$tableLinkTargetName." order by rand() limit 1");

    // à voir comment on mets plusieurs valeurs ? format raw, separées par des , ?
    // du coup ça teste le procEditField, la mise à jour du dict et du sql 
    $ds->procEditField(['field'=>'LinkMono',
			       'options'=>['default'=>$monodefault]]);
    $ds->procEditField(['field'=>'LinkMulti',
			'options'=>['default'=>$multidefault]]);


    //static::$tools->sqldump('desc '.static::$tablename);
    //static::$tools->sqldump('select field, json_value(dparam, "$.default.value") from DICT where dtab=? and field like ?',[static::$tablename, 'Link%']);
    
    // ajout de la valeur par defaut 
    
    DataSource::clearCache();

    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $mono = $ds->getField('LinkMono');
    $multi = $ds->getField('LinkMulti');
    
    $this->assertTrue(!empty($mono->default), "LinkMono a une valeur par défaut");
    $this->assertTrue(!empty($multi->default), "LinkMulti a une valeur par défaut");

    // insertion
    $ret = $ds->procInput([]);

    $this->assertNotEmpty($ret['oid']);

//    static::$tools->sqldump("select LinkMono, LinkMulti from ".static::$tablename." where koid = ?", [$ret['oid']]);
    
    $this->assertSQLRowIsOk(static::$tablename,
			    ['KOID'=>$ret['oid'],'LANG'=>TZR_DEFAULT_LANG],
			    ['LinkMono'=>$monodefault,
			     'LinkMulti'=>$multidefault]);
    
  }
  /**
   * champ markdonw
   * -> la représentation interne, display et edition des liens 
   * incluant des alias sous forme [alias],[moid,oid,alias]
   * @depends testProcInput 
   */
  function testMarkdown($fieldsandoids){
    // test des transformations de liens
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $field = $ds->getField('MarkdownTextMono');
    // le gestionnaire de rubrique du menu gauche existe toujours
    // et avec un 'home' en général,
    $itadmin = Module::singletonFactory(XMODBACKOFFICEINFOTREE_TOID);
    $oidhome = getDB()->fetchOne("select koid from {$itadmin->table} where alias=?", ['home']);
    $oidtop = getDB()->fetchOne("select koid from {$itadmin->table} where alias=?", ['top']);
    $field->arrow2link = true;
    
    $this->assertTrue(isset($oidhome), 'Prérequis ; l \'alias "home" du bo infotree existe');
    $field->aliasmodule = $itadmin->_moid;
    $liennorm = "{$itadmin->_moid},{$oidhome},home";
    $liennorm2 = "{$itadmin->_moid},{$oidtop},top";
    $texts = [
      ['[tests liens]([home])',"[tests liens]([{$liennorm}])"],
      ['[tests liens]([home] "L\'accueil")',"[tests liens]([{$liennorm}] \"L'accueil\")"],
      ["[tests liens]([home] \"L'accueil 1\") et un autre [tests liens top]([top]) et des choses sans [home]","[tests liens]([{$liennorm}] \"L'accueil 1\") et un autre [tests liens top]([{$liennorm2}]) et des choses sans [home]"],
      ['[tests liens inconnu]([home_inconnu])',"[tests liens inconnu]([{$itadmin->_moid},x,home_inconnu])"],
      ['[tests liens inconnu title]([inconnu_title] "inconnu")', "[tests liens inconnu title]([{$itadmin->_moid},x,inconnu_title] \"inconnu\")"],
    ];
    // normalisation des liens
    $mdtext = $texts[0][0];
    $mdtextnorm = $texts[0][1];
    $field->_normalizelinks($mdtext);
    $this->assertEquals($mdtext, $mdtextnorm, "Transformation de l'alias d'un lien en moid,oid");

    $mdtext = $texts[3][0];
    $mdtextnorm = $texts[3][1];
    $field->_normalizelinks($mdtext);
    $this->assertEquals($mdtext, $mdtextnorm, "Transformation de l'alias (INCONNU) d'un lien en moid,oid, alias");

    $mdtext = $texts[4][0];
    $mdtextnorm = $texts[4][1];
    $field->_normalizelinks($mdtext);
    $this->assertEquals($mdtext, $mdtextnorm, "Transformation de l'alias + title (INCONNU) d'un lien en moid,oid, alias");
    
    $mdtext = $texts[1][0];
    $mdtextnorm = $texts[1][1];
    $field->_normalizelinks($mdtext);
    $this->assertEquals($mdtext, $mdtextnorm, "Transformatoin de l'alias d'un lien avec title en moid,oid");

    $mdtext = $texts[2][0];
    $mdtextnorm = $texts[2][1];
    $field->_normalizelinks($mdtext);
    $this->assertEquals($mdtext, $mdtextnorm, "Transformatoin des alias de plusieurs liens en moid,oid");

    // edition
    foreach([[0, "Transformation inverse de l'alias d'un lien en moid,oid"],
	     [3, "Transformation inverse de l'alias (inconnu) d'un lien en moid,oid"],
	     [1, "Transformatoin de l'alias d'un lien avec title en moid,oid"],
	     [2, "Transformatoin des alias de plusieurs liens en moid,oid"]]
      as list($it, $mess)){
      $mdtext = $texts[$it][0];
      $mdtextnorm = $texts[$it][1];
      $field->_mklinks3($mdtextnorm);
      $this->assertEquals($mdtext, $mdtextnorm, $mess);
    }

    // postedit, juste pour vérifier 
    foreach([[2, "Post edit"],
	     [3, "Post edit alias inconnu"]]
      as list($it, $mess)){
      $mdtext = $texts[$it][0];
      $mdtextnorm = $texts[$it][1];
      $r = $field->post_edit($mdtext);
      $decoded = json_decode($r->raw);
      $this->assertEquals($mdtextnorm, $decoded->markdown, $mess);
    }

    // en affichage : interne -> url
    foreach([[0, "Transformation interne url", 'home'],
	     [1, "Transformatoin interne url (lien avec title)",'home'],
    ] as list($it, $mess, $alias)){
      $mdtext = $texts[$it][0];
      $mdtextnorm = $texts[$it][1];
      $preview = $field->generatePreview($mdtextnorm);
      $field->_mklinks2($preview);
      // comme dans Core\Alias::mklink2
      $link = $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&amp;class=\Seolan\Module\InfoTree\InfoTree&amp;function=viewpage&amp;template=Module/InfoTree.viewpage.html&amp;moid='.$itadmin->_moid.'&amp;tplentry=it&amp;alias='.$alias;
      $link = preg_quote($link);
      $this->assertEquals(1, preg_match("@{$link}@", $preview), $mess."\n\t link : '{$link}'\n\t text norm : '{$mdtextnorm}'=>'{$preview}'  ");
    }
  }

    
  /**
   * test spécifique pour les champs fichiers
   *
   * @depends testProcEdit
   */
  public function testFiles(array $allfieldsAndOid)
  {

    //on crée un fichier .txt
    $txtName = 'testUnit' . date('d_m_Y_H_i_s') . '.txt';
    $text = '[ ' . date('d/m/Y H:i:s') . ' ]';
    $text .= "\t->\t TXT for field File";
    file_put_contents($txtName, $text);

    //on crée une image .svg
    $svgName1 = 'testUnit_img' . date('d_m_Y_H_i_s') . '.svg';
    $image_text = '<?xml version="1.0" encoding="utf-8"?>
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="300" height="200">
                  <rect width="100" height="80" x="0" y="70" fill="purple" />
                  <line x1="5" y1="5" x2="250" y2="95" stroke="red" />
                  <circle cx="90" cy="80" r="50" fill="pink" />
                  <text x="150" y="60">svg for test field File </text>
                </svg>';
    file_put_contents($svgName1, $image_text); 

    //on crée une image .svg
    $svgName2 = 'testUnit_img2_' . date('d_m_Y_H_i_s') . '.svg';
    $image_text2 = '<?xml version="1.0" encoding="utf-8"?>
                 <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="300" height="200">
                   <text x="150" y="60">svg 2 for test field File </text>
                 </svg>';
    file_put_contents($svgName2, $image_text2);

    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $field = $ds->getField('FileMono');
    $type['FileMono'] = str_replace('/', '\/', mime_content_type($txtName));
    $update['FileMono'] = $txtName;
    $field = $ds->getField('FileMulti');
    $type['FileMulti'] = [str_replace('/', '\/', mime_content_type($svgName1)), str_replace('/', '\/', mime_content_type($svgName2))];
    $update['FileMulti'] = [$svgName1, $svgName2];

    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);
    unset($update["oid"]);

    $filter_display['selectedfields'] = ['FileMono', 'FileMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    // vérification du champ fichier mono valué
    $contents = file_get_contents($fiche['oFileMono']->filename);
    $this->assertTZRFileExists($fiche['oFileMono']);

    $this->assertEquals($text, $contents, "Vérification du contenu du fichier du champ FileMono");
    // json du mono valué : h w éventuellement file (oid) name title
    
    // vérification du fichier multivalué
    $this->assertFileExists($fiche['oFileMulti']->dirname);
    
    $selectJson = getDB()->fetchOne("SELECT FileMulti FROM " . static::$tablename . " WHERE `KOID`= ? and `LANG`= ? ", [$rU["oid"], TZR_DEFAULT_LANG]);
    $json_decoded = json_decode($selectJson, true);
    $this->assertEquals(str_replace(static::$tablename . ":", "", $rU["oid"]), $json_decoded['dir'], "Vérification de la prorpriété dir du json");
    $this->assertEquals(str_replace("\/", "/", $type['FileMulti'][0]), $json_decoded['files'][0]['mime'], "Vérification du type mime du fichier sauvegarder dans le json");
    $this->assertEquals(str_replace("\/", "/", $type['FileMulti'][1]), $json_decoded['files'][1]['mime'], "Vérification du type mime du fichier sauvegarder dans le json");
    $this->assertEquals($svgName1, $json_decoded['files'][0]['name'], "Vérification de la propriété name du json");
    $this->assertEquals($svgName2, $json_decoded['files'][1]['name'], "Vérification de la propriété name du json");
    // en dernier, les url => risque de skip
    // workarround sur la securité des fichiers
    
    if ($GLOBALS['TZR_SECURE']['_all'] === true){
      
      $this->markTestSkipped('Secure all active, file contents tests skipped');
      
    } else {

      $this->assertEquals($text,
			  $foo = file_get_contents($GLOBALS['HOME_ROOT_URL'].$fiche['oFileMono']->url),
			  "Vérification du contenu du champ File par l'url ({$fiche['oFileMono']->url})");

      $furl = $GLOBALS['HOME_ROOT_URL'].$fiche['oFileMulti']->catalog[0]->url;
      $this->assertEquals($image_text,
			  file_get_contents($furl),
			  "Vérification accès par url ($furl) du champ FileMulti");

      $furl = $GLOBALS['HOME_ROOT_URL'] . $fiche['oFileMulti']->catalog[1]->url;
      $this->assertEquals($image_text2,
			  file_get_contents($furl),
			  "Vérification accès par url ($furl) du champ FileMulti");
    }
  }

  /**
   * test spécifique pour les champs ensemble de chaine
   * @depends testProcEdit
   */
  public function testStringSet(array $allfieldsAndOid) {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $field = $ds->getField('StringSetMono');
    $value = 'update StringSet ';
    $soid = 'tusoid';
    for($i=1; $i<=10; $i++){
      $field->newString("{$value} {$i}", "{$soid}{$i}", $i);
    }
    
    for($i=1; $i<=10; $i++){
      $this->assertSQLRowIsOk('SETS',
			      ['SOID' => "{$soid}{$i}",
			       'SLANG' => TZR_DEFAULT_LANG,
			       'STAB' => static::$tablename,
			       'FIELD' => 'StringSetMono'],
			      ['SORDER' => $i,
			       'STXT' => "{$value} {$i}" 
      ]);
    }
    
    \Seolan\Field\StringSet\StringSet::clearCache();
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    
    $ui = rand(2,9);
    $usoid = "{$soid}{$ui}";
    $uval = "{$value} {$ui}";

    $update["oid"] = $allfieldsAndOid['oid'];
    $update["StringSetMono"] = $usoid;

    $ds->procEdit($update);

    $filter_display['selectedfields'] = ['StringSetMono'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals($uval, $fiche['oStringSetMono']->text, "string set raw");
    
  }
  /**
   * test spécifique pour les champs date
   *
   * @group testDate
   * @depends testProcEdit
   */
  public function testDate(array $allfieldsAndOid) {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $date1 = $ds->getField('DateMono'); // on récupère le champ
                                        // le champ date n'est pas multivaluable.

    // Date en format FR
    $update["DateMono_FMT"] = "d/m/Y"; // format de la date
    $update["LANG_USER"] = "FR";
    $update["LANG_DATA"] = "FR";
    $update['DateMono'] = '30/10/2020';

    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals('2020-10-30', $fiche["oDateMono"]->raw);
    $this->assertEquals('30/10/2020', $fiche["oDateMono"]->text);
    $this->assertEquals('30/10/2020', $fiche["oDateMono"]->html);
    unset($update["oid"]);
    unset($update["DateMono_FMT"]);
    unset($update["LANG_USER"]);
    unset($update["LANG_DATA"]);
    $update['DateMono'] = '2020-10-30'; // la date en base de donnée est stocker au format aaaa-mm-dd
    $this->assertSQLRowIsOk(static::$tablename, [
        'KOID'=>$rU['oid'],
        'LANG'=>TZR_DEFAULT_LANG
    ], $update); // on vérifie que la date est bien stocker.
    $update["DateMono_FMT"] = 'd/m/Y'; // format de la date

    $update["LANG_USER"] = 'FR';
    $update["LANG_DATA"] = 'FR';
    $update['DateMono'] = '2020-11-12'; // valeur du champs

    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals('2020-11-12', $fiche["oDateMono"]->raw);
    $this->assertEquals('12/11/2020', $fiche["oDateMono"]->text);
    $this->assertEquals('12/11/2020', $fiche["oDateMono"]->html);
    unset($update["oid"]);
    unset($update["DateMono_FMT"]);
    unset($update["LANG_USER"]);
    unset($update["LANG_DATA"]);

    $this->assertSQLRowIsOk(static::$tablename, [
        'KOID'=>$rU['oid'],
        'LANG'=>TZR_DEFAULT_LANG
    ], $update);

    // date particulière

    // today
    $update['DateMono'] = 'today'; // valeur du champs
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);
    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals(date('Y-m-d'), $fiche["oDateMono"]->raw);
    $this->assertEquals(date('d/m/Y'), $fiche["oDateMono"]->text);
    $this->assertEquals(date('d/m/Y'), $fiche["oDateMono"]->html);

    // 0000-00-00
    $update['DateMono'] = '0000-00-00'; // valeur du champs
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);
    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals(TZR_DATE_EMPTY, $fiche["oDateMono"]->raw);
    $this->assertEquals('', $fiche["oDateMono"]->text); // vide pour ce cas
    $this->assertEquals('', $fiche["oDateMono"]->html); // vide pour ce cas

    // empty
    $update['DateMono'] = 'empty'; // valeur du champs
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);
    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals(TZR_DATE_EMPTY, $fiche["oDateMono"]->raw);
    // $this->assertEquals('0000-00-00', $fiche["oDateMono"]->text); //vide pour ce cas
    // $this->assertEquals('0000-00-00', $fiche["oDateMono"]->html); //vide pour ce cas

    // no value
    $update['DateMono'] = ''; // valeur du champs
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);
    $filter_display['selectedfields'] = [
        'DateMono',
        'DateMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals(TZR_DATE_EMPTY, $fiche["oDateMono"]->raw);
    // $this->assertEquals('0000-00-00', $fiche["oDateMono"]->text); //vide pour ce cas
    // $this->assertEquals('0000-00-00', $fiche["oDateMono"]->html); //vide pour ce cas

    // Date en format GB
    /*
     * $date1 = $ds->getField('DateMono'); //on récupère le champ
     *
     * $update["DateMono_FMT"]='d/m/Y'; //format de la date
     *
     * $update["LANG_USER"]='GB';
     * $update["LANG_DATA"]='GB';
     * $update['DateMono']='2020-10-07'; //valeur du champs
     *
     *
     * $update["oid"]=$allfieldsAndOid['oid'];
     * $rU=$ds->procEdit($update);
     *
     * $filter_display['selectedfields']=['DateMono','DateMulti'];
     * $filter_display["oid"]=$allfieldsAndOid['oid'];
     * $fiche=$ds->display($filter_display);
     * ["_selectedlangs"]=> array(2) { [0]=> string(2) "FR" [1]=> string(2) "GB"
     *
     * $this->assertEquals('2020-10-07', $fiche["oDateMono"]->raw);
     * $this->assertEquals('07/10/2020', $fiche["oDateMono"]->text);
     * $this->assertEquals('07/10/2020', $fiche["oDateMono"]->html);
     * unset($update["oid"]);
     * unset($update["DateMono_FMT"]);
     * unset($update["LANG_USER"]);
     * unset($update["LANG_DATA"]);
     *
     * $this->assertSQLRowIsOk(static::$tablename, ['KOID' =>$rU['oid'], 'LANG'=>TZR_DEFAULT_LANG],$update); //on vérifie que la date est bien stocker.
     */
  }

  /**
   * test spécifique pour les champs dates
   * @group testDate
   * @depends testProcEdit
   */
  public function testDateTime(array $allfieldsAndOid)
  {
    //DateTime

    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $update["DateTimeMono_FMT"]["date"] = 'd/m/Y'; //format de la date

    $update["LANG_USER"] = 'FR';
    $update["LANG_DATA"] = 'FR';
    $update['DateTimeMono']["date"] = "2020-12-20"; //valeur du champs
    $update['DateTimeMono']["hour"] = "08:30:00";

    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['DateTimeMono', 'DateTimeMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);


    $this->assertEquals('2020-12-20 08:30:00', $fiche["oDateTimeMono"]->raw);
    $this->assertEquals('20/12/2020 08:30:00', $fiche["oDateTimeMono"]->text);
    $this->assertEquals('20/12/2020 08:30:00', $fiche["oDateTimeMono"]->html);
    unset($update["oid"]);
    unset($update["DateTimeMono_FMT"]);
    unset($update["LANG_USER"]);
    unset($update["LANG_DATA"]);
    $update['DateTimeMono'] = '2020-12-20 08:30:00';

    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);
  }
  /**
   * test spécifique pour les champs dates
   * @group testDate
   * @depends testProcEdit
   */
  public function testTimestamp(array $allfieldsAndOid)
  {
    //timestamp, date de mise a jour

    $my_timestamp = date('Y-m-d H:i:s');
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $update['TimestampMono'] = $my_timestamp;
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['TimestampMono', 'TimestamMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals($my_timestamp, $fiche["oTimestampMono"]->raw);
    $this->assertEquals(date('d/m/Y H:i:s', strtotime($my_timestamp)), $fiche["oTimestampMono"]->text);
    $this->assertEquals(date('d/m/Y H:i:s', strtotime($my_timestamp)), $fiche["oTimestampMono"]->html);

    unset($update["oid"]);
    unset($update["DateTimeMono_FMT"]);
    unset($update["LANG_USER"]);
    unset($update["LANG_DATA"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);
  }
  /**
   * test spécifique pour les champs dates
   * @group testDate
   * @depends testProcEdit
   */
  public function testTime(array $allfieldsAndOid)
  {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    //Champ Time ou Heure/Durée
    $update['TimeMono'] = "08:31:30";
    $update["oid"] = $allfieldsAndOid['oid'];


    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['TimeMono', 'TimeMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals("08:31:30", $fiche["oTimeMono"]->raw);
    $this->assertEquals("08:31:30", $fiche["oTimeMono"]->text);
    $this->assertEquals("08:31:30", $fiche["oTimeMono"]->html);
    //pourquoi en console je peut pas mettre 08:31:30 mais je suis obliger de chosir entre 08:31 ou 08:32 par contre la avec du code je peut ?
    $this->assertEquals(8, $fiche["oTimeMono"]->hour);
    $this->assertEquals(31, $fiche["oTimeMono"]->minute);
    $this->assertEquals(30, $fiche["oTimeMono"]->second);
  }
  /**
   * test spécifique pour les champs logique
   * @depends testProcEdit
   */
  public function testBoolean(array $allfieldsAndOid){
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    //test NON
    $update['BooleanMono'] = "2";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['BooleanMono', 'BooleanMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals("2", $fiche["oBooleanMono"]->raw);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->html);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->text);

    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    //test OUI
    $update['BooleanMono'] = "1";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['BooleanMono', 'BooleanMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals("1", $fiche["oBooleanMono"]->raw);
    $this->assertEquals("Oui", $fiche["oBooleanMono"]->text);
    $this->assertEquals("Oui", $fiche["oBooleanMono"]->html);


    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    //test 777
    $update['BooleanMono'] = "777";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['BooleanMono', 'BooleanMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals("2", $fiche["oBooleanMono"]->raw);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->text);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->html);

    unset($update["oid"]);
    $update['BooleanMono'] = '2';
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    //test on

    $update['BooleanMono'] = "2";
    $options['BooleanMono_HID'] = 1;
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update, $options);

    $filter_display['selectedfields'] = ['BooleanMono', 'BooleanMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);
    $this->assertEquals("2", $fiche["oBooleanMono"]->raw);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->text);
    $this->assertEquals("Non", $fiche["oBooleanMono"]->html);

    unset($update["oid"]);
    unset($update['_HID']);
    $update['BooleanMono'] = '2';
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    // tests controle des options
    $field = clone($ds->getField('BooleanMono')); // pour pas exploser le bon
    $ftype = '\Seolan\Field\Boolean\Boolean';
    $fcount = $field->fcount;
    $forder = $field->forder;
    $comp = $field->compulsory;
    $brows = $field->browsable;
    $trans = $field->translatable;
    $que = $field->queryable;
    $multiv = $field->multivaluable;
    $publi = $field->published;
    $target = $field->target;
    $label = $field->label;
    $isok = function($options)use($field,$ftype,$fcount,$forder,$comp,$que,$brows,$trans,$multiv,$publi,$target,$label) {
      return \Seolan\Field\Boolean\Boolean::fieldDescIsCorrect($field->field, //!! le nom
 							       $ftype,
							       $fcount,
							       $forder,
							       $comp,
							      $que,
							       $brows,
							       $trans,
							       $multiv,
							       $publi,
							      $target,
							       $label,
							       $options);
    };
    // fielddesciscorrect à reprendre
    // $options = ['default'=>3];
    // $res = $isok($options);
    // $this->assertFalse($res, "rejet des valeurs par défaut <> des valeurs vrai / faux");
    // $options = ['default'=>45, 'TRUE'=>45, 'FALSE'=>0];
    // $res = $isok($options);
    // $this->assertTrue($res, "surcharge des valeurs vrai/faux");
  }

  /**
   * test Champ texte
   * - nettoyage des javascripts, option raw, BO, FO
   * - transformation des liens (@todo)
   * - enregistrement en xml (toxml)
   *
   * @depends testProcEdit
   */
  public function testText(array $allfieldsAndOid) {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $update['TextMono'] = "<b>LongText</b>";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, [
        'KOID'=>$rU['oid'],
        'LANG'=>TZR_DEFAULT_LANG
    ], $update);

    $filter_display['selectedfields'] = [
        'TextMono',
        'TextMulti'
    ];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $update['TextMono'] = "<script>alert('msg');</script>"; // le javascript n'est pas enlevé en BO
    $update["oid"] = $allfieldsAndOid['oid'];

    // comment passer en mode fo ?
    // define('TZR_ADMINI',0); je peut pas redefinir une constante
    // = pb : on ne peut pas moocker les méthodes statiques
    // on ne peut pas redéfinir des constantes
    // ? l'extension runkit ? PEC vieux ?

    // runkit_constant_remove('TZR_ADMINI');
    // define('TZR_ADMINI',0);

    // suppression des codes html et js eventuel lorsqu'on n'est pas en BO
    // if(!Shell::admini_mode() && empty($options['raw']))

    $rU = $ds->procEdit($update);

    unset($update["oid"]);

    if (! Shell::admini_mode()){
      $update['TextMono'] = "";
      $this->assertSQLRowIsOk(static::$tablename, [
          'KOID'=>$rU['oid'],
          'LANG'=>TZR_DEFAULT_LANG
      ], $update);
    }else{ // mode BO
      $this->assertSQLRowIsOk(static::$tablename, [
          'KOID'=>$rU['oid'],
          'LANG'=>TZR_DEFAULT_LANG
      ], $update);
    }
  }
  /**
   * test Champ texte enrichi
   * - transformation des liens
   * @depends testProcEdit
   */
   public function testNormalizeRichText(array $allfieldsAndOid) {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    // normalisation des liens
    // forme []->[] (todo)
    // récupération de l'oid rubrique, enrichissement classes tzr-errorlink tzr-internallink
    // conservation des attributs des liens
    $field = $ds->getField('RichTextMono');
    // force le source module : on prend le menu gauche
    list($itmod,$aliases) = $this->getTestInfoTree();
    $field->arrow2link = true;
    $field->sourcemodule = $itmod->_moid;
    $field->aliasmodule = $itmod->_moid;
    // besoin de 3 alias de base
    $this->assertEquals(true, !empty($aliases['home']) && !empty($aliases['top']) && !empty($aliases['bottom']), 
        "besoin des alias 'home, top, bottom' dans le module '{$itmod->getLabel()}'");
    // encodage des liens et ajouts des classes
    $caseno = 0;
    foreach([

        '<a href="https://www.xsalto.com">Lien externe</a>'=>[
            false,
            null
        ],
        '<a href="[home]">UN</a>'=>[
            true,
            [
                [
                    $itmod->_moid,
                    $aliases['home']
                ]
            ]
        ],
        '<a href="[existe-pas-alias]">erreur lien</a>'=>[
            false,
            null
        ],
        '<a href="[home]" class="tzr-errorlink">plus en erreur lien</a>'=>[
            true,
            [
                [
                    $itmod->_moid,
                    $aliases['home']
                ]
            ],
            true
        ],
        '<a style="someprop:somevalue" href="[top]" data-truc="tests attribut" class="test-class1 test-class2" >TROIS</a>'=>[
            true,
            [
                [
                    $itmod->_moid,
                    $aliases['top']
                ]
            ]
        ],
        '<p><a accesskey="Z" class="test-class3" dir="ltr" href="[bottom]" id="testsID" lang="fr" name="testn-ameddd" style="color:blue" tabindex="10000" title="tests attributs divers">avec attributs divers</a></p>' . '<p><a class="test-class4 test-class5 test-class6" href="[home]" title="title tests"></a></p>'=>[
            true,
            [
                [
                    $itmod->_moid,
                    $aliases['bottom']
                ],
                [
                    $itmod->_moid,
                    $aliases['home']
                ]
            ]
        ]
    ] as $t=>$parms){
      $caseno ++;
      $olderror = false;
      list($ok,$linksres,$olderror) = $parms;
      $tori = $t;
      $field->_normalizelinks($t);

      $this->trace(__METHOD__ . "before {$caseno} : {$tori}");
      $this->trace(__METHOD__ . "after  {$caseno} : {$t}");

      list($odom,$oxpath) = $this->getDomHTML($tori);
      list($ndom,$nxpath) = $this->getDomHTML($t);

      // ! ne prendre en compte que les href=[xxx]
      $newlinks = $nxpath->query('//a');
      foreach($oxpath->query('//a') as $i=>$onode){
        $ohref = $onode->getAttribute('href');
        if (substr($ohref, 0, 1) != '['){
          continue;
        }else{
          // vérifier qu'inchangé ?
        }
        $nnode = $newlinks->item($i);
        // transformation du href
        $nhref = $nnode->getAttribute('href');

        $ncssclass = $nnode->getAttribute('class');
        $ocssclass = $onode->getAttribute('class');
        $linkres = "[{$linksres[$i][0]},{$linksres[$i][1]}]";
        if ($ok){
          $this->assertEquals($linkres, $nhref, "transformation du href {$caseno} : $tori => $t - {$ohref} => {$linkres} ");
          $this->assertContains('tzr-internallink', $ncssclass, "Lien ok porte classe tzr-internallink cas $caseno");
          $this->assertContains('tzr-internallink', $ncssclass, "Lien ok porte classe tzr-internallink cas $caseno");
        }else{
          // vérif classe tzr-errorlink
          $this->assertContains('tzr-errorlink', $ncssclass, "Lien en erreur porte la classe tzrt-errorlink cas {$caseno} \n\t{$t}\n\t{$tori} \n\t'{$ncssclass}/{$ocssclass}'");
        }
        // vérif conservation des classes
        if (! empty($ocssclass)){
          $ocssclasses = preg_split('/ /', $ocssclass, - 1, PREG_SPLIT_NO_EMPTY);
          // cas lien initialement en erreur : tzr-errorlink doit pas rester positionnée
          if ($olderror && in_array('tzr-errorlink', $ocssclasses)){
            $ocssclass = str_replace('tzr-errorlink', ' ', $ocssclass);
          }
          $ncssclass = str_replace('tzr-internallink', ' ', $ncssclass);
          $this->assertTrue($this->compareCssClasses($ncssclass, $ocssclass), "Les classes initiales sont conservées '{$ocssclass}'/'{$ncssclass}' cas $caseno");
        }
      }
    }
    // on ne retraite pas (pour le moment) un lien déjà encodé
    $casno = 0;
    foreach([
        '<a href="[\'104\',\'bla bla blas\']" class="tzr-errorlink">Already processed cas 1</a>',
        '<a href="[\'104\',\'bla bla blas\']">Already processed</a>',
        '<a href="[existepas]" class="test-class tzr-errorlink">Already processed in error</a>'
    ] as $t){
      $casno ++;
      $to = $t;
      $t = $field->_normalizelinks($t);
      list($odom,$oxpath) = $this->getDomHTML($to);
      list($ndom,$nxpath) = $this->getDomHTML($t);
      $nlinks = $nxpath->query('//a');
      $olinks = $oxpath->query('//a');
      $ncssclass = $nlinks->item(0)->getAttribute('class');
      $ocssclass = $olinks->item(0)->getAttribute('class');
      $this->assertTrue($this->compareCssClasses($ocssclass, $ncssclass), "already processed links stay unchanged cas {$casno}");
    }
  }
  private function compareCssClasses($a, $b) {
    $aclasses = preg_split('/ /', $a, - 1, PREG_SPLIT_NO_EMPTY);
    $bclasses = preg_split('/ /', $b, - 1, PREG_SPLIT_NO_EMPTY);
    return (array_diff($aclasses, $bclasses) == [] && array_diff($bclasses, $aclasses) == []);
  }
  private function getDomHTML($text) {
    $dom = new \DomDocument();
    $dom->loadHTML("<!doctype html><html><head><meta charset='utf-8'></head><body>{$text}</body></html>");
    $xpath = new \DomXPath($dom);
    return [
        $dom,
        $xpath
    ];
  }
    
  /**
   * test Champ texte longs : nettoyge des javascripts
   * @depends testProcEdit
   */
  public function testRichText(array $allfieldsAndOid){
    
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $update['RichTextMono'] = "<b>RichText</b>";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);


    // nettoyage des balises en consultation ??? si le texte commence par le script, tidyString le vire ? ...
    $orival = '<b>et du texte</b><script>alert("msg");</script>';
    $cleanval = '<b>et du texte</b>';
    $ds->getField('RichTextMono')->tidy=false;  // à voir ensuite, mise en forme ...
    $update['RichTextMono'] = $orival;

    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    static::$tools->sqldump("select richtextmono from {$ds->getTable()} where koid=?", [$update['oid']]);

    unset($update["oid"]);
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);

    $filter_display['selectedfields'] = ['RichTextMono', 'RichTextMulti'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $this->assertEquals($cleanval, $fiche['oRichTextMono']->html);
    
  }

  /**
   * test des champ label/libellé
   * @depends testProcEdit
   */
  public function testLabel(array $allfieldsAndOid)
  {

    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    $update['LabelMono'] = "<strong>TestLabel_" . date('Y-m-d H:i:s') . "</strong>";
    $update["oid"] = $allfieldsAndOid['oid'];
    $rU = $ds->procEdit($update);

    $filter_display['selectedfields'] = ['LabelMono'];
    $filter_display["oid"] = $allfieldsAndOid['oid'];
    $fiche = $ds->display($filter_display);

    $testExe = getDB()->execute('select * from LABELS where koid=? and lang=? and LABEL=?', [$fiche['oLabelMono']->raw, TZR_DEFAULT_LANG, $update['LabelMono']]);
    $this->assertEquals('1', $testExe, "Vérification de la création du label dans la table label");

    unset($update["oid"]);
    $update['LabelMono'] = $fiche['oLabelMono']->raw;
    $this->assertSQLRowIsOk(static::$tablename, ['KOID' => $rU['oid'], 'LANG' => TZR_DEFAULT_LANG], $update);
  }


  /** 
  * tester si cela est multivaluable
  * @depends testCreateDSWithAllFields
  **/
  public function testMultivaluable($allfields)
  {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    foreach ($allfields as $fname) {
      $field = $ds->getField($fname);
      if ($field::isMultiValuable() == true) { 
        $this->assertTrue(true);
        //qu'est-ce que l'on doit vérifier si le champ est multivalué ? puis qu'en code on peut toujours crée un champ multivalué mais si ça ne fonction pas en console.
      }
      else {
        $this->assertTrue(true);
        //qu'est-ce que l'on doit vérifier si le champ est monovalué
      }
    }
    $this->markTestIncomplete('ce test n\'est pas fini. La base est faite a savoir fair la diférence entre le champ multivalué et les champ monovalué maitenant la question est qu\'est-ce qu\'on veut tester ?');
  }
  /**
   *  supression d'une ligne dans la table
   * @depends testProcInput
   * @depends testLinks
   * @group main
   */
  public function testDeleteLine($allfields)
  {
    $ds = DataSource::objectFactoryHelper8(static::$tablename);

    //on vérifie que la ligne sql existe
    $testExe = getDB()->execute("SELECT *  from `TU_TABLEWITHALLFIELDS` where KOID=?", [$allfields['oid']]);
    $this->assertEquals('1', $testExe, "Vérification de l'existence de la ligne ");

    // on fait un display pour avoir les références aux fichiers
    $filter_display["oid"] = $allfields['oid'];
    $fiche = $ds->display(['selectedfields'=>['FileMono', 'FileMulti', 'LabelMono'],
			   'oid'=>$allfields['oid']
    ]);

    // vérifier qu'on a bien les fichiers en place avant la suppression
    $fiche['oFileMono']->html;
    $this->assertTZRFileExists($fiche['oFileMono']);

    $this->trace($fiche['oFileMulti']->dirname);
    $this->trace(file_exists($fiche['oFileMulti']->dirname));
    static::$tools->execdump('/home/reynaud/tree '.$fiche['oFileMulti']->dirname);

    $this->assertTZRFileExists($fiche['oFileMulti'],"exists");
    
    $result = $ds->del(['oid' => $allfields['oid']]); //suppression de la ligne

    //on vérifie que la requete ne renvoi rien / que la ligne sql n'existe plus
    $testExe = getDB()->execute("SELECT *  from `TU_TABLEWITHALLFIELDS` where KOID=?", [$allfields['oid']]);
    $this->assertEquals('0', $testExe, "Vérification de la supresion de la ligne "); //si l'exécution de la requete sql a réussi alors  $testExe=1


    //tester la suppression du champ label

    $testExe = getDB()->execute('select * from LABELS where koid=? and lang=? and LABEL=?', [$fiche['oLabelMono']->raw, TZR_DEFAULT_LANG, $update['LabelMono']]);
    $this->assertEquals('0', $testExe, "Vérification de la supresion du label dans la table label");

    $select_log = getDB()->select("SELECT * FROM `LOGS` WHERE `object`= ? and `etype`= ? and LANG= ?", [$allfields['oid'], 'delete', TZR_DEFAULT_LANG]);

    // S'il y'a de multiples logs de supression de la table cette ligne échoue $this->assertEquals('1', $select_log->rowCount(), "Vérification de la table des logs "); //si l'exécution de la requete sql a réussi alors  $testExe=1
    $this->assertNotEquals('0', $select_log->rowCount(), "Vérification de la table des logs "); //Donc on vérifie qu'il en est au moins 1

    // vérification de la suppression des fichiers de la ligne
    $this->assertTrue(file_exists($GLOBALS['DATA_DIR'] . $fiche['oFileMono']->dirname('FileMono')),
		      "vérification de l'archivage du fichier");
    $this->trace(__METHOD__.$fiche['oFileMono']->dirname('FileMono'));
    $this->assertTrue(file_exists($GLOBALS['DATA_DIR'] . $fiche['oFileMulti']->dirname('FileMulti')),
				"vérification de l'archivage du fichier");


    //on vérifie que le fichier n'existe plus dans les dossiers non archives 
    $fileExiste = file_exists($fiche['oFileMono']->catalog[0]->filename); 
    $this->assertFalse($fileExiste, "vérification de la supresion du fichier");
  }
  /**
   * tests des valeurs par défaut
   * - prise en compte des valeurs niveau sql
   * - ignorer les valeurs invalides - ex 'A' pour un real
   * @depends testCreateDSWithAllFields
   * @group main
   */
  function testAllDefaultValues($allfields){
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $allsqltypes = [];
    foreach($ds->desc as $fn=>$fd){
      $allsqltypes[] = strtolower($fd->sqltype());
    }
    $this->checkDefaultValues($ds, 'first');
    //static::$tools->sqldump('show columns from '.static::$tablename);
    // forçage d'une valeur par defaut pour plus avoir de '' ou null
    foreach($ds->desc as $fn=>$fd){
      $meta = getDb()->fetchRow('show columns from '.static::$tablename.' where Field = ?', [$fn]);
      $mdefault = $fd->getDefaultValue();
      if (empty($default)){
	$sqltype = $fd->sqltype();
	if (substr($sqltype, 0, 7) == 'varchar'){
	  preg_match('/varchar\(([0-9]+)\)/', $sqltype, $parts);
	  $vclen = $parts[1];
	  $sqltype = 'varchar';
	}
	
	switch($sqltype){
	  case 'int':
	  case 'int(11)':
	  case 'tinyint':
	  case 'double':
	    $defaultValue = rand(1,12);
	    break;
	  case 'date':
	    $defaultValue = date('Y-m-d');
//	    $defaultValue = '2021-02-29';
	    break;
	  case 'datetime':
	  case 'timestamp';
	    $defaultValue = date('Y-m-d h:i:s');
//	    $defaultValue = date('Y-m-41 00:01:02');
	    break;
	  case 'time':
	    $defaultValue = date('h:i:s');
	    break;
	  case 'point':
	  case 'point not null':
	    $defaultValue = 'later';
	    break; // à voir
	  case 'text':
	  case 'mediumtext':
	    $defaultValue = "new default value  $fn";
	    break;
	  case 'varchar': 
	    $defaultValue = substr("$fn default value", 0, $vclen);
	}
	if ($defaultValue != 'later')
	  $ds->procEditField(['field'=>$fn, 'options'=>['default'=>$defaultValue]]);
	static::trace("'$fn' => '{$fd->sqltype()}' '{$mdefault}' '{$defaultValue}' '{$fd->getDefaultValue()}'");
      }
    }
    // vérification que ces valeurs sont bien prises en compte dans les DDL etc
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    $this->checkDefaultValues($ds, 'second');

    //static::$tools->sqldump('show columns from '.static::$tablename);
    //static::$tools->sqldump("select field, json_value(dparam,'$.default.value') from DICT where dtab=?", [static::$tablename]);
    
  }
  /**
   * vérifie que la valeur par défaut d'un champ est bien définie au niveau sql
   * aux champs UPD, CREAD, GmapPoint2
   */
  protected function checkDefaultValues($ds, $mess){
    foreach($ds->desc as $fn=>$fd){
      $meta = getDb()->fetchRow('show columns from '.static::$tablename.' where Field = ?', [$fn]);
      $default = $fd->getDefaultValue();
      // ?? mais varchar 'toto',  text mediumtext ''toto'' 
      if (substr($meta['Default'],0,1) == '\'' && substr($meta['Default'],-1) == '\'')
	$meta['Default'] = substr($meta['Default'], 1, -1);
      if (in_array($fn, ['UPD', 'CREAD']))
	$default = 'current_timestamp()';

      //static::trace("$mess '$fn' => '{$fd->getDefaultValue()}' '$default' '{$fd->sqltype()}'");
      //static::$tools->sqldump('show columns from '.static::$tablename.' where Field = ?', [$fn]);

      if ($fd instanceof \Seolan\Field\Timestamp\Timestamp){
	continue; // ces champs ont un getDefaultValue qui retourne date('Ym... à voir
      }
      if (isset($default)){
	//static::trace("$mess checking default $fn");
	$this->assertEquals($default,
			    $meta['Default'],
			    "Le champ {$fn} a '{$default}' pour valeur par defaut dans le ddl sql . found : '{$meta['Default']}'");
	
      } else {
	$this->assertEquals(null,
			    $meta['Default'],
			    "Le champ {$fn} n'a pas de valeur par défaut dans le ddl sql");
      }
    }
  }
  /**
   * des supressions de champs dans la table
   * @depends testCreateDSWithAllFields
   * @group main
   */
  public function testDeleteField($allfields){
    $ds = DataSource::objectFactoryHelper8(static::$tablename);
    foreach ($allfields as $fn) {
      $result = $ds->delField(['field' => $fn]);
      $this->assertFieldIsDeleted($ds, $fn);
    }
  }
  //tests des langues
  /**
   * @group testTraduction
   * tablenotrad UN TEST témoin de comment ça se passe pour une table non traduisible
   * 
   */
  public function testTableNoTrad()
  {
    $dt = Table::procNewSource([
      "translatable" => "0",
      "publish" => "0",
      "auto_translate" => "0",
      "btab" => static::$tableNoTradName,
      "bname" => [TZR_DEFAULT_LANG => static::$tableNoTradName]
    ]);

    $ds = DataSource::objectFactoryHelper8(static::$tableNoTradName);
    //chammp en mode non traduisible
    $ds->createField(
      'shortTextNoTrad',                          //ord obl que bro tra mul pub tar
      'shortTextNoTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '0',
      '0',
      '1',
      '1',
      '0',
      '0',
      '1',
      'ShortText'
    ); //mono valué 

    //champ en mode tradusible 
    
    $ds->createField(
      'shortTextTrad',                          //ord obl que bro tra mul pub tar
      'shortTextTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      'ShortText'
    ); //mono valué 

    $this->assertSQLTableExists(static::$tableNoTradName); //On vérifie que la table existe
    $this->assertSQLColumnExists('LANG', static::$tableNoTradName);
    $this->assertSQLColumnExists('shortTextNoTrad', static::$tableNoTradName);
    $this->assertSQLColumnExists('shortTextTrad', static::$tableNoTradName);


    $insert['shortTextNoTrad'] = 'shortTextNoTrad';
    $insert['shortTextTrad'] = 'shortTextTrad';
    $insert["LANG"] = TZR_DEFAULT_LANG;
    $r = $ds->procInput($insert);
    $this->assertSQLRowIsOk(static::$tableNoTradName, ['KOID' =>$r['oid'], 'LANG'=>TZR_DEFAULT_LANG],$insert);
   
  }

  /**
   * test des traductions automatiques
   * @group testTraduction
   */
    public function testTableTradAuto(){
        if (count($GLOBALS['TZR_LANGUAGES']) == 1){
            $this->markTestSkipped('Single language console, test skipped');
        }
    $dt = Table::procNewSource([
      "translatable" => "1",
      "publish" => "1",
      "auto_translate" => "1",
      "btab" => static::$tableTradName,
      "bname" => [TZR_DEFAULT_LANG => static::$tableTradName]
    ]);

    $ds = DataSource::objectFactoryHelper8(static::$tableTradName);
    //chammp en mode non traduisible
    $ds->createField(
      'shortTextNoTrad',                          //ord obl que bro tra mul pub tar
      'shortTextNoTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '0',
      '0',
      '1',
      '1',
      '0',
      '0',
      '1',
      'ShortText'
    ); //mono valué 
    //champ en mode tradusible
    $ds->createField(
      'shortTextTrad',                          //ord obl que bro tra mul pub tar
      'shortTextTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      'ShortText'
    ); //mono valué 

    // après chaque modification de strucure, penser à nettoyer les caches !!!!
    // <= les objects en mémoire sont pas à jours, même si les données SQL le sont
    DataSource::clearCache();
    $ds=DataSource::objectFactoryHelper8(static::$tableTradName);
    
    //on vérifie que les colone sql que l'on va uttilisé dans le test existe
    $this->assertSQLColumnExists('LANG',  static::$tableTradName);
    $this->assertSQLColumnExists('shortTextNoTrad',  static::$tableTradName);
    $this->assertSQLColumnExists('shortTextTrad',  static::$tableTradName);

    //on fait une insertion en langue de base
    $insert['shortTextNoTrad'] = 'shortTextNoTrad';
    $insert['shortTextTrad'] = 'shortTextTrad';
    $insert["LANG"] = TZR_DEFAULT_LANG;
    $r = $ds->procInput($insert);
    unset( $insert["LANG"]);

    $langsToTest=array_keys($GLOBALS['TZR_LANGUAGES']); //list des codes de langue uttilisé dans la console.
    //on vérifie la traduction dans tout les langue de la console.
    foreach($langsToTest as $lang){
      $this->assertSQLRowIsOk( static::$tableTradName, ['KOID' =>$r['oid'], 'LANG'=>$lang],$insert);
    }
      
    //ProcEdit sur la seconde langue:
    Shell::setLang($langsToTest[1]); //on passe en deuxième langue
    $actuaLang=\Seolan\Core\Lang::getLocale();
    $this->assertEquals($GLOBALS['TZR_LANGUAGES'][$langsToTest[1]], $actuaLang['code'], "vérification du switch de langue"); //on vérifie qu'on bien switcher sur la deuxième langue
    $update['shortTextTrad'] = 'ProcEdit'.$langsToTest[1];
    $update['oid'] = $r['oid'];
    $rU=$ds->procEdit($update);
    unset($update["oid"]);

    //procEdit en langue autre que de base => mise à jour seulement des champs traduisibles et que pour la ligne de la langue
    //on vérifie que la modification n'a eu lieux que sur la deuxième langue.
    $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$rU['oid'], 'LANG'=>$langsToTest[1]], $update);
    $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$rU['oid'], 'LANG'=>TZR_DEFAULT_LANG], $insert);  //on vérifie qu'on a  pas écraser la donée dans la langue de base
    foreach($langsToTest as $lang){
      if($lang==$langsToTest[1]){ //si on est dans en deuxième langue alors
        $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$rU['oid'], 'LANG'=>$lang], $update); //on vérifie que mise à jour c'est effectuer sur le champ de la deuxième langue
      }else { //sinon on est dans une autre langue.
        $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$rU['oid'], 'LANG'=>$lang], $insert); //on vérifie qu'on a  pas écraser la donée dans les langue qu'on n'a pas modifier
      }
    }
    //on vérifie qu'on peut pas faire d'édition dans une langue sur un champ nont tradusible.
    $update['shortTextNoTrad'] = 'ProcEdit'.$langsToTest[1];
    $update['LANG'] = $langsToTest[1];
    $update['oid'] = $r['oid'];
    $rU=$ds->procEdit($update);
    unset( $update["LANG"]);
    unset( $update["oid"]);
    unset($insert['shortTextTrad']);

    foreach($langsToTest as $lang){
        $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$r['oid'], 'LANG'=>$lang], $insert);  //on vérifie que l'on a les même valeur qu'avant
    }
    
    //test sur la mise à jour d'un champ non traduisible en langue de base
    //sur la mise à jour en langue de base d'un champ non traduisible,       faut vérifier que ça répercute aussi, car si la données existe       faut la mettre à jour
    Shell::setLang(TZR_DEFAULT_LANG); //on retourne sur la langue de base
    $update['shortTextNoTrad'] = 'ProcEdit'.TZR_DEFAULT_LANG;
    $update['LANG'] = TZR_DEFAULT_LANG;
    $update['oid'] = $r['oid'];
    $rU=$ds->procEdit($update);
    unset( $update["LANG"]);
    unset( $update["oid"]);
    unset($update['shortTextTrad']);
    
    foreach($langsToTest as $lang){
      $this->assertSQLRowIsOk( static::$tableTradName, ['KOID'=>$r['oid'], 'LANG'=>$lang], $update); 
    }

  }

  //on test les traduction sur une table qui se traduit pas automatiquement
  /** 
  * @group testTraduction
  **/
  public function testTableTradWithoutAutoTrad(){
      if (count($GLOBALS['TZR_LANGUAGES']) == 1){
          $this->markTestSkipped('Single language console, test skipped');
      }
    Shell::setLang(TZR_DEFAULT_LANG);
    $dt = Table::procNewSource([
      "translatable" => "1",
      "publish" => "1",
      "auto_translate" => "0",
      "btab" => static::$tableTradWithoutAutoTradName,
      "bname" => [TZR_DEFAULT_LANG => static::$tableTradWithoutAutoTradName]
    ]);

    $ds = DataSource::objectFactoryHelper8(static::$tableTradWithoutAutoTradName); 
    //champ en mode non traduisible
    $ds->createField(
      'shortTextNoTrad',                          //ord obl que bro tra mul pub tar
      'shortTextNoTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '0',
      '0',
      '1',
      '1',
      '0',
      '0',
      '1',
      'ShortText'
    ); //mono valué 

    $ds->createField(
      'shortTextTrad',                          //ord obl que bro tra mul pub tar
      'shortTextTrad',
      '\Seolan\Field\ShortText\ShortText',
      '30',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      '1',
      'ShortText'
    ); //mono valué

     // après chaque modification de strucure, penser à nettoyer les caches !!!!
    // <= les objects en mémoire sont pas à jours, même si les données SQL le sont
    DataSource::clearCache();
    $ds = DataSource::objectFactoryHelper8(static::$tableTradWithoutAutoTradName); 

    $this->assertSQLColumnExists('LANG',  static::$tableTradWithoutAutoTradName);
    $this->assertSQLColumnExists('shortTextNoTrad',  static::$tableTradWithoutAutoTradName);
    $this->assertSQLColumnExists('shortTextTrad',  static::$tableTradWithoutAutoTradName);

    $insert['shortTextNoTrad'] = 'shortTextNoTrad';
    $insert['shortTextTrad'] = 'shortTextTrad';
    $r = $ds->procInput($insert);
    $this->assertSQLRowIsOk(static::$tableTradWithoutAutoTradName, ['KOID' =>$r['oid'], 'LANG'=>TZR_DEFAULT_LANG],$insert);
    $langsToTest=array_keys($GLOBALS['TZR_LANGUAGES']); //on récupère la liste des langues présent dans la console.
    $select_log = getDB()->select("SELECT * FROM ".static::$tableTradWithoutAutoTradName." WHERE `KOID`= ? and `LANG`= ? and `shortTextTrad`= ?", [$r['oid'], $langsToTest[1], $insert['shortTextTrad']]);
    $this->assertEquals('0', $select_log->rowCount(), "Impossible d'affirmer qu'il n'y a pas d'insertion dans la deuxièmement langue. La traduction non automatique ne fonction pas correctement normalement il devrait y'avoir uniquement un insertion dans la langue de base. code de la seconde langue ".$langsToTest[1]);

    //on change de langue pour vérifier la traduction non automatique
    for ($i = 0; $i < sizeof($langsToTest); $i++) { //cette boucle sert a faire des éditions et des assertions sur l'ensemble des langues paramétrées sauf celle de base
      if($langsToTest[$i] != TZR_DEFAULT_LANG){ //on ne conait l'index de la langue par défaut dans le tableau
        Shell::setLang($langsToTest[$i]);
        $actuaLang=\Seolan\Core\Lang::getLocale();
        $this->assertEquals($GLOBALS['TZR_LANGUAGES'][$langsToTest[$i]], $actuaLang['code'], "vérification du switch de langue"); //on vérifie qu'on bien switcher sur la deuxième langue
        $update['shortTextTrad'] = 'ProcEdit'.$langsToTest[$i];
        $update['oid'] = $r['oid'];
        $rU=$ds->procEdit($update);
        unset($update['oid']);
        $this->assertSQLRowIsOk(static::$tableTradWithoutAutoTradName, ['KOID' =>$rU['oid'], 'LANG'=>$langsToTest[$i]],$update); //on vérifie la modification en 2ème langue
      }
    }
    //on vérifie qu'on peut pas modifier un champ non tradusible dans une autre langue
    //test sur la mise à jour d'un champ non traduisible en deuxième langue
    $update['shortTextNoTrad'] = 'ProcEdit'.$langsToTest[1];
    $update['oid'] = $r['oid'];
    $rU=$ds->procEdit($update);
    unset($update['oid']);
    $update['shortTextNoTrad']='shortTextNoTrad';
    unset($update['shortTextTrad']);
    
    foreach($langsToTest as $lang){
        $this->assertSQLRowIsOk( static::$tableTradWithoutAutoTradName, ['KOID' =>$rU['oid'], 'LANG'=>$lang],$update);
    }
    
    $select_log = getDB()->select("SELECT * FROM ".static::$tableTradWithoutAutoTradName." WHERE `KOID`= ? and `LANG`= ? and `shortTextNoTrad`= ?", [$r['oid'], $langsToTest[1], 'ProcEdit'.$langsToTest[1]]);
    $this->assertEquals('0', $select_log->rowCount(), "Impossible d'affirmer qu'il n'y a pas d'édition deuxièmement dans la langue. Ce champ est censé être non traduisible or il a été traduit. Code de la seconde langue : ".$langsToTest[1]);

    //on vérifie que le procedit marche en langue de base sur un champ non traduisible
    //test sur la mise à jour d'un champ non traduisible en langue de base
    Shell::setLang(TZR_DEFAULT_LANG);
    $update['shortTextNoTrad'] = 'ProcEdit'.TZR_DEFAULT_LANG;
    $update['oid'] = $r['oid'];
    $rU=$ds->procEdit($update);
    unset($update['oid']);
    
     //on vérifie la modification  dans tout les langue de la console dont la langue de base
     //a mise à jour en langue de base d'un champ non traduisible, faut vérifier que ça répercute aussi, car si la données existe faut la mettre à jour.
     foreach($langsToTest as $lang){
      $this->assertSQLRowIsOk( static::$tableTradWithoutAutoTradName, ['KOID' =>$rU['oid'], 'LANG'=>$lang],$update);
     }
    
  }
  /**
   * outil création de tout type de champ
   */
  protected function createFields($ds, $field, $field_name, &$allfields, &$todofields){
    $defaultValue = function($fname, $ftype) use($ds){
      return $this->getFieldTestValue($ds,
				      $fname,
				      static::$optionsDefaultValue,
				      $ftype,
				      $ds->getTable());
    };
    if ($field == "\Seolan\Field\Country\Country") {
      $allfields[] = $field_name . 'Mono';
      $allfields[] = $field_name . 'Multi';
      $ds->createField(
        $field_name . 'Mono',//ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1',"COUNTRYISO"
      ); //mono valué  
      
      $test = $ds->createField(
        $field_name . 'Multi',                         //ord obl que bro tra mul pub tar
        $field_name . 'Multi',
        $field,
        '30','0','0','1','1','0','1','1','COUNTRYISO'
      ); //multivalué 
      
    } elseif ($field == "\Seolan\Field\DependentLink\DependentLink") {
      $todofields[] = $field_name . 'Mono';
      $todofields[] = $field_name . 'Multi';
    } elseif ($field == '\Seolan\Field\Color\Color') { //tester color
      $allfields[] = $field_name . 'Mono';
      $allfields[] = $field_name . 'Multi';
      $ds->createField(
        $field_name . 'Mono',                          //ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1',null
      ); //mono valué  "
      
      $test = $ds->createField(
        $field_name . 'Multi',                          //ord obl que bro tra mul pub tar
        $field_name . 'Multi',
        $field,
        '30','0','0','1','1','0','1','1',null
      );
    } elseif ($field == '\Seolan\Field\Real\Real' || $field == '\Seolan\Field\Rating\Rating') {
      $allfields[] = $field_name . 'Mono';
      $ds->createField(
        $field_name . 'Mono',                          //ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1',null,
	['default'=>$defaultValue($field_name.'Mono', $field)] 
      ); //mono valué  "
    } elseif ($field == "\Seolan\Field\Label\Label") {
      // ? multi ?
      $allfields[] = $field_name . 'Mono';
      $ds->createField(
        $field_name . 'Mono',                          //ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1','LABELS'
      ); //mono valué  
      
      /* champ label non multivaluable */
    } elseif ($field == "\Seolan\Field\DataSource\DataSource") {
      $allfields[] = $field_name . 'Mono';
      $ds->createField(
        $field_name . 'Mono',                          //ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1',null
      ); //mono valué  
    } elseif ($field == '\Seolan\Field\User\User') {
      $allfields[] = $field_name . 'Mono';
      $allfields[] = $field_name . 'Multi';
      $ds->createField(
        $field_name . 'Mono',                          //ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1','USERS'
      ); //mono valué  
      
      $test = $ds->createField(
        $field_name . 'Multi',                          //ord obl que bro tra mul pub tar
        $field_name . 'Multi',
        $field,
        '30','0','0','1','1','0','1','1','USERS'
      ); //multivalué 
      
    } elseif ($field == '\Seolan\Field\Document\Document') {
      $allfields[] = $field_name . 'Mono';
      $todofields[] = $field_name . 'Multi';
      //document c'est lié a la base doc.
      
      //regler le parametre base documentaire
      //il faut que je comprend comme se paramètre est enregister 
      
      $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1','T013',['bdocmodule' => 159] // @TODO, à tester ensuite
      ); //mono valué  "
      
      
    } elseif ($field == '\Seolan\Field\Thesaurus\Thesaurus') {
      $allfields[] = $field_name . 'Mono';
      $allfields[] = $field_name . 'Multi';
      
      //TODO : table cible du thésaurus, champs parent
      
      $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1','T003', // voir TODO dessus
	['flabel'=>'title', 'fparent'=>null]
      ); 
      $test = $ds->createField(
        $field_name . 'Multi',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Multi',
        $field,
        '30','0','0','1','1','0','1','1','T003', // voir TODO desssus
	['flabel'=>'title', 'fparent'=>null] 
      );
    } elseif ($field == '\Seolan\Field\StringSet\StringSet') {
      $defaultMono = $this->getFieldTestValue($ds,
					      $field_name.'Mono',
					      static::$optionsDefaultValue,
					      '\Seolan\Field\StringSet\StringSet',
					      $ds->getTable());
      $defaultMulti = $this->getFieldTestValue($ds,
					       $field_name.'Multi',
					       static::$optionsDefaultValue,
					       '\Seolan\Field\StringSet\StringSet',
					       $ds->getTable()
      );
      $allfields[] = $field_name . 'Mono';
      $allfields[] = $field_name . 'Multi';
      $stringMono = $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '30','0','0','1','1','0','0','1',null,
	['default'=>$defaultMono]
      ); 
      
      $ds->createField(
        $field_name . 'Multi',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Multi',
        $field,
        '30','0','0','1','1','0','1','1',null,
	['default'=>$defaultMulti]
      ); 
      // on ajoute quelques valeurs possibles pour avoir un vrai "set"
      $stringMono = $ds->getField($field_name . 'Mono'); 
      $stringMulti = $ds->getField($field_name . 'Multi');
      for($i=1; $i<5; $i++){
	$stringMono->newString("Valeur pour StringMono %i");
	$stringMulti->newString("Valeur pour StringMulti %i");
      }
    } elseif ($field == '\Seolan\Field\Boolean\Boolean'){
      $allfields[] = $field_name . 'Mono';
      $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',        
	$field,
        '64','0','0','1','1','0','1','1',null,['default'=>$defaultValue($field_name.'Mono', $field)]
      ); //mono valué  "
    } elseif ($field == '\Seolan\Field\ShortText\ShortText'){
      $allfields[] = $field_name . 'Mono';

      $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',
        $field,
        '124','0','0','1','1','0','0','1',null,
	['default'=>$defaultValue($field_name.'Mono', $field)] 
      ); 
      
    } elseif ($field == '\Seolan\Field\ApplicationLink\ApplicationLink'){
      if (TZR_USE_APP) {
	$allfields[] = 'APP';
	$ds->createField(
	  'APP',                          //fcount ord obl que bro tra mul pub tar
	  'Application',
	  $field,
	  '64','0','0','1','1','0','0','1','APP',
	); //mono valué  "
      }
    } else {
      $allfields[] = $field_name . 'Mono';

      $ds->createField(
        $field_name . 'Mono',                          //fcount ord obl que bro tra mul pub tar
        $field_name . 'Mono',
	$field,
        '64','0','0','1','1','0','0','1',null,
      ); //mono valué  "
      if ($field::isMultiValuable()){
	$allfields[] = $field_name . 'Multi';      
	$test = $ds->createField(
      	  $field_name . 'Multi',                          //fcount ord obl que bro tra mul pub tar
          $field_name . 'Multi',        
	  $field,
          '64','0','0','1','1','0','1','1',null,
	); //multivalué 
      }
    }
    /*
       les champs LinkMulti et LinkMono (lien vers objet) passent dans le else de cette méthode.
       Ils sont paramétrés et testés dans testLinks : puisqu'on crée une table spécifique static::$tableLinkTargetName pour les tester.
     */
  }
  /**
   * Retourne une valeur de test pour un champ, en fonction de son type
   * 
   */
  private function getFieldTestValue(DataSource $ds, string $fname, $options=['date delay'=>'+2 days',
									      'hour delay'=>'+2 hours',
									      'text content'=>'insert',
									      'numeric content'=>4,
									      'boolean value'=>2,
  ], $fieldclassname=null, $tablename=null){
    if ($fieldclassname === null){
      $field = $ds->getField($fname);
      $ftype = $field->get_ftype();
    } else {
      $field = null;
      $ftype = $fieldclassname;
    }
    $randval = uniqid();
    switch ($ftype) {
      case '\Seolan\Field\Link\Link': // parametre dans un test ci dessous voir TestFiles et Testlinks 
      case '\Seolan\Field\File\File':
        $val = null;
        break;
      case '\Seolan\Field\ShortText\ShortText':
      case '\Seolan\Field\Text\Text':
      case '\Seolan\Field\RichText\RichText':
        $val = "{$ftype} {$fname} {$options['text content']} {$randval}";
        break;
      case '\Seolan\Field\Date\Date'; //date et cie
        $val = date('Y-m-d', strtotime($options['date delay']));  //  date dans 5 jours .
        break;
      case '\Seolan\Field\Time\Time'; //date et cie
        $val = date('H:i:s', strtotime($options['hour delay']));
        break;
      case '\Seolan\Field\DateTime\DateTime'; //date et cie
      case '\Seolan\Field\Timestamp\Timestamp'; 
        $val = date('Y-m-d H:i:s', strtotime($options['date delay']));
        break;
      case '\Seolan\Field\Boolean\Boolean':
        $val = $options['boolean value'];
        break;
      case '\Seolan\Field\Chrono\Chrono':
      case '\Seolan\Field\Order\Order':
      case '\Seolan\Field\Real\Real':
      case '\Seolan\Field\Rating\Rating':
        $val = $options['numeric content']+rand(1,1000);
        break;
      case '\Seolan\Field\StringSet\StringSet': 
	$soid = 'TUSOID'.uniqid();
	$slabel = "label for soid {$soid} {$options['text content']} field {$fname}";
        if ($field !== null)
	  $field->newString($slabel, $soid, null);
	else
	  getDB()->execute("INSERT INTO SETS (SOID,STAB,FIELD,SLANG,STXT,SORDER) values (?,?,?,?,?,?)",
                           [$soid,$tablename,$fname,TZR_DEFAULT_LANG,$slabel,1]);
        $val = $soid;
        break;
      default:
        $val = null;
    }
    return $val;
  }
  protected function createTableLink(){
    $dsLinks = Table::procNewSource([
      "translatable" => "0",
      "publish" => "0",
      "auto_translate" => "0",
      "btab" => static::$tableLinkTargetName,
      "bname" => [TZR_DEFAULT_LANG => static::$tableLinkTargetName]
    ]);
    $dsLinks = DataSource::objectFactoryHelper8(static::$tableLinkTargetName);
    $dsLinks->createField(
      "Titre_Link",
      "Titre_Link",
      '\Seolan\Field\ShortText\ShortText',
      '64',      '0',       '0',       '1',       '1',       '0',       '0',       '1'
    );
    // on  insère quelques lignes dans la table destination et on garde les valeurs pour vérification des mises à jour
    $ri = [];
    $linkVals = [];
    for ($i = 1; $i <= 10; $i++) { //on inser 3 valeur de titre donc 3 ligne
      $linkVal = "ShortText Titre_Link $i";
      $insert["Titre_Link"] = $linkVal;
      $linkVals[] = $linkVal;
      $ri[] = $dsLinks->procInput($insert);
    }
    return [$ri, $linkVals];
  }

  /**
   * destruct main table and targets
   */
  public static function clearFixtures(){
    static::trace(__METHOD__);
    static::forceDelTable(static::$tablename);
    static::forceDelTable(static::$tableprocnewsource_name);
    static::forceDelTable(static::$tableLinkTargetName);
    static::forceDelTable(static::$tableNoTradName);
    static::forceDelTable(static::$tableTradName);
    static::forceDelTable(static::$tableTradWithoutAutoTradName);
  }
}

//
