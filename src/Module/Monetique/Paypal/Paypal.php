<?php
namespace Seolan\Module\Monetique\Paypal;

/**
 * \brief Classe XModPaypal.
 * Classe de gestion des transactions Paypal.
 */
class Paypal extends \Seolan\Module\Monetique\Monetique{
  public $defaultTemplate = 'Module/Monetique.paypal.html'; ///< Template Paypal par défaut.
  public $formUrlPreProd = NULL; //"https://www.sandbox.paypal.com/cgi-bin/webscr"; ///< url de soumission de pré-production.
  public $formUrlProd = NULL; ///< url de soumission de pré-production.
  public $password = NULL; ///< url de soumission de pré-production.
  public $signature = NULL; ///< url de soumission de pré-production.
  public $user = NULL;
  public $urlApiPaypalPreProd = NULL;
  public $urlApiPaypalProd = NULL;

  protected function webPaymentHandling(\Seolan\Module\Monetique\Model\Transaction $transaction){
    // Initialisation des paramètres d'appel
    $callParms = $this->paypalForm($transaction);
    $transaction->callParms = $callParms;

    // Création du formulaire à envoyer en banque
    foreach ($callParms as $key => $value) {
      $paypalForm['fields'] .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
    }
    if ($this->testMode(true)) {
      $paypalForm['url'] = $this->formUrlPreProd;
    }else{
      $paypalForm['url'] = $this->formUrlProd;
    }
    $transaction->callParms['url'] =  $paypalForm['url'];
    $paypalForm['method'] = "POST";

    $transaction->callParms['method'] = "POST";
    if ($this->testMode(true)) {
      $transaction->callParms['url'] = $this->formUrlPreProd;
    }else{
      $transaction->callParms['url'] = $this->formUrlProd;
    }
    // Retourne la transaction en cours, le formulaire envoyé en banque ainsi que le template et son entrée
    return [$transaction, $paypalForm, TZR_SHARE_DIR.$this->defaultTemplate, 'paypalForm'];
  }

  private function paypalForm($transaction){
    $params = [];
    // Gestion du paiement multiple si la commande indique plusieurs échéances
    if($transaction->nbDeadLine > 1){
      // Préparation des paramètres de multi paiement
      /* Calcule du montant des prochain prélévement */
      // On divise le montant total en centimes par le nombre d'écheances
      $montant = $transaction->amount / $transaction->nbDeadLine;
      $montant = round($montant , 2);
      // Calcule de la différence du aux arrondis
      $diff= $transaction->amount - ($montant* $transaction->nbDeadLine);
      // Calcule de la fréquence des prélévement ( $order->options['frequencyDuplicate'] est en jours)
      $frequenceDivise = $transaction->frequencyDuplicate /30;
      // On récupère la partie entière qui sera le nombre de mois entre chaque prélévement
      $frequenceDivise = explode('.', $frequenceDivise);
      // Fréquence des prélèvements en mois
      $frequencyDuplicate = sprintf('%02d',$frequenceDivise[0]);
      // Mise en forme des paramètre d'appel de multi-paiement
      $params['cmd'] = '_xclick-subscriptions';
      // Montant des prélévements
      $params['a1'] = $montant+$diff ;
      // Durée de l'abonnement
      $params['p1'] = $frequencyDuplicate;
      // Fréquence de prelevement mensuelle
      $params['t1'] = 'M';
      // Montant des prélévements
      $params['a3'] = $montant ;
      // Durée entre chaques prelevement
      $params['p3'] = $frequencyDuplicate;
      // Fréquence de prelevement
      $params['t3'] = 'M';
      // Paiement de souscription réccurents
      $params['src'] = '1';
      // Nombre d'échéances
      $params['srt'] = $transaction->nbDeadLine-1;
      // Si un paiement récurrent échoue, PayPal tente de recouvrer la somme deux fois avant d'annuler l'abonnement.
      $params['sra'] = '1';
      // Référence de l'abonné
      $params['invoice'] = $transaction->refAbonneBoutique;

    }
    // Sinon paiement unique
    else{
      // Paiement en une fois si seulement la référence est renseigné dans 'PBX_CMD'
      $params['cmd'] = '_xclick';
      // Le montant (utiliser le point comme séparateur décimal)
      $params['amount'] = $transaction->amount;
    }

    // Paramètres de base
    $params['user'] = $this->user;
    $params['pwd'] = $this->password;
    $params['signature'] = $this->signature;
    $params['country'] = 'FR';
    // Devise en Euros par défaut
    $params['currency_code'] = 'EUR';
    // Je considère que les taxes éventuelles ont déjà été calculé
    $params['tax'] = '0.00';
    // La page de retour si paiement accepté: name="return"
    $params['return'] = $this->urlPayed;
    // La page de retour si la transaction est annulée: name="cancel_return"
    $params['cancel_return'] = $this->urlCancelled;
    // La page qui sera appelée par l'IPN: name="notify_url"
    $params['notify_url'] = $this->urlAutoResponse;
    // L’adresse e-mail de votre compte PayPal
    $params['business'] = $this->siteId;
    // Le descriptif de la transaction, visible par l’acheteur
    $params['item_name'] = $transaction->oid;
    // un identifiant interne, non visible par l’acheteur (option)
    $params['item_number'] =  $transaction->orderReference;

    // PayPal ne demande pas à l’acheteur de saisir un message à votre intention si = 1
    $params['no_note'] = '1';
    // Si « 0 », alors PayPal demande à l’acheteur de saisir une adresse de livraison, et vous la communique
    // Si « 1 », alors PayPal ne demande pas à l’acheteur de saisir une adresse de livraison
    $params['no_shipping'] = '0';
    //  définit le langage à présenter par défaut à l’acheteur: Fr, eN, eS, iT, de etc... (option)
    $params['lc'] = $this->lang;
    // Paramètre pour le bouton
    $params['bn'] = "PP-BuyNowBF";
    // Memorise le customerOid
    $params['custom'] = $transaction->customerOid;
    if( $transaction->captureMode == self::AUTHORIZATION_ONLY){
      $params['paymentaction'] = 'authorization';
    }else{
      $params['paymentaction'] = 'sale';
    }

    // Redirection du client avec les paramètres en POST
    $params['rm'] = '1';
    // Si la commande nécéssite l'abonnement du client
    if (isset($transaction->refAbonneBoutique) && $transaction->enrollement == true) {
      /* A FINALISER : ATTENTE DE REPONSE (MAIL) PAYPAL */
      // $params=array();
      $token = $this->setUpPaymentAuthorization();
      $params['cmd'] = '_express-checkout';
      $params['token']=$token;
      $transaction->porteur=$token;
    }
    return $params;
  }

  private function setUpPaymentAuthorization(){
    $callParms = $this->paypalFormEnrollement();
    $paypalForm = $this->prepareCurlForm($callParms);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $responseParms = $this->sendCurlForm($paypalForm);
    //   if(stripos($responseParms['ACK'],'Success')){
      return $responseParms['TOKEN'];
    /* }else{ */
    /*   throw new \Exception(get_class($this).' ::setUpPaymentAuthorization : '.print_r($responseParms,true)); */
    /* } */
  }

  private function paypalFormEnrollement($transaction){
    $callParms = [];
    // Construction du formulaire
    $callParms['USER'] = urlencode($this->user);
    $callParms['PWD'] = urlencode($this->password);
    $callParms['SIGNATURE'] = urlencode($this->signature);
    $callParms['METHOD'] = "SetExpressCheckout";
    $callParms['VERSION'] = '86';
    //Payment authorization
    $callParms['PAYMENTREQUEST_0_PAYMENTACTION'] = 'AUTHORIZATION';
    // Montant de l'autorisation
    $callParms['PAYMENTREQUEST_0_AMT'] = 1000;
    $callParms['PAYMENTREQUEST_0_CURRENCYCODE'] = "EUR";
    //The type of billing agreement
    $callParms['L_BILLINGTYPE0'] = "MerchantInitiatedBilling";
    $callParms['L_BILLINGAGREEMENTDESCRIPTION0'] = "Forfait vierge";
    // La page de retour si paiement accepté: name="return"
    $callParms['returnUrl'] = $this->urlPayed;
    // La page de retour si la transaction est annulée: name="cancel_return"
    $callParms['cancelUrl'] = $this->urlCancelled;
    return $callParms;
  }

  protected function webPaymentUnFoldReponse() {
    $transaction = new \Seolan\Module\Monetique\Model\Transaction();
    // lire le formulaire provenant du système PayPal et ajouter 'cmd'
    $req = 'cmd=_notify-validate';

    // Dans la boucle foreach, nous allons récupérer tous les paramètres passés par la méthode POST et les stocker dans la variable $req. Cette dernière nous permettra de renvoyer toutes les données à PayPal pour vérification.
    foreach ($_POST as $key => $value) {
      $value = urlencode(stripslashes($value));
      $req .= "&$key=$value";
      if($this->isUrlEncoded($value)){
	$transaction->responseParms{$key} = urldecode($value);
      }else{
	$transaction->responseParms{$key} = $value;
      }
    }
    // $transaction->responseParms['token'] = $req;
    // renvoyer au système PayPal pour validation
    // Maintenant, nous préparons le header à renvoyer au système de paypal pour obtenir la vérification de l'intégrité des données reçues. Nous ouvrons ensuite une connexion avec le serveur sandbox paypal (A remplacer par www.paypal.com en production).
    $header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
    $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $header .= "Content-Length: " . strlen($req) . "\r\n";
    $header .= "Connection: close\r\n";
    if ($this->testMode(true)) {
      $payPalHOST = 'ssl://www.sandbox.paypal.com';
      $header .= "Host: www.sandbox.paypal.com\r\n\r\n";
    } else {
      $payPalHOST = 'ssl://www.paypal.com';
      $header .= "Host: www.paypal.com\r\n\r\n";
    }

    $fp = fsockopen ($payPalHOST, 443, $errno, $errstr, 30);
    // On récupère toutes les données que l'on va traiter.
    $transaction->oid = @$_POST['item_name'] ?: $_POST['item_name1'];
    $transaction->orderReference = @$_POST['item_number'] ?: $_POST['item_number1'];
    $payment_status = $_POST['payment_status'];
    $transaction->amount = abs($_POST['mc_gross']);
    $payment_currency = $_POST['mc_currency'];
    $transaction->transId = $_POST['txn_id'];
    $receiver_email = $_POST['receiver_email'];
    $payer_email = $_POST['payer_email'];
    $transaction->customerOid = $_POST['custom'];
    // Si la connexion au service paypal a réussie, on lui envoi le header préparé précedemment, puis on récupère les information renvoyée par PayPal. Si le résultat est "VERIFIED" alors, la transaction est valide, les données envoyées et récupérées sont correctes. Dans le cas contraire, paypal renverra le code "INVALID".
    if (!$fp) {
      // ERREUR HTTP
      $transaction->statusComplement = "Erreur HTTP";
      throw new \Exception(get_class($this).' ::webPaymentUnFoldReponse : '.$transaction->statusComplement);
    } else {
      fputs ($fp, $header . $req);
      while (!feof($fp)) {
	$res = fgets ($fp, 1024);
	if (stripos($res, "VERIFIED") !== false) {
	  // transaction valide
	  // vérifier que payment_status a la valeur Completed ou Pending (Pour une simple demande d'autorisation)
	  if ( $payment_status == "Completed" ||  $payment_status == "Pending") {
	    \Seolan\Core\Logs::critical(get_class($this).'::webPaymentUnFoldReponse', 'status de la transaction ayant pour KOID '.$transaction->oid." : ".$payment_status." et capture mode = ".$this->getCaptureMode($transaction));
	    if( ($this->getCaptureMode($transaction) == self::CATCH_PAYMENT && $payment_status == "Completed") || ($this->getCaptureMode($transaction) == self::AUTHORIZATION_ONLY) && $payment_status == "Pending"){
	      $transaction->status = self::SUCCESS;
	      if($this->getCaptureMode($transaction) == self::AUTHORIZATION_ONLY){
	        $transaction->statusComplement = "Demande d'autorisation acceptée.";
	      }
	    }else if ( ($this->getCaptureMode($transaction) == self::CATCH_PAYMENT) && $payment_status == "Pending"){
	      $transaction->status = self::RUNNING;
	      $transaction->statusComplement = "En attente d'autorisation de la banque.";
	    }else{
	      $transaction->status = self::ERROR;
	      $transaction->statusComplement = "Erreur à vérifiée.";
	    }
	    // On vérifie que txn_id n'a pas été précédemment traité
	    if($this->responseAlreadyReceived($transaction)){
	      $transaction->statusComplement = "Response already received";
	      throw new \Exception(get_class($this).' ::webPaymentUnFoldReponse : '.$transaction->statusComplement);
	    } else {
	      // vérifier que receiver_email est votre adresse email PayPal principale
	      if ( $this->siteId == $receiver_email) {
		// vérifier que payment_amount et payment_currency sont corrects
		// traiter le paiement
		$transaction->responseCode = '00';
		$transaction->status = self::SUCCESS;
		/*	if($transaction->responseParms['txn_type']=='subscr_payment'){	}*/
	      }
	      else {
		// Mauvaise adresse email paypal
		$transaction->statusComplement = "Il y a eu une erreur d'email paypal vous concernant , ce n'est pas vous qui avait encaissé ce paiement.";
		throw new \Exception(get_class($this).' ::webPaymentUnFoldReponse : '.$transaction->statusComplement);
	      }
	    }
	  }else if ( $payment_status == "Refunded" ) {
	    $transaction->statusComplement = "Remboursement de commande: ".$transaction->orderReference;
	    $transaction->oid = $this->getRefundWithTransOri($transaction->oid);
	    if(empty($transaction->oid)){
	      $transaction->status = self::ERROR;
	    }else{
	      $transaction->status = self::SUCCESS;
	      $transaction->responseCode = '00';
	    }
	  }else if( $_POST['txn_type'] == 'subscr_signup'){
	    if($_POST['payer_status'] == 'verified'){
	      $transaction->status = self::RUNNING;
	      $transaction->responseCode = '';
	      $transaction->amount =  $transaction->responseParms['mc_amount1'] ;
	      $transaction->amount += $transaction->responseParms['mc_amount3'] *$transaction->responseParms['recur_times'];
	    }
	  }else {
	    // Statut de paiement: Echec
	    $transaction->status = self::ERROR;
	    $transaction->statusComplement = "Retour de la reponse non vérifiée.";
	    $transaction->responseCode = '-1';
	  }
	}
	else if (stripos($res, "INVALID") !== false) {
	  // Transaction invalide
	  $transaction->status = self::INVALID;
	  $transaction->statusComplement = "Signature invalide.";
	  $transaction->responseCode = '-1';
	  \Seolan\Core\Logs::critical(__METHOD__ , "Validation de la transaction par paypal invalide : " . $req . "\nReponse de paypal :" . $res);
	}
      }
      fclose ($fp);
    }
    return $transaction;
  }

  private function responseAlreadyReceived($transaction){
    $rs = getDB()->select('select status from '.$this->xset->getTable().' where `KOID`="'.$transaction->oid.'"');
    $res = null;
    if($rs->rowCount()==1){
      $res  = $rs->fetch();
      if($res['status'] == self::RUNNING){
	return false;
      }else{
	return true;
      }
    }else{
      \Seolan\Core\Logs::critical(get_class($this).'::responseAlreadyReceived', 'status de la transaction d\'origine ayant pour KOID '.$transaction->transOri.' non trouvé!');
      throw new \Exception(get_class($this).'::responseAlreadyReceived : status de la transaction d\'origine ayant pour KOID '.$transaction->transOri.' non trouvé!');
    }
  }

  protected function refundHandling($transaction){
    // Mémorisation des paramètres nécéssaire au remboursement
    list($paramsRetourOrigin, $amountOri) = $this->getResponseParmsOrigin($transaction);

    if($amountOri <  $transaction->amount){
      $transaction->statusComplement = ' ::refundHandling : Le montant du remboursement ne peut être supérieur au montant d\'origine : '.$transaction->amount.' > '.$amountOri;
      throw new \Exception(get_class($this). $transaction->statusComplement);
    }
    // Création du formulaire de remboursement (Mise à jour de l'attribut $transaction->callParms)
    $transaction->callParms = $this->refundPaypalForm($paramsRetourOrigin, $transaction);
    // Mémorisation des paramètres d'appel
    $appel['oid'] = $transaction->oid ;
    $appel['callParms'] = $transaction->callParms;
    $appel['options'] =['callParms'=>['raw'=>true,'toxml'=>true]];
    $this->xset->procEdit($appel);
    // Préparation du formulaire de remboursement
    $paypalForm = $this->prepareCurlForm($transaction->callParms);
    // Envoi du formulaire et mise à jour du retour dans $transaction
    $transaction->responseParms = $this->sendCurlForm($paypalForm);


    if("SUCCESS" == strtoupper($transaction->responseParms["ACK"])) {
      exit('Refund Completed Successfully: '.print_r($httpParsedResponseAr, true));
      $transaction->status = self::SUCCESS;
      $transaction->statusComplement = 'Remboursement réalisé avec succès';
    }else if("SUCCESSWITHWARNING" == strtoupper($transaction->responseParms["ACK"])){
      $transaction->status = self::SUCCESS;
      $transaction->statusComplement = 'Remboursement réalisé avec succès, mais à vérifier.';
    }else{
      $transaction->status = self::ERROR;
      $transaction->statusComplement = 'Remboursemment échoué.';
    }
    \Seolan\Core\Logs::critical(get_class($this).'::refundHandlingFIN', print_r($transaction,true));
    // Traitement de la réponse contenu dans $transaction
    return $transaction;
  }


  private function refundPaypalForm($paramsRetourOrigin, $transaction){
    $callParms = [];
    // Construction du formulaire
    $callParms['USER'] = urlencode($this->user);
    $callParms['PWD'] = urlencode($this->password);
    $callParms['SIGNATURE'] = urlencode($this->signature);
    $callParms['METHOD'] = "RefundTransaction";
    $callParms['VERSION'] = '94';
    $callParms['TRANSACTIONID'] = $paramsRetourOrigin['txn_id'];
    $callParms['REFUNDTYPE'] = "Partial";
    $callParms['AMT'] = $transaction->amount;
    return $callParms;
  }

  private function getResponseParmsOrigin($transaction){
    $rs = getDB()->select('select responseParms, amount from TRANSACMONETIQUE where KOID=?', [$transaction->transOri]);
    $res = null;
    if($rs->rowCount()==1){
      $res  = $rs->fetch();
      $params = \Seolan\Core\System::xml2array($res['responseParms']);
      return [$params, $res['amount']];
    }else{
      \Seolan\Core\Logs::critical(get_class($this).'::getResponseParmsOrigin', 'responseParms, transaction d\'origine ayant pour KOID '.$transaction->transOri.' non trouvée!');
      throw new Exception(get_class($this).'::getResponseParmsOrigin : responseParms, transaction d\'origine ayant pour KOID '.$transaction->transOri.' non trouvée!');
    }
  }
  private function getRefundWithTransOri($transOri){
    $rs = getDB()->select('select KOID from '.$this->xset->getTable().' where `transOri`="'.$transOri.'"');
    $transactionOid = null;
    if($rs->rowCount()==1){
      $transactionOid  = $rs->fetch(\PDO::FETCH_COLUMN);
    }
    else{
      \Seolan\Core\Logs::critical(get_class($this), 'Transaction de remboursement ayant '.$transOri.' comme origine non trouvée.');
    }
    return $transactionOid;
  }

  private function isUrlEncoded($string){
    $test_string = $string;
    while(urldecode($test_string) != $test_string){
      $test_string = urldecode($test_string);
    }
    return (urlencode($test_string) == $string)?True:False;
  }

  protected function refundReplay($transaction) {}

  protected function duplicateHandling($transaction) {
    throw new Exception(__METHOD__ . ' not implemented');
  }

  public function initOptions() {
    parent::initOptions();
    $alabel = \Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','modulename');
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','formurlpreprod'),'formUrlPreProd','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','formurlprod'),'formUrlProd','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','user'),'user','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','password'),'password','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','signature'),'signature','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','urlapipaypalpreprod'),'urlApiPaypalPreProd','text', NULL,NULL,$alabel);
    $this->_options->setOpt(\Seolan\Core\Labels::getSysLabel('Seolan_Module_Monetique_Paypal_Paypal','urlapipaypalprod'),'urlApiPaypalProd','text', NULL,NULL,$alabel);

  }


  /**
   * \brief Fonction de préparation du formulaire Curls à envoyer à Paybox.
   * Concatène les champs du formulaire Paybox à soumettre.
   * \param Array $payboxParams : Le tableau contenant les paramètres à envoyer.
   * \return Sring $payboxForm : La chaine contenant la requête à envoyer.
   */
  private function prepareCurlForm($paypalParams){
    $paypalForm ='';
    foreach($paypalParams as $key => $value){
      $paypalForm .= $key.'='.$value.'&';
    }
    // On enlève le dernier '&'
    $paypalForm = substr($paypalForm,0,strlen($paypalForm)-1);
    return $paypalForm;
  }

  /**
   * \brief Fonction d'émission d'une requête à Paypal.
   * Envoi une requête CURL à Paypal.
   * \param String $paypalForm : La chaine contenant les paramètres de la transaction.
   * \param \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction en cours qui va être mise à jour.
   * \return \Seolan\Module\Monetique\Model\Transaction &$transaction : La transaction passé en paramètre et mise à jour.
   */
  private function sendCurlForm($paypalForm){
    // Recherche du serveur disponible
    if($this->testMode(true)){
      $url = $this->urlApiPaypalPreProd;
    }
    else{
      $url = $this->urlApiPaypalProd;
    }

    if($url){
      // Initialisation d'une session CURL
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      // TRUE pour afficher tous les événements. Écrit la sortie sur STDERR ou dans le fichier spécifié en utilisant CURLOPT_STDERR. False pour le contraire
      curl_setopt($ch, CURLOPT_VERBOSE, 1);
      // Turn off the server and peer verification (TrustManager Concept).
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);

      // Set the request as a POST FIELD for curl.
      curl_setopt($ch, CURLOPT_POSTFIELDS, $paypalForm);

      // Get response from the server.
      $httpResponse = curl_exec($ch);

      if(!$httpResponse) {
	$transaction->statusComplement = $paypalForm->callParms['METHOD']." ".curl_error($ch).'('.curl_errno($ch).')';
	throw new \Exception(get_class($this).' ::sendCurlForm : '.$transaction->statusComplement);
      }

      // Extract the response details.
      $httpResponseAr = explode("&", $httpResponse);

      $httpParsedResponseAr = [];
      foreach ($httpResponseAr as $i => $value) {
	$tmpAr = explode("=", $value);
	if(sizeof($tmpAr) > 1) {
	  $httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
	}
      }

      if((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
	$transaction->statusComplement ="Invalid HTTP Response for POST request(".$paypalForm.") to ".$url;
	throw new \Exception(get_class($this).' ::sendCurlForm : '.$transaction->statusComplement);
      }else{
	$transaction->responseParms = $httpParsedResponseAr;
      }
      return $this->formatParams($httpParsedResponseAr);
    }

  }

  private function formatParams($response ) {
    $formatedParams = [];
    foreach($response as $k => $v){
      if($k == "REFUNDTRANSACTIONID"){
	$formatedParams[$k]=$v;
      }
      if($k == "FEEREFUNDAMT"){
	$formatedParams[$k]=urldecode($v);
      }
      if($k == "GROSSREFUNDAMT"){
	$formatedParams[$k]=urldecode($v);
      }
      if($k == "NETREFUNDAMT"){
	$formatedParams[$k]=urldecode($v);
      }
      if($k == "CURRENCYCODE"){
	$formatedParams[$k]=$v;
      }
      if($k == "TOTALREFUNDEDAMOUNT"){
	$formatedParams[$k]=urldecode($v);
      }
      if($k == "TIMESTAMP"){
	$formatedParams[$k]=urldecode($v);
      }
      if($k == "CORRELATIONID"){
	$formatedParams[$k]=$v;
      }
      if($k == "ACK"){
	$formatedParams[$k]=$v;
      }
      if($k == "VERSION"){
	$formatedParams[$k]=$v;
      }
      if($k == "BUILD"){
	$formatedParams[$k]=$v;
      }
      if($k == "REFUNDSTATUS"){
	$formatedParams[$k]=$v;
      }
      if($k == "PENDINGREASON"){
	$formatedParams[$k]=$v;
      }
      else{
	$k = str_replace ('=&gt;', '', $k);
	$formatedParams[urldecode($k)]=urldecode($v);
      }
    }
    return $formatedParams;
  }

}
