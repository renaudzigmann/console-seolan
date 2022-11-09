<?php
namespace UnitTests;
/**
 * tests des labels (et choses associées)
 */

class LabelsTests extends BaseCaseWithTemplates {
  /**
   * le tag label (src\Library\smarty\plugin\function.label.php) facilite l'utilisation des labels (table des labels) dans les gabarits en :
   * - recherchant le label de façon case insensitive
   * - ajoutant le label dans la table si pas déjà présent
   * - parsant les tokens (valeurs dynamiques dans le label)
   */
  function testLabelTagAjoutLecture(){

    static::initMockShell(); // pour les labels

    // on nettoie le label
    $varname = 'TUTESTSVAR001'.date('ymdhis');
    $text = "TEXT FOR {$varname}";
    $tpltext = "<%label v='{$varname}' t='{$text}'%>";
    $result = $this->getParser()->parseText($tpltext);

    // contenu correctement parsé
    $this->assertEquals($text, $result, "le tag label renvoie '{$result}', attendu : '{$text}'");

    // ligne ajoutée dans la table des labels
    $row = getDB()->fetchRow('select * from LABELS where lang=? and variabl=? and  label like ?', [TZR_DEFAULT_LANG, $varname, "%$text%"]);
    $this->assertTrue(isset($row), "ligne ajoutée en base de variabl : '$varname'");

    // si ligne modifiée, l'appel au tag doit ramener la ligne existante
    $newtext = "TEXT FOR {$varname} MODIFIED";
    getDB()->execute('update LABELS set label=? where lang=? and koid=?',
		     [$newtext,
		      TZR_DEFAULT_LANG,
		      $row['KOID']]);
    
    $tpltext = "<%label v='{$varname}' t='{$newtext}'%>";
    $result = $this->getParser()->parseText($tpltext);

    $this->assertEquals($newtext, $result, "le tag label renvoie '{$result}', attendu '{$newtext}'");

    return ;
    
    // le nom de la variable est nettoyé => marche plus
    // à voir si il faut remettre
    // preg_replace(['/(^_|_$)/','/[^\w]+/'], ['','_'], strtolower(removeAccents($v))));
    $varname2Raw = "_Tu \ttésts-var002_".date('ymdhis');
    $varname2 = "TUTESTSVAR002_".date('ymdhis');
    $text = "TEXT FOR {$varname2Raw}";
    $tpltext = "<%label v='{$varname2Raw}' t='{$text}'%>";
    $result = $this->getParser()->parseText($tpltext);

    // contenu correctement parsé quand même
    $this->assertEquals($text, $result, "le tag label renvoie '{$result}', attendu : '{$text}'");

    // ligne ajoutée dans la table des labels
    $row = getDB()->fetchRow('select * from LABELS where lang=? and variabl=? and  label like ?', [TZR_DEFAULT_LANG, $varname2, "%$text%"]);
    
    $this->assertTrue(isset($row), "ligne ajoutée en base de variabl : '$varname2'");

    static::$tools->sqldump('select title, variabl, label from LABELS where variabl like ?', ['TUTESTSVAR%']);
    
  }
  /**
   * @depends testLabelTagAjoutLecture
   */
  function testLabelTagToken(){
    $varname = "TUTESTSVAR003_".date('ymdhis');
    $text = "TEXT FOR {$varname} with %token1 and %token2";
    $tpltext = "<%label v='{$varname}' t='{$text}' token1='value for token 1' token2='value for token2'%>";
    $expectedResult = "TEXT FOR {$varname} with value for token 1 and value for token2";
    $parsedResult = $this->getParser()->parseText($tpltext);
    
    // contenu correctement parsé quand même
    $this->assertEquals($expectedResult, $parsedResult,"le tag label renvoie '{$parsedResult}', attendu : '{$expectedResult}'");

    static::$tools->sqldump('select title, variabl, label from LABELS where variabl like ?', ['TUTESTSVAR%']);
    
  }
  function testLabelField(){
    $this->markTestIncomplete();
  }
  public static function clearFixtures(){
    static::trace(__METHOD__);
    parent::clearFixtures();
    getDB()->execute("delete from LABELS where variabl like 'TUTESTSVAR%'");
  }
}

