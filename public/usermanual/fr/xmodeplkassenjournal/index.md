Module Rapprochement Kassen Journal
===============================


1. Import des journaux Team Axess:

Les journaux Team Axess peuvent être importés manuellement ou via lecture de mail si un envoi est effectué automatiquement par Team Axess.

-   Il est important que les noms des fichiers contiennent une chaîne de caractère identifiable  ex : JRB_ ) afin d'être reconnu par le script d'extraction des pièces jointes.
-   De même un mail ne doit contenir qu'un seul fichier joint et non zippé.
-   On reçoit un fichier par station.
-   Le format accepté est le .csv.

Boîtes mails : voir champs Nom d'utilisateur dans la configuration du module de chaque station


2. Création des logs Team Axess:

Les logs Team Axess sont générés lors des appels au webservice Team Axess ( commande, annulation ). Ils contiennent le Type, Numéro de ticket, Numéro de type de personne, Numéro de secteur, Date et heure d'appel, Numéro de journal, Numéro de série, Numéro de retour journal, Numéro de produit, Montant, Référence commande, Référence ligne.


3. Rapprochement des transactions:

- Pour les tickets:
Les transactions Team Axess sont rapprochées en fonction de leur numéro de série, type et montant. Ce qui veut dire que pour une ligne de transaction Team Axess nous recherchons une ligne de logs Team Axess enregistrée en base ayant le même numéro de série, type et montant.

- Pour les produits:
Les transactions Team Axess sont rapprochées en fonction de leur numéro de journal, numéro de série, type et montant. Ce qui veut dire que pour une ligne de transaction Team Axess nous recherchons une ligne de logs Team Axess enregistrée en base ayant le même numéro de journal, numéro de série, type et montant.

- Pour les annulations:
Les transactions Team Axess sont rapprochées en fonction de leur type et de la date de transaction. Ce qui veut dire que pour une ligne de transaction Team Axess nous recherchons une ligne de logs Team Axess enregistrée en base ayant le même type et une date de transaction compris dans un intervalle défini par le paramêtre dateComparInterval.

Les statuts « rapprochés » :

-   NON : transaction non rapprochée.
-   AUTO : transaction rapprochée automatiquement.
-   MANUEL : transaction rapprochée manuellement.
-   MAUVAIS PRIX: transaction dont le prix n'est pas cohérent.
-   DOUBLON : transaction en double.

Cas spécifiques :

-   Pour les remboursements on ne rapproche les lignes de transaction que si la somme de toutes les lignes des transactions en annulation pour un confirmation number est égale à la somme de toutes les lignes de logs Team Axess en annulation pour le même confirmation number.


4. Alertes et notifications:

Une tache cron de contrôle des lignes non rapprochées génère des alertes mail à epasslibre@xsalto.com ( création de ticket GLPI ) et au client si le paramètre « mailNotification » est renseigné au niveau des options du module .

Une tache de contrôle des doublons de transfert génère des alertes mail à epasslibre@xsalto.com. Ce contrôle compare les dates de début et de fin de validité des forfaits d'un wtp pour identifier les transactions ayant des numéro de série différent et des dates validités identiques. Attention ce contôle ne fonctionne pas sur des forfaits non datés transmis à des jours différents la date de validité étant la date du jour de transfert.


5. Configuration du module:

<table>
<tbody>
<tr>
<td>Intervalle ( en seconde ) de comparaison des dates</td><td>Spécifie l'intervalle ( +/- ) de comparaison des dates de transaction ( kassen/ logsTA ) pour le rapprochement des annulations.</td>
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
<td>Les noms de fichiers fournis sont uniques ( datés )</td><td>Spécifie si les noms des fichiers transmis sont uniques</td>
</tr>
<tr>
<td>Nombre de colonnes pour les lignes à traiter dans le fichier</td><td>Spécifie le nombre de colonne que doit contenir une ligne de données à traiter</td>
</tr>
<tr>
<td>Index des colonnes des données à importer ( sous forme de tableau PHP )</td><td>Spécifie les index des colonnes dans les lignes de données. Se présente sous forme de tableau PHP.</td>
</tr>
</tbody>
</table>
