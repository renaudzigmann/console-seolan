<?php
namespace Seolan\Core\Module;

abstract class ModuleWithSourceManagement extends \Seolan\Core\Module\Module{
  function secGroups($function, $group=NULL){
    $g['adminBrowseFields']=array('admin');
    $g['adminPrint']=array('admin');
    $g['adminClear']=array('admin');
    $g['adminDuplicate']=array('admin');
    $g['adminProcDuplicate']=array('admin');
    $g['adminChk']=array('admin');
    $g['adminEditSourceProperties']=array('admin');
    $g['adminProcEditSourceProperties']=array('admin');
    $g['adminNewField']=array('admin');
    $g['adminProcNewField']=array('admin');
    $g['adminEditField']=array('admin');
    $g['adminProcEditField']=array('admin');
    $g['adminProcEditFields']=array('admin');
    $g['adminDelField']=array('admin');
    $g['adminBrowseStrings']=array('admin');
    $g['adminNewString']=array('admin');
    $g['adminProcNewString']=array('admin');
    $g['adminEditString']=array('admin');
    $g['adminProcEditString']=array('admin');
    $g['adminDelString']=array('admin');
    $g['adminSortStrings']=array('admin');
    $g['adminClearStrings']=array('admin');
    $g['adminPreImportFieldsSec']=array('admin');
    $g['adminImportFieldsSec']=array('admin');
    $g['adminResetChrono']=array('admin');
    $g['adminRepairTranslations']=array('admin');
    if(isset($g[$function])) {
      if(!empty($group)) return in_array($group, $g[$function]);
      else return $g[$function];
    }
    return parent::secGroups($function,$group);
  }

  /// Liste des actions générale du module
  protected function _actionlist(&$my,$alfunction=true) {
    parent::_actionlist($my,$alfunction);
    // Mode conception et administration
    if($this->secure('','adminBrowseFields')){
      $o1=new \Seolan\Core\Module\Action($this, 'administration', \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General', 'administration'),
			    '&moid='.$this->_moid.'&function=adminBrowseFields&template=Core/Module.admin/browseFields.html','admin');
      $o1->setToolbar('Seolan_Core_General', 'administration');
      $my['administration']=$o1;
    }
  }

  /// Fonctions sur gestion des champs
  function al_adminBrowseFields(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'importfieldssec',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module','importfieldssec'),
			  '&moid='.$this->_moid.'&_function=adminPreImportFieldsSec&template=Core/Module.admin/preimportfieldssec.html&tplentry=br','more');
    $o1->menuable=true;
    $my['importfieldssec']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'newfield',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','new_field'),
			  '&moid='.$this->_moid.'&function=adminNewField&template=Core/Module.admin/newField.html','edit');
    $o1->menuable=true;
    $my['newfield']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'emptydata',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','empty_data'),
			  'javascript:'.$uniqid.'.emptybase();','more');
    $o1->menuable=true;
    $my['emptydata']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'clonebase',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','clone'),
			  '&moid='.$this->_moid.'&function=adminDuplicate&template=Core/Module.admin/duplicate.html&tplentry=br','more');
    $o1->menuable=true;
    $my['clonebase']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'checkrbase',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','check_and_repair'),
			  '&moid='.$this->_moid.'&function=adminChk&skip=1&repair=1&_next='.rawurlencode(\Seolan\Core\Shell::get_back_url(0)),'more');
    $o1->menuable=true;
    $my['checkrbase']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'propbase',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','properties'),
			  '&moid='.$this->_moid.'&function=adminEditSourceProperties&template=Core/Module.admin/editSource.html','more');
    $o1->menuable=true;
    $my['propbase']=$o1;
    // cas des tables traduisibles : recréer les données manquantes
    // $this->getAutoTranslate() ?
    if (($translatable = $this->xset->getTranslatable()) && $translatable != TZR_LANG_FREELANG){
      $o1=new \Seolan\Core\Module\Action($this,'repairtranslation', \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_DataSource_DataSource','repair_translations'),
			    '&moid='.$this->_moid.'&function=adminRepairTranslations&skip=1&_next='.rawurlencode(\Seolan\Core\Shell::get_back_url(0)),
			    'more');
      $o1->menuable=true;
      $my['repairtrasnlation']=$o1;
    }
    $o1=new \Seolan\Core\Module\Action($this,'printbase',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','print'),
			  'javascript:'.$uniqid.'.printselected();');
    $o1->menuable=true;
    $my['printbase']=$o1;
  }

  /// Fonctions sur gestion des champs
  function al_adminBrowseStrings(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $o1=new \Seolan\Core\Module\Action($this,'alphasort',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','alpha_sort'),
			  '&moid='.$this->_moid.'&function=adminSortStrings&skip=1&field='.$_REQUEST['field'].'&_next='.rawurlencode(\Seolan\Core\Shell::get_back_url(0)));
    $o1->menuable=true;
    $my['alphasort']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'deletebase',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','delete_all'),
			  'javascript:'.$uniqid.'.deleteall();');
    $o1->menuable=true;
    $my['deleteall']=$o1;
    $o1=new \Seolan\Core\Module\Action($this,'newstring',\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_General','new'),
			  '&moid='.$this->_moid.'&function=adminNewString&template=Core/Module.admin/newString.html&field='.$_REQUEST['field']);
    $o1->menuable=true;
    $my['newstring']=$o1;
  }

  /// Fonctions sur gestion des champs
  function al_adminEditField(&$my){
    $uniqid='v'.\Seolan\Core\Shell::uniqid();
    $br=\Seolan\Core\Shell::from_screen('');
    $o1=new \Seolan\Core\Module\Action($this,'resetchrono','Reset',
			  '&moid='.$this->_moid.'&_function=adminResetChrono&field='.$br['field'].'&boid='.$br['boid'].'&skip=1&_next='.
			  rawurlencode(\Seolan\Core\Shell::get_back_url(0)),'more');
    $o1->menuable=true;
    $my['resetchrono']=$o1;
  }

  /////////////////////////////////////////////
  // FONCTIONS D'ADMINISTRATION DE LA SOURCE //
  /////////////////////////////////////////////
  /// Liste des champs d'une table du module
  public function adminBrowseFields($ar=NULL) {
    $p=new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->browseFields($ar);
    $tablesec=array();
    $this->anyFieldsSec($tablesec);
    $r1['tableSec']=$tablesec;
    if($tplentry!=TZR_RETURN_DATA) {
      $r1['functions'] = array();
      $this->al_adminBrowseFields($r1['functions'] );
    }
    \Seolan\Core\Shell::toScreen1($tplentry, $r1);
  }

  /// Imprime la liste des champs d'une table
  public function adminPrint($ar=NULL) {
    $p = new \Seolan\Core\Param($ar, array());
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->browseFields($ar);
    \Seolan\Core\Shell::toScreen1($tplentry, $r1);
  }

  /// Ecran de generation d'un nouveau champ
  public function adminNewField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->newField($ar);
    \Seolan\Core\Shell::toScreen1($tplentry,$r1);
  }

  /// Créér un nouveau champ
  public function adminProcNewField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->procNewField($ar);
    \Seolan\Core\Shell::toScreen1($tplentry, $r1);
  }

  /// Ecran edition d'un champ
  public function adminEditField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->editField($ar);
    \Seolan\Core\Shell::toScreen1($tplentry,$r1);
  }

  /// Enregistre les modification sur un champ
  public function adminProcEditField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->procEditField($ar);
    \Seolan\Core\Shell::toScreen1($tplentry,$r1);
  }

  /// Edition des champs multiple à partir du browse
  public function adminProcEditFields($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->procEditFields($ar);
    \Seolan\Core\Shell::toScreen1($tplentry,$r1);
  }

  /// Supprime un champ
  public function adminDelField($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $r1=$this->xset->delField($ar);
    \Seolan\Core\Shell::toScreen1($tplentry,$r1);
  }

  /// Vide la table
  public function adminClear($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->clear(array('tplentry'=>TZR_RETURN_DATA));
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry,$ret);
  }

  /// Prepare la duplication d'une source
  public function adminDuplicate($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->duplicateDataSource($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Duplique une table du module
  public function adminProcDuplicate($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->procDuplicateDataSource($ar);
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Check/repare une table du module
  public function adminChk($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->chk($ar);
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }
  /// crée les traductions manquantes
  public function adminRepairTranslations($ar=null){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->repairTranslations($ar);
    if(\Seolan\Core\Shell::hasNext()) setSessionVar('message',$ret['message']);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Prepare l'edition des propriétés d'une source du module
  public function adminEditSourceProperties($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->editProperties($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Enregistre les modifications des propriétés d'une source du module
  public function adminProcEditSourceProperties($ar=NULL){
    $p=new \Seolan\Core\Param($ar,NULL);
    $tplentry=$p->get('tplentry');
    $ret=$this->xset->procEditProperties($ar);
    return \Seolan\Core\Shell::toScreen1($tplentry, $ret);
  }

  /// Parcours les valeurs d'un \Seolan\Field\StringSet\StringSet
  public function adminBrowseStrings($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid));
    $tset->browse($ar);
  }

  /// Prepare l'ajout d'une valeur à un \Seolan\Field\StringSet\StringSet
  public function adminNewString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->newString($ar);
  }

  /// Ajout d'une valeur à un XStringSetDe
  public function adminProcNewString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->procNewString($ar);
    if($ret['error']) setSessionVar('message',$ret['message']);
  }

  /// Prepare l'edition d'une valeur d'un \Seolan\Field\StringSet\StringSet
  public function adminEditString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->editString($ar);
  }

  /// Enregistre les modification d'une valeur d'un \Seolan\Field\StringSet\StringSet
  public function adminProcEditString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $ret=$tset->procEditString($ar);
    if($ret['error']) setSessionVar('message',$ret['message']);
  }

  /// Supprime une valeur d'un \Seolan\Field\StringSet\StringSet
  public function adminDelString($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->delString($ar);
  }

  /// Supprime toutes les valeurs d'un \Seolan\Field\StringSet\StringSet
  public function adminClearStrings($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->clearStrings($ar);
  }

  /// Reoordonne les valeurs d'un \Seolan\Field\StringSet\StringSet par ordre alphabétique
  public function adminSortStrings($ar=NULL) {
    $p=new \Seolan\Core\Param($ar,NULL);
    $boid=$p->get('boid');
    $tset=new \Seolan\Field\StringSet\Management(array('boid'=>$this->boid,'_options'=>array('local'=>true)));
    $tset->sortStrings($ar);
  }

  /// Réinitialise un chrono
  public function adminResetChrono($ar=NULL){
    $p=new \Seolan\Core\Param($ar, array());
    $table=$this->table;
    $field=$p->get('field');
    \Seolan\Core\DbIni::clear('Chrono::'.$table.'::'.$field);
  }

  /// Prepare l'importation d'un csv contenant les regles de securité sur les champs
  public function adminPreImportFieldsSec($ar=NULL){
  }

  /// Importe d'un csv contenant les regles de securité sur les champs
  /// Colonne en entete : AKOID => nom sql du champ, AFUNCTION => niveau de droit, AGRP => uid ou gid ou chaine de ugrpnames (voir param)
  public function adminImportFieldsSec($ar=NULL){
    $p=new \Seolan\Core\Param($ar,array('delallfields'=>0,'delotherfields'=>0,'file'=>$_FILES['file']['tmp_name'],
			    'endofline'=>"\r\n",'separator'=>';','quote'=>"\"",
			    'ugrpnames'=>array('AUTH'=>TZR_GROUPID_AUTH,'ALL'=>TZR_USERID_NOBODY)));
    $ugrpnames=$p->get('ugrpnames');
    $delbefore=$p->get('delbefore');
    if(!empty($delbefore)){
      // @RZ utiliser la fonction clean des ACL
      getDB()->execute('delete from ACL4 where AMOID="'.$this->_moid.'" and AKOID like "_field-%"');
      \Seolan\Core\Logs::update('security', \Seolan\Core\User::get_current_user_uid(), 'Delete all fields security rules for '.$this->_moid);
    }
    $file=$p->get('file');

    $message='';
    $spec->general->endofline=$p->get('endofline');
    $spec->general->separator=$p->get('separator');
    $spec->general->quote=$p->get('quote');
    $rawdata=@file_get_contents($file);
    $data=_getCSVData($rawdata,$spec);
    $head=$data[0];
    $l=count($data);
    $tot=0;
    for($i=1;$i<$l;$i++){
      $row=array();
      foreach($head as $j=>$h){
        if($data[$i][$j]==='') continue;
        $pos=strpos($h,'[');
        if($pos) $tmp='['.substr($h,0,$pos).']'.substr($h,$pos);
        else $tmp='['.$h.']';
        $tmp=str_replace(array('[',']'),array("['","']"),$tmp);
        $data[$i][$j]=addslashes($data[$i][$j]);
	eval('$row'.$tmp.'="'.str_replace('"','\"',$data[$i][$j]).'";');
      }
      if(empty($row['AKOID']) || empty($row['AFUNCTION']) || empty($row['AGRP'])){
	$message.='AKOID, AFUNCTION ou AGRP manquant ligne '.($i+1).'<br>';
	continue;
      }
      if(empty($this->xset->desc[$row['AKOID']])){
	$message.='Le champ '.$row['AKOID'].' n\'existe pas à la ligne '.($i+1).'<br>';
	continue;
      }
      if(!in_array($row['AFUNCTION'],array('none','ro','rw'))){
	$message.='Les droits doivent être none, ro ou rw à la ligne '.($i+1).'<br>';
	continue;
      }
      if(!empty($ugrpnames[$row['AGRP']])) $row['AGRP']=$ugrpnames[$row['AGRP']];
      if(in_array(\Seolan\Core\Kernel::getTable($row['AGRP']),array('USERS','GRP'))){
	$rs=getDB()->select('select KOID from '.\Seolan\Core\Kernel::getTable($row['AGRP']).' where KOID="'.$row['AGRP'].'"');
	if($rs->rowCount()!=1){
	  $message.='La cible des droits n\'existe pas à la ligne '.($i+1).'<br>';
	  continue;
	}
      }else{
	$message.='La cible des droits n\'est ni un USERS ni un GRP à la ligne '.($i+1).'<br>';
	continue;
      }

      $tot++;
      $this->procSecEdit(array('oid'=>'_field-'.$row['AKOID'],'level'=>$row['AFUNCTION'],'uid'=>$row['AGRP']));
    }
    $message.='Nombre de règle importée : '.$tot.'<br>';
    \Seolan\Core\Shell::toScreen2('','message',$message);
  }


  /**
   * Construction de la liste les contacts signataires pour la signature électronique de documents
   *
   * @param $ar
   * @return void
   */
  function browseElectronicSignatureContacts($ar) {
    $apiData = $this->universignWebServiceInfo();

    if(!$apiData || !$apiData['login'] || !$apiData['passwd'] || !$apiData['url']) {
      die(\Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module', 'esign_badspecs'));
    }

    $p = new \Seolan\Core\Param($ar,array());
    $all = $p->get('all');
    $oidsel = $p->get('_selected');
    $oid = $p->get('oid');
    if(count($oidsel) == 1) {
      $oid = $oidsel[0];
    }

    // on récupère les destinataires utilisés la dernière fois pour ce doc
    $registry = \Seolan\Core\Registry::getInstance();
    $lastUsed = (array)$registry->get($this->_moid, \Seolan\Core\User::get_current_user_uid(), $oid, "browseelectronicsignaturecontacts");
    // Liste des users/groupes
    $users = static::objectFactory(array('tplentry'=>TZR_RETURN_DATA,'toid'=>XMODUSER2_TOID,'_options'=>array('local'=>1)));

    $list = \Seolan\Core\User::getUsersAndGroups(true, $all, @$this->directorymodule);
    \Seolan\Core\Shell::toScreen1('bru', $list[0]);
    \Seolan\Core\Shell::toScreen1('brus_selected', $lastUsed);
    \Seolan\Core\Shell::toScreen1('brg', $list[1]);
    \Seolan\Core\Shell::toScreen2('brm', 'directory_module', @$this->directorymodule);

    // intégration du champ user : on récupère un champ user en mode standard (avec autocompletion et cie)
    // comme pour le mode treeview
    $selectors = [];
    $selopts= ['compulsory'=>0,'multivalued'=>1];
    if (!empty($this->directorymodule)){
      $dirmod = static::objectFactory(['moid'=>$this->directorymodule,
        'interactive'>=false,
        'tplentry'=>TZR_RETURN_DATA
      ]);
      if (!$dirmod instanceof \Seolan\Module\User\User){
        $selopts['sourcemodule']=$this->directorymodule;
      }
    }
    $ulabel = \Seolan\Core\Labels::getTextSysLabel('Seolan_Core_Module_Module', 'userlist');
    foreach(['udest' => 'to'] as $fn=>$luname){
      $selopts['field'] = $fn;
      $selopts['label'] = $ulabel; //'&nbsp;&nbsp';
      $lastvalues = $lastUsed[$luname]??null;
      $selectors[$fn] = $users->getUserSelector($this->_moid,$selopts,$lastvalues);
    }
    $selectors['_userfield'] = true;
    \Seolan\Core\Shell::toScreen1('selector', $selectors);
  }

  function sendDocumentsToContactsList($ar) {

    $p = new \Seolan\Core\Param($ar);

    $foid = $p->get("foid"); // Identifiant du fichier
    $fname = $p->get("fname"); // Nom du champ fichier dans table SQL du fichier
    $fsigned = $p->get("fsigned"); // Nom du champ fichier dans table SQL du fichier

    // Récupération des informations du document
    $res = $this->display(
      array(
        "oid" => $foid,
        "selectedfields" => [$fname],
        'tplentry' => TZR_RETURN_DATA,
        '_mode' => 'object',
      )
    );

    // Récupération du chemin local du document à signer
    // TODO: gestion multi documents à voir.
    $document[] = [
      'path' => $res['o'.$fname]->filename,
      'filename' => $res['o'.$fname]->originalname
    ];

    // Construction de la liste des signataires à partir de la selection
    $contacts = array();

    $udest = array_filter($p->get("udest"));
    $users = static::objectFactory(array('tplentry'=>TZR_RETURN_DATA,'toid'=>XMODUSER2_TOID,'_options'=>array('local'=>1)));
    foreach($udest as $dest) {
      $user = $users->display(array('oid'=>$dest,'tplentry'=>TZR_RETURN_DATA));
      $contacts[] = [
        'firstname' => $user['ofirstname']->raw ?: $user['ofullnam']->raw,
        'lastname' => $user['olastname']->raw ?: '',
        'email' => $user['oemail']->raw,
      ];
    }

    $dest_aemails = $p->get("dest_aemails");
    $dest_aemails = preg_split('/;/', $dest_aemails, null, PREG_SPLIT_NO_EMPTY);
    foreach($dest_aemails as $email) {
      $contacts[] = [
        'firstname' => '',
        'lastname' => '',
        'email' => $email,
      ];
    }

    // Envoi pour signature
    if (count($document) && count($contacts)) {

      $apiData = $this->universignWebServiceInfo();

      $electronicSignatureWebService = new ElectronicSignatureWebService($apiData['url'], $apiData['login'], $apiData['passwd']);

      // Envoi de la demande au service web
      $res = $electronicSignatureWebService->sendDocumentsForElectronicSignature($document, $contacts, 'Demande de signature via console Séolan');

      // Si la requète de soumission de document s'est bien passée. On obtient un id de requète pour suivi.
      if ($res['id']) {

        $signatures = \Seolan\Core\DbIni::get('electronic_signature', 'val');
        if (!$signatures) {
          $signatures = array();
        }

        // On enrichit la liste de suivi de signature
        $signatures[$res['id']] =
          [
            'moid' => $this->_moid,
            'foid' => $foid,
            'fsigned' => $fsigned,
          ];
        \Seolan\Core\DbIni::set('electronic_signature', $signatures);

        // TODO: peut mieux faire
        echo 'Demande de signature envoyée.';

      } else if ($res['error']) {
        // Erreur de soumission au service à gérer
        echo $res['error'];
      }
    }
  }

  /**
   *
   * @return void
   * @throws \DOMException
   */
  protected function checkNumericalSignatureValidations() {

    $apiData = $this->universignWebServiceInfo();
    if(empty($apiData)) return;

    $electronicSignatureWebService = new ElectronicSignatureWebService($apiData['url'],$apiData['login'],$apiData['passwd']);

    $signatures = \Seolan\Core\DbIni::get('electronic_signature', 'val');
    if (count($signatures)) {
      foreach ($signatures as $signatureId => $signature) {
        if ($signature['moid'] == $this->_moid) {

          // Demande d'état de signature au service Universign
          $res = $electronicSignatureWebService->sendTransactionInfoRequest($signatureId);

          switch ($res['status']) {

            case 'ready': // Document(s) en attente des signatures par les signataires. On ne fait rien.
              break;

            case 'canceled': // La demande de signature a été annulée.
              // Suppression des signatures en attente
              unset($signatures[$signatureId]);
              \Seolan\Core\DbIni::set('electronic_signature', $signatures);
              break;

            case 'completed': // Tous les signataires ont signé le(s) document(s)

              // Récupération des documents signés
              $dlRes = $electronicSignatureWebService->sendDownloadSignedDocumentsRequest($signatureId);

              if( $dlRes['status'] == 'error'){
                \Seolan\Core\Logs::debug(__METHOD__.' '.$dlRes['description']);
              }
              else
              {
                foreach ( $dlRes as $document ){

                  $filenameInfo = pathinfo($document['fileName']);

                  // Renommage du document
                  $filename = $filenameInfo['filename']."_signed.".$filenameInfo['extension'];

                  // Contenus signés
                  $content = base64_decode($document['content']);

                  $tmpFilename = $filenameInfo['filename']."_signé_".$document['id'].".".$filenameInfo['extension'];
                  file_put_contents(TZR_TMP_DIR.$tmpFilename, $content);

                  $paramProc = ['oid' => $signature['foid'],
                    $signature['fsigned']=> [
                      'size' => @filesize(TZR_TMP_DIR.$tmpFilename),
                      'type' => 'application/pdf',
                      'name' => $filename,
                      'tmp_name' => TZR_TMP_DIR.$tmpFilename,
                      'filename_del' => 'off',
                      'title' => $filename,
                    ]
                  ];

                  $this->procEdit($paramProc);

                  // TODO: voir gestion des ID pour récupération multi documents. En l'état on ne prend que le premier.
                  break;
                }

                // Suppression des signatures en attente
                unset($signatures[$signatureId]);
                \Seolan\Core\DbIni::set('electronic_signature', $signatures);
              }

              break;

            case 'error': // Problème avec la requète
              \Seolan\Core\Logs::debug(__METHOD__.' '.$res['description']);
              break;
          }
        }
      }
    }
  }

  /**
   * Les informations d'identification du Web Service Universing sont stockées dans la table des comptes externes
   * (table _ACCOUNTS -> url, login et passwd) dans un enregistrement dont le type est "WebServiceUniversign'.
   *
   * @return array
   */
  private function universignWebServiceInfo(): array {

    // Récupération des infos de l'API Universign en base
    $apiData = getDB()->fetchRow('SELECT login,passwd,url FROM `_ACCOUNTS` WHERE `atype` = "WebServiceUniversign"');
    if (!$apiData) {
      return [];
    }

    return $apiData;
  }

  protected function _daemon($period = "any") {
    parent::_daemon($period);

    // Validation des signatures numériques de documents pdf en attente
    $this->checkNumericalSignatureValidations();
  }


}
