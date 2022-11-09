<?php
namespace Seolan\Module\Sitra;

/**
 * User: bsevajol
 * Date: 18/05/15
 * Time: 16:32
 */

/**
 * Class SelectionsSync
 * Chargé de synchroniser les champs "selections" dans les table consolidés.
 */
class SelectionsSync {

  /**
   * @var XModTable
   */
  protected $selections_module;

  /**
   * @var array
   * Contient les modules qui doivent être consolidés
   */
  protected $consolidation_modules = array();

  /**
   * @param array $consolidation_modules: Liste des modules concernés.
   */
  public function __construct(array $consolidation_modules) {
    $this->selections_module = \Seolan\Core\Module\Module::objectFactory(\Seolan\Core\Ini::get('module_sitra_selections'));
    $this->consolidation_modules = $consolidation_modules;
  }

  /**
   * Synchronise les champs sélection des modules passés à la construction.
   */
  public function synchronize() {
    $correspondance = $this->getCorrespondances();

    // Au préalable on efface les liens des fiches qui ne sont pas dans $local_correspondance (car elles n'ont plus de
    // selection)
    $this->clearSelections($correspondance);

    foreach ($this->consolidation_modules as $consolidation_module) {
      $local_koids_in_this_module = $this->getFichesKoidsInModule($correspondance, $consolidation_module);

      // Mise à jour des fiches
      foreach ($correspondance as $fiche_koid => $selections_koids) {
        $local_fiche_koid = $this->getLocalFicheKoidReplaced($fiche_koid, $consolidation_module);

        // Hack: la taille en bdd du champs de SIT_SELECTIONS n'était pas assez grand. Il y a des valeurs tronqués.
        // En attendant la correction, on contrôle la tête du KOID:
        if (strpos($local_fiche_koid, $consolidation_module->table) !== False
            && in_array($local_fiche_koid, $local_koids_in_this_module)) {

          $consolidation_module->procEdit(array(
            '_nolog'=>true,
            '_noupdateupd'=>true,
            'oid' => $local_fiche_koid,
            'selections' => $selections_koids
          ));
        }
      }
    }


  }

  /**
   * Retourne la liste des sélections pour chaque fiche.
   * @return array : ('SITRA_KOID' => array('SELECTION_KOID','SELECTION_KOID',...)...)
   */
  protected function getCorrespondances() {
    $correspondance = array();
    $selections = $this->getSelections();

    foreach ($selections as $selection) {
      $selection_fiches_koids = $selection['oobjetsTouristiques']->oidcollection;
      foreach ($selection_fiches_koids as $selection_fiche_koid) {

        if (!isset($correspondance[$selection_fiche_koid])) {
          $correspondance[$selection_fiche_koid] = array();
        }

        $correspondance[$selection_fiche_koid][] = $selection['oid'];
      }
    }

    return $correspondance;
  }

  /**
   * @return mixed
   */
  protected function getSelections() {
    if(!$this->selections_module) {
      return [];
    }
    return $this->selections_module->procQuery(array(
      '_mode' => 'object',
      'selectedfields' => array('objetsTouristiques'),
      'pagesize' => '99999',
      'where' => '(ignore_sync != "1" OR ignore_sync IS NULL)',
    ))['lines'];
  }

  /**
   * @param array $correspondance
   */
  protected function clearSelections(array $correspondance) {
    $sitra_koids = array_keys($correspondance);

    foreach ($this->consolidation_modules as $consolidation_module) {
      $local_koids = $this->getReplacedKoids($sitra_koids, $consolidation_module);

      getDB()->fetchCol('UPDATE '.$consolidation_module->table.' SET selections = "" '
        .'WHERE KOID NOT IN ("'
        .implode('","', $local_koids)
        .'")');
    }
  }

  /**
   * Retourne la liste de koids mis à jour avec le nom de table du module passé en paramètre.
   *
   * @param array $sitra_koids
   * @param \Seolan\Core\Module\Module $module
   * @return array
   */
  protected function getReplacedKoids(array $sitra_koids, \Seolan\Core\Module\Module $module) {
    $that = $this;
    return array_map(function($sitra_koid) use ($that, $module) {
      return $that->getLocalFicheKoidReplaced($sitra_koid, $module);
    }, $sitra_koids);
  }

  /**
   * Retourne les KOIDs des fiches présents dans le module passé en paramètre.
   * @param array $correspondance
   * @param \Seolan\Core\Module\Module $consolidation_module
   * @return array: of KOID
   */
  protected function getFichesKoidsInModule(array $correspondance, \Seolan\Core\Module\Module $consolidation_module) {
    $local_koids = $this->getReplacedKoids(array_keys($correspondance), $consolidation_module);
    $module_table = $consolidation_module->table;

    return getDB()->fetchCol('SELECT DISTINCT(KOID) FROM '.$module_table
      .' WHERE KOID IN ("'.implode('","', $local_koids).'")');
  }

  /**
   * Retourne le KOID dont le nom de la table correspond au module passé en paramétre.
   * @param $fiche_koid
   * @param \Seolan\Core\Module\Module $consolidation_module
   * @return string
   */
  protected function getLocalFicheKoidReplaced($fiche_koid, \Seolan\Core\Module\Module $consolidation_module) {
    return str_replace('SIT_OBJETSTOURISTIQUES', $consolidation_module->table, $fiche_koid);
  }

}
