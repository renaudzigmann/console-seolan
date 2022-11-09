<?php
namespace Seolan\Library;

/**
 * @author Bastien Sevajol <bastien.sevajol@xsalto.com>
 * Classe permettant de consolider rapidement une table a partir d'une autre.
 * En definissant la correspondance des champs dans $fields_correspondance et
 * en surchargeant les médthodes ci-dessous
 * TODO: Ajouter lien vers documentation.
 */
abstract class DataConsolider {  
  
  /** @var \Seolan\Module\Table\Table */
  protected $source_module;
  
  /** @var \Seolan\Module\Table\Table */
  protected $target_module;
  
  protected $source_module_id;
  protected $target_module_id;
  
  protected $target_reference_data = array();
  protected $fields_correspondance = array();
  
  protected $usleep;
  
  /**
   * @param int $usleep
   */
  public function __construct($usleep = Null) {
    $this->usleep = $usleep;
    $this->source_module = $this->getModule($this->getSourceModuleId());
    $this->target_module = $this->getModule($this->getTargetModuleId());
  }
  
  /**
   * Retourne le module correspondant a l'id transmis.
   * @param int $module_id
   * @return \Seolan\Module\Table\Table
   * @throws Exception
   */
  protected function getModule($module_id) {
    if (($module = \Seolan\Core\Module\Module::objectFactory($module_id)) instanceof \Seolan\Module\Table\Table) {
      return $module;
    }
    throw new \Exception ("$module_id is not a \Seolan\Module\Table\Table module !");
  }
  
  /**
   * Retourne l'identifiant du module source
   * @return int
   * @throws Exception
   */
  public function getSourceModuleId() {
    if ($this->source_module_id) {
      return $this->source_module_id;
    }
    throw new \Exception("You must specify source_module_id !");
  }
  
  /**
   * Retourne l'identifiant du module cible
   * @return int
   * @throws Exception
   */
  public function getTargetModuleId() {
    if ($this->target_module_id) {
      return $this->target_module_id;
    }
    
    throw new \Exception("You must specify target_module_id !");
  }
  
  /**
   * Seule méthode publique de la classe. C'est celle-ci qui est appelé pour 
   * lancer la consolidation.
   */
  public function consolid() {
    $this->loadTargetReferenceData();
    foreach ($this->getLanguages() as $language) {
      foreach ($this->getSourceObjects($language) as $source_object) {
        $consolided_object = $this->getConsolidedObjectData($source_object);
        $this->synchronizeObject($source_object, $consolided_object, $language);
        if ($this->usleep) usleep($this->usleep);
      }
    }
  }
  
  /**
   * Récupère et place dans $this->target_reference_data les données qui nous 
   * servirons a determiner si l'objet que l'on s'apprête a consolider existe 
   * déjà dans le module cible.
   * <b>Surchargez cette méthode pour avoir plus de données nécessaire lorsque
   * l'on cherchera a determiner si l'objet en cours existe déjà dans la table
   * cible (isSourceObjectExistInTarget()).</b>
   */
  protected function loadTargetReferenceData() {
    $this->target_reference_data = getDB()->fetchCol(
      "SELECT DISTINCT(KOID) FROM ".$this->target_module->table
    );
  }
  
  /**
   * Retourne les langues utilisés et configurés dans "TZR_LANGUAGES"
   * @return array
   * @throws Exception
   */
  protected function getLanguages() {
    if (!count($GLOBALS['TZR_LANGUAGES'])) {
      throw new \Exception("TZR_LANGUAGES is not defined");
    }
    
    return array_keys($GLOBALS['TZR_LANGUAGES']);
  }

  /**
   * Retourne les objets ainsérer/mettre à jour dans la cible.
   * @param string $language
   * @return array
   */
  protected function getSourceObjects($language) {
    $data = $this->source_module->procQuery($this->getSourceObjectsParameters($language));
    if (!array_key_exists('lines', $data) || empty($data['lines'])) {
      return array();
    }
    return $data['lines'];
  }
  
  /**
   * @param string $language
   * @return array
   */
  protected function getSourceObjectsParameters($language) {
    return array(
      '_fromDataImport',
      '_local' => True,
      'tplentry' => TZR_RETURN_DATA,
      'LANG_DATA' => $language,
      '_mode' => 'object',
      'selectedfields' => 'all',
      'pagesize' => 999999999
    );
  }
  
  /**
   * Construit le tableau qui sera transmis au procEdit ou au procInsert a partir
   * des données de l'objet source. Pour que les données soit formattés correctement,
   * voir dans getTargetFieldValue().
   * @param array $source_object
   * @return array
   */
  protected function getConsolidedObjectData(array $source_object) {
    $consolided_object_data = array();
    
    foreach ($this->getTargetFields() as $target_field) {
      $consolided_object_data[$target_field] = $this->getTargetFieldValue($target_field, $source_object);
    }
    
    return $consolided_object_data;
  }
  
  /**
   * Retourne les champs de la table cible que l'on va "nourrir" de données.
   * Ces champs sont définis (comme valeurs) dans $this->fields_correspondance.
   * @return array
   */
  protected function getTargetFields() {
    return array_values($this->getFieldsCorrespondance());
  }
  
  /**
   * Retourne $this->fields_correspondance en vérifiant préalablement qu'il a été
   * renseigné.
   * @return array
   * @throws Exception
   */
  protected function getFieldsCorrespondance() {
    if (empty($this->fields_correspondance)) {
      throw new \Exception("You must define the field correspondance");
    }
    return $this->fields_correspondance;
  }
  
  /**
   * Retourne la valeur correspondant au champs de l'objet que l'on insérera/updatera
   * dans la table cible.
   * TODO: Découper la méthode en plusieurs petites
   * @param string $target_field_name
   * @param array $source_object
   * @return ...
   * @throws Exception
   */
  protected function getTargetFieldValue($target_field_name, $source_object) {
    $personalized_method = 'calculate_'.$target_field_name;
    $source_field_name = array_search($target_field_name, $this->getFieldsCorrespondance());
    if (strpos($source_field_name,'__OBJECT__')===0) {
      if (method_exists($this, $personalized_method)) {
        return $this->$personalized_method($source_object);
      } else {
        throw new \Exception("You must write personalized method \"$personalized_method\" for __OBJECT__ source of target field");
      }
    } elseif (array_key_exists($source_field_name, $source_object)){
      if (method_exists($this, $personalized_method)) {
        return $this->$personalized_method($source_object[$source_field_name]);
      }
      if ($source_object[$source_field_name] instanceof \Seolan\Core\Field\Value) {
        return $source_object[$source_field_name]->raw;
      }
      throw new \Exception("Unable to calculate \"$target_field_name\", please write method \"$personalized_method\"");
    }
    
    throw new \Exception("No \"$source_field_name\" in source object: Unable to calculate target field value");
  }
  
  /**
   * Insère ou met à jour les données de l'objet (préparés et donc prête pour la
   * table cible).
   * @param array $source_object
   * @param array $consolided_object_data
   * @param string $language
   */
  protected function synchronizeObject($source_object, $consolided_object_data, $language) {
    $source_object_target_koid = $this->getNewKoid($source_object);
    if ($this->isSourceObjectExistInTarget($source_object_target_koid, $source_object)){
      $this->updateObject($this->getRemoteKoid($source_object), $consolided_object_data, $language);
    } else {
      $this->insertObject($source_object_target_koid, $consolided_object_data, $language);
    }
  }
  
  protected function getRemoteKoid(array $source_object){
    throw new \Exception("You must implement me.");
  }
  
  /**
   * C'est ici que l'on détermine si l'objet en cours existe déjà ou non dans la
   * table cible. Surcharger cette méthode si son comportement par défaut ne 
   * convient pas (si vous avez besoin de données supplémentaires dans 
   * $this->target_reference_data, surchargez egallement loadTargetReferenceData().
   * @param int $source_object_target_koid
   * @param int $source_object 
   * @return boolean
   */
  protected function isSourceObjectExistInTarget($source_object_target_koid, array $source_object) {
    if (in_array($source_object_target_koid, $this->target_reference_data)) {
      return True;
    }
    return False;
  }
  
  /**
   * Retourne le koid que doit avoir cet objet dans la table cible. Si retourne Null, 
   * alors on ne précise pas de newoid a l'insert.
   * Surcharger si l'on veut que lors de l'insertion l'enregistrement est un 
   * koid personalisé.
   * @param array $source_object
   * @return string
   */
  protected function getNewKoid($source_object) {
    return Null;
  }
  
  protected function updateObject($target_koid, $consolided_object_data, $language) {
    $this->target_module->procEdit(array_merge($consolided_object_data, array(
      'oid' => $target_koid,
      '_langs' => array($language)
    )));
  }
  
  protected function insertObject($newoid, $consolided_object_data, $language) {
    $data = $consolided_object_data;
    if ($language == 'FR') {
      if ($newoid) {
        $data = array_merge($consolided_object_data, array(
          'newoid' => $newoid
        ));
      }
      $this->target_module->procInsert($data);
    } else {
      $this->updateObject($newoid,$consolided_object_data, $language);
    }
  }
  
}
