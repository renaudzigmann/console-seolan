Module Rapprochement SkiDATA
===============================


1. Import des journaux skidata:

Les journaux skidata peuvent être importés manuellement ou via lecture de mail si un envoi est effectué automatiquement par skidata.

-   Il est important que les noms des fichiers contiennent une chaîne de caractère identifiable  ex : JRB_ ) afin d'être reconnu par le script d'extraction des pièces jointes.
-   De même un mail ne doit contenir qu'un seul fichier joint et non zippé.
-   On reçoit un fichier par station.
-   Le format accepté est le .csv.

Boîtes mails : voir champs Nom d'utilisateur dans la configuration du module de chaque station


2. Création des logs skidata:

Les logs skidata sont générés lors des appels au webservice skidata ( commande, annulation ). Ils  contiennent le confirmation number, le type, la date et heure d'appel, le montant, un lien vers la commande et des liens vers les lignes de commandes.
Pour les commandes, on ne génère qu'une seule ligne par confirmation number, car les commandes sont passées groupées par catalogue à skidata.
Pour les annulations on génère une ligne par ligne annulée.


3. Rapprochement des transactions:

Les transactions skidata sont rapprochées en fonction de leur confirmation number, type, id commande et montant. Ce qui veut dire que pour une ligne de transaction skidata nous recherchons une ligne de logs skidata enregistrée en base ayant le même confirmation number, le même type, le même id commande et le même montant.

Les statuts « rapprochés » :

-   NON : transaction non rapprochée.
-   AUTO : transaction rapprochée automatiquement.
-   MANUEL : transaction rapprochée manuellement.
-   MAUVAIS PRIX: transaction dont le prix n'est pas cohérent.
-   DOUBLON : transaction en double.

Cas spécifiques :

-   Pour les remboursements on ne rapproche les lignes de transaction que si la somme de toutes les lignes des transactions en annulation pour un confirmation number est égale à la somme de toutes les lignes de logs skidata en annulation pour le même confirmation number.


4. Alertes et notifications:

Une tache cron de contrôle des lignes non rapprochées génère des alertes mail à epasslibre@xsalto.com ( création de ticket GLPI ) et au client si le paramètre « mailNotification » est renseigné au niveau des options du module .


5. Configuration du module:

<table>
<tbody>
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
<td>Les noms de fichiers fournis sont uniques ( datés )</td><td>Spécifie si les noms des fichiers transmis sont uniques</td>
</tr>
</tbody>
</table>





