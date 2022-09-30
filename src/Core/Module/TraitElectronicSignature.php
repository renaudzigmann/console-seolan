<?php

namespace Seolan\Core\Module;

use DOMDocument;
use DOMException;
use DOMNode;

/**
 * Encapsule les échanges avec le service d'Universign dans le cadre de la signature numérique de documents.
 *
 * NB: Les informations d'identification à l'API sont stockées dans la table des comptes externes (table _ACCOUNTS -> url,
 * login et passwd) dans un enregistrement dont le type est "WebServiceUniversign'.
 */
trait TraitElectronicSignature
{
  /** @var bool Document embarqué en base64 dans le flux XML. */
  private bool $embedDocument = true;

  /**
   * Force l'envoie des documents par le biais de leur URL. N'est utilisé que pour faciliter le débogage en dev.
   * Dans le cadre de la console, les documents sont systématiquement encodés dans la requète.
   *
   * @return void
   */
  public function disableEmbededDocuments() {
    $this->embedDocument = false;
  }

  /**
   * Construction du body XML pour la requête "requester.requestTransaction"
   *
   * cf: https://help.universign.com/hc/fr/articles/4408178785681-Configuration-Postman-pour-cr%C3%A9er-une-transaction
   *
   * @return string
   * @throws DOMException
   */
  public function generateXMLRequestTransaction(array $data): string {

    $domTree = new DOMDocument('1.0', 'UTF-8');

    $methodCallNode = $domTree->appendChild($domTree->createElement("methodCall"));
    $methodCallNode->appendChild($domTree->createElement("methodName", "requester.requestTransaction"));

    $paramsNode = $methodCallNode->appendChild($domTree->createElement("params"));
    $paramNode = $paramsNode->appendChild($domTree->createElement("param"));
    $valueNode = $paramNode->appendChild($domTree->createElement("value"));
    $strucNode = $valueNode->appendChild($domTree->createElement("struct"));

    $description = $data['description'];
    $this->addMember($domTree, $strucNode, 'profile', 'default', 'string');
    $this->addMember($domTree, $strucNode, 'mustContactFirstSigner', '1', 'boolean');
    $this->addMember($domTree, $strucNode, 'finalDocSent', '1', 'boolean');
    $this->addMember($domTree, $strucNode, 'finalDocRequesterSent', '1', 'boolean');
    $this->addMember($domTree, $strucNode, 'description', $description['description'], 'string');
    $this->addMember($domTree, $strucNode, 'language', $description['language'], 'string');
    $this->addMember($domTree, $strucNode, 'handwrittenSignatureMode', '0', 'int');
    $this->addMember($domTree, $strucNode, 'chainingMode', 'email', 'string');


    //
    // Ajout des signataires
    //

    $arrayNode = $domTree->createElement("array");
    $dataNode = $arrayNode->appendChild($domTree->createElement("data"));

    foreach ($data['signers'] as $signer) {

      // Signataire 1
      $value2Node = $dataNode->appendChild($domTree->createElement("value"));
      $struct2Node = $value2Node->appendChild($domTree->createElement("struct"));
      $this->signer($domTree, $struct2Node,
        $signer['firstname'],
        $signer['lastname'],
        $signer['email'],
        $data['feedbackUrls']['successURL'],
        $data['feedbackUrls']['cancelURL'],
        $data['feedbackUrls']['failURL']);

    }

    $this->addMember($domTree, $strucNode, 'signers', $arrayNode, 'xml');

    //
    // Ajout des documents à signer
    //

    $arrayNode = $domTree->createElement("array");
    $dataNode = $arrayNode->appendChild($domTree->createElement("data"));
    foreach ($data['pdfs'] as $index => $filePathOrUrl) {
      $filename = basename($filePathOrUrl);

      $value2Node = $dataNode->appendChild($domTree->createElement("value"));
      $struct2Node = $value2Node->appendChild($domTree->createElement("struct"));

      if ($this->embedDocument) {
        $b64Doc = chunk_split(base64_encode(file_get_contents($filePathOrUrl)));
        $this->document($domTree, $struct2Node, '', $b64Doc, $filename, $data['signers']);
      } else {
        $this->document($domTree, $struct2Node, $filePathOrUrl, '', $filename, $data['signers']);
      }
    }

    $this->addMember($domTree, $strucNode, 'documents', $arrayNode, 'xml');

    $domTree->preserveWhiteSpace = false;
    $domTree->formatOutput = true;
    $xml_string = $domTree->saveXML();

    return $xml_string;
  }

  /**
   * Construction du body XML pour la requête "requester.getTransactionInfo".
   *
   * cf: https://help.universign.com/hc/fr/article_attachments/4402728276113/GuideIntegrationSignatureElectroniqueUniversign_202106.pdf
   *
   * @return string
   * @throws DOMException
   */
  public function generateXMLGetTransactionInfo(string $identifier): string {

    $domTree = new DOMDocument('1.0', 'UTF-8');

    $methodCallNode = $domTree->appendChild($domTree->createElement("methodCall"));
    $methodCallNode->appendChild($domTree->createElement("methodName", "requester.getTransactionInfo"));

    $paramsNode = $methodCallNode->appendChild($domTree->createElement("params"));
    $paramNode = $paramsNode->appendChild($domTree->createElement("param"));
    $valueNode = $paramNode->appendChild($domTree->createElement("value"));
    $valueNode->appendChild($domTree->createElement("string", $identifier));

    $domTree->preserveWhiteSpace = false;
    $domTree->formatOutput = true;
    $xml_string = $domTree->saveXML();

    return $xml_string;
  }

  /**
   * Construction du body XML pour la requête "requester.getDocuments".
   *
   * cf: https://help.universign.com/hc/fr/article_attachments/4402728276113/GuideIntegrationSignatureElectroniqueUniversign_202106.pdf
   *
   * @return string
   * @throws DOMException
   */
  public function generateXMLGetDocuments(string $identifier): string {

    $domTree = new DOMDocument('1.0', 'UTF-8');

    $methodCallNode = $domTree->appendChild($domTree->createElement("methodCall"));
    $methodCallNode->appendChild($domTree->createElement("methodName", "requester.getDocuments"));

    $paramsNode = $methodCallNode->appendChild($domTree->createElement("params"));
    $paramNode = $paramsNode->appendChild($domTree->createElement("param"));
    $valueNode = $paramNode->appendChild($domTree->createElement("value"));
    $valueNode->appendChild($domTree->createElement("string", $identifier));

    $domTree->preserveWhiteSpace = false;
    $domTree->formatOutput = true;
    $xml_string = $domTree->saveXML();

    return $xml_string;
  }

  /**
   * Envoi des documents pour signature numérique.
   *
   * NB: Chaque document PDF ne doit pas excéder 10 Mo. L’ensemble de documents PDF ne doit pas excéder 15 Mo.
   * Cf: https://help.universign.com/hc/fr/article_attachments/4402728276113/GuideIntegrationSignatureElectroniqueUniversign_202106.pdf
   *
   * Cf: https://github.com/globalis-ms/universign-service
   *     https://help.universign.com/hc/fr/article_attachments/4411961562001/universign-guide-8.88.pdf
   *     https://help.universign.com/hc/fr/article_attachments/4402728276113/GuideIntegrationSignatureElectroniqueUniversign_202106.pdf
   *
   * @param array $documents Liste des chemins d'accès locaux ou des URLs (en fonction de $embedDocument) des documents à signer.
   * @param array $contacts Liste des contacts signataires. Chaque enregistrement est un tableau contenant les
   * champs 'firstname', 'lastname' et 'email'.
   * @param string $description Description de la demande de signature (Nom de la Collecte Universign).
   * @return array|string[] Retourne un champ 'id' et 'url' en cas de succès ou un champ 'error' sinon.
   */
  public function sendDocumentsForElectronicSignature(array $documents, array $contacts, string $description = ''): array {

    if (!count($documents) || !count($contacts)) {
      return ['error' => "No document or contact for signature."];
    }

    $feedbackUrls =
      [
        'successURL' => 'https://www.universign.eu/fr/sign/success/',
        'cancelURL' => 'https://www.universign.eu/fr/sign/cancel/',
        'failURL' => 'https://www.universign.eu/fr/sign/failed/',
      ];

    $descriptions =
      [
        "description" => $description,
        "language" => "fr",
      ];

    $data =
      [
        "pdfs" => $documents,
        "signers" => $contacts,
        "description" => $descriptions,
        "feedbackUrls" => $feedbackUrls,
      ];

    $xml = $this->generateXMLRequestTransaction($data);

    $requestXMLResult = $this->sendRequest($xml);

    $error = $this->analyzeRequestError($requestXMLResult);
    if ($error) {
      return ['error' => $error];
    } else {
      return $this->analyzeBasicRequestFeedback($requestXMLResult);
    }
  }

  /**
   * Demande d'état d'avancement de signature de document(s).
   *
   * @param string $identifier
   * @return array|string[] Retourne les champs description, transactionId et status (ready, canceled, completed)
   * en cas de succès. Sinon retourne un status 'error' avec un champ description.
   * @throws DOMException
   */
  public function sendTransactionInfoRequest(string $identifier): array {

    $xml = $this->generateXMLGetTransactionInfo($identifier);

    $requestXMLResult = $this->sendRequest($xml);

    $error = $this->analyzeRequestError($requestXMLResult);
    if ($error) {
      return ['description' => $error,
        'status' => 'error'];
    } else {
      return $this->analyzeBasicRequestFeedback($requestXMLResult);
    }
  }

  /**
   * Récupération des documents signés.
   *
   * @param string $identifier
   * @return array|string[]
   * @throws DOMException
   */
  public function sendDownloadSignedDocumentsRequest(string $identifier): array {

    $xml = $this->generateXMLGetDocuments($identifier);

    $requestXMLResult = $this->sendRequest($xml);

    $error = $this->analyzeRequestError($requestXMLResult);
    if ($error) {
      return ['description' => $error,
        'status' => 'error'];
    } else {
      return $this->analyzeDocumentsRequestFeedback($requestXMLResult);
    }
  }

  /**
   * Analyse le flux xml pour vérifier qu'il n'y a pas d'erreur retournée.
   *
   * @param string $xmlString
   * @return string
   */
  public function analyzeRequestError(string $xmlString): ?string {

    $res = '';
    $xml = simplexml_load_string($xmlString);
    $errorDesc = $xml->xpath('//methodResponse/fault/value/struct/member[1]/value/int');
    if (is_array($errorDesc) && count($errorDesc)) {
      $res = $this->stringForErrorCode((string)$errorDesc[0]);

      // Erreur inconnue. On récupère la chaine du XML (pas de traduction possible du coup :( ).
      if (!$res) {
        $res = $xml->xpath('//methodResponse/fault/value/struct/member[2]/value/string')[0];
      }
    } else {
      $errorDesc = $xml->xpath('/error[1]');
      if (is_array($errorDesc) && count($errorDesc))
        $res = $errorDesc[0];
    }

    return $res;
  }

  /**
   * Analyse basique du flux xml de retour 'methodResponse' après requète.
   *
   * @param string $xmlString
   * @return array Retourne un tableau des données extraites.
   */
  public function analyzeBasicRequestFeedback(string $xmlString): array {

    $res = array();
    $xml = simplexml_load_string($xmlString);
    $members = $xml->xpath('//methodResponse/params/param/value/struct/member');

    foreach ($members as $member) {
      $name = (string)@$member->xpath('name')[0];
      $value = (string)@$member->xpath('value/string')[0];

      if ($name && $value)
        $res[$name] = $value;
    }

    return $res;
  }

  /**
   * Analyse du flux xml de retour 'methodResponse' après requète de demande de documents.
   *
   * @param string $xmlString
   * @return array Chaque élément du tableau est composé d'un champ 'fileName', 'id' et 'content' (pdf en base 64)
   */
  public function analyzeDocumentsRequestFeedback(string $xmlString): array {

    $res = array();
    $xml = simplexml_load_string($xmlString);
    $documents = $xml->xpath('//methodResponse/params/param/value/array/data/value');

    foreach ($documents as $document) {

      $doc = array();

      foreach (@$document->xpath('struct/member') as $member) {

        $name = (string)@$member->xpath('name')[0];

        switch ($name) {
          case 'fileName':
            $value = (string)@$member->xpath('value/string')[0];
            break;

          case 'content':
            $value = (string)@$member->xpath('value/base64')[0];
            break;

          case 'id':
            $value = (string)@$member->xpath('value/i4')[0];
            break;

          default:
            $value = '';
            break;
        }

        if ($name && $value)
          $doc[$name] = $value;
      }

      $res[] = $doc;
    }

    return $res;
  }

  /**
   * Requete vers le web service d'Universign
   *
   * @param string $xmlRequest
   * @return string Retour XML du service après requète
   */
  private function sendRequest(string $xmlRequest): string {

    $apiData = $this->universignWebServiceInfo();
    $apiURL = $apiData['url'];
    $apiLogin = $apiData['login'];
    $apiPassword = $apiData['passwd'];

    $process = curl_init($apiURL);
    curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
    curl_setopt($process, CURLOPT_HEADER, false);
    curl_setopt($process, CURLOPT_USERPWD, $apiLogin.":".$apiPassword);
    curl_setopt($process, CURLOPT_TIMEOUT, 30);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($process, CURLOPT_POSTFIELDS, $xmlRequest);
    $result = curl_exec($process);
    curl_close($process);

    // Check si la réponse est bien au format XML ou si erreur HTTP du serveur.
    if (@simplexml_load_string($result))
      return $result;
    else
      return '<?xml version="1.0" encoding="UTF-8"?><error>'.$result.'</error>';
  }

  /**
   * Retourne l'interprétation littérale d'un code d'erreur renvoyé par l'API d'Universign.
   *
   * Cf: https://help.universign.com/hc/fr/articles/360000413425-Comment-interpr%C3%A9ter-un-code-erreur-obtenu-depuis-l-API-Universign-
   *
   * @param string $errorCode
   * @return string
   */
  private function stringForErrorCode(string $errorCode): ?string {

    if (!$errorCode)
      return null;

    $universignAPIErrors =
      [
        '73011' => 'No valid contract sign account.',
        '73251' => 'Signature session timed out.',
        '73252' => 'No more credit for signature.',
        '73253' => 'Missing document or signature fields about the document for thetransaction.',
        '73254' => 'Missing role as RA operator.',
        '73104' => 'Indicates that the user doesn\'t have a mailsecret (password) set',
        '73107' => 'Indicates that the user requested an action to be performed on the mail attachments but there are no attachments, or we were not able to read them',
        '73108' => 'Indicates that the mail service is disabled for the user (or theuser\'s organization).',
        '73110' => 'Indicates that the request mail is too big (its size is greater than the configured size limit).',
        '73111' => 'Indicates that the request mail for an attachment timestamping contains too many attachments',
        '73112' => 'Indicates that the (unregistered) user has used all of his freestamps',
        '73304' => 'The user does not exist.',
        '73314' => 'The phone number is used by another user.',
        '73315' => 'The given parameters of the registered identity are invalid.',
        '72604' => 'The requested organization cannot be found.',
        '73203' => 'Indicates that the daily limit has been reached',
        '72001' => 'Indicates an authentication error or failure.',
        '72005' => 'Indicates that the targeted operation needs more tokens than the account contains.',
        '72009' => 'A payment error occurred.',
        '72108' => 'You cannot delete yourself when you\'re an administrator; you cannot delete other users when you\'re not administrator.',
        '72109' => 'A given discount code does not exist.',
        '72112' => 'The given profile id does not exist or does not belong to the user.',
        '72113' => 'This user cannot be modified/deleted because an attached certificate (with a status that does not allow the operation) exists.',
        '72117' => 'Indicates that a user with the same phone number already exists',
        '72501' => 'The email sent failed.',
        '72205' => 'Indicates that an error occurred while reading a PDF document.',
        '72701' => 'An error occurred while reading the PDF template.',
        '72702' => 'An error occurred while reading a font.',
        '72703' => 'Unable to generate the PDF.',
        '72705' => 'Invalid signature mode.',
        '72706' => 'Invalid or incomplete SEPA data.',
        // 73020: Erreur fourretout renvoyée par l'API. On ne traite pas et on préfèrera récupérer la string du XML.
      ];

    return @$universignAPIErrors[$errorCode];
  }

  /**
   * Les informations d'identification à l'API sont stockées dans la table des comptes externes (table _ACCOUNTS -> url,
   * login et passwd) dans un enregistrement dont le type est "WebServiceUniversign'.
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

    /*return [
      'url' => 'https://sign.test.cryptolog.com/sign/rpc/',
      'login' => 'renaud.zigmann@xsalto.com',
      'passwd' => 'bidonbidon125',
    ];*/
  }

  /**
   * Constuction XML d'un member
   *
   * @param DOMDocument $document
   * @param DOMNode $node
   * @param string $name
   * @param $value
   * @param $valueType
   * @return DOMNode
   * @throws DOMException
   */
  private function addMember(DOMDocument $document, DOMNode $node, string $name, $value, $valueType): DOMNode {

    $memberNode = $node->appendChild($document->createElement("member"));

    $memberNode->appendChild($document->createElement("name", $name));
    $valueNode = $memberNode->appendChild($document->createElement("value"));

    switch ($valueType) {

      case 'xml':
        // Injection brute
        $valueNode->appendChild($value);
        break;

      default:
        $valueNode->appendChild($document->createElement("$valueType", $value));
        break;
    }

    return $memberNode;
  }

  /**
   * Construction XML d'un signataire
   *
   * @param DOMDocument $dom
   * @param DOMNode $node
   * @param string $firstname
   * @param $lastname
   * @param $emailAddress
   * @param $successURL
   * @param $cancelURL
   * @param $failURL
   * @param $certificateType
   * @return void
   */
  private function signer(DOMDocument $dom, DOMNode $node, string $firstname, $lastname, $emailAddress,
                                      $successURL, $cancelURL, $failURL, $certificateType = 'simple'): void {

    $this->addMember($dom, $node, 'firstname', $firstname, 'string');
    $this->addMember($dom, $node, 'lastname', $lastname, 'string');
    $this->addMember($dom, $node, 'emailAddress', $emailAddress, 'string');

    $this->addMember($dom, $node, 'successURL', $successURL, 'string');
    $this->addMember($dom, $node, 'cancelURL', $cancelURL, 'string');
    $this->addMember($dom, $node, 'failURL', $failURL, 'string');

    $this->addMember($dom, $node, 'certificateType', $certificateType, 'string');
  }

  /**
   * Construction XML d'un document PDF à signer
   *
   * @param DOMDocument $dom
   * @param DOMNode $node
   * @param string $url
   * @param string $data64
   * @param string $name
   * @return void
   */
  private function document(DOMDocument $dom, DOMNode $node, string $url, string $data64, string $name, array $signers): void {

    $this->addMember($dom, $node, 'documentType', 'pdf', 'string');

    // Le document PDF peut être lié via son URL ou son contenu préchargé.
    if ($url)
      $this->addMember($dom, $node, 'url', $url, 'string');
    else if ($data64)
      $this->addMember($dom, $node, 'content', $data64, 'base64');

    $this->addMember($dom, $node, 'name', $name, 'string');

    //
    // Ajout des champs de signature pour chaque signataire
    //

    $arrayNode = $dom->createElement("array");
    $dataNode = $arrayNode->appendChild($dom->createElement("data"));
    foreach ($signers as $index => $signer) {

      $value2Node = $dataNode->appendChild($dom->createElement("value"));
      $struct2Node = $value2Node->appendChild($dom->createElement("struct"));
      $this->addMember($dom, $struct2Node, 'name', 'Signature '.$signer['firstname']." ".$signer['lastname'], 'string');
      $this->addMember($dom, $struct2Node, 'signerIndex', $index, 'int');
    }

    $this->addMember($dom, $node, 'signatureFields', $arrayNode, 'xml');
  }
}