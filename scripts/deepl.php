<?php
if(!empty(TZR_DEEPL_WEBSERVICE_KEY)) {
    define('TZR_ADMINI',1);
    if (false === include_once($_SERVER['DOCUMENT_ROOT'].'../tzr/local.php')) {
        header("HTTP/1.1 500 Seolan Server Error");
        exit(0);
    }
    
    if (false === include_once($LIBTHEZORRO.'bootstrap.php')) {
        header("HTTP/1.1 500 Seolan Server Error");
        exit(0);
    }
    include_once($LIBTHEZORRO.'src/Library/SecurityCheck.php');
    \Seolan\Library\SecurityCheck::assertIsUrl($_SERVER['REQUEST_URI']);
    sessionStart();
    set_time_limit(0);
    $p = new \Seolan\Core\Param([]);
    $mode = $p->get('mode');
    $srcLang = strtoupper($p->get('srcLang'));
    $targetLang = strtoupper($p->get('targetLang'));
    /*
      Il y a 3 types d'action a réaliser dans ce script :
      - vérifier que le code iso de la langue cible est bien disponible dans les langues cibles de l'API de DeepL
      - récupérer le texte dans la langue de base dans InfoTree
      - faire la requête API et renvoyer le(s) texte(s) traduit 
    */
    switch($mode){
    case "check_iso":
        //Tableau Contenant tout les codes ISO reconnu par l'API de DeepL (récupéré dans la documentation de l'API) => à mettre à jour de temps en temps : https://www.deepl.com/fr/docs-api/translating-text/request/
        $DeeplSrcLang = ["BG","CS","DA","DE","EL","EN","ES","ET","FI","FR","HU","IT","JA","LT","LV","NL","PL","PT","RO","RU","SK","SL","SV","ZH"];
        $DeeplLang = ["BG","CS","DA","DE","EL","EN-GB","EN-US","EN","ES","ET","FI","FR","HU","IT","JA","LT","LV","NL","PL","PT-PT","PT-BR","PT","RO","RU","SK","SL","SV","ZH"];
        die(in_array(strtoupper($srcLang),$DeeplSrcLang) && in_array(strtoupper($targetLang),$DeeplLang));
        break;
    case "getSrcText":
        $name = $p->get("name");
        $zone = $p->get("zone");
        $oidsection = $p->get("oidsection");
        $oidit = $p->get("oidit");
        $moid = $p->get("moid");
        $infotree = \Seolan\Core\Module\Module::objectFactory($moid);
        $ors = $infotree->getORS($zone,$oidsection);
        $disp = $infotree->viewSectionTrad($p,$oidit,$ors);
        $text = [];
        foreach($name as $k => $n){
            $text[$k] = $disp[0]["o".$n]->html;
        }
        die(json_encode($text));
        break;
    default:
        // On vérifie qu'un utilisateur est connecté
        if(\Seolan\Core\User::authentified()){
            // On récupère la clé API
            $key = TZR_DEEPL_WEBSERVICE_KEY;
            $text = $p->get('text');
            // On construit la requête
            $rq = new \Seolan\Core\JsonClient\Request();
            $url = TZR_DEEPL_WEBSERVICE_URL;
            // si text est un Array : On traduit plusieurs champs en même temps
            // sinon c'est qu'on ne traduit qu'un seul champ
            if(is_array($text)){
                foreach($text as $t){
                    //tag_handling => xml permet d'indiquer à l'API qu'il ne faut pas traduire le texte <XXX> car c'est une balise (pour les RichText)
                    $param = ['auth_key'=>$key,'text'=>$t,'source_lang'=>$srcLang,'target_lang'=>$targetLang,'tag_handling'=>'xml'];
                    $response[] = json_decode($rq->doRequest('POST',$url,$param,[])->data,true)['translations'][0]['text'];
                }
                //On prépare la réponse pour pouvoir l'envoyer correctement
                $response = json_encode($response);
            } else {
                //tag_handling => xml permet d'indiquer à l'API qu'il ne faut pas traduire le texte <XXX> car c'est une balise (pour les RichText)
                $param = ['auth_key'=>$key,'text'=>$text,'source_lang'=>$srcLang,'target_lang'=>$targetLang,'tag_handling'=>'xml'];
                $response = json_decode($rq->doRequest('POST',$url,$param,[])->data,true)['translations'][0]['text'];
            }
            // On retourne le texte traduit
            die($response);
        } else {
            header('HTTP/1.0 401 Authorization Required');
            die('Authorization Required');
        }
    }
}
