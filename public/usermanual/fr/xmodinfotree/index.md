Module « Gestion de Rubriques »
===============================

Introduction
------------

Le module de gestion de rubriques permet :

-   la création d'une arborescence de pages,
-   la saisie de contenus dans les pages,
-   la publication des pages sur le site Internet,
-   l'insertion dans les pages de sections dynamiques, dont les contenus
    sont intégrés depuis d'autres modules.

Dans la suite de ce chapitre, nous utiliserons indifféremment les termes
rubriques et pages.

Quelques définitions
--------------------

-   **une rubrique** : c'est l'élément qui gère les niveaux ou pages de
    votre site. La rubrique contient des informations essentielles à la
    construction de la page et de l'arborescence du site (valide ou non
    valide, sa rubrique « mère », des liens vers des pages spécifiques,
    titre )
-   **une section** : une partie de la page, partie élémentaire de la
    page, pour laquelle vous disposez de gabarits d'affichage (ou mises
    en pages) , préformatés, liés à la feuille de style du site.
-   **une page** : contenu d'une rubrique, ensemble de sections.
-   **une section sur requête, ou section dynamique** : une section qui
    fait appel au contenu d'un module, de type ensemble de fiches. Ex :
    une page de l'annuaire. Ces sections disposent également de gabarits
    (ou mises en pages) d'affichages préparés.
-   '''les mises en page ''': elles existent pour les sections
    « simples » et pour les sections « dynamiques », elles ont été
    créées pour votre site et en respectent la feuille de style.

Le sommaire ou arborescence de votre site
-----------------------------------------

Page de référence du site, qui contient toutes les pages existantes.
C'est dans cette page que vous structurez votre site. Le nom des
rubriques dépend toujours du site que vous gérez. L'écran se divise en
trois parties : la barre d'outils, la zone de recherche et la liste des
rubriques, organisée sous la forme d'une arborescence dépliable.

[[Image:|thumb|

<center>
</center>
<center>
Illustration 73: Arborescence partiellement dépliée

</center>
]]

Sur cette page vous disposez de nombreuses fonctions pour construire et
manipuler votre arborescence, fonctions présentes dans le menu Contenu
ou accessibles par les icônes.

Chaque ligne présente une rubrique.

La boite à cocher en début de ligne permet de **sélectionner la
rubrique** pour lui appliquer une commande du menu Contenu.

Les boutons + et - permettent de **déplier et replier** la rubrique si
elle possède des sous-rubriques. Un **titre affiché en** gras signale
une rubrique qui a du contenu d'information. Les chiffres entre
parenthèse à la suite du titre indiquent le nombre de sous-rubriques.

Une rubrique affichée sur un fond grisée est inactive. Elle n'est pas
visible sur le site.

En milieu de ligne se trouvent l'identifiant unique de la rubrique,
dénommé alias, puis les icônes d'édition et de manipulation.

  ----------- --------------------------------------------
  <center>    Sélection de la rubrique
  <Image:>    
              
  </center>   
              

  <center>    Édition, mise à jour
  <Image:>    
              
  </center>   
              

  <center>    Déplacer la rubrique vers le haut
  <Image:>    
              
  </center>   
              

  <center>    Déplacer la rubrique vers le bas
  <Image:>    
              
  </center>   
              

  <center>    Ajouter une sous-rubrique dans la rubrique
  <Image:>    
              
  </center>   
              
  ----------- --------------------------------------------

### Menu et barre d'outils

La plupart des commande s'appliquent aux rubriques préalablement
sélectionnées.

**Ajouter une rubrique **: ajouter une rubrique manuellement, en
précisant sa rubrique mère

**Supprimer **: supprimer une ou plusieurs rubriques

**Valider **: valider une ou plusieurs rubriques

**Invalider **: invalider une ou plusieurs rubriques

**Déplacer **: déplacer une ou plusieurs rubriques vers une autre
rubrique choisie dans le navigateur de rubrique

**Dupliquer **: copier une ou plusieurs rubriques

**Tout replier **: Replier complètement l'arborescence et non niveau par
niveau en utilisant les (+) ou (-) devant chaque rubrique, ou affichage
/non affichage des sections d'une page

**Tout déplier **: déplier complètement l'arborescence du site, ou les
mini menus d'une page

**Exporter **: export de l'ensemble des rubriques et sous-rubriques
sélectionnées

Édition d'une rubrique
----------------------

[[Image:|thumb|

<center>
Illustration 74: Édition d'une rubrique

</center>
]]

Une rubrique est composée d'un contenu de page et de propriétés globales
à la rubrique.

#### Propriétés / champs de la rubrique.

Les champs de la rubrique sont accessibles via l'onglet « Rubrique ».

Les champs de la rubrique sont des informations qui sont attachées
globalement à la rubrique. Ces informations peuvent varier d'un site à
l'autre, mais incluent nécessairement au minimum les champs suivants :

-   Titre de la rubrique
-   Alias de la rubrique. Utilisé par exemple pour définir des liens
    internes. Vous pouvez le laisser vide à la création; il sera alors
    calculé automatiquement à partir du titre lors de la validation de
    la saisie.
-   État de validation de la rubrique (champ Validé).
-   Ordre de la rubrique parmi toutes les rubriques de la rubrique mère.
    Renseigné automatiquement à la création.
-   La rubrique mère dans l'arborescence,

D'autres champs sont généralement présents :

-   Complément d’URL qui contient des informations à concaténer à l’URL
-   Redirection : champ à renseigner lorsque l'accès à cette rubrique
    doit se traduire par une redirection.
-   Description : champ contenant la description de la rubrique sous la
    forme d'un texte relativement court, affiché et / ou utilisé pour le
    référencement de la page.

Pour éditer ces informations, utiliser le bouton « Éditer » en bas
d'onglet.

#### Contenu

Le contenu de page est affiché et modifiable dans l'onglet « Contenu ».
Les pages sont constituées de « sections ». Chaque section peut être
déplacée, dans la page, ou déplacée dans une autre page ( utilisation du
navigateur de rubriques). Chaque section peut être validée ou invalidée
individuellement.

[[Image:|thumb|

<center>
Illustration 75: Barre d'édition de section

</center>
]]

Chaque section dispose de son « mini menu d'édition », intégrant, dans
l'ordre de l'illustration, les fonctions suivantes :

-   sélection de la section,
-   édition du contenu de la section,
-   suppression de la section,
-   déplacement de la section d'un rang vers le bas,
-   déplacement de la section d'un rang vers le haut,

-   déplacement de la section en bas de page
-   déplacement de la section en haut de page
-   validation / invalidation de la section
-   informations sur la section : type de section, date de dernière
    modification.

Ajouter une section
-------------------

La version 8 de la console Séolan vous propose 3 types de sections :

-   les sections de contenu « classiques », intégrant des textes des
    images et tout autre type de contenu que vous pouvez saisir
    directement dans la section.
-   Les sections de données dites « dynamiques ». Il s'agit d'intégrer
    dans des pages des éléments d'informations en provenance d'autres
    tables, pour lesquelles ont été définies des mises en pages
    spécifiques à votre site. Jusqu'à la version 7 de la console Séolan,
    il s'agissait de la seule méthode d'intégration de contenus depuis
    des modules autres que le gestionnaire de rubriques.
-   Les modules d'affichage. La fonction est proche de la précédente,
    mais elle a été généralisée et élaborée. Pour plus d'information sur
    les modules d'affichage, veuillez vous reporter à la section
    spécifique.

L'ajout d'une section est réalisé par l'onglet « Ajouter une section »,
dans lequel le choix entre les trois types de sections vous est proposé.
Dans la suite de cette section nous commentons l'ajout d'une section
« classique ».

#### Les gabarits de section

Le type des contenus d'une section est défini par un **gabarit ou une
mise en page**. Plusieurs mises en page standards sont disponibles; en
fonction des besoins de votre site, des mises en pages spécifiques sont
éventuellement ajoutées.

#### Pour ajouter une section

-   Choix du gabarit dans la liste déroulante
-   Choix de la position
-   OK, pour création de la section à remplir
-   Édition de la section pour saisie de son contenu
-   Page d'édition, de même type qu'une page d'un module de fiche. Même
    type de champs, mêmes outils disponibles (image, dates, texte
    enrichi..).
-   validation de la page
-   ré affichage des sections de votre page.

Par défaut les sections créées sont invalidées (en grisées), ceci afin
de compléter les contenus de pages, même si votre page est déjà publiée
sur le site.

#### Champs standards et utilisation de la feuille de style du site

Trois champs sont toujours présents quelque soit le gabarit.

-   titre de section, en feuille de style tag « H1 » avec éventuellement
    génération dynamique de la typo en flash
-   sous titre, en feuille de style tag « H2 » à la couleur de la charte
    de la page
-   chapeau, en feuille de style «tag « H3 »

Sections de données dynamiques
------------------------------

Ces sections vont permettre d'insérer dans la page des informations
issues des modules; par exemple pour afficher le contenu d'un annuaire.
Elles sont souvent basées sur un moteur de recherche permettant la
génération d'une requête.

Après création de la section, il faut préciser les paramètres de la
« requête », par choix dans les boites de recherches présentes dans la
page.

-   Choix du gabarit dans la liste déroulante
-   Choix de la position
-   OK, pour création de la section « vide »
-   Édition de la section pour positionnement des « critères » de la
    requête.
-   Choix des propriétés de requête
-   Sauver : La requête est créée et associée au « gabarit dynamique »

Les alias
---------

Les alias sont utilisés pour référencer des rubriques dans divers
contextes.

Lorsqu'une page est référencée par un alias, l'adresse publique de la
page sera dans la mesure du possible inspirée de l'alias : une page dont
l'alias est « conditions-generales-de-vente », seront accessible par un
navigateur sous le nom « conditions-generales-de-vente.html ». Ce
comportement est utile à la fois à l'internaute, qui dispose d'une
adresse « lisible », aux moteurs de recherche qui peuvent identifier
dans l'adresse des mots clés, et à vous même pour communiquer à vos
correspondants des adresses de page simplifiées.

A ce titre, les alias doivent répondre à quelques contraintes.

**Unicité **: un alias doit être unique dans le site. Il ne vous sera
pas possible d'indiquer deux fois le même alias pour deux pages
différentes.

**Codage **: les alias ne doivent contenir que des caractères non
accentués, des – et des chiffres. Tout autre caractère, tel que
l'espace, sera refusé ou supprimé automatiquement.

Les alias sont utilisés lors de la rédaction de vos contenus pour
indiquer un lien vers la rubrique. Dans un champ texte enrichi, diverses
syntaxes simplifiées peuvent être utilisées et sont indiquées en tête de
ce document.

Les modules d'affichage
-----------------------

Les modules d'affichage permettent d'intégrer dans la page des
informations extraites depuis les autres modules de la console, ainsi
que d'autres fonctions telles que des formulaires. Les affichages
générés utilisent des mises en page génériques, dont vous pouvez
personnaliser l'apparence par simple manipulation des feuilles de style.

Administrateur : Gestion du menu administration ou Menu système
---------------------------------------------------------------

Le menu système est géré dans un module gestion de rubriques, basé sur
la table des rubriques CS8 et sur la tables des sections CS8SEC.

### Paramétrage des droits

Le paramétrage des droits doit être le suivant pour les groupes
d'utilisateurs qui ont accès au backoffice :

-   Pour avoir "Tous les modules" dans la colonne de gauche, paramétrer
    le droit "Lecture seulement" sur le Module Administration (et pas
    sur le module "Menu Administration"
-   Pour ne pas avoir "Tous les modules" dans la colonne de gauche,
    paramétrer le droit "Traverser" sur le Module Administration (et pas
    sur le module "Menu Administration")
-   Droit lecture seulement sur le module "Menu Administration"
-   Paramètre "Gérer la sécurité sur les objets" positionné à vrai sur
    le module "Menu Administration"

Pour gérer les pages visibles par utilisateur, il ne faut que très
rarement gérer les droits avec les règles de sécurité sur le menu
administration. Les rubriques et les sous-rubriques n'apparaissent que
si l'utilisateur en cours à les droits sur les modules insérés dans les
pages.