Gestion des utilisateurs et groupes d'utilisateurs [Admin]
==========================================================

Les utilisateurs sont gérés à partir du module « Gestion Utilisateurs ».
les groupes d'utilisateurs sont gérés à partir du module « Gestion
Groupes ».

Un utilisateur peut appartenir à plusieurs groupes d'utilisateurs. Un
groupe peut être inclus dans un autre groupe. L'utilisateur dispose des
droits les plus étendus hérités de l'ensemble des groupes auxquels il
appartient.

Les droits sont définis de préférence au niveau des groupes, mais
peuvent aussi être définis au niveau de chaque utilisateur si
nécessaire. Dans ces deux modules (Gestion des Utilisateurs et Gestion
des Groupes) il est possible de définir le niveau d'accès pour chaque
module.

Gestion des groupes d'utilisateurs [Admin]
------------------------------------------

Accès au module : Menu de gauche : tous les modules, puis propriétés
système, puis « Gestion des groupes ».

Sur cette page est affiché la liste des groupes existants dans votre
espace collaboratif. Des groupes génériques sont toujours présents dans
la liste des groupes. Ce sont :

-   administrateurs – ensemble des utilisateurs disposant de droits
    élargis, sur l'administration des modules et de la base de données
-   tout le monde – le « public » , droits sans authentification – cas
    d'un site internet par exemple
-   utilisateurs authentifiés – tout utilisateur authentifié, c'est à
    dire ayant passé avec succès la page de connexion.

D'autres groupes sont généralement créés en fonction des droits qui
peuvent être gérés.

Le module de gestion des utilisateurs, comme le module de gestion des
groupes, est géré comme un ensemble de fiches, et vous en retrouvez la
quasi-totalité des fonctions : parcours, recherche, import,
export, édition, etc.

Dans la barre d'outils de la gestion des groupes vous trouvez un
pictogramme d'accès direct à la gestion des utilisateurs, et vice versa.
En effet, ces deux modules sont intimement liés et il est pratique de
pouvoir naviguer rapidement de l'un vers l'autre.

### Création d'un groupe d'utilisateur

Appel du formulaire de création d'un groupe d'utilisateurs, via l’icône
« Nouveau » dans la barre d'outils.

1.  Affichage du formulaire de création de groupe :\
     Nom : Nom du groupe tel qu'il apparaîtra partout\
     Description : Commentaire indiquant le rôle de ce groupe.
    Optionnel. Pour information seulement\
     Groupe de sécurité : Ensemble des groupes dont ce groupe hérite en
    termes de droits.\
     Utiliser les préférences de : Nom d'un utilisateur utilisé comme
    modèle lors de la création de nouveaux utilisateurs dans ce groupe.
    Les nouveaux utilisateurs héritent en particulier des signets des
    modèles.
2.  Sauver

### Suppression d'un groupe

La suppression d'un groupe d'utilisateurs, n'entraîne pas la suppression
des utilisateurs, uniquement la suppression des droits des utilisateurs
qui étaient hérités du groupe supprimé.

Gestion des utilisateurs [Admin]
--------------------------------

Ce module est destiné à maintenir les comptes des utilisateurs ayant
accès à la console Séolan. Il permet la création, la suppression, la
modification des utilisateurs. Il intègre les informations
indispensables à la définition d'un utilisateur (identifiant, e mail.,
date de début de validité, date de fin de validité, mot de passe) ainsi
que des informations plus qualitatives (Nom, Prénom, numéro de
téléphone, etc), ayant plus d'usage dans un contexte « annuaire ».

Accès au module : Menu de gauche : tous les modules, puis propriétés
système, puis « Gestion des utilisateurs ».

Le module de gestion des utilisateurs, comme le module de gestion des
groupes, est géré comme un ensemble de fiches, et vous en retrouvez la
quasi-totalité des fonctions : parcours, recherche, import,
export, édition, etc.

### Création d'un nouvel utilisateur

Appel du formulaire de création d'un d'utilisateur, via l’icône
« Nouveau » dans la barre d'outils.

1.  mise à jour et création de tous les champs de données de
    l'utilisateur

-   -   alias – nom d'utilisateur ( servant à l'authentification).
    -   mot de passe – minimum 6 caractères alpha numérique
    -   nom complet
    -   e mail. – pour envoi des abonnements ou informations
    -   groupes – choix du/des groupes utilisateurs
    -   date de début et de fin de validité du compte utilisateur créé

1.  sauver

Lorsqu'un utilisateur est créé dans un groupe disposant d'un utilisateur
modèle, les signets du nouvel utilisateur sont initialisés avec le
modèle. Un utilisateur est disponible immédiatement après sa création.

### Changer temporairement d'identité

La fonction « Changer d'identité » disponible à partir de la liste des
utilisateurs permet à un administrateur de « devenir » temporairement
cet utilisateur. En réalisant cette opération, il peut ainsi contrôler
l'ensemble des droits attribué à cet utilisateur. Cette fonction est
symbolisée par le picto <Image:>.

Une fois le changement d'identité réalisé, il est possible pour
l'administrateur de reprendre son identité en cliquant sur ce même picto
apparaissant en haut de page.

### Envoyer le mot de passe

Fonction disponible par le menu Contenu \> Envoyer le mot de passe

Cette fonction permet d'envoyer un e mail. contenant une invitation à se
connecter avec le nom d'utilisateur et un nouveau mot de passe. Tous les
utilisateurs sélectionnés avec la boîte à cocher son traités.

### Désactiver un utilisateur

Il peut souvent être plus commode de désactiver un utilisateur plutôt
que de la supprimer. Cela permet en particulier de conserver tous les
liens vers cet utilisateur, dans les logs, les documents dont il est
l'auteur, etc. Nous vous conseillons alors de réaliser deux actions :

-   changer l'e-mail associé à cet utilisateur pour qu'il ne reçoive
    plus d'informations, par exemple depuis les abonnements, les
    fonctions de notification ou les newsletters,
-   indiquer dans les dates de validité de son compte la fin de son
    activité : cela lui interdira toute connexion.

Gérer les droits d'accès aux modules [Admin]
--------------------------------------------

Les droits sont classés selon

-   aucun droit spécifique – module inaccessible – par défaut à la
    création des modules
-   traverser – rend le module accessible mais le contenu est caché
-   lecture seulement – droits en lecture seulement
-   lecture/écriture – droits en mise à jour
-   lecture/écriture/validation – droits de mise à jour et de validation
    (publication) des contenus
-   contrôle total – droits administrateur sur le module, droit de
    maintenance de la structure du module concerné, accès aux propriétés
    de ce module

### Positionner des droits d'un groupe d'utilisateurs ou d'un utilisateur

1.  accès aux droits de ce groupe via le picto « cadenas »
2.  [+] choisir le module, et ouvrir les droits associés pour ce groupe\
     [[Image:|thumb|
    <center>
    </center>
    \

    <center>
    Illustration 86: Positionnement des droits utilisateurs

    </center>
    ]]

3.  Les droits posés et courants sont symbolisés, par un « cadre bleu »
    autour du bouton radio. Clic, et positionnement des nouveaux droits,
    via les boutons radios.
4.  Sauver

### Limiter les connexions à l'interface d'administration

Dans le cas où certains utilisateurs ne doivent pas avoir accès au
backoffice, il est prévu un champ spécifique permettant de brider les
accès au backoffice de manière simplifiée. Pour mettre en œuvre ce
fonctionnement il faut :

-   Créer le champ de nom SQL 'BO' (comme backoffice), dans la table des
    utilisateurs.
-   Les utilisateurs pour lesquels ce champ est positionné à Vrai
    peuvent accéder au backoffice. Les autres seront refusés.