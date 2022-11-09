<?php
namespace Seolan\Module\Sitra;

/**
 * User: bsevajol
 * Date: 23/04/15
 * Time: 16:39
 */

/**
 * Pont entre l'api d'OpenSystem permettant de récupérer des données auprès de cette meme api.
 * Class OpenSystemApis
 */
class OpenSystemApis {

  /// URL d'appele de l'API pour récupération du flux XML
  const API_URL = 'http://proxy-xml.open-system.fr/rest.aspx';

  /// Login de connexion à l'API Open System
  protected $login = '';

  /// Mot de passe de connexion à l'API Open System
  protected $pass = '';

  /**
   * A cache for request
   * @var array: {SITRA2_ID: "api response"}
   */
  protected $cache = array();

  /**
   * Force l'utilisation du cache. Si un objet SITRA demandé n'est pas dans le cache lorsque ce paramètre est activé,
   * alors un NotFoundException sera levé.
   * @var bool
   */
  protected $cache_only = False;

  /**
   * @param $login Login de connexion à l'API
   * @param $pass Pass de connexion à l'API
   */
  public function __construct($login, $pass) {
    $this->login = $login;
    $this->pass = $pass;
  }

  /**
   * @param $sitra2_id
   * @return String: Identifiant Ardeche en direct
   * @throws NotFoundException
   */
  public function getArdecheDirectId($sitra2_id) {
    $api_object = $this->getApiObject($sitra2_id);

    foreach ($api_object->Liaisons as $liaison) {
      if ((string) $liaison->Liaison->ObjetOS->SegmentsCodeUI->Metier == 'ARDI') {
        return (string) $liaison->Liaison->ObjetOS->CodeUI;
      }
    }

    throw new \Seolan\Core\Exception\NotFound();
  }

  /**
   * Note: Actuellement on considère tout identifiant qui n'est pas Ardeche en direct comme un identifiant OpenSystem !
   *
   * @param $sitra2_id
   * @return string: Identifiant OpenSystem
   * @throws NotFoundException
   */
  public function getOpenSystemId($sitra2_id) {
    $api_object = $this->getApiObject($sitra2_id);

    foreach ($api_object->Liaisons as $liaison) {
      $metier = $liaison->Liaison->ObjetOS->SegmentsCodeUI->Metier;
      if ((string) $metier && (string) $metier !== 'ARDI') {
        return (string) $liaison->Liaison->ObjetOS->CodeUI;
      }
    }

    throw new \Seolan\Core\Exception\NotFound();
  }

  /**
   * Retourne l'objet OpenSystem correspondant.
   *
   * <Transaction>
   *   <Retour>...</Retour>
   *   <Resultat>
   *      <Objets>
   *        <Objet> <------ Celui-ci
   *          ...
   *
   * @param $sitra2_id
   * @return SimpleXMLElement
   * @throws NotFoundException
   */
  protected function getApiObject($sitra2_id) {
    // On ne contacte l'API que si on a rien en cache et que l'on est pas limité a l'utilisation du cache
    if (!array_key_exists($sitra2_id, $this->cache) && !$this->cache_only) {
      try {
        $api_response_object = simplexml_load_string($this->getApiResponse($sitra2_id));
        $this->cache[$sitra2_id] = $api_response_object->Resultat->Objets->children()[0];
      } catch (Exception $exc) {
        // On créer une entré dans le tableau cache. Afin de spécifier que la demande auprès de l'api à été effectué
        // pour cet ID. Et donc eviter de recontacter l'api pour cet ID les autre fois.
        $this->cache[$sitra2_id] = Null;
        \Seolan\Core\Logs::critical("getApiObject: object $sitra2_id not found");
        throw new \Seolan\Core\Exception\NotFound();
      }
    // Si on considère le cache comme seule source de donnée, alors on as pas de correspondance pour cet ID
    } elseif (!array_key_exists($sitra2_id, $this->cache) && $this->cache_only) {
      throw new \Seolan\Core\Exception\NotFound();
    }

    if (!$this->cache[$sitra2_id]) {
      throw new \Seolan\Core\Exception\NotFound();
    }

    return $this->cache[$sitra2_id];
  }

  /**
   * Retourne la réponse brute de l'api pour ce paramètre
   * @param null $sitra2_id: Si le paramètre vaut nul, l'API répond l'intégralité des résultats.
   * @return string
   * @throws Exception
   */
  protected function getApiResponse($sitra2_id = Null) {
    if (!( $response = file_get_contents($this->getApiUrl($sitra2_id)) )) {
      \Seolan\Core\Logs::critical("getApiResponse: getApiUrl($sitra2_id) is null ");
      throw new Exception();
    }

    return $response;
  }

  /**
   * Retourne l'URL complète de la requête à effectuer auprès de l'API
   * @param null $sitra2_id
   * @return string
   */
  protected function getApiUrl($sitra2_id = Null) {
    $parameters = array(
      'Login' => $this->login,
      'Pass' => $this->pass,
      'Action' => 'concentrateur_liaisons'
    );
    if ($sitra2_id) {
      $parameters['Cle'] = $sitra2_id;
    }
    return self::API_URL.'?'.http_build_query($parameters, '', '&');
  }

  /**
   * Récupère la totalité des données de l'API et la met en cache.
   * Active l'utilisation forcé du cache ($this->cache_only) afin que lorsque l'ID SITRA2 demandé n'est pas stocké dans
   * le cache on considère que l'on n'aura rien du coté de l'API non plus.
   * @throws Exception
   */
  public function enableCacheUsageOnly() {
    $this->cache_only = True;
    \Seolan\Core\Logs::debug("enableCacheUsageOnly");
    $full_response_object = simplexml_load_string($this->getApiResponse());

    foreach ($full_response_object->Resultat->Objets->children() as $objet_node) {
      $sitra2_id = (string) $objet_node->ObjetCle->Cle;
      $this->cache[$sitra2_id] = $objet_node;
    }
  }

  /**
   * Retourne le login
   * @return string
   */
  public function getLogin() {
    return $this->login;
  }

}
