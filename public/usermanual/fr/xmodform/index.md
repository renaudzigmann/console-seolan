Module de gestion de formulaires
================================

Introduction
------------

Le module de gestion de formulaires permet la création d'enquêtes, de
formulaires, par diffusion directe d'un mail de sollicitation, ou encore
par publication du formulaire sur le site Internet associé.

Fonctions principales
---------------------

[[Image:|thumb|

<center>
</center>
<center>
Illustration 76: Page d'accueil du module

</center>
]]La page d'accueil du module donne immédiatement accès à la liste des
formulaires définis. Le fonctionnement est le même que les ensembles de
fiches habituels à ce niveau.

### Propriétés générales du formulaire

Les champs à renseigner sont les suivants. Attention, les champs peuvent
avoir des significations ou des comportements légèrement différents en
fonction du mode de diffusion du formulaire.

-   Titre : nom du nouveau formulaire,
-   Texte d'introduction : Texte enrichi affiché en introduction du
    formulaire,
-   Texte de bas de page : texte enrichi affiché en fin de formulaire,
    avant le bouton de sauvegarde,
-   Date d'ouverture : date de début de campagne. Il n'est pas possible
    de répondre au formulaire avant la date d'ouverture,
-   Date de clôture : date de fin de campagne. Il n'est pas possible de
    répondre au formulaire après la date de clôture,
-   Table des questions : table contenant les réponses. La plupart du
    temps vous n'avez pas besoin de modifier ce champ qui est géré
    automatiquement par la console Séolan.
-   Réponses multiples : permet de répondre plusieurs fois au même
    formulaire. Ce champ n'a pas de sens si le formulaire est anonyme,
    par exemple publié sur un site Internet. Par contre, il prend tout
    son sens lors d'une diffusion nominative par e mail.
-   Possibilité de réédition : dans certains cas, il peut être utile de
    sauvegarder la saisie en cours, puis de reprendre la saisie plus
    tard. Si ce champ est position à Oui, cette possibilité est offerte
    grâce à un bouton complémentaire en fin de formulaire.
-   Accès ouvert : Si ce champ est positionné à Oui, alors le formulaire
    est disponible de manière anonyme, à travers un site Internet ou par
    e mail., sans identification du répondant.
-   Destinataires internes : Liste des personnes de la base utilisateurs
    auxquelles le formulaire est diffusé.
-   Destinataires externes : Liste des e mails des destinataires
    externes.
-   Invitations envoyées : Si ce champ est positionné à Oui, alors
    l'envoi des invitations a été effectué.
-   URL d'accès direct : champ en lecture seulement contenant l'adresse
    d'accès direct au formulaire dans le cas où il s'agit d'un
    formulaire ouvert.
-   Libellé du bouton : Texte apparaissant sur le bouton d'envoi ou de
    sauvegarde en bas de formulaire.

### Création des questions du formulaire

[[Image:|thumb|

<center>
Illustration 77: Onglet d'édition des questions

</center>
]]Les questions du formulaire sont accessibles par l'onglet « Éditer le
formulaire ». Chaque question est symbolisée par un bloc contenant les
champs suivants :

-   Question : texte de la question
-   Groupe : Groupe de champ. Chaque changement de groupe dans la page
    génère un titrage
-   Commentaire : Texte d'aide ou d'explication associé à la question
-   Obligatoire : Si positionné à Oui, la réponse au champ est
    obligatoire.
-   Type de réponse : type du champ réponse associé à la question.

La barre située au dessus de chaque question comprend les fonctions
suivantes, dans l'ordre :

-   Création d'une nouvelle question de même type,
-   Suppression de la question
-   Changement d'ordre (monter et descendre la question)
-   Changement d'ordre (mettre en premier ou en dernier)
-   Type de la question et nom de la question

Le déplacement de la question peut aussi être réalisé par drag & drop en
saisissant la barre d'outil de la question.

### Visualiser le formulaire

A partir de la liste des formulaires, utiliser l’icône « Voir » associée
à ce questionnaire, puis l'onglet « Visualiser le formulaire ». Il est
visualisé dans la feuille de style connue dans le back office.

[[Image:|thumb|

<center>
Illustration 78: Visualisation du formulaire

</center>
]]

Diffusion du formulaire
-----------------------

Les modes de diffusion possibles sont les suivants :

-   diffusion par e mail. à des utilisateurs du backoffice,
-   diffusion par e mail. à des destinataires non utilisateurs du
    backoffice,
-   publication dans le backoffice sur une page dédiée,
-   publication sur un site internet.

Les différents cas de figure peuvent être conjugués. Dans les deux
premiers cas les utilisateurs reçoivent un e mail. contenant une
invitation à répondre. Dans les deux cas suivant ils répondent sur une
page formatée.

### Publication sur une page du site Internet public

[[Image:|thumb|

<center>
Illustration 79: Choix d'un module d'affichage "Formulaires"

</center>
]]

[[Image:|thumb|

<center>
Illustration 80: Choix du formulaire et de la page de message

</center>
]]

[[Image:|thumb|

<center>
Illustration 81: Formulaire publié, côté backoffice

</center>
]]

[[Image:|thumb|

<center>
Illustration 82: Formulaire publié, sur le site public

</center>
]]

Exporter les résultats
----------------------

Les résultats d'enquête sont exportables via le backoffice, en mode
consultation des formulaires, via l'onglet « Tableau de bord ». Dans cet
onglet, vous disposez du nombre de réponses ainsi que du bouton d'export
des réponses.

[[Image:|thumb|

<center>
Illustration 83: Tableau de bord du formulaire

</center>
]]