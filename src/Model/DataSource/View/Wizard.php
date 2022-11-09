<?php
namespace Seolan\Model\DataSource\View;
class Wizard extends \Seolan\Core\DataSource\Wizard{
  function __construct($ar=NULL){
    parent::__construct($ar);
  }

  function istep1($ar=NULL){
    if(empty($this->_datasource->btab)) $this->_datasource->btab=\Seolan\Model\DataSource\Table\Table::newTableNumber('V');
    if(empty($this->_datasource->classname)){
        $this->_datasource->classname=\Seolan\Core\DataSource\DataSource::$_sources['\Seolan\Model\DataSource\View\View']['CLASSNAME'];
    }
    $this->_datasource->auto_translate=0;
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name'),'bname','ttext');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code'),'btab','text');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','published'),'publish','boolean');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','translatable'),'translate','list',
			    array('values'=>array('1','0',TZR_LANG_FREELANG),
				  'labels'=>array(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','yes','text'),
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','no','text'),
						  \Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','freelang','text'))));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','maintable'),'maintable','table');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','query'),'query','text',array('rows'=>5,'cols'=>80));
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','theclass'),'classname','text');
    $this->_step = 2;
  }
  /**
   * validation de la requete
   * saisie du paramétrage des champs
   * @param array $ar
   */
  function istep2($ar = NULL) {
    $this->_datasource->viewfields = '';
    $q = preg_replace('/[\n\r]/', ' ', $this->_datasource->query);
    if (!preg_match('/^select (.*) from .*$/Ui', $q,$ret)) {
      setSessionVar('message', 'requête incorrecte : <i>'.$this->_datasource->query.'</i>');
      return $this->istep1();
    }
    $viewFieldsMess = 'Compléter la description des champs : <br>';
    $fieldsnames = [];
    try{
      // vérification de la requete de base et des champs formés  
      $ors = getDB()->select($this->_datasource->query)->fetch();
      if ($ors){
        $fieldsnames = array_keys($ors);
        if (!in_array('KOID', $fieldsnames) || !in_array('LANG', $fieldsnames) || !in_array('UPD', $fieldsnames)){
          setSessionVar('message', 'Erreur lors de la tentative de vérification des champs (KOID, LANG, UPD) :  '.$this->_datasource->query);
          return $this->istep1();
        }
      } else {
        \Seolan\Core\Shell::alert('Requête de création non vérifiée. Attention, elle doit toujours définir KOID et LANG', 'info');
      }
      list($params, $viewFieldsMess) = self::defaultViewFields($this->_datasource->query, $fieldsnames);
    } catch(\Exception $e){
      setSessionVar('message', ' Erreur lors de la tentative de vérification de la requête : '.$this->_datasource->query);
      return $this->istep1();
    }
    \Seolan\Core\Shell::alert(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View','query').' : '.$this->_datasource->query, 'info');
    \Seolan\Core\Shell::alert($viewFieldsMess, 'info');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Model_DataSource_View_View', 'viewfields'), 
          			    'viewfields', 
          			    'text', 
          			    array('rows' => 5, 'cols' => 80)
          			    );
    if(empty(trim($this->_datasource->viewfields))){
      list($params) = self::defaultViewFields($this->_datasource->query, $fieldsnames);
      $this->_options->set($this, 'viewfields', $params);
    }
    $this->_step = 3;

  }

  function iend($ar=NULL){
    global $XSHELL;
    if(empty(trim($this->_datasource->viewfields))){
      list($this->_datasource->viewfields) = self::defaultViewFields($this->_datasource->query);
    }

    $param=array('btab'=>$this->_datasource->btab, 'bname'=>$this->_datasource->bname,
		 'publish'=>$this->_datasource->publish,'classname'=>$this->_datasource->classname,
		 'translatable'=>$this->_datasource->translatable,'auto_translate'=>$this->_datasource->auto_translate,
		 'bparam'=>(array)$this->_datasource);
    $ret=\Seolan\Model\DataSource\View\View::procNewSource($param);
    if($ret['error']){
      $this->_step=1;
      $XSHELL->tpldata['wd']['message']=$ret['message'];
      $this->irun($ar);
      return;
    }
    if(!empty($ret['message'])) setSessionVar('message',$ret['message']);
    $moid=\Seolan\Core\Module\Module::getMoid(XMODDATASOURCE_TOID);
    $XSHELL->setNext('&moid='.$moid.'&function=browse&tplentry=br&template=Module/DataSource.browse.html');
    clearSessionVar('DataSourceWd');
    return $ret['boid'];
  }
  /**
   * Formatter des lignes de configuration pour les champs
   * @param string $query
   * @return string
   */
  protected static function defaultViewFields($query=null, $fieldsnames=[]){
    $qfields = [];
    $params = '';
    $mess = '';
    if (isset($query)){
      preg_match('/^select (.*) from .*$/Ui', preg_replace('/[\n\r]/', ' ', $query),$ret);
      $ret=explode(',',$ret[1]);
      foreach($ret as $sql){
        $sql = trim($sql);
        list($expression, $name)=explode(' as ',$sql);
        list($table,$field)=explode('.',trim($expression));
        if($name!='KOID' && $name!='LANG') {
          $qfields[$name] = [$table, $field, $name, $sql];
        }
      }
    }
    $qfields = array_merge(array_flip($fieldsnames), $qfields);
    $i = 0;
    foreach($qfields as $name=>$fieldSpec){
      if (in_array($name, ['KOID', 'LANG'])){
        continue;
      }
      $i++;
      if (!is_array($fieldSpec)){
      	$mess .= "-- compléter TTTT et FFFF$i ci-dessous\n";
      	$params .= "TTTT:FFFF$i:$name\n";
      } else {
        list($table, $field, $name, $sql) = $fieldSpec;
      	if (\Seolan\Core\System::tableExists($table)){
      	  if (\Seolan\Core\System::fieldExists($table, $field)){
      	    $params .= "$table:$field:$name\n";
      	  } else {
      	    $mess .= "-- $sql : '$field' n'est pa un champ, vérifier '$field'\n";
      	    $params .= "$table:$field:$name\n";
      	  }
      	} else {
      	  $mess .= "-- $sql : '$table' n'est pas une table, vérifier '$table'\n";
      	  $params .= "XXXX:$field:$name\n";
      	}
      }
    }
    return [$params, $mess];
  }
}
