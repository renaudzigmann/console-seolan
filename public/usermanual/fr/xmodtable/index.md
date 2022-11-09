# Module ensemble de fiches

Module permettant de gérer un ensemble de données structurées, de type
image, fichiers, textes, texte enrichi, logique, liens, URL. Ce type de
module est applicable à un grand nombre de cas de figures :

- annuaires : représentation d'annuaire de personnes, de sociétés, d'adhérents, d'organismes,
- catalogues : produits, photos, documents,
- listes d'utilisateurs
- liste d'actions
- ensemble de documents simples
- listes de tâches

## Parcourir des fiches 

A l'ouverture du module en mode Parcourir, vous disposez d'une liste de
l'ensemble des fiches visibles. Certaines fiches peuvent être cachées si vous ne disposez pas de tous les droits.

Les colonnes affichées comme celles disponibles dans les recherches avancées ou rapides sont paramétrées par l'administrateur du site.
L'ensemble des ces informations est paramétrable.

Les icones utilisées dans la manipulation des fiches sont les suivantes :

* ![](/tzr/templates/ico/general/view.png)    Afficher, Voir ( internet )
* ![](/tzr/templates/ico/general/edit.png)   Modifier, Éditer - crayon
* ![](/tzr/templates/ico/general/new.png)   Nouveau, Créer – page blanche
* ![](/tzr/templates/ico/general/delete.png)   Supprimer
* ![](/tzr/templates/ico/general/browse.png)   Voir la liste, Parcourir
* ![](/tzr/templates/ico/general/query.png)   Rechercher, Recherche avancée
* ![](/tzr/templates/ico/general/currentquery.png)   Recherche active
* ![](/tzr/templates/ico/general/sec.png)   Sécurité, paramètres de sécurité
* ![](/tzr/templates/ico/general/admin.png)   Administration
* ![](/tzr/templates/ico/general/prop.png)   Propriétés du module
* ![](/tzr/templates/ico/general/refresh.png)   Rejouer
* ![](/tzr/templates/ico/general/next.png)   Navigation entre fiches détaillées, précédente, suivante
* ![](/tzr/templates/ico/general/previous.png)   Retour ( page précédente )
* ![](/tzr/templates/ico/general/printer.png)   Imprimer ( fenêtre de dialogue, choix des éléments à imprimer)

### Chemin

Donne la localisation de la page consultée par rapport à l'accueil de la
console ( nom du module, nom de la fiche visitée )

### Recherche rapide

Parmi tous les critères de recherche, certains sont présentés en haut du tableau de fiches. Quelques règles de recherche utiles :

- les mots entiers ou partiels sont admis
-  de préférence utiliser des minuscules non accentuées ( recherche sur
    tous les caractères, majuscules et accents compris)
-   autocomplétion sur certains champs. Une
    liste d'occurrences est proposée après la saisie des premiers
    caractères. Les champs bénéficiant de ce dispositif sont signalés
    par le symbole ![](/tzr/templates/images8/commun/ac_input_fond.gif)

### Entêtes de liste

L'entête du tableau des fiches indique les noms des champs présentés dans le tableau. Les premières colonnes contiennent les actions possibles : sélection, suppression, modification, visualisation. Il est aussi possible de :

- trier dans le sens ascendant ou descendant en utilisant les boutons ![Tri Ascendant](/tzr/templates/ico/xmodtable/arrow_up.png) et ![Tri Descendant](/tzr/templates/ico/xmodtable/arrow_down.png)
- editer le contenu de la colonne en utilisant le bouton ![Editer la colonne](/tzr/templates/ico/xmodtable/small_edit.png)


### En ligne

Une ligne par fiche avec accès aux fonctions de sélection,visualisation, édition et suppression pour chaque fiche.

* Boîte à cocher : sélection de la fiche
* ![](/tzr/templates/ico/general/view.png)   Afficher la fiche
* ![](/tzr/templates/ico/general/edit.png)   Modifier la fiche
* ![](/tzr/templates/ico/general/delete.png)   Supprimer la fiche

Toutes les actions ne sont pas disponibles en fonction des droits.

### Menu Contenu

Les actions possibles depuis le menu **Contenu** sont :

- *valider ou invalider les fiches*, pour les ensembles de fiches sélectionnées supportant la validation,
- *exporter les fiches* sélectionnées ou toutes les fiches du module.
- *importer des fiches* depuis un fichier

#### Contenu/Exporter

Paramétrage de l'export par une fenêtre de dialogue. Les données exportées sont celles qui sont sélectionnées ou l'ensemble des données.

-   choix des champs de la table à exporter
-   choix de l'ordre dans le fichier d'export
-   choix du format d'export
-   possibilité d'exporter les fichiers ( images, doc..) associés à
    chaque enregistrement
-   possibilité de poser l'export sur un FTP
-   ajout d'un identifiant unique sur chaque ligne, qui permettra un réimport du fichier dans duplication des données

#### Contenu/importer

Paramétrage de l'import par une boite de dialogue avec les informations suivantes:

-   « upload » d'un fichier de votre PC ou poste de travail
-   choix d'une procédure d'import
-   choix, option « libellé » ou « sql » : indique si la première ligne
    contient un libellé ou le nom du champ en base de données.

### Menu Affichage

Ensemble de fonction permettant de modifier la forme du contenu de la liste des fiches.

* *libellé* : sélectionnez les champs qui seront utilisés dans la liste
"Sélectionnez un champ" puis, pour chacun, renseignez la valeur
recherchée.

[[Image:|thumb|

<center>
Illustration 25: Formulaire de Recherche Avancée

</center>
]]Vous pouvez sélectionner plusieurs fois le même champ; très pratique
pour définir une période entre deux dates par exemple.

Le résultat de votre recherche est affiché sous forme de liste, dans le
même environnement que le module complet (identique au mode de recherche
rapide).

### Picto – Imprimer

[[Image:|thumb|

<center>
Illustration 26: Choix des options d'impression

</center>
]]

Impression de toute la liste ou seulement des fiches sélectionnées.
Ouverture d'une fenêtre de choix des colonnes à imprimer, ordre de tri,
format et adresse e mail. d'un destinataire si format PDF.

### Menu Affichage

Ensemble des fonctions de navigation dans la liste, paramétrage du
nombre de lignes sur la page (40 par défaut), choix des colonnes à
afficher ou à supprimer de l'affichage.

[[Image:|thumb|

<center>
Illustration 27: Menu Affichage "Ouvert"

</center>
]]

### Menu Plus

==== [[Image:|thumb|

<center>
Illustration 28: Illustration: Menu Plus Ouvert

</center>
]]Plus/S'abonner ==== La fonction d'abonnement permet d'être averti par
e mail. des créations, modifications qui interviennent sur les fiches du
module. Vous retrouverez cette fonction dans tous les modules de votre
espace.

[Image:L](Image: "wikilink")'administrateur peut abonner un groupe
d'utilisateurs par défaut, lors du paramétrage de l'espace.

#### Plus/Avertir

Fonction qui permet de transmettre directement à un utilisateur de
l'espace, ou une autre personne, par e mail., le contenu d'une fiche ou
de plusieurs fiches.

Vous retrouverez cette fonction dans tous les modules de votre espace.

[[Image:|thumb|

<center>
Illustration 29: après choix d'un élément d'annuaire, envoi à un
utilisateur du groupe Administrateur

</center>
]]Étape 1 : choix dans la liste des fiches à envoyer ( case à cocher) ou
affichage d'une fiche

Étape 2 : choix des destinataires de l'e-mail, choix des destinataires
en copie, ou copie cachée ( fonctionnement similaire à un client de
messagerie ). choix des destinataires par sélection dans un groupe, ou
sélection d'un groupe complet. Vous pouvez également indiquer un e mail.
manuellement.

Étape 3: envoi d'un message en complément, correction du sujet

Étape 4 : envoi

#### Plus/ajouter au menu

Fonction administrateur, permettant de gérer , ajouter des pages dans le
menu général de la console

Édition d'une fiche, mise à jour d'une fiche
--------------------------------------------

Les procédures d'édition, les champs et outils disponibles sont
identiques en création et suppression.

Les libellés des champs suivis d'une astérisque signalent le caractère
obligatoire de la saisie d'une valeur pour ce champ. Un tel champ est
encadré d'un trait gras et le fond est teinté en jaune si aucune valeur
n'est présente à la validation.

=== [[Image:|thumb|

<center>
Illustration 30: Formulaire d'édition d'une fiche

</center>
]]Types de champ de saisie ===

-   **boites à cocher** : dans le cas de valeurs multiples
-   **boutons radios** : dans le cas de valeur unique
-   **texte court** : une ligne de saisie. Certains champs de ce type
    sont suivis d'une liste des valeurs déjà saisies dans ce champ pour
    d'autres fiches du même module
-   **texte long** : plusieurs lignes sont disponibles. Le retour
    chariot, est considéré comme un retour à la ligne
-   **texte enrichi** : identique au texte long mais bénéficie d'outils
    d'édition qui vous permettent de gérer du gras, italique, couleurs,
    tableaux, etc.
-   **URL** : avec la possibilité d'associer un libellé et de choisir la
    fenêtre de destination
-   **date** : disposent en cliquant sur « Sélection » d'un calendrier
    facilitant la saisie ce qui facilite la saisie correcte d'une date
-   **Champ lien** : permet de choisir parmi une liste de valeurs
    extraites d'un autre module et ainsi de lier la fiche en cours avec
    une autre fiche. Il s'agit soit d'une liste déroulante, soit de
    cases à cocher. Lorsque les sélections sont multiples dans une liste
    déroulante, il faut cliquer sur le lien choisi, avec SHIFT ou CTRL
    suivant que l'on sélectionne les éléments entre 2 bornes (shift) ou
    individuellement (ctrl)
-   **images ou fichiers** : outil de parcours sur votre poste d'images
    ou de fichiers insérer

Visualisation d'une fiche
-------------------------

[[Image:|thumb|

<center>
Illustration 31: Fiche en Visualisation

</center>
]]

**Onglet général** : La page est affichée en mode console. Les liens
deviennent actifs. Vous pouvez naviguer sur les liens internes ou
externes.

**Onglet Propriétés système**: date de dernière mise à jour, validité de
l'enregistrement ( un enregistrement non valide ne pourra pas être
affiché sur le site), et identifiant base de données.

<Image:><Image:>**Onglet Archive** : affichage des modifications de la
fiche, ici états successifs d'un document.