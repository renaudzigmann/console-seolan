<?php

namespace Seolan\Module\Redirect;

use Seolan\Module\Table\Table;

/**
 * Class Redirect
 *
 * Gestion des redirection/Remplacement des contenus en fonctions de règle établis en Back-Office.
 */
class Redirect extends Table {

  /**
   * Liste des upgrades
   */
  static public $upgrades = [
    '20221207'=>''
  ];

  /**
   * Le header est modifié pour contenir le code HTTP et on affiche la contenu cible.
   */
  const MODE_CONTENT_REPLACEMENT = 'content_replacement';

  /**
   * Le header contient un "Location: " avec le code HTTP configuré.
   */
  const MODE_HEADER_LOCATION = 'header_location';

  /**
   * Le script s'arrête. Utilse pour un 410 par exemple.
   */
  const MODE_END = 'end';

  /**
   * L'expression source est une expression régulière.
   */
  const SEARCH_REGEX = 'regex';

  /**
   * L'expression source est contenu dans l'url demandé.
   */
  const SEARCH_LIKE = 'like';

  /**
   * L'expression source est identique à l'url demandé.
   */
  const SEARCH_EQUAL = 'equal';

  /**
   * Execute la procédure conséquente si il y a correspondance.
   * @param $needle_url
   * @return bool: Retourne True si une redirection à été appliqué
   */
  public function doIfHaveMatch($needle_url) {
    if(($redirection = $this->getMatchRedirectOrNull($needle_url))) {
      $this->doRedirection($redirection);
      return True;
    }
    return False;
  }

  protected function getMatchRedirectOrNull($needle_url, $options = array()) {
    $oidsRedirect = $this->getMatchRedirectOids($needle_url);

    if(!$oidsRedirect) return Null;

    $options = array_merge($options, array(
      'target_page' => array(
        'target_fields' => array(
          'alias'
        )
      )
    ));

    $redirections_browse = $this->browse(array(
      '_local' => True,
      'tplentry' => TZR_RETURN_DATA,
      '_mode' => 'object',
      'cond' => array('KOID' => array('=', $oidsRedirect)),
      'options' => $options,
      'pagesize' => 1
    ));

    if($redirections_browse['lines']) {
      return $redirections_browse['lines'][0];
    }

    return Null;
  }

  protected function getMatchRedirectOids($needle_url) {
    $urlEncoded = $needle_url;
    $GLOBALS['XSHELL']->encodeRewriting($urlEncoded);
    $urlEncoded = preg_replace('/\?.*/', '', $urlEncoded);

    $sql_where = ' (source_type = "url" AND (( source_url_is = "' . self::SEARCH_EQUAL . '" AND source_url = "' . $urlEncoded . '" ) ';
    $sql_where .= ' OR ( source_url_is = "' . self::SEARCH_LIKE . '" AND "' . $urlEncoded . '" LIKE CONCAT("%",source_url,"%") ) ';
    $sql_where .= ' OR ( source_url_is = "' . self::SEARCH_REGEX . '" AND "' . $urlEncoded . '" REGEXP(source_url) ))) ';

    $tableInfoTree = $this->xset->desc['source_page']->target;
    if($tableInfoTree) {
      if($_REQUEST['alias']) {
        $alias = $_REQUEST['alias'];
      }
      else {
        $matches = array();
        if($tableInfoTree && preg_match('/alias=([^&]+)/', $needle_url, $matches)) {
          $alias = $matches[1];
        }
      }

      if($alias) {
        $sql_where .= " OR (source_type = 'page' AND source_page in (select KOID from $tableInfoTree where alias = '$alias'))";
      }
    }

    $bootstrapApplication = \Seolan\Module\Application\Application::getBootstrapApplication();
    if ($bootstrapApplication->oid != null) {
      $sql_where = $sql_where.'AND APP='.getDB()->quote($bootstrapApplication->oid);
    }

    $select = 'select KOID from ' . $this->table . ' where ' . $sql_where;

    if(isset($this->xset->desc['ordre'])) {
      $select .= " order by ordre ASC";
    }

    $oidsRedirect = getDB()->fetchCol($select);

    return $oidsRedirect;
  }

  /**
   * @param array $redirection
   */
  protected function doRedirection(array $redirection) {
    switch($redirection['oredirection_mode']->raw) {

      case self::MODE_CONTENT_REPLACEMENT:
        $this->doContentRemplacementRedirection($redirection);
        break;

      case self::MODE_HEADER_LOCATION:
        $this->doHeaderLocationRedirection($redirection);
        break;

      case self::MODE_END:
        $this->doEnd($redirection);
        break;

    }
  }

  protected function doContentRemplacementRedirection(array $redirection) {
    $type = $redirection['otarget_type']->raw;
    if($type == 'url') {
      $redirect_url = $redirection['otarget_url']->raw;
      http_response_code($redirection['ohttp_code']->raw);
      if(substr($redirect_url, 0, 1) == '/') {
        $redirect_url = $GLOBALS['HOME_ROOT_URL'] . $redirect_url;
      }
      echo(file_get_contents($redirect_url));
      exit(0);
    }
    elseif($type == 'page') {
      $_REQUEST['alias'] = $redirection['otarget_page']->link['oalias']->raw;
      http_response_code($redirection['ohttp_code']->raw);
    }
  }

  protected function doHeaderLocationRedirection(array $redirection) {
    $rewrited_url = $this->getHeaderLocationRedirectionUrl($redirection);
    $code = $redirection['ohttp_code']->raw;
    header('Location: ' . $rewrited_url, True, $code);
    exit();
  }

  protected function getHeaderLocationRedirectionUrl(array $redirection) {
    $redirect_url = '/';
    $type = $redirection['otarget_type']->raw;
    if($type == 'url') {
      $redirect_url = $redirection['otarget_url']->raw;
    }
    elseif($type == 'page') {
      $redirect_to_alias = $redirection['otarget_page']->link['oalias']->raw;
      $redirect_url = $GLOBALS['TZR_SESSION_MANAGER']::complete_self() . 'alias=' . $redirect_to_alias;
      $GLOBALS['XSHELL']->encodeRewriting($redirect_url);
    }
    return $redirect_url;
  }

  /**
   * Termine le script après avoir mis à jour le code HTTP.
   * @param array $redirection
   */
  protected function doEnd(array $redirection) {
    $code = $redirection['ohttp_code']->raw;
    http_response_code($code);
    exit();
  }

}
