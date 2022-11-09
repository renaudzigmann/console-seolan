<?php
  /// ajout de la section fonction "site search"
function InfoTree_20220401_sitesearch(){
  $title = 'Site Search Form';
  $functions = '\Seolan\\Module\InfoTree\InfoTree::siteSearch'; 
  $exists = getDB()->fetchOne('select koid from TEMPLATES where title=? and functions =? ',
			      [$title,
			       $functions]);
  

  if ($exists)
    getDB()->execute('delete from TEMPLATES where koid=?', [$exists]);
  
  $filename = TZR_TMP_DIR.uniqid().'sitesearchsftemplate.html';
  file_put_contents($filename, '<%include file="Module/InfoTree.defaulttemplates/disp-sitesearch.html"%>');
  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('TEMPLATES');
  $ds->procInput(['_options'=>['local'=>1],
		  'title'=>$title,
		  'functions'=>$functions,
		  'disp'=>$filename,
		  'gtype'=>'function'
  ]);
  
}
function InfoTree_comment_20220401_sitesearch(){
  return "Ajout du gabarit de section fonction 'Recherche sur le site'";
}
