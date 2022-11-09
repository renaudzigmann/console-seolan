<?php
namespace Seolan\Module\ContentTemplate;

class ContentTemplate extends \Seolan\Module\Table\Table {
  function __construct($ar=NULL) {
    parent::__construct($ar);
    \Seolan\Core\Labels::loadLabels('Seolan_Module_ContentTemplate_ContentTemplate', true);
    \Seolan\Core\Labels::reloadLabels();
  }
  
  /// Sécurité des fonctions accessibles par le web
  function secGroups($function, $group=NULL) {
    $g=array();
    $g['displayTopicsWhichUseThisTemplate']=array('admin');
    $g['browseUsed']=array('admin');
    $g['browseUnused']=array('admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      return $g[$function];
    }
    return parent::secGroups($function,$group);
  }
  
  /// Ajoute un onglet au display et le contenu des gabarits
  function display($ar) {
    $gabarit = parent::display($ar);

    $gabarit['__ajaxtabs'] = array(array(
      'url' => '/scripts/admin.php?moid='.$this->_moid.'&function=displayTopicsWhichUseThisTemplate&oid='.$gabarit['oid'].'&template=Module/ContentTemplate.topics.html&skip=1',
      'title' => \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_ContentTemplate_ContentTemplate','topicstab')),
    );

    return \Seolan\Core\Shell::toScreen1('br', $gabarit);
  }

  /// Afficher des actions personnalisées
  public function _actionlist(&$my, $alfunction=true) {
    parent::_actionlist($my, $alfunction);
    if ($this->secure('','browseUsed')) {
      $o1=new \Seolan\Core\Module\Action($this,'browseUsed',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_ContentTemplate_ContentTemplate','browseused'),'&moid='.$this->_moid.'&_function=browseUsed&template=Module/Table.browse.html&tplentry=br', 'more');
      $o1->menuable=true;
      $o1->group='actions';
      $my['browseUsed']=$o1;
    }
    if ($this->secure('','browseUnused')) {
      $o1=new \Seolan\Core\Module\Action($this,'browseUnused',\Seolan\Core\Labels::getTextSysLabel('Seolan_Module_ContentTemplate_ContentTemplate','browseunused'),'&moid='.$this->_moid.'&_function=browseUnused&template=Module/Table.browse.html&tplentry=br', 'more');
      $o1->menuable=true;
      $o1->group='actions';
      $my['browseUnused']=$o1;
    }
  }

  /// Affiche les rubriques utilisant ce gabarit
  function displayTopicsWhichUseThisTemplate($ar) {
    \Seolan\Core\Logs::critical(__METHOD__);
    $p = new \Seolan\Core\Param($ar);
    $oid = $p->get('oid');
    $rubriques_oids = $this->getTopicsWhichUseThisTemplate($oid);

    if (empty($rubriques_oids)) {
      echo \Seolan\Core\Labels::getTextSysLabel('Seolan_Module_ContentTemplate_ContentTemplate','no_result');
      return;
    }

    \Seolan\Core\Shell::toScreen2("topics","array",$rubriques_oids);
  }

  /// Retourne la liste des rubriques utilisant ce template
  public function getTopicsWhichUseThisTemplate($oidtpl, $only_oids = false) {
    $all_topics_oids = array();
    $infotree_moids = getDB()->select('SELECT MOID FROM MODULES WHERE TOID=?',[XMODINFOTREE_TOID])->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($infotree_moids as $infotree_moid) {
      $infotree = \Seolan\Core\Module\Module::objectFactory($infotree_moid);
      \Seolan\Core\Logs::critical(__METHOD__." infotree tname={$infotree->tname} name={$infotree->name}");
      $topics_oids = getDb()->select("SELECT DISTINCT KOIDSRC FROM {$infotree->tname} WHERE KOIDTPL=? order by KOIDDST",[$oidtpl])->fetchAll(\PDO::FETCH_COLUMN);
      if (empty($topics_oids)) continue;
      if ($only_oids) {
        $all_topics_oids[$infotree->tname] = $topics_oids;
        continue;
      }
      $all_topics_oids[$infotree->tname] = (object) array('mod'=>$infotree, 'oids'=>[]);
      foreach($topics_oids as $oid) {
        $r = $infotree->_categories->rDisplay($oid, array(), false,'','',array('selectedfields' => ['UPD','PUBLISH','title','alias','linkup'], '_published' => false, 'linkup' => ['display_text_format' => '%_title']));
        $all_topics_oids[$infotree->tname]->oids[$oid] = [
          'oid' => $oid,
          'PUBLISH' => $r['oPUBLISH']->raw,
          'UPD' => $r['oUPD']->raw,
          'url' => $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$infotree_moid.'&_function=goto1&oid='.$oid.'&tplentry=br',
          'title' => $r['otitle']->text, //"{$r['olinkup']->text} &gt; {$r['otitle']->text}",
          'html' => \Seolan\Field\Link\xlinkdef_display_html($oid, array('display_format' => '%_linkup &gt; %_title')),
        ];
      }
    }
    //LETTERS
    $letters = getDb()->select("SELECT KOID, name, subject, UPD FROM LETTERS WHERE disp=? and lang=?",[$oidtpl, \Seolan\Core\Shell::getLangData()])->fetchAll();
    if (!is_array($letters) || !count($letters))
      return $all_topics_oids;
    $letters_moids = \Seolan\Core\Module\Module::modulesUsingTable('LETTERS');
    $letters_moid = array_key_first($letters_moids);
    $all_topics_oids['LETTERS'] = (object) array('mod'=>\Seolan\Core\Module\Module::objectFactory($letters_moid), 'oids'=>[]);
    foreach($letters as $letter) {
      $all_topics_oids['LETTERS']->oids[$letter['KOID']] = [
        'oid' => $letter['KOID'],
        'PUBLISH' => 1,
        'UPD' => $letter['UPD'],
        'url' => $GLOBALS['TZR_SESSION_MANAGER']::complete_self().'&moid='.$letters_moid.'&_function=goto1&oid='.$letter['KOID'].'&tplentry=br',
        'title' => "{$letter['name']} (sujet : {$letter['subject']})",
        'html' => \Seolan\Field\Link\xlinkdef_display_html($letter['KOID'], array('display_format' => '%_name (sujet : %_subject)')),
      ];
    }

    return $all_topics_oids;
  }
  
  /// Affiche seulement les gabarits utilisés
  public function browseUsed($ar) {
    $oids = array();
    $infotree_moids = getDB()->select('SELECT MOID FROM MODULES WHERE TOID=?',[XMODINFOTREE_TOID])->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($infotree_moids as $infotree_moid) {
      $infotree = \Seolan\Core\Module\Module::objectFactory($infotree_moid);
      $fetched_koids = getDb()->select("SELECT DISTINCT KOIDTPL FROM {$infotree->tname}")->fetchAll(\PDO::FETCH_COLUMN);
      $oids = array_merge($fetched_koids, $oids);
    }
    $ar['where'] = 'KOID IN ("'.implode('","', $oids).'")';
    return parent::browse($ar);
  }

  /// Affiche seulement les gabarits non utilisés
  public function browseUnused($ar) {
    $oids = array();
    $infotree_moids = getDB()->select('SELECT MOID FROM MODULES WHERE TOID=?',[XMODINFOTREE_TOID])->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($infotree_moids as $infotree_moid) {
      $infotree = \Seolan\Core\Module\Module::objectFactory($infotree_moid);
      //\Seolan\Core\Logs::critical(__METHOD__." sql=". "SELECT DISTINCT KOIDTPL FROM {$infotree->tname}");
      $fetched_koids = getDb()->select("SELECT DISTINCT KOIDTPL FROM {$infotree->tname}")->fetchAll(\PDO::FETCH_COLUMN);
      $oids = array_merge($fetched_koids, $oids);
    }
    $ar['where'] = 'KOID NOT IN ("'.implode('","', $oids).'")';
    //\Seolan\Core\Logs::critical(__METHOD__." where=". 'KOID NOT IN ("'.implode('","', $oids).'")');
    return parent::browse($ar);
  }

}
