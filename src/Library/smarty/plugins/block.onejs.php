<?php
/**
 * tag pour le "onejs.php"
 * -> rendre les javascripts demandés :
 *    - tels quels (debug)
 *    - en un lot, onejs.php etc
 * $params : type, base, id (à des fins de débug), charset, async
 */
function smarty_block_onejs($params, $content, &$smartyTplInstance, &$repeat) {
  if($repeat) {
    return;
  }
  $minify = isset($params['minify']) ? $params['minify'] : 0;
  $base = isset($params['base']) ? $params['base'] : '';
  $id = $smartyTplInstance->template_resource.'#'.(isset($params['id']) ? $params['id'] : '');
  $async = isset($params['async']) ? $params['async'] : '';

  $scripts = preg_split('/[\n?\r]+/', $content, -1, PREG_SPLIT_NO_EMPTY);

  // + les debug ip ?
  if(!isset($GLOBALS['ONEJS_DEBUG']['*']) && !isset($GLOBALS['ONEJS_DEBUG'][$id])) {
    $sep = '';
    $url = TZR_SHARE_SCRIPTS.'onejs.php?'.($minify ? 'minify=1&' : '').'files=';
    foreach($scripts as $i => $src) {
      $src = trim(str_replace("\t", "", $src));
      if(substr($src, 0, 1) == '#') {
        continue;
      }
      //inclut répertoire complet (locales)
      if(preg_match('/(\/)$/', $src)) {
        foreach(glob(TZR_WWW_DIR."{$base}{$src}*.js") as $filename) {
          $file = str_replace(TZR_WWW_DIR, '', $filename);
          $url .= $sep.$file;
          $sep = ':';
        }
        continue;
      }
      //inclut single file
      $url .= $sep.$base.$src;
      $sep = ':';
    }
    $htmlcode = "\n".'<script '.$async.' src="'.myUrl2cdn($url).'"></script>';
  }
  else {
    $htmlcode = "<!-- $id smarty_block_onejs -->";
    // en debug seulement
    // split non texte
    $htmlcode .= "\n<!-- raw content : \n $content \n -->\n";
    foreach($scripts as $src) {
      $src = trim(str_replace("\t", "", $src));
      // en tests on vérifie que le fichier existe ?
      if(substr($src, 0, 1) == '#' || empty($src)) {
        \Seolan\Core\Logs::debug(__FUNCTION__.' '.$src);
        continue;
      }
      $pathfile = $GLOBALS['TZR_WWW_DIR'].$base.''.$src;
      if(!file_exists($pathfile)) {
        \Seolan\Core\Logs::critical(__FUNCTION__, "file $pathfile does not exists");
      }
      if(substr($src, 0, 1) == '#') {
        continue;
      }

      //inclut répertoire complet (locales)
      if(preg_match('/(\/)$/', $src)) {
        foreach(glob(TZR_WWW_DIR."{$base}{$src}*.js") as $filename) {
          $file = str_replace(TZR_WWW_DIR, '', $filename);
          $htmlcode .= '<script src="'.$file.'"></script>'."\n";
        }
        continue;
      }

      //inclut single file
      \Seolan\Core\Logs::debug('adding jsfile 4'.$base.' '.$src);

      $htmlcode .= '<script src="'.myUrl2cdn($base.$src).'"></script>'."\n";
    }
  }
  return $htmlcode;
}
