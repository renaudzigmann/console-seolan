<?php
namespace Seolan\Module\InfoTree;
class Wizard extends \Seolan\Core\Module\Wizard {
  /// Nom des templates de base situés dans public/xmodinfotree/defaulttemplates
  public static function getDefaultTemplates() {
    return [
      'page' => [
        'text-only.html'      => '01 - Texte seul',
        'image-text.html'     => '02 - Image + texte',
        'text-image.html'     => '03 - Texte + image',
        'image-large.html'    => '04 - Grande image',
        '4-images.html'       => '05 - 1-2-3-4 images',
        'highlight.html'      => '06 - Texte mis en valeur',
        '2-columns.html'      => '07 - 2 colonnes',
        '3-columns.html'      => '08 - 3 infos à la une',
        'download.html'       => '09 - Fichier à télécharger',
        'flash.html'          => '10 - Flash',
        'video.html'          => '11 - Vidéo',
        'external-video.html' => '12 - Vidéo externe',
        'audio.html'          => '13 - Audio',
        'iframe.html'         => '14 - Insertion site externe',
        'sitemap.html'        => '15 - Plan du site',
        'service-public.html' => '16 - Comarquage service public',
        'roadmap.html'        => '17 - Itinéraire',
        'nivo-slider.html'    => '18 - Slider',
        'nl-4-images.html'    => 'NL - 4 images',
        'nl-download.html'    => 'NL - Fichier à télécharger',
        'nl-highlight.html'   => 'NL - Texte mis en valeur',
        'nl-image-large.html' => 'NL - Grande image',
        'nl-image-text.html'  => 'NL - Image + texte',
        'nl-separator.html'   => 'NL - Séparateur',
        'nl-text-image.html'  => 'NL - Texte + image',
        'nl-text-only.html'   => 'NL - Texte seul',
      ]
    ];
  }

  /**
   * Première étape, on demande si l'on souhaite :<ul>
   *  <li>créer une nouvelle table pour les rubriques</li>
   *  <li>créer une table pour les contenus</li>
   *  <li>si l'on souhaite ajouter les templates de base au nouveau gestionnaire de rubriques</li>
   * </ul>
   */
  function istep1() {
    global $TZR_LANGUAGES;
    $this->_module->modulename = \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics');
    $this->_module->group = \Seolan\Core\Labels::getSysLabel('Seolan_Core_General','website');
    foreach($TZR_LANGUAGES as $keylang => $adminlang) {
      $this->_module->comment[$keylang] = \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','comment');
    }
    parent::istep1();
    $this->_module->do_create_structure=$this->_module->do_create_dyn_structure=true;
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','createstructure'), 'do_create_structure', 'boolean');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','create_new_topics_table_comment'), 'do_create_structure');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','create_new_data_table'), 'do_create_data_structure', 'boolean');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','create_new_data_table_comment'), 'do_create_data_structure');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','create_new_dyn_table'), 'do_create_dyn_structure', 'boolean');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','create_new_dyn_table_comment'), 'do_create_dyn_structure');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','create_default_templates','text'),'do_create_default_templates','boolean');
    $this->_options->setComment(\Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','create_default_templates_comment','text'), 'do_create_default_templates');
  }

  /**
   * Deuxième étape, on demande des précisions sur les noms et code SQL des tables à créer
   */
  function istep2() {
    // On choisit une table des rubriques existante
    if (!$this->_module->do_create_structure) {
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table'), 'table', 'table');
    // On créé une nouvelle table des rubriques en demandant quels champs l'on souhaite y ajouter
    } else {
      $topics_table_name = \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics');
      $this->_module->bname = $this->_module->group.' - '.$this->_module->modulename;
      $this->_module->btab = \Seolan\Model\DataSource\Table\Table::newTableNumber('TOPICS');
      $create_field = \Seolan\Core\Labels::getSysLabel('Seolan_Core_Module_Module','create_field');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name').' ['.$topics_table_name.']', 'bname', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code').' ['.$topics_table_name.']', 'btab', 'text');
      $this->_module->do_create_field_linkin =
      $this->_module->do_create_field_urlext =
      $this->_module->do_create_field_custtitle =
      $this->_module->do_create_field_custdescr = true;
      $this->_module->do_create_field_style = true;
      $this->_module->field_style_table = '';
      $this->_options->setOpt($create_field.' "'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_linkin').'"','do_create_field_linkin','boolean');
      $this->_options->setOpt($create_field.' "'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_urlext').'"','do_create_field_urlext','boolean');
      $this->_options->setOpt($create_field.' "'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_custtitle').'"','do_create_field_custtitle', 'boolean');
      $this->_options->setOpt($create_field.' "'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_custdescr').'"', 'do_create_field_custdescr', 'boolean');
      $this->_options->setOpt($create_field.' "'.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_style').'"', 'do_create_field_style', 'boolean');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','table').' ['.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_style').']', 'field_style_table', 'table', [], '');
    }

    // On demande le nom et le code de la nouvelle table des contenus
    if ($this->_module->do_create_data_structure) {
      $data_table_name = \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','data_table_name');
      $this->_module->datatablename = $this->_module->group.' - '.$this->_module->modulename.' - '.$data_table_name;
      $this->_module->datatablecode = \Seolan\Model\DataSource\Table\Table::newTableNumber('DATA');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name').' ['.$data_table_name.']', 'datatablename', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code').' ['.$data_table_name.']', 'datatablecode', 'text');
    // Dans le cadre de la création des templates par défaut, on demande à quelle table les associer
    } elseif ($this->_module->do_create_default_templates) {
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','choose_data_table'), 'datatablecode', 'table');
    }

    if($this->_module->do_create_dyn_structure) {
      $dyn_table_name = \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','dyn_table_name');
      $this->_module->dyntablename = $this->_module->group.' - '.$this->_module->modulename.' - '.\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','dyn_table_name');
      $this->_module->dyntablecode = \Seolan\Model\DataSource\Table\Table::newTableNumber('DYNDATA');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_name').' ['.$dyn_table_name.']', 'dyntablename', 'text');
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Core_DataSource_DataSource','table_code').' ['.$dyn_table_name.']', 'dyntablecode', 'text');
    }else{
      $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','choose_dyn_table'), 'dyntablecode', 'table');
    }
  }

  /**
   * A la création du module, on créé toutes les tables avec les champs demandés
   */
  function iend($ar=NULL) {
    global $XSHELL;
    // Création de la nouvelle table des rubriques
    if($this->_module->do_create_structure) {
      $returns[] = $this->createCategoryStructure($this->_module->btab, $this->_module->bname);
      $this->_module->table = $this->_module->btab;
    }
    // Création de la table des contenus
    if ($this->_module->do_create_data_structure) {
      $returns[] = self::createDataStructure($this->_module->datatablecode, $this->_module->datatablename);
    }
    // Création de la table des contenus dynamiques
    $this->_module->dyntable=$this->_module->dyntablecode;
    if ($this->_module->do_create_dyn_structure) {
      $returns[] = self::createDynamicDataStructure($this->_module->dyntablecode, $this->_module->dyntablename);
    }
    foreach ($returns as $return) {
      foreach ($return as $res) {
        $ar['message'].= strip_tags($res['message']).'<br>';
      }
    }
    // Création de la table des sections (lien entre les tables rubriques, templates et contenus)
    self::createLinkStructure($this->_module->table);
    $moid = parent::iend($ar);
    // Ajout des templates par défaut en les liant à ce module
    if ($this->_module->do_create_default_templates) {
      $message = $XSHELL->tpldata['wd']['message'];
      $results = static::createDefaultTemplates([
        'modid' => $moid,
        'tab'   => $this->_module->datatablecode,
      ]);
      if (!empty($results['added_templates']))
        $message.= 'Added templates : <br> - '.implode('<br> - ',$results['added_templates']).'<br>';
      if (!empty($results['skipped_templates']))
        $message.= 'Skipped templates : <br> - '.implode('<br> - ',$results['skipped_templates']).'<br>';
      $XSHELL->tpldata['wd']['message'] = $message.'<br>Installation end';
    }
    return $moid;
  }

  /**
   * Créé une table des sections spécifique à chaque table des rubriques (préfixée par IT)
   */
  static function createLinkStructure($it_table_code) {
    $sections_table_code = 'IT'.$it_table_code;
    if (!\Seolan\Core\System::tableExists($sections_table_code)) {
      getDB()->execute('CREATE TABLE '.$sections_table_code.'(KOIDSRC VARCHAR(40) DEFAULT "0" NOT NULL,'.
                       'KOIDDST varchar(40) DEFAULT "0" NOT NULL,'.
                       'KOIDTPL varchar(40) DEFAULT "0" NOT NULL,'.
                       'ZONE  VARCHAR(255) NOT NULL DEFAULT "default",'.
                       'ORDER1 INTEGER DEFAULT 0,'.
                       'ITOID varchar(40) NOT NULL PRIMARY KEY)');
      getDB()->execute('ALTER TABLE '.$sections_table_code.' ADD INDEX (KOIDSRC)');
      getDB()->execute('ALTER TABLE '.$sections_table_code.' ADD INDEX (KOIDDST)');
      getDB()->execute('ALTER TABLE '.$sections_table_code.' ADD INDEX (ZONE)');
    }
    return $sections_table_code;
  }

  /**
   * Créé une table des rubriques
   */
  function createCategoryStructure($table_code, $table_name) {
    $ar1['translatable'] = '1';
    $ar1['auto_translate'] = '1';
    $ar1['btab'] = $table_code;
    $ar1['bname'][TZR_DEFAULT_LANG] = $table_name;
    $res[] = \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    \Seolan\Core\DataSource\DataSource::clearCache();
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table_code);
    $ord = 3;
    //                                                                                                                 size   ord  obl que bro tra mul pub tar
    $res[] = $x->createField('title',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','title'),'\Seolan\Field\ShortText\ShortText',                            '120',$ord++,'1','1','1','1','0','1');
    $res[] = $x->createField('corder',\Seolan\Core\Labels::getSysLabel('Seolan_Core_General','order'),'\Seolan\Field\Order\Order',                                 '3',$ord++,'1','0','0','0','0','0');
    $res[] = $x->createField('linkup',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_linkup'),'\Seolan\Field\Link\Link',               '5',$ord++,'0','1','0','0','0','0',$ar1['btab']);
    $res[] = $x->createField('alias',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','alias'),'\Seolan\Field\ShortText\ShortText',                        '60',$ord++,'0','0','0','0','0','0');
    if ($this->_module->do_create_field_linkin == 1){
      $res[] = $x->createField('redirmethod','Type de redirection','\Seolan\Field\StringSet\StringSet','128',$ord++,'0','1','0','0','0','0','',
                               ['default'=>'302','checkbox'=>0]);
      $f=$x->getField('redirmethod');
      $f->newString('Intégration du contenu','content');
      $f->newString('Redirection permanente','301');
      $f->newString('Redirection temporaire','302');
      if ($this->_module->do_create_field_style){
	$res[] = $x->createField('style',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_style'),'\Seolan\Field\Link\Link','5',$ord++,'0','0','0','0','0','0', empty($this->_module->field_style_table)?TZR_DEFAULT_TARGET:$this->_module->field_style_table);
      }
      $res[] = $x->createField('linkin',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_linkin'),'\Seolan\Field\Link\Link', '',$ord++,'0','0','0','0','0','0',$ar1['btab'],
                               ['dependency'=>['f'=>'redirmethod',
                                                         'op'=>['idx'=>'=','idx2'=>'!='],
                                                         'dval'=>['idx'=>'','idx2'=>''],
                                                         'style'=>['idx'=>'hidden','idx2'=>''],
                                                         'val'=>['idx'=>'','idx2'=>''],
                                                         'nochange'=>['idx'=>'0','idx2'=>'1']
                                                        ]
                                    ]
                               );
      $res[] = $x->createField('redirext','Redirection externe','\Seolan\Field\ShortText\ShortText','255',$ord++,'0','1','0','1','0','0','',
                               ['dependency'=>['f'=>'redirmethod',
                                                         'op'=>['idx'=>'=','idx2'=>'!=','idx3'=>'='],
                                                         'dval'=>['idx'=>'','idx2'=>'','idx3'=>'content'],
                                                         'style'=>['idx'=>'hidden','idx2'=>'','idx3'=>'hidden'],
                                                         'val'=>['idx'=>'','idx2'=>'','idx3'=>''],
                                                         'nochange'=>['idx'=>'0','idx2'=>'1','idx3'=>'1']
                                                         ]
                                    ]
                              );
      $this->_module->linkin = 'linkin';
    }
    if ($this->_module->do_create_field_urlext == 1)
      $res[] = $x->createField('urlext',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_urlext'),'\Seolan\Field\Url\Url',            '128',$ord++,'0','0','0','0','0','0');
    if ($this->_module->do_create_field_custtitle == 1)
      $res[] = $x->createField('custtitle',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_custtitle'),'\Seolan\Field\ShortText\ShortText','120',$ord++,'0','0','0','0','0','0');
    if ($this->_module->do_create_field_custdescr == 1)
      $res[] = $x->createField('custdescr',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','topics_field_custdescr'),'\Seolan\Field\Text\Text',     '128',$ord++,'0','0','0','0','0','0');
    // Insertion des pages systèmes
    $system_pages = [
      ['alias'=>'home','title'=>'Home','PUBLISH'=>1],
      ['alias'=>'error404','title'=>'Error 404 : Not Found','PUBLISH'=>1],
    ];
    foreach ($system_pages as $page) {
      $x->procInput($page);
    }
    return $res;
  }

  /**
   * Créé une table des contenus
   */
  static function createDataStructure($table_code, $table_name=null) {
    // creation de la table des categories
    $ar1['translatable'] = '1';
    $ar1['auto_translate'] = '1';
    $ar1['btab'] = $table_code;
    if ($table_name == null){
      $table_name = "Auto : $table_code";
    }
    $ar1['bname'][TZR_DEFAULT_LANG] = $table_name;
    $res[] = \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table_code);
    $ord = 3;
    //                                                                                                                  size  ord   obl que bro tra mul pub tar
    $res[] = $x->createField('titsec', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_title'),'\Seolan\Field\ShortText\ShortText',       '255',$ord++,'0','1','1','1','0','1');
    $res[] = $x->createField('subtit', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_subtit'),'\Seolan\Field\ShortText\ShortText',      '255',$ord++,'0','1','1','1','0','1');
    $res[] = $x->createField('chapeau',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_chapeau'),'\Seolan\Field\Text\Text',           '50',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('txt1',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_txt').' 1','\Seolan\Field\RichText\RichText',         '60',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('txt2',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_txt').' 2','\Seolan\Field\RichText\RichText',         '60',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('txt3',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_txt').' 3','\Seolan\Field\RichText\RichText',         '60',$ord++,'0','1','0','1','0','0');
    for ($i = 1; $i <= 4; $i++) {
      $res[] = $x->createField('img'.$i,    \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_img')." $i",'\Seolan\Field\Image\Image',     '',$ord++,'0','0','0','0','0','0');
      $res[] = $x->createField('imglnk'.$i, \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_imglnk')." $i",'\Seolan\Field\Boolean\Boolean',   '',$ord++,'0','0','0','0','0','0');
      $res[] = $x->createField('imgurl'.$i, \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_imgurl')." $i",'\Seolan\Field\Url\Url',    '',$ord++,'0','0','0','0','0','0');
      $res[] = $x->createField('imgleg'.$i, \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_imgleg')." $i",'\Seolan\Field\Boolean\Boolean','120',$ord++,'0','1','0','1','0','0');
    }
    $res[] = $x->createField('fichier',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_fichier'),'\Seolan\Field\RichText\RichText',       '60',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('height', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_height'), '\Seolan\Field\ShortText\ShortText',       '5',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('width',  \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_width'),  '\Seolan\Field\ShortText\ShortText',       '5',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('flashb', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_flashb'), '\Seolan\Field\Color\Color',            '',$ord++,'0','1','0','1','0','0');
    $res[] = $x->createField('flashwm',\Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_flashwm'),'\Seolan\Field\StringSet\StringSet',        '',$ord++,'0','1','0','1','0','0');
    $f = $x->getField('flashwm');
    if ($f) {
      $f->newString('transparent','transparent');
      $f->newString('opaque','opaque');
      $f->newString('window','window');
      $f->newString('direct','direct');
      $f->newString('gpu','gpu');
    }
    $res[] = $x->createField('lat', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_lat'), '\Seolan\Field\ShortText\ShortText',            '20',$ord++,'0','1','0','0','0','0');
    $res[] = $x->createField('lng', \Seolan\Core\Labels::getSysLabel('Seolan_Module_InfoTree_InfoTree','section_field_lng'), '\Seolan\Field\ShortText\ShortText',            '20',$ord++,'0','1','0','0','0','0');
    return $res;
  }

  /// Créé la table des sections dynamiques si elle n'existe pas déjà
  function createDynamicDataStructure($table_code, $table_name) {
    $ar1['translatable'] = '1';
    $ar1['auto_translate'] = '1';
    $ar1['btab'] = $table_code;
    $ar1['bname'][TZR_DEFAULT_LANG] = $table_name;
    $res[] = \Seolan\Model\DataSource\Table\Table::procNewSource($ar1);
    $x = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS='.$table_code);

    $ord = 3;
    //                                                                     size  ord   obl que bro tra mul pub tar
    $res[] = $x->createField('tpl','Gabarit','\Seolan\Field\Link\Link',                    '0',$ord++,'1','1','1','0','0','1','TEMPLATES');
    $res[] = $x->createField('module','Module des données','\Seolan\Field\Module\Module',    '0',$ord++,'0','1','1','0','0','0');
    $res[] = $x->createField('query','Paramètres','\Seolan\Field\Serialize\Serialize',         '70',$ord++,'0','0','0','1','0','0');
    // Champ PUBLISH non traduisible par défaut
    $x->procEditField([
      'tplentry'=>TZR_RETURN_DATA,
      'field'=>'PUBLISH',
      '_todo'=>'save',
      'translatable'=>false
    ]);
    return $res;
  }

  static function convertToMultiZone($mod){
    // Création du champ tpl
    if(!$mod->_categories->fieldExists('tpl')){
      $mod->_categories->createField('tpl','Template de page','\Seolan\Field\Link\Link',0,'','0','1','0','0','0','0','TEMPLATES');
      $mod->_categories->procEditField([
        'tplentry'=>TZR_RETURN_DATA,
        'field'=>'tpl',
        '_todo'=>'save',
        'options'=>[
          'filter'=>'gtype="cat"',
          'checkbox'=>false
        ]
      ]);
    }
    // Création du champ model
    if(!$mod->_categories->fieldExists('model')){
      $mod->_categories->createField('model','Modèle de page','\Seolan\Field\Link\Link',0,'','0','1','0','0','0','0',$mod->table);
      $mod->_categories->procEditField([
        'tplentry'=>TZR_RETURN_DATA,
        'field'=>'model',
        '_todo'=>'save',
        'options'=>[
          'filter'=>'linkup="'.$mod->table.':MODELS"',
          'checkbox'=>false
        ]
      ]);
    }
    // Création de la table des infos sur les zones
    if(!\Seolan\Core\DataSource\DataSource::sourceExists($mod->zonetable)){
      \Seolan\Model\DataSource\Table\Table::procNewSource([
        'btab'=>$mod->zonetable,
        'bname'=>[TZR_DEFAULT_LANG=>$mod->getLabel().' : Paramètres des zones'],
        'publish'=>false,
        'own'=>false,
      ]);
      $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS='.$mod->zonetable);
      //                                                                                                 size ord  obl que bro tra mul pub tar
      $x->createField('cat','Page','\Seolan\Field\Thesaurus\Thesaurus',                                                       '0','1' ,'1','1','1','0','0','1',$mod->table);
      $x->procEditField(['field'=>'cat','ftype'=>'\Seolan\Field\Thesaurus\Thesaurus',
                              'options'=>['flabel'=>'title','fparent'=>'linkup','quickadd'=>false,'sourcemodule'=>$mod->_moid]]);
      $x->createField('zone','Zone','\Seolan\Field\ShortText\ShortText',                                                    '255','2' ,'1','1','1','0','0','1');
      $x->createField('inherit','Ajouter le contenu du modèle','\Seolan\Field\StringSet\StringSet',                           '0','3' ,'1','1','0','0','0','0');
      $x->getField('inherit')->newString('Ne pas ajouter','none');
      $x->getField('inherit')->newString('Avant','add_before');
      $x->getField('inherit')->newString('Après','add_after');
      $x->createField('_not_editable','Zone non éditable par les pages utilisant le modèle','\Seolan\Field\Boolean\Boolean',   '0','4' ,'0','1','0','0','0','0');
    }

    // Insertion de la rubrique des modèle
    $mod->procInput(['title'=>'Modèles de page','corder'=>9999,'alias'=>'models','descr'=>'Modèles de page','newoid'=>$mod->getModelsOid()]);

    // Création champ zones dans TEMPLATES
    $x=\Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=TEMPLATES');
    if(!$x->fieldExists('zones')){
      $x->createField('zones','Zones','\Seolan\Field\ShortText\ShortText',    255,  '',  '0','1','0','0','1','0');
    }
  }
  /**
   * Import des templates de base d'un gestionnaire de rubriques
   * @param $ar array(
   *   'tab'  => nom SQL de la table des contenus,
   *   'moid' => MOID du module à qui vont appartenir les templates
   * )
   * @return array(
   *   'skipped_templates' => Tableau des templates déjà existants,
   *   'added_templates' => Tableau des templates insérés,
   * )
   */
  public static function createDefaultTemplates($ar) {
    $p = new \Seolan\Core\Param($ar);
    $tab = $p->get('tab');
    $moid = $p->get('modid');
    if (empty($moid)) return \Seolan\Core\Logs::notice('\Seolan\Core\Module\Module::createDefaultTemplates', 'No MOID specified');
    // On récupère les titres des templates actuels afin de vérifier si les templates par défaut ont déjà été insérés (à défaut d'avoir un ID)
    $xds_templates = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('BCLASS=\Seolan\Model\DataSource\Table\Table&SPECS=TEMPLATES');
    $br_templates = $xds_templates->browse(['cond'=>['modid'=>['=',$moid]],'pagesize'=>9999]);
    $current_titles = [];
    
    foreach ($br_templates['lines_otitle']??[] as $title) {
      $current_titles[] = $title->raw;
    }
    $default_templates_path = 'Module/InfoTree/public/templates/defaulttemplates';
    // Tableaux renvoyés par la fonction
    $skipped_templates = $added_templates = [];
    // Récupère la liste des templates dans la classe fille
    $default_templates = static::getDefaultTemplates();
    // Parcours du tableau default_templates du module wizard
    foreach ($default_templates as $tpl_gtype => $tpl_files) {
      // Boucle sur les templates
      foreach ($tpl_files as $tpl_filename => $tpl_title) {
	// On vérifie qu'aucun template du même titre n'existe déjà
	if (in_array($tpl_title, $current_titles)) {
	  $skipped_templates[] = $tpl_title;
	  continue;
	}
        $ret=static::createDefaultTemplate($default_templates_path, $tpl_gtype, $tpl_title, $tpl_filename, $moid, $tab);
        if($ret){
          $added_templates[] = $tpl_title;
        }else{
          $skipped_templates[] = $tpl_title;
        }
      }
    }
    // Utilisé pour construire le message de retour dans le template
    return [
      'skipped_templates' => $skipped_templates,
      'added_templates' => $added_templates,
    ];
  }

  public static function createDefaultTemplate($default_templates_path, $tpl_gtype, $tpl_title, $tpl_filename, $moid, $tab, $new_oid=null){
    // Tableau des données à insérer pour ce template
    $insert_data = [
      'title'  => $tpl_title,
      'modid'  => $moid,
      'gtype'  => $tpl_gtype,
      'tab'    => $tab,
      'newoid' => $new_oid,
    ];
    // Modes d'affichage des templates correspondant aux champs SQL de la table TEMPLATES
    $modes = ['disp','edit','print'];
    $count_not_exists = 0;
    foreach ($modes as $mode) {
      $tpl_tzrpathname = $default_templates_path.'/'.$tpl_gtype.'/'.$mode.'/'.$tpl_filename;
      if (!file_exists(TZR_SHARE_DIR.$tpl_tzrpathname)) {
        $count_not_exists++;
        continue;
      }
      // On écrit le code smarty qui inclut le template de base dans un fichier temporaire qui sera inséré dans les TEMPLATES
      $tpl_tmp = TZR_TMP_DIR.'link-'.$mode.'-'.$tpl_filename;
      file_put_contents($tpl_tmp, '<%include file="`$smarty.const.TZR_SHARE_DIR`'.$tpl_tzrpathname.'"%>');
      $insert_data[$mode] = $tpl_tmp;
    }
    // Si aucun template n'existe dans aucun des modes, il n'y a rien à insérer
    if ($count_not_exists == count($modes)) return false;
    // Insertion du template dans la table des TEMPLATES
    \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('SPECS=TEMPLATES')->procInput($insert_data);
    return true;
  }

}

