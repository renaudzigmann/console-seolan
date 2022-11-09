Module Rapprochement bancaire
===============================


1. Import des journaux de rapports bancaires

Les journaux de rapports bancaires sont importés en fonction de leur cycle d'envoi ( quotidien, hebdomadaire … ) par la banque. Ceux-ci sont envoyés par mail ou déposés via FTP et importés sur les serveurs xsalto ( dossier: var/bank-reporting-files ) par lecture des mails et extraction des fichiers de journaux bancaires attachés.
Dans le cas d'un dépôt FTP le dossier de dépôt se situe à racine du serveur web ( exemple: /var/www/restricted/ftp/payboxJRB/superbesse/deposit ) et lien symbolique pointant sur ce dossier est créé dans var/bank-reporting-files/deposit.

-   Il est important que les noms des fichiers contiennent une chaîne de caractère identifiable ( ex : JRB_ ) afin d'être reconnu par le script d'extraction des pièces jointes.
-   De même un mail ne doit contenir qu'un seul fichier joint et non zippé.
-   Le format accepté est le .csv.
-   Paybox vs SystemPay : les fichiers transmis par Paybox contiennent tous les types de transactions ( télécollecté et non télécollecté ) alors que les fichiers SystemPay ne contiennent que les transactions télécollectées.

Boîtes mails : voir champs Nom d'utilisateur dans la configuration du module de chaque station


2. Rapprochement des commandes

Les commandes sont rapprochées en fonction de leur référence, montant et état. Ce qui veut dire que pour une ligne de transaction bancaire nous recherchons la commande enregistrée en base ayant la même référence, le même montant et est un état de fabrication correspondant à une commande payée ( paiement acceptée, complète, close, annulation ).

Les statuts « rapprochés » :

-   NON : transaction non rapprochée.
-   AUTO : transaction rapprochée automatiquement.
-   MANUEL : transaction rapprochée manuellement. Cela correspond à un paiement ou un remboursement non effectué depuis la console ePassLibre. 
-   PARTIEL : transaction correspondant à une commande payée en plusieurs fois dont le dernier paiement n'a pas encore été reçu.
-   RETOUR BANQUE INVALIDE : Existence d'une transaction bancaire pour une commande en attente de paiement ou en abandon dans Seolan (Problème sur le retour banque coté Seolan). Ce statut nécessite une intervention manuelle sur le statut de la commande pour éviter une réclamation client.

Cas spécifiques :

-   Les paiements en plusieurs fois génèrent des statuts rapprochement « PARTIEL » jusqu'à réception du dernier paiement qui fait passer les transactions « PARTIEL » en statut « AUTO » afin de finaliser le rapprochement de la commande traitée.
-   Les remboursements sont traités différemment suivant qu'ils sont effectués en console ou directement dans l'interface bancaire : les remboursements faits en console sont traités de façon automatique ( statut auto ) alors que les remboursements effectués dans l'interface bancaire le sont de façon manuelle( paramètres « remboursement manuel» - statut manuel ).

Concernant les remboursements, les remboursements du montant d'une ligne de commande ou de l'intégralité du montant de la commande est possible en automatique via une fonction ePassLibre une fois la ligne ou la commande annulée.
Par contre, il n'est pas possible de rembourser une partie du montant d'une ligne de commande. (remboursement d'un pourcentage du montant d'une ligne par exemple)
Dans ce cas, ePassLibre n'a pas connaissance des remboursements effectués par les stations directement dans leur outil de banque. Nous avons par contre l'information via le fichier des transactions reçu par la banque. C'est ce type de transaction qui a besoin d'être rapproché manuellement et  topé  « remboursement manuel ». C'est la station qui est en charge de contrôler les mouvements non rapprochés.
Pour information, le montant de ces transactions « remboursement manuel » peuvent être pris en compte dans le tableau de bord en positionnant la propriété correspondante au niveau du module « tableau de bord ».


3. Alertes et notifications

Des contrôles de la validité des commandes sont effectués :

Les statuts « rapprochés » :

-   Les commandes payées en banque mais non traitées en console suite à un problème de traitement du retour bancaire ( statut : Retour banque invalide ).--> Risque de réclamations clients.
-   Les commandes traitées en console mais non payées : les commandes « non rapprochées » sont contrôlées afin de s'assurer de la présence d'une transaction valide transmise par la banque.--> Manque de CA pour la station, client peut skier sans avoir payé.
-   Les commandes payées plusieurs fois : les commandes « non rapprochées » sont contrôlées afin de s'assurer qu'une seule ( et non plusieurs ) transaction valide a été transmise par la banque. --> Risque de réclamations
-   Il est possible d'envoyer par mail à la station un rapport sur le nombre de commande non rapprochées en mettant en place une tache cron lançant la méthode checkNotLettered. L'email de destination est celui définit dans le paramètre E-mail de notification.

Si une commande n'a pu être rapprochée elle ne sera pas marquée « rapproché » donc sera re-traitée lors des taches suivantes jusqu'à rapprochement.
Tous ces contrôles génèrent des alertes mail à epasslibre@xsalto.com ( création de ticket GLPI ).



4. Configuration du module:


<table>
<tbody>
<tr>
<td>Champ référence</td><td>Spécifie le champs correspondant aux ref commande du journal bancaire</td>
</tr>
<tr>
<td>Champ montant</td><td>Spécifie le champs correspondant aux montants commande du journal bancaire</td>
</tr>
<tr>
<td>Unité montant</td><td>Spécifie l'unité de montant ( euros, centimes )</td>
</tr>
<tr>
<td>Unicité</td><td>Spécifie les champs pour le calcul d'unicité des lignes</td>
</tr>
<tr>
<td>Champ type de transaction</td><td>Spécifie le champs correspondant aux types de transaction du journal bancaire</td>
</tr>
<tr>
<td>Valeur du champ type de transaction pour le remboursement</td><td>Spécifie la valeur du champs correspondant aux types de transaction pour les remboursements</td>
</tr>
<tr>
<td>Nom d'utilisateur</td><td>Spécifie l'utilisateur de la boîte mail</td>
</tr>
<tr>
<td>Mot de passe</td><td>Spécifie le mot de passe de la boîte mail</td>
</tr>
<tr>
<td>Numéro de ligne de l'entête ( 1ère ligne = 0 )</td><td>Spécifie le numéro de ligne de l'entête pour l'import de fichier</td>
</tr>
<tr>
<td>Expression recherchée dans le nom des fichiers pour les identifier</td><td>Spécifie la chaîne de caractères permettant d’identifier les fichiers de journaux bancaires dans les pièces jointes des mails</td>
</tr>
<tr>
<td>Rapprochement automatique après import</td><td>Spécifie si le rapprochement est lancé automatiquement après l'import</td>
</tr>
<tr>
<td>Séparateur CSV</td><td>Spécifie le séparateur CSV</td>
</tr>
<tr>
<td>Champ statut ( état de fabrication )</td><td>Spécifie le champs correspondant au statut de la transaction ( Paybox ). Ne pas définir si seules les transaction valides sont transmises dans les rapports ( ex : SystemPay ).</td>
</tr>
<tr>
<td>Statut valide </td><td>Spécifie la valeur du champs statut de transaction ( Paybox ). Laisser vide si le fichier bancaire ne contient que les transactions payées ( ex : SystemPay ).</td>
</tr>
<tr>
<td>Heure de début de la plage de contrôle des commandes valides non payées</td><td>Spécifie le début de la plage de calcul de contrôle des commandes valides non payées. Exemple : les commandes seront contrôlées de l'avant veille 22:00:00 à la veille 22:00:00.
Travailler sur des horaires éloignés de minuit  permet de résoudre le problème du décalage entre la date de commande et la date de traitement de la commande en banque.</td>
</tr>
<tr>
<td>Interval de fin</td><td>Spécifie l'interval de fin ( en jours ) de la pèriode de contrôle de commandes invalides ( le jour même - un nombre de jour )</td>
</tr>
<tr>
<td>E-mail de notification</td><td>Spécifie l'E-mail de notification pour l'envoi d'alerte au client</td>
</tr>
<tr>
<td>Nom de fichier unique</td><td>Spécifie si le nom de fichier est unique ( à mettre à false si le nom des fichiers fournis n'est pas unique )</td>
</tr>
</tbody>
</table>





