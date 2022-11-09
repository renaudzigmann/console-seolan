<?php 
namespace Seolan\Field\File\Autodesk;
use \Seolan\Field\File\Autodesk\Net\InterfaceRequest as InterfaceRequest;
use \Seolan\Field\File\Autodesk\Net\InterfaceResponse as InterfaceResponse;
/**
 * interface avec les api autodesk 
 * -> stocker les fichiers et les préparer
 * -> retrouver leurs vues pour le viewer
 * -> fournir des token
 * -> effacer 
 * https://forge.autodesk.com/en/docs/viewer/v7/developers_guide/overview/
 * https://developer.autodesk.com/en/docs/data/v2/overview/retention-policy/
 * Suppose d'avoir ouvert un compte et créé une application, 
 * https://forge.autodesk.com/myapps/
 * Avec les droits : "Model Derivative API" (qui permet la conversion)
 * et de la paramétrer (id et secret) dans le local.php, constantes :
 * AUTODESK_APP_ID et AUTODESK_APP_SECRET
 */
class AutodeskInterface {
  private $debug = false;
  private $url = 'https://developer.api.autodesk.com';
  private $dataRetentionPolicy = "transient"; // transient (24h), temporary, persistent (il faut le del)
  private $clientId = null;
  private $clientSecret = null;
  static protected $supportedMimes = ['application/dwg','image/vnd.dwg', 'application/x-step'];
  function __construct($id, $secret,$bucketsPrefix=null, $debug=false){
    $this->clientId = $id;
    $this->clientSecret = $secret;
    if ($bucketsPrefix !== null){
      $this->bucketsPrefix = $bucketsPrefix;
    } else {
      $this->bucketsPrefix = preg_replace('@https?:\/\/@', '', $GLOBALS['TZR_SESSION_MANAGER']::makeDomainName());
      $this->bucketsPrefix = str_replace(':','', $this->bucketsPrefix);
    }
    $this->debug = $debug;
  }
  /**
   * fourni un token avec juste des droits de lecture pour l'utlisation du viewer web
   */
  function getViewerToken(){
    return $this->getAccessToken('client_credentials', ['viewables:read']);
  }
  /**
   * retourne l'urn  (fichier déjà préparé) 
   * ou prepare le fichier pour la consultation avec le viewer web
   * si fichier plus récent ou pas encore consulté
   */
  function getViewUrn($file, $prepareIfNot=true){ 
    $fileid = static::getVarFileId($file);
    list($data, $fileupd) = \Seolan\Core\DbIni::get($fileid);
    if ($data != null && $data != 'running' && isset($data['views'])){
      $filemtime=@filemtime($file->filename);
      $datemtime = date('Y-m-d H:i:s', $filemtime);
      if ($filemtime && $fileupd && $datemtime>$fileupd){
	\Seolan\Core\Logs::notice(__METHOD__,'force refresh view '.$file->filename.','.$fileupd.','.$datemtime);
	$data = null;
      }
    }
    if ($data != null && $data != 'running' && isset($data['views'])){
      if ($data['views']['result'] != 'success'){
	$data = $this->refreshStatus($data, $file);
	if ($data == null){
	  return ['error', null];
	}
      }
      return [$data['views']['result'], $data['views']['urn']];
    } elseif ($prepareIfNot){
      if (isset($data) && $data == 'running'){
	return ['running', null];
      } else {
	// on locke et on top creation en cours pour ne pas demander plusieurs fois le même fichier
	if ($lck=\Seolan\Library\Lock::getLock($fileid)) {
          \Seolan\Core\DbIni::set($fileid, 'running');
          \Seolan\Library\Lock::releaseLock($lck);
        }

	$data = $this->prepareFile($file);

	\Seolan\Core\Logs::notice(__METHOD__." prepare file {$file->filename} {$data['views']['result']}");
	return [$data['views']['result'], $data['views']['urn']];
      }
    } else {
      return ['running', null];
    }
  }
  /**
   * efface dun fichier préparé
   * https://developer.autodesk.com/en/docs/data/v2/reference/http/buckets-:bucketKey-objects-:objectName-DELETE/
   * le nettoyage de _VARS est fait par contre
   */
  protected function deleteFile($file){
    // todo
  }
  /**
   * préparation effective d'un fichier 
   * -> creation du bucket si nécessaire
   * -> updload
   * -> conversion en svf
   * -> memorisation des urn et autres éléments (bucket container notament)
   */
  protected function prepareFile($file){
    try{
      // token avec les droits buckets / updload et translate ! la doc oublie parfois data:read pour le 2me
      $accessToken = $this->getAccessToken('client_credentials', ['bucket:create','bucket:read','data:read', 'data:write','viewables:read']);
      $req = new InterfaceRequest([sprintf(InterfaceRequest::HEADER_BEARER, $accessToken)], $this->debug);
      
      // récuperer le bucket - le crééer si nécessaire
      $bucket = $this->getBucket($file, $req);
      
      // ajouter le fichier
      $uploadRes = $this->uploadFileToBucket($bucket['bucketKey'], $file, $req);
      
      // convertir le fichier 
      $b64ObjectId = base64_encode($uploadRes['objectId']);
      $convertRes = $this->convertSeedFile($b64ObjectId, $file, $req);
      
      $id = static::getVarFileId($file);

      // on stocke en "encours" avant refresh status
      $convertRes['result'] = 'inprogress';
      $data = ['seed'=>$uploadRes,'views'=>$convertRes];

      \Seolan\Core\DbIni::set($id, $data);

      // commencer le suivi des conversions
      return $this->refreshStatus($data, $file);

    } catch(\Throwable $t){
      \Seolan\Core\Logs::critical(__METHOD__, $t->getMessage());
      return ['error', null];
    }
  }
  /**
   * refresh du status ()
   */
  protected function refreshStatus($data, $file){
    $accessToken = $this->getViewerToken();
    $req = new InterfaceRequest([sprintf(InterfaceRequest::HEADER_BEARER, $accessToken)], $this->debug);
    $url = $this->url.sprintf('/modelderivative/v2/designdata/%s/manifest', $data['views']['urn']);
    $res = $req->doRequest('GET', $url, null, [InterfaceRequest::HEADER_JSON]);
    try{
      $details = $res->getData();
      \Seolan\Core\Logs::notice(__METHOD__,$file->shortname.' '.$details['status'].' '.$details['progress']);
      if ($details['status'] == 'success' && $details['progress'] == 'complete'){
	$data['views']['result']='success';
	\Seolan\Core\DbIni::set($this->getVarFileId($file), $data);
	return $data;
      }	elseif ($details['status'] == 'pending' || $details['status'] == 'inprogress'){
	$data['views']['result']='inprogress';
	\Seolan\Core\DbIni::set($this->getVarFileId($file), $data);
	return $data;
      } elseif(in_array($details['status'], ['failed','timeout'])){
	// nettoyer ?
	return null;
      }
    }catch(\Throwable $t){
      \Seolan\Core\Logs::critical(__METHOD__,$t->getMessage());
      return null;
    }
  }
  /**
   * conversion du fichier 
   * todo à voir ? 3d ou pas ?
   * à l'issue ne fichier converti n'est peut être pas dispo
   * -> voir refresh status
   */
  protected function convertSeedFile($objectId, $file, $req){

    $url = $this->url.'/modelderivative/v2/designdata/job';

    $params = ['input'=>['urn'=>$objectId],
	       // un tableau de formats
	       'output'=>['formats'=>[['type'=>'svf','views'=>['2d','3d']]]]];

    \Seolan\Core\Logs::notice(__METHOD__, $url);
				      
    $rep = $req->doRequest('POST', $url, $params, [InterfaceRequest::HEADER_JSON]);

    $convert = $rep->getData();

    if (!isset($convert['result']) || !in_array($convert['result'], ['success','created'])){
      throw \Seolan\Core\Exception\Exception("error on file conversion ...{$convert['result']}");
    }
    return $convert;

  }
  // todo à voir : à checker versus en ligne de commande avec -T ... < taille mémoire des fichiers ?
  protected function uploadFiletoBucket($bucketKey, $file, $req){
    // !! autodesk attend en fait un un nom de fichier et qui se termine avec la donne extension 
    $url = $this->url.sprintf('/oss/v2/buckets/%s/objects/%s', $bucketKey, static::getFileId($file));
    \Seolan\Core\Logs::notice(__METHOD__, $url.' '.$file->filename);
    $rep = $req->doRequest('PUT', $url, file_get_contents($file->filename), 
			  [InterfaceRequest::HEADER_OCTECT_STREAM,
			   sprintf(InterfaceRequest::HEADER_CONTENT_LENGTH, filesize($file->filename))]);
    return $rep->getData();
  }
  /**
   * 
   */
  public static function getVarFileId($file){
    return 'autodesk_'.static::getFileId($file);
  }
  /**
   * originalname pour avoir l'extension (nécessaire pour Autocad) 
   * et filename pour avoir un identifiant unique (!path complet donc )
   */ 
  public static function getFileId($file){
    return 'seed_'.md5($file->filename).cleanFilename($file->originalname);
  }
  
  public static function configureViewer($r, $options, $multi=false){
    if (in_array($r->mime, static::$supportedMimes) ){
      $params = json_encode(['varid'=>$r->varid,
			     'uniqid'=>\Seolan\Core\Shell::uniqid(),
			     'url'=>TZR_AJAX8.'?'.http_build_query(['field'=>$r->field,
								    'table'=>$r->table,
								    'lang'=>\Seolan\Core\Shell::getLangData(),
								    'moid'=>@$options['fmoid'],
								    'class'=>'\Seolan\Field\File\File',
								    'function'=>'autocadViewer',
								    '_silent'=>1,
								    '_skip'=>1,
								    'filename'=>($multi?$r->filename:false),
								    'oid'=>$options['oid']??null])]);
      return "<button class='btn btn-default btn-md btn-inverse btn-viewer' type='button' onclick='TZR.AutodeskViewer.load($params);' type='button'><span class='glyphicon csico-view'></span></button>";
    } else {
      return \Seolan\Field\File\Viewer\Viewer::configureViewer($r, $options, $multi);
    }
  }
  public static function deleteViewerData($file){
    \Seolan\Core\Logs::debug(__METHOD__.' check : '.$file->filename);
    if (in_array($file->mime, static::$supportedMimes)){
      $fileid = static::getVarFileId($file);
      \Seolan\Core\Logs::debug(__METHOD__.' del : '.$fileid);
      $data = \Seolan\Core\DbIni::get($fileid, 'val');
      if ($data){
	// delete sur autodesk ?
	// delete dans _VARS;
	\Seolan\Core\DbIni::clear($fileid, false);
      }
    }
  }
  public static function active(){
    return defined('AUTODESK_APP_ID') && defined('AUTODESK_APP_SECRET');
  }
  /**
   * récupère le bucket pour un fichier donné (? table/champs ?)
   * -> les buckets doivent avoir des id distinct
   * -> on regarde si existe et on crée si nécessaire
   */
  protected function getBucket($file, $req){
    $bucket = null;
    // buckets par console/table/field existant ou pas 
    // buckets sont des minuscules [-_.a-z0-9]{3,128}
    $bucketName = strtolower($this->bucketsPrefix.$file->table.$file->field);
    $url  = $this->url.'/oss/v2/buckets/'.$bucketName.'/details';
    \Seolan\Core\Logs::notice(__METHOD__, $url);
    $bResp = $req->doRequest('GET', $url, null, [InterfaceRequest::HEADER_JSON]);
    
    if ($bResp->ok()){
      return $bResp->getData();
    } elseif ($bResp->notFound()) {
	// on crée le bucket (et on mémorise pour au moins pouvoir l'effacer ? todo à voir)
	$url = $this->url.'/oss/v2/buckets';

	$params = ["bucketKey"=>$bucketName,
		   "policyKey"=>$this->dataRetentionPolicy];

	\Seolan\Core\Logs::notice(__METHOD__, $url);
	
	$resp = $req->doRequest('POST', $url, $params, [InterfaceRequest::HEADER_JSON]);

	return $resp->getData();

    } else {
      \Seolan\Core\Logs::critical(__METHOD__, "unable to check bucket for file $bucketName {$bResp->status}");
      return $bResp->getData();
    }
  }
  /**
   * retourne un token avec les autorisations demandées
   * -> par exemple, le viewer : lecture seulement, etc 
   * https://developer.autodesk.com/en/docs/oauth/v2/reference/http/authenticate-POST/
   */
  function getAccessToken($grantType, $scopes){
    $k = 'autodesk'.$this->clientId.$grantType.implode('', $scopes);
    $val = \Seolan\Core\DbIni::get($k, 'val');
    if (empty($val) || $val['expire_time'] <= date('Y-m-d H:i:s')){
      $resp = $this->requestToken($grantType, $scopes);
      if ($resp->ok()){
	$val = $resp->getData();
	$val['expire_time'] = date('Y-m-d H:i:s', time()+$val['expires_in']);
	\Seolan\Core\DbIni::set($k, $val);
      } else {
	throw new \Seolan\Core\Exception\Exception('unable to get access token '.$resp->getStatus());
      }
    }
    return $val['access_token'];
  }
  /**
   * requete d'identification / authentifiaction ! url form encoded
   */
  protected function requestToken($grantType, $scopes){
    $req = new InterfaceRequest([], $this->debug);
    $url = $this->url.'/authentication/v1/authenticate';
    $params = "client_id={$this->clientId}&client_secret={$this->clientSecret}&grant_type=$grantType";
    $params .= '&scope='.implode('%20', $scopes); // space (%20) separated list of scope
    \Seolan\Core\Logs::notice(__METHOD__, $url);
    $resp = $req->doRequest('POST', $url, $params, [InterfaceRequest::HEADER_WWW_FORM]);
    return $resp;
  }
}
