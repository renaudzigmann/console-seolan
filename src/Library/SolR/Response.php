<?php
namespace Seolan\Library\SolR;

/**
 * Surcharge magique de la réponse de Solr (Apache_Solr_Response) afin de surcharger
 * l'objet avec des méthodes de récupération des résultats propre à Seolan.
 * @author Bastien Sevajol <bastien.sevajol@xsalto.com>
 */
class Response extends \Seolan\Library\ClassOverloader {
  
  /** @param Apache_Solr_Response $object */
  public function __construct(\Apache_Solr_Response $object) {
    parent::__construct($object);
  }
  
  /**
   * @param array $modules_ids Tableau di'dentifiant de module pour filtrer les résultats 
   * <b>Si tableau vide: Aucun filtre</b>
   * @return array Collection de résultats sous forme de tableaux atrray('id, 'moid', title', [...])
   */
  public function getResults(array $modules_ids = array()){
    $results=array();
    foreach ($this->object->response->docs as $document){
      $result = $this->getResult($document);
      if (in_array($result['moid'], $modules_ids) || empty($modules_ids)){
        $results[] = $result;
      }
    }
    return $results;
  }
  
  protected function getResult(\Apache_Solr_Document $document){
    $result = array();
    $result_names = $document->getFieldNames();
    foreach ($result_names as $result_name){
      $result_field = $document->getField($result_name);
      $result[$result_field['name']] = $result_field['value'];
    }
    return $result;
  }
  
  /**
   * @see Voir \Seolan\Library\SolR\Response::getResults() pour les paramètres
   * @return array Tableau de KOIDs
   */
  public function getResultKoids(array $modules_ids = array()){
    $koids = array();
    $results = $this->getResults($modules_ids);
    foreach ($results as $result){
      // Format d'un id: "<LANG>|<KOID>|<MOID>"
      $result_exploded = explode('|', $result['id']);
      $koids[] = $result_exploded[1];
    }
    return $koids;
  }
  
}
