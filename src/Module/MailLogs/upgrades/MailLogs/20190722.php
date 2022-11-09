<?php
  /**
   * Ajout des champs bodyfile dans _MLOGS et _MLOGSD
   * Et transfert du contenu du champ body
   */
function MailLogs_20190722() {

  if (!\Seolan\Core\System::tableExists('_MLOGS'))
    return;

  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_MLOGS');
  $fbody = $ds->getField('body');
  $ds->createField('bodyfile', 'HTML body', '\Seolan\Field\File\File', 0, ($fbody->forder+1), 0, 1, 1, 0, 0, 0);
  if (!$ds->fieldExists('bodyfile')){
    echo("\n adding bodyfile to _MLOGS");
  }

  if (!\Seolan\Core\System::tableExists('_MLOGSD'))
    return;

  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8('_MLOGSD');
  $fbody = $ds->getField('body');
  if (!$ds->fieldExists('bodyfile')){
    echo("\n adding bodyfile to _MLOGSD");
    $ds->createField('bodyfile', 'HTML body', '\Seolan\Field\File\File', 0, ($fbody->forder+1), 0, 1, 1, 0, 0, 0);
  }

  // transformer les anciens contenu html en fichiers ...  (bodyfile sur _MLOGS et _MLOGSD);
  $code = <<<'EOT'
  $updateTable = function($ors, $ds){
    $file = TZR_TMP_DIR.uniqid('conversioneml').'.html';
    file_put_contents($file, $ors['body']);
    $bodyfile =  ['tmp_name'=>$file,
		  'type'=>'text/html',
		  'name'=>'message contents '.$ors['UPD'],
		  'title'=>'message contents '.$ors['UPD']
		  ];
    $ds->procEdit(['_options'=>['local'=>1,''],
                   '_noupdateupd'=>1,
                   '_nolog'=>1,
		   'oid'=>$ors['koid'],
		   'bodyfile'=>$bodyfile,
		   'body'=>'' // on efface le body
		   ]
		  );
  };
  $tb = '_MLOGS';
  $tbd = '_MLOGSD';
  $ds = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($tb);
  $dsd = \Seolan\Core\DataSource\DataSource::objectFactoryHelper8($tbd);
  $oids = getDB()->fetchCol('select /*h*/ koid from '.$tb.' where html=1 and ifnull(bodyfile,"")=""');
  echo("\n".count($oids)." lines to convert in $tb");
  $nbd=0;
  $nb=0;
  echo("\ndone :");
  foreach($oids as $oid){
    $ors=getDB()->fetchRow('select /*h*/ koid, body, UPD from '.$tb.' where koid=?', [$oid]);
    $nb++;
    $updateTable($ors, $ds);
    $rsd = getDB()->select('select /*d*/ koid, body, UPD from '.$tbd.' where mlogh=? and ifnull(bodyfile,"")=""', [$ors['koid']]);
    $nbd += $rsd->rowCount();
    while($orsd = $rsd->fetch()){
      $updateTable($orsd, $dsd);
    }
    if ((($nb+$nbd)%100) == 0){
      echo("\r".($nb+$nbd).' done');
    }
  }
  echo("\n$nbd details lines converted");
  echo("\ndone");
EOT;
  $batch = new \Seolan\Core\Batch();
  $batch->addAction('convert _MLOGS', $code);

}
