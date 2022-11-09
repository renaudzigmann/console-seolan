<?php
/***************************************************************************************************
 * Webservices V3 - pack version 03- WSAPI
 * Release Note: version 2.1
 * Correction du calcul des dates pour prendre en compte les fuseaux horaires
 *dans la fonction getSignatureValue de la classe FieldDate
 *
 * version 2.0
 * Ajout de la gestion des types de réponse pour le calcul de signature au retour.
 * Insertion de toutes les méthodes sous forme de fonction.
 * Ajout du type threeDsResult pour la gestion des paiements 3DS.
 * Ajout de la méthode modifyAndValidate
 *
 * version 1.2
 * Fix de l'ordre des champs dans le calcul de la signature de la méthode create
 *
 * version 1.1
 * Suppression de l'utilisation de la commande 'print_r' dans l'ensemble des fonctions.
 *
 * Ce fichier regroupe un ensemble de fonctions utilisées dans les exemples de codes fourni par
 * Lyra Network à l'adresse suivante https://systempay.cyberpluspaiement.com/html/code.html
 *
 ***************************************************************************************************/
namespace Seolan\Module\Monetique\SystemPay\Library;
class WSAPI {

  /** fonction raccourci qui permet de creer un objet de type date*/
  function _addFieldDate($name, $label=null, $description=null, $type='Date',$required=true) {
    $this->$name = new \Seolan\Module\Monetique\SystemPay\Model\Field\Date($name, $label, $description, $type,$required);
  }

  /** fonction raccourci qui permet de creer un objet*/
  function _addField($name, $label=null, $description=null, $type=null,$required=false) {
    $this->$name = new \Seolan\Module\Monetique\SystemPay\Model\Field\Field($name, $label, $description, $type,$required);
  }

  function _addFieldThreeDS($name, $label=null, $description=null, $type=null,$required=false) {
    $this->$name = new \Seolan\Module\Monetique\SystemPay\ThreeDsResult($name, $label, $description, $type,$required);
  }

  function __construct() {
    $wsdl='https://systempay.cyberpluspaiement.com/vads-ws/v3?wsdl';
    try{
      $this->client = new \SoapClient($wsdl,['trace' =>1]);
    }catch(\SoapFault $fault){
      \Seolan\Core\Logs::critical(get_class($this), 'Error SOAP : '.$fault->faultcode."-".$fault->faultstring);
    };


    /** Déclaration des objets de type simple
     * $name, $label=null, $description=null, $type,$required=false
     */
    $this-> _addField ('shopId', 'Identifiant de la boutique', null, 'String', true);
    $this-> _addField ('transactionId', 'Identifiant de transaction', null, 'String', true);
    $this-> _addField ('orderId', 'Référence de la commande', null, 'String', true);
    $this-> _addField ('cardNumber', 'Numéro de carte', null, 'String', true);
    $this-> _addField ('orderInfo', 'Description libre de la commande',	null, 'String');
    $this-> _addField ('orderInfo2', 'Description libre de la commande', null, 'String');
    $this-> _addField ('orderInfo3', 'Description libre de la commande', null, 'String');
    $this-> _addField ('customerId', 'Code client', null, 'String');
    $this-> _addField ('customerTitle',	'Civilité', null, 'String');
    $this-> _addField ('customerName', 'Nom client', null, 'String');
    $this-> _addField ('customerPhone', 'Téléphone client', null, 'String');
    $this-> _addField ('customerMail', 'Mail client', null, 'String');
    $this-> _addField ('customerAddress', 'Adresse client', null, 'String');
    $this-> _addField ('customerZipCode', 'Code postal client',	null, 'String');
    $this-> _addField ('customerCity', 'Ville client',	null, 'String');
    $this-> _addField ('customerCountry', 'Pays client', null, 'String');
    $this-> _addField ('customerIP', 'Adresse IP', null, 'String');
    $this-> _addField ('eci', 'ECI', null, 'String');
    $this-> _addField ('xid', 'XID', null, 'String');
    $this-> _addField ('cavv', 'CAVV', null, 'String');
    $this-> _addField ('comment', 'Commentaire « libre »', null, 'String');
    $this-> _addField ('cvv','Cryptogramme visuel',null,'String');
    $this-> _addField ('subReference',null,'Ne pas renseigner','String');
    $this-> _addField ('contractNumber','Numéro de contrat commerçant','Ne pas renseigner à vide','String');
    $this-> _addField ('customerLanguage','Langue client','Code ISO 639-1, sur 2 caractères',	'String');
    $this-> _addField ('brand','Brand de la carte ','"VISA" ou "MASTERCARD"','String',true);
    $this-> _addField ('ctxMode','Contexte de sollicitation','"TEST" ou "PRODUCTION")','String',true);
    $this-> _addField ('cardNetwork','Réseau de carte ','AMEX", "CB", "MASTERCARD", "VISA", "MAESTRO", "E-CARTEBLEUE','String',true);
    $this-> _addField ('enrolled','Statut enrôlement porteur ','"Y" : Enrôlé'."<br/>". '"N" : Non enrôlé'."<br/>". '"U" : Inconnu','String');
    $this-> _addField ('paymentMethod','Source du paiement' ,'"EC" : E-Commerce' ."<br/>". '"BO" : Backoffice' ."<br/>". '"MOTO" : mail ou téléphone' ."<br/>". '"CC" : centre d’appel' ."<br/>". '"OTHER" : autres' ,'String',true);
    $this-> _addField ('authStatus','Statut authentification ','"Y" : Authentifié 3DS'."<br/>". '"N" : Error Authentification'."<br/>". '"U" : Authentification impossible'."<br/>". '"A" : Essai d’authentification','String');
    $this-> _addField ('cavvAlgorithm','Algorithme CAVV ','"0" : HMAC'."<br/>". '"1" : CVV'."<br/>". '"2" : CVV_ATN'."<br/>". '"3" :  Mastercard SPA','String');
    $this-> _addField ('amount','Montant de la transaction','en plus petite unité monétaire','Long',true);
    $this-> _addField ('devise','Devise','Code monnaie ISO 4217' ."<br/>". 'Euro : 978',	'int',true);
    $this-> _addField ('sequenceNb','Numéro de séquence de la transaction',null,'int',true);
    $this-> _addField ('subPaymentType',null,'Ne pas renseigner','int');
    $this-> _addField ('subPaymentNumber',null,'Ne pas renseigner','int');
    $this-> _addField ('validationMode','Mode de validation','0 = Automatique' ."<br/>". ' 1 = Manuelle','int');
    $this-> _addField ('customerSendEmail','Envoi e-mail client souhaité','"0" : Non'."<br/>". '"1" : Oui','bool');
    $this-> _addFieldThreeDS ('threeDsResult',null,'Ne pas renseigner', 'array');
    $this-> _addField ('autorisationNb');
    $this-> _addField ('newTransactionId');
    $this-> _addField ('cardType');
    $this-> _addField ('errorCode');
    $this-> _addField ('extendedErrorCode');
    $this-> _addField ('transactionStatus');
    $this-> _addField ('initialAmount');
    $this-> _addField ('cvAmount');
    $this-> _addField ('cvDevise');
    $this-> _addField ('type');
    $this-> _addField ('multiplePaiement');
    $this-> _addField ('cardCountry');
    $this-> _addField ('transactionCondition');
    $this-> _addField ('vadsEnrolled');
    $this-> _addField ('vadsStatus');
    $this-> _addField ('vadsECI');
    $this-> _addField ('vadsXID');
    $this-> _addField ('vadsCAVVAlgorithm');
    $this-> _addField ('vadsCAVV');
    $this-> _addField ('vadsSignatureValid');
    $this-> _addField ('directoryServer');
    $this-> _addField ('authMode');
    $this-> _addField ('markAmount');
    $this-> _addField ('markDevise');
    $this-> _addField ('markNb');
    $this-> _addField ('markResult');
    $this-> _addField ('markCVV2_CVC2');
    $this-> _addField ('authAmount');
    $this-> _addField ('authDevise');
    $this-> _addField ('authNb');
    $this-> _addField ('authResult');
    $this-> _addField ('authCVV2_CVC2');
    $this-> _addField ('warrantlyResult');
    $this-> _addField ('captureNumber');
    $this-> _addField ('rapprochementStatut');
    $this-> _addField ('refoundAmount');
    $this-> _addField ('refundDevise');
    $this-> _addField ('litige');
    $this-> _addField ('timestamp');

    /** Déclaration des objets de type 'Date'
     * $name, $label=null, $description=null, $type='Date',$required=false, $value=null
     */
    $this->_addFieldDate ('transmissionDate',	'Date de transaction');
    $this->_addFieldDate ('presentationDate',	'Date de remise demandée');
    $this->_addFieldDate ('cardExpirationDate',	'Date expiration de la carte');
    $this->_addFieldDate ('captureDate');
    $this->_addFieldDate ('markDate');
    $this->_addFieldDate ('authDate');
    $this->_addFieldDate ('autorisationDate');
    $this->_addFieldDate ('remiseDate');

    /***************************************************************************************************
     * Liste des paramètres obligatoires pour le calcul de la signature des méthodes 'webservices Standard'
     * définies par le wsdl: https://systempay.cyberpluspaiement.com/vads-ws/v3?wsdl
     **************************************************************************************************/
    //Liste des paramètres de la fonction create
    $this->create = [$this->shopId,
			  $this->transmissionDate,
			  $this->transactionId,
			  $this->paymentMethod,
			  $this->orderId,
			  $this->orderInfo,
			  $this->orderInfo2,
			  $this->orderInfo3,
			  $this->amount,
			  $this->devise,
			  $this->presentationDate,
			  $this->validationMode,
			  $this->cardNumber,
			  $this->cardNetwork,
			  $this->cardExpirationDate,
			  $this->cvv,
			  $this->contractNumber,
			  $this->threeDsResult,
			  $this->subPaymentType,
			  $this->subReference,
			  $this->subPaymentNumber,
			  $this->customerId,
			  $this->customerTitle,
			  $this->customerName,
			  $this->customerPhone,
			  $this->customerMail,
			  $this->customerAddress,
			  $this->customerZipCode,
			  $this->customerCity,
			  $this->customerCountry,
			  $this->customerLanguage,
			  $this->customerIP,
			  $this->customerSendEmail,
			  $this->ctxMode,
			  $this->comment];

    //Liste des paramètres de la fonction getInfo
    $this->getInfo =  [$this->shopId,
			    $this->transmissionDate,
			    $this->transactionId ,
			    $this->sequenceNb,
			    $this->ctxMode];

    //Liste des paramètres de la fonction cancel
    $this->cancel=  [$this->shopId,
			  $this->transmissionDate,
			  $this->transactionId,
			  $this->sequenceNb,
			  $this->ctxMode,
			  $this->comment];

    //Liste des paramètres de la fonction validate
    $this->validate =  [$this->shopId,
			     $this->transmissionDate,
			     $this->transactionId,
			     $this->sequenceNb,
			     $this->ctxMode,
			     $this->comment];

    //liste des paramètres de la fonction force
    $this->force =  [$this->shopId,
			  $this->transmissionDate,
			  $this->transactionId,
			  $this->sequenceNb,
			  $this->ctxMode,
			  $this->autorisationNb,
			  $this->autorisationDate,
			  $this->comment];

    //Liste des paramètres de la fonction modify
    $this->modify =  [$this->shopId,
			   $this->transmissionDate,
			   $this->transactionId,
			   $this->sequenceNb,
			   $this->ctxMode,
			   $this->amount,
			   $this->devise,
			   $this->remiseDate,
			   $this->comment];

    //Liste des paramètres de la fonction modifyAndValidate
    $this->modifyAndValidate =  [$this->shopId,
				      $this->transmissionDate,
				      $this->transactionId,
				      $this->sequenceNb,
				      $this->ctxMode,
				      $this->amount,
				      $this->devise,
				      $this->remiseDate,
				      $this->comment];

    //Liste des paramètres de la fonction refund
    $this->refund =  [$this->shopId,
			   $this->transmissionDate,
			   $this->transactionId,
			   $this->sequenceNb,
			   $this->ctxMode,
			   $this->newTransactionId,
			   $this->amount,
			   $this->devise,
			   $this->presentationDate,
			   $this->validationMode,
			   $this->comment];

    //Liste des paramètres de la fonction duplicate
    $this->duplicate =  [$this->shopId,
			      $this->transmissionDate,
			      $this->transactionId,
			      $this->sequenceNb,
			      $this->ctxMode,
			      $this->orderId,
			      $this->orderInfo,
			      $this->orderInfo2,
			      $this->orderInfo3,
			      $this->amount,
			      $this->devise,
			      $this->newTransactionId,
			      $this->presentationDate,
			      $this->validationMode,
			      $this->comment];

    //Liste des paramètres du type TransactionInfo
    $this->transactionInfo =  [$this->errorCode,
				    $this->extendedErrorCode,
				    $this->transactionStatus,
				    $this->shopId,
				    $this->paymentMethod,
				    $this->contractNumber,
				    $this->orderId,
				    $this->orderInfo,
				    $this->orderInfo2,
				    $this->orderInfo3,
				    $this->transmissionDate,
				    $this->transactionId,
				    $this->sequenceNb,
				    $this->amount,
				    $this->initialAmount,
				    $this->devise,
				    $this->cvAmount,
				    $this->cvDevise,
				    $this->presentationDate,
				    $this->type,
				    $this->multiplePaiement,
				    $this->ctxMode,
				    $this->cardNumber,
				    $this->cardNetwork,
				    $this->cardType,
				    $this->cardCountry,
				    $this->cardExpirationDate,
				    $this->customerId,
				    $this->customerTitle,
				    $this->customerName,
				    $this->customerPhone,
				    $this->customerMail,
				    $this->customerAddress,
				    $this->customerZipCode,
				    $this->customerCity,
				    $this->customerCountry,
				    $this->customerLanguage,
				    $this->customerIP,
				    $this->transactionCondition,
				    $this->vadsEnrolled,
				    $this->vadsStatus,
				    $this->vadsECI,
				    $this->vadsXID,
				    $this->vadsCAVVAlgorithm,
				    $this->vadsCAVV,
				    $this->vadsSignatureValid,
				    $this->directoryServer,
				    $this->authMode,
				    $this->markAmount,
				    $this->markDevise,
				    $this->markDate,
				    $this->markNb,
				    $this->markResult,
				    $this->markCVV2_CVC2,
				    $this->authAmount,
				    $this->authDevise,
				    $this->authDate,
				    $this->authNb,
				    $this->authResult,
				    $this->authCVV2_CVC2,
				    $this->warrantlyResult,
				    $this->captureDate,
				    $this->captureNumber,
				    $this->rapprochementStatut,
				    $this->refoundAmount,
				    $this->refundDevise,
				    $this->litige,
				    $this->timestamp];

    //Liste des paramètres du type standardResponse
    $this->standardResponse =  [$this->errorCode, $this->extendedErrorCode, $this->transactionStatus, $this->timestamp];

    //Liste des paramètres du type threeDsResult
    $this->threeDsResult = [$this->brand, $this->enrolled, $this->authStatus, $this->eci, $this->xid, $this->cavv, $this->cavvAlgorithm];
  }


  /****************************************************************************************************
   * Fontion getSignature:
   * @param 	$data:	liste des paramètres à signer
   * @param 	$methode: 	key de la methode utilisée: ex create ou getInfo etc..
   * @return 	$wsSignature:	SHA1 de la chaine construite à partir des paramètres à signer
   *
   * Exemple d'appel de cette fonction:
   *
   * $wsSignature= $obj-> getSignature($data,$methode_name);
   *
   * $obj: objet de la classe WSAPI
   * $data: tableau des paramètres à signer
   * $methode_name : variable de type string, valorisée à "getInfo" pour cet exemple
   *
   * ATTENTION:
   *			Tous les champs de type 'Date' doivent être exprimés au format W3C
   *			L'objet $sign doit contenir le certificat dans l'attribut 'key'
   *			Cette fonction ne fonctionne qu'avec les web services Standards V3
   ***************************************************************************************************/
  function getSignature ($data,$methode){
    $raw_sign =	'';
    $list=$this->$methode;
    foreach($list as $field) {
      //récupération de la value, chaine vide si param non défini
      $name=$field->name;

      $value = array_key_exists($name, $data) ? $data[$name] : null;

      $field->setValue($value);
      $raw_sign .= $field->getSignatureValue() . '+';

    }

    $raw_sign .=$this->key;
    //echo $raw_sign;
    $wsSignature = sha1($raw_sign);
    return $wsSignature;

  }

  /****************************************************************************************************
   * Fontion formatReq
   * Parse l'ensemble des paramètres attendus, remplace par null la value d'un champ si celui ci n'est pas envoyé.
   *
   * @param 	$data		:	liste des paramètres à envoyer à soap
   * @param 	$methode	: 	key de la methode utilisée: ex create, getInfo etc..
   * @return 	$wsSignature:	sha1 de la chaine construite à partir des paramètres à signer
   *
   ****************************************************************************************************/
  function formatReq($data,$methode){
    $result = [];

    //Tableau des paramètres à passer à soap
    $list=$this->$methode;

    foreach($list as $field) {
      //récupération de la value, null si param non défini
      $name=$field->name;
      $value= array_key_exists($name, $data) ? $data[$name] : null;

      $field->setValue($value);
      $formatted_value = $field->getValue();

      //Ajout au tableau des paramètres soap
      if ($formatted_value!==null){
	$result[$name] = $formatted_value;
      }
    }
    return $result;
  }

  /******************************************************************************************************
   * Fonction getErrorCode:
   * Affiche le libélé du code erreur 'errorCode' renvoyé par la plateforme de paiement après une requète WS
   * @param : errorCode : Numéro du code erreur
   *
   * @return: $errorLabel : libéllé du code erreur
   *****************************************************************************************************/
  function getErrorCode ($errorCode) {
    $errorTab= ["0" => "Action réalisée avec succès",
		     "1" => "Action non autorisé",
		     "2" => "Transaction non trouvée",
		     "4" => "TransactionId/Sequence/TransmissionDate  déjà existante",
		     "5" => "Mauvaise signature",
		     "10" => "Mauvais montant, (vérifiez qu'il ne depasse pas le montant de la transaction d'origine)",
		     "11" => "Mauvaise devise",
		     "12" => "Type carte non connu",
		     "13" => "Date expiration carte incorrecte",
		     "14" => "CVV obligatoire",
		     "15" => "Contrat inconnu",
		     "16" => "Mauvais numéro de carte (longeur, luhn, …)",
		     "50" => "Paramètre ‘shopId’ invalide",
		     "51" => "Paramètre ‘transmissionDate’ invalide",
		     "52" => "Paramètre ‘transactionId’ invalide",
		     "53" => "Paramètre ‘ctxMode’ invalide",
		     "50" => "Paramètre ‘shopId’ invalide",
		     "51" => "Paramètre ‘transmissionDate’ invalide",
		     "52" => "Paramètre ‘transactionId’ invalide",
		     "53" => "Paramètre ‘ctxMode’ invalide",
		     "54" => "Paramètre ‘comment’ invalide",
		     "57" => "Paramètre ‘presentationDate’ invalide",
		     "58" => "Paramètre ‘newTransactionId’ invalide",
		     "59" => "Paramètre ‘validationMode’ invalide",
		     "60" => "Paramètre ‘orderId’ invalide",
		     "61" => "Paramètre ‘orderInfo’ invalide",
		     "62" => "Paramètre ‘orderInfo2’ invalide",
		     "63" => "Paramètre ‘orderInfo3’ invalide",
		     "64" => "Paramètre ‘PaymentMethod’ invalide",
		     "65" => "Paramètre ‘cardNetwork’ invalide",
		     "66" => "Paramètre ‘contractNumber’ invalide",
		     "67" => "Paramètre ‘customerId’ invalide",
		     "68" => "Paramètre ‘customerTitle’ invalide",
		     "69" => "Paramètre ‘customerName’ invalide",
		     "70" => "Paramètre ‘customerPhone’ invalide",
		     "71" => "Paramètre ‘customerMail’ invalide",
		     "72" => "Paramètre ‘customerAddress’ invalide",
		     "73" => "Paramètre ‘customerZipCode’ invalide",
		     "74" => "Paramètre ‘customerCity’ invalide",
		     "75" => "Paramètre ‘customerCountry’ invalide",
		     "76" => "Paramètre ‘customerLanguage’ invalide",
		     "77" => "Paramètre ‘customerIP’ invalide",
		     "78" => "Paramètre ‘customerSendMail’ invalide",
		     "99" => "Error inconnue"];

    if(array_key_exists($errorCode,$errorTab)){
      $errorLabel = $errorTab[$errorCode];
      return $errorLabel;
    }
  }

  /******************************************************************************************************
   * Fonction getTypeResponse
   * Donne le type de réponse pour chaque méthode telles qu'elles sont définies dans le wsdl
   * @param: $methode_name le key de la methode
   *
   *@return: $type: le type de reponse
   *****************************************************************************************************/
  Function getTypeResponse($methode_name){
    if ($methode_name=="refund" || $methode_name=="getInfo" || $methode_name == "duplicate" || $methode_name == "create" ){
      $type= "transactionInfo";
    }else{
      $type="standardResponse";
    }
    return($type);
  }

  /******************************************************************************************************
   * Fonction getResponse:
   * Permet de calculer la signature lors de la réponse de la plateforme.
   * @param: $methode_name: key de la methode
   * @param: $answer: objet contenant le retour reçu par l'appel d'un webservice
   *
   * @return: $response: tableau contenant deux valeurs:
   * •	$result: booleen (true si la comparaison des signatures est valide, false dans le cas contraire)
   * •	$message: message indiquant le resultat de la comparaison des signatures
   *****************************************************************************************************/
  Function getResponse($methode_name, $answer){
    $response_type = $this->getTypeResponse($methode_name);

    $param = [];
    $param = get_object_vars($answer);

    // calcul signature
    $wsSigncalc = $this-> getSignature($param,$response_type);

    // comparaison des signatures
    if($answer->signature != $wsSigncalc){
      $result= '0';
      $message="Analyse de la réponse: Réponse invalide.";
    }else{
      $result='1';
      $message="Analyse de la réponse : Signature valide.";
    }
    $response=  [];
    $response['result']=$result;
    $response['message']=$message;
    return $response;
  }

  /******************************************************************************************************
   * Fonction create:
   * Demande de création de paiement.
   * @param: tableau de paramètres de type createPaiementInfo
   * @return: $res: objet de type transactionInfo
   *****************************************************************************************************/

  function create($param){
    $methode_name="create";
    $wsSignature= $this-> getSignature($param,$methode_name);

    $param_soap= [];
    $param_soap['createInfo'] = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;

    $res = $this->client-> __soapCall($methode_name,$param_soap);

    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;
  }

  /******************************************************************************************************
   * Fonction getInfo:
   * Cette fonction permet d’interroger une transaction pour en connaître ses différents attributs
   * @param: tableau de paramètres contenant les champs suivants:
   * • shopId
   * • transmissionDate
   * • transactionId
   * • sequenceNb
   * • ctxMode
   *
   * @return: $res: objet de type transactionInfo
   *****************************************************************************************************/

  function getInfo($param){
    $methode_name="getInfo";
    $wsSignature= $this-> getSignature($param,$methode_name);

    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;

    $res = $this->client-> __soapCall($methode_name,$param_soap);

    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;

  }

  /******************************************************************************************************
   * Fonction modify:
   * Cette fonction permet de modifier le montant d’une transaction (à la baisse) ou d’en modifier la date de remise souhaitée.
   * Les transactions pouvant faire l’objet d’une modification possèdent l’un des statuts suivant :
   * • A valider
   * • A valider et autoriser
   * • En attente
   * • En attente d’auto
   * • En attente de remise
   *
   * @param: tableau de paramètres contenant les paramètres suivants:
   * • shopId
   * • transmissionDate
   * • transactionId
   * • sequenceNb
   * • ctxMode
   * • amount
   * • devise
   * • remiseDate
   * • comment
   *
   * @return: $res: objet de type standardResponse
   *****************************************************************************************************/
  function modify($param){
    $methode_name="modify";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;
    $res = $this->client-> __soapCall($methode_name,$param_soap);
    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;

  }

  /******************************************************************************************************
   * Fonction validate:
   * Cette fonction permet de modifier le montant d’une transaction (à la baisse) ou d’en modifier la date de remise souhaitée et de valider la transaction si besoin.
   * Les transactions pouvant faire l’objet d’une modification possèdent l’un des statuts suivant :
   * • A valider
   * • A valider et autoriser
   * • En attente
   * • En attente d’auto
   * • En attente de remise
   * @param:tableau de paramètres contenant les champs suivants:
   * • shopId
   * • transmissionDate
   * • transactionId
   * • sequenceNb
   * • ctxMode
   * • amount
   * • devise
   * • remiseDate
   * • comment
   *
   * @return: $res: objet de type standardResponse
   *****************************************************************************************************/
  function modifyAndValidate($param){
    $methode_name="modifyAndValidate";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;
    $res = $this->client-> __soapCall($methode_name,$param_soap);
    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;
  }

  /******************************************************************************************************
   * Fonction validate:
   * Cette fonction permet d’autoriser la remise en banque d’une transaction à la date de présentation demandée dans le paiement original. Les transactions pouvant faire l’objet d’une validation possèdent l’un des statuts suivants :
   * • A valider
   * • A valider et autoriser
   * @param:tableau de paramètres contenant les champs suivants:
   * • shopId
   * • transmissionDate
   * • transactionId
   * • sequenceNb
   * • ctxMode
   * • comment
   *
   * @return: $res: objet de type standardResponse
   *****************************************************************************************************/
  function validate ($param) {
    $methode_name="validate";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;
    $res = $this->client-> __soapCall($methode_name,$param_soap);
    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;
  }

  /******************************************************************************************************
   * Fonction cancel:
   * Cette fonction permet d’annuler définitivement une transaction, non encore remisée, disposant d’un des statuts suivants :
   * • A valider
   * • A valider et autoriser
   * • En attente
   * • En attente d’auto
   * • En attente de remise
   *
   * @param: tableau de paramètres contenant les champs suivants:
   * •	shopId
   * •	transmissionDate
   * •	transactionId
   * •	sequenceNb
   * •	ctxMode
   * •	comment
   *
   * @return: $res: objet de type standardResponse
   *****************************************************************************************************/
  function cancel ($param) {
    $methode_name="cancel";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $param_soap['wsSignature']= $wsSignature;
    $res = $this->client-> __soapCall($methode_name,$param_soap);
    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;
  }
  function force ($param) {
    $methode_name="force";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_modifyAndValidate= [];
    $param_modifyAndValidate = $this-> formatReq($param,$methode_name);
    $param_modifyAndValidate['wsSignature']= $wsSignature;
    $res = $this->client-> __soapCall($methode_name,$param_modifyAndValidate);
    //print_r($this->getResponse($methode_name,$res));
    //print_r($this->getErrorCode ($res->errorCode));
    //print_r($res);
    return $res;
  }

  function duplicate ($param) {
    /* La méthode ci-dessous permet de récupérer l’entête HTTP de la réponse */
    /* $client étant une instance du client SOAP utilisé pour appeler les WS */
    $header = $this->client->__getLastResponseHeaders();
    /* Dans la chaîne de caractère obtenue, nous recherchons la présence de l’ID de la session
       HTTP, stockée dans l’élément "JSESSIONID" : */
    if(preg_match("#JSESSIONID=([A-Za-z0-9\.]+)#",$header, $matches)){
      $JSESSIONID = $matches[1];;
    }
    $cookie= $JSESSIONID;
    $this->client->__setCookie('JSESSIONID', $cookie);

    $methode_name="duplicate";
    $wsSignature= $this->getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this->formatReq($param,$methode_name);
    $param_soap['wsSignature']=$wsSignature;
    try{
      $res = $this->client-> __soapCall($methode_name, $param_soap );
    }catch (\Exception $e) {
      \Seolan\Core\Logs::critical(get_class($this).' ::duplicate ',  'Exception reçue : '.$e->getMessage());
      \Seolan\Core\Logs::critical(get_class($this).' ::duplicate ',  print_r($e->getMessage(), true) );
    }
    return $res;
  }

  function refund ($param) {
    /* La méthode ci-dessous permet de récupérer l’entête HTTP de la réponse */
    /* $client étant une instance du client SOAP utilisé pour appeler les WS */
    $header = $this->client->__getLastResponseHeaders();
    /* Dans la chaîne de caractère obtenue, nous recherchons la présence de l’ID de la session
       HTTP, stockée dans l’élément "JSESSIONID" : */
    if(preg_match("#JSESSIONID=([A-Za-z0-9\.]+)#",$header, $matches)){
      $JSESSIONID = $matches[1];;
    }
    $cookie= $JSESSIONID;
    $this->client->__setCookie('JSESSIONID', $cookie);

    $methode_name="refund";
    $wsSignature= $this-> getSignature($param,$methode_name);
    $param_soap= [];
    $param_soap = $this-> formatReq($param,$methode_name);
    $wsSignature= $this-> getSignature( $param_soap,$methode_name);
    $param_soap['wsSignature']= $wsSignature;
    try{
      $res = $this->client-> __soapCall($methode_name,$param_soap);
    }
    catch (\Exception $e) {
      \Seolan\Core\Logs::critical(get_class($this).' ::refund ',  'Exception reçue : '.$e->getMessage());
      \Seolan\Core\Logs::critical(get_class($this).' ::refund ',  print_r($e->getMessage(), true) );
    }
    return $res;
  }
}
