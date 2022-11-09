<?php
namespace Seolan\Library\Comarquage;

class Comarquage {

  function __construct($ar=NULL){
    \Seolan\Core\Labels::loadLabels('Seolan_Module_Comarquage_Comarquage');    
    $this->initComarquage($ar);

  }

  protected function initComarquage($ar=NULL) {
    $p=new \Seolan\Core\Param($ar=[]);
    $this->categorie=$p->get('CATEGORIE') ?? 'part';
    $data_directory = TZR_COMARQUAGE_LIBRARY ?? $LIBTHEZORRO.'src/Library/Comarquage/';
    $tmp_directory = TZR_COMARQUAGE_CACHE_DIR ?? TZR_TMP_DIR.'/comarquage/';
    $this->xsl_path = $data_directory.'xsl/';
    $home_xml = 'home-'.$this->categorie.'.xml';
    $home_xsl = 'spSeolanHome.xsl';
    
    $url = ($_REQUEST['alias'] ? '/'.$_REQUEST['alias'].'.html' : $_SERVER['REQUEST_URI']);
    $url = parse_url($url);
    parse_str($url['query'], $params);
    $params['CATEGORIE'] = $this->categorie;
    $params['xml'] = '';
    $url['query'] = http_build_query($params);
    $link = $url['path'].'?'.$url['query'];

    $this->COMARQUAGE_PARAMS = array_replace_recursive([
      'HYPERLIEN_PART' => $link,  // Lien de chaque page pour les particuliers (prend l'alias défini ci-dessus par défaut)
      'HYPERLIEN_ASSO' => $link,  // Lien de chaque page pour les assos (prend l'alias défini ci-dessus par défaut)
      'HYPERLIEN_PRO'  => $link,  // Lien de chaque page pour les pros (prend l'alias défini ci-dessus par défaut)
      'CATEGORIE'      => $this->categorie,                     // 'part', 'asso' ou 'pro'
      'DONNEES'        => $tmp_directory,          // Répertoire où seront stockés les XML téléchargés quotidiennement
      'PICTOS'         => $data_directory.'pictos/',           // Répertoire où sont situés les quelques pictogrammes utilisés par les XSL
      'IMAGES'         => $data_directory.'images/',           // Répertoire où sont situés les images utilisées par les XSL
      'AFF_PICTOS'     => 'false',                        // A renseigner si l'on souhaite afficher les pictos utilisés dans les XSL
      'AFF_IMAGES'     => 'false',                        // A renseigner si l'on souhaite afficher les images utilisées dans les XSL
      'BOOTSTRAP'      => 'true',                         // Active ou non l'affichage en mode BOOTSTRAP
      'MODE_PIVOT'     => 'web',                          // Affiche les liens génériques de service-public.fr (sinon mettre 'pivot' = XML à fournir en local))
      'XSL_PATH'       => $this->xsl_path,                // Répertoire des fichiers XSL récupérés sur https://adullact.net/projects/co-marquage-sp/ puis modifiés en interne
      'HOME_XML'       => $home_xml, // Fichier du menu principal (page d'accueil de la catégorie pouvant être personnalisée)
      'HOME_XSL'       => $home_xsl,             // XSL à utiliser pour la page d'accueil, spMainMenu.xsl est le menu complet, spSeolanHome.xsl est un menu personnalisé reprenant les items de la page d'accueil de service-public.fr
    ],
    @$this->COMARQUAGE_PARAMS ?: []
    );

    try {
      $directory = $tmp_directory.$this->categorie.'/';
      // Vérification de l'existence du répertoire DONNEES contenant les XML, et de la crontab les téléchargeant

      if (!file_exists($directory)) { /* || !exec("crontab -l -u $HOME", $output) || !preg_match('@service-public.fr/vdd/3.0/'.$this->categorie.'@', implode("\n", $output))) {*/
        throw new \Exception('No crontab to download XML from service-public.fr. Please execute the following line and schedule it daily with `$ crontab -e` :'.PHP_EOL.
        'mkdir -p '.$directory.'; cd '.$directory.';wget '.str_replace('$CATEGORIE', $this->categorie, TZR_COMARQUAGE_URL).';unzip -o vosdroits-latest.zip;rm vosdroits-latest.zip;');
      }

      // Récupération du XML à traiter en fonction de l'URL
      $redirections_xml_filename = $directory.'/redirections.xml';
      if (!file_exists($redirections_xml_filename))
        throw new \Exception('XML file "'.$redirections_xml_filename.'" not found');

      $xml_basename = 'home-'.$this->categorie.'.xml';
      if (preg_match('@^\w+$@', @$_GET['xml']))
        $xml_basename = $_GET['xml'].'.xml';
      $this->comarquage_check_redirections($xml_basename, $redirections_xml_filename);

      $this->xml_filename = file_exists($directory.$xml_basename) ? $directory.$xml_basename : $data_directory.'/'.$xml_basename;

      if (!file_exists($this->xml_filename))
        throw new \Exception('XML file "'.$this->xml_filename.'" not found');

      $this->xsl_filename = $this->xsl_path.$this->comarquage_get_xsl($xml_basename);
      if (!file_exists($this->xsl_filename))
        throw new \Exception('XSL file "'.$this->xsl_filename.'" not found');


    } catch(Exception $e) {

      \Seolan\Core\Logs::critical('COMARQUAGE SERVICE PUBLIC /tzr/modsp/modsp.php: '.$e->getMessage());
      if (!defined('TZR_DEBUG_MODE') || !TZR_DEBUG_MODE)
        mail(
          TZR_DEBUG_ADDRESS,
          "COMARQUAGE ERROR on $_SERVER[SERVER_NAME]",
          $e->getMessage()."\n\n\$_REQUEST = ".var_export($_REQUEST, true)."\n\n\$_SERVER = ".var_export($_SERVER, true)
        );

    }
  }

  public function transformToHtml() {
    // Indispensable pour que les tags <xsl:include> fonctionnent dans les fichiers XSL
    chdir($this->xsl_path);
    $xml = new \DomDocument();
    \Seolan\Core\Logs::debug("Loading XML $this->xml_filename...");
    $xml->loadXML(file_get_contents($this->xml_filename));
    $xsl = new \DomDocument();
    \Seolan\Core\Logs::debug("Loading XSL $this->xsl_filename...");
    $xsl->loadXML(file_get_contents($this->xsl_filename));
    // Chargement du processeur XSLT
    $xslt = new \XsltProcessor();
    $xslt->importStyleSheet($xsl);

    // Transmission des paramètres globaux situés dans la feuille « spVariables.xsl »
    foreach ($this->COMARQUAGE_PARAMS as $name => $value) {
      \Seolan\Core\Logs::debug("XsltProcessor::setParameter '$name' => '$value'");
      $xslt->setParameter(null, $name, $value);
    }

    return $xslt->transformToXML($xml);
  }
// Vérifie les redirections indiquées par le XML redirections.xml
  private function comarquage_check_redirections(&$xml_basename, $redirections_xml_filename) {
    $redirections_xml = new \DomDocument();
    \Seolan\Core\Logs::debug("Loading XML REDIRECTIONS  $redirections_xml_filename...");
    $redirections_xml->loadXML(file_get_contents($redirections_xml_filename));
    $xpath = new \DomXPath($redirections_xml);
    $xml_node = str_replace('.xml', '', $xml_basename);
    foreach ($xpath->query('//Redirection[@from="'.$xml_node.'"]') as $entry) {
      $to = $entry->getAttribute('to');
      \Seolan\Core\Logs::debug("Redirect node $xml_node to node $to");
      $xml_basename = $to.'.xml';
    }
  }
// Fonction renvoyant le XSL correspondant au XML demandé
  private function comarquage_get_xsl($xml_filename) {
    if ($xml_filename == $this->COMARQUAGE_PARAMS['HOME_XML']) 
      return $this->COMARQUAGE_PARAMS['HOME_XSL'];
    $xml_to_xsl_correspondance = [
      'accueil'           => 'spMainNoeud.xsl',
      'arborescence'      => 'spMainNoeud.xsl',
      'centresDeContact'  => 'spMainNoeud.xsl',
      'commentFaireSi'    => 'spMainNoeud.xsl',
      'menu'              => 'spMainMenu.xsl',
      'questionsReponses' => 'spMainNoeud.xsl',
      'redirections'      => 'spMainNoeud.xsl',
      'servicesEnLigne'   => 'spMainNoeud.xsl',
      // All versions
      'F\w+' => 'spMainFiche.xsl',
      'N\w+' => 'spMainNoeud.xsl',
      'R\w+' => 'spMainRessource.xsl',
    ];
    foreach ($xml_to_xsl_correspondance as $xsl_reg => $xsl_basename) {
      if (preg_match("@$xsl_reg.xml$@", $xml_filename))
        return $xsl_basename;
    }
    return 'spMainNoeud.xsl';
  }
}