
Rem au 17/02

Quand on lance la totalité, il semble que nos tests soient faits de telle façon que des erreurs surviennent qui n'arrivent pas
si on lance chaque fichier indépendamement ...

FileByFile.php lance tous les tests un à un et affiche un compte rendu erreur/ok
Il faut se placer dans le rep des tests (ça scanne '.');


Pour lancer PHPUnit en ligne de commande
- aller dans le dossier csx/test/UnitTest.
- exécuter la commande suivante : php-seolan10 phpunit
On exécute ainsi   actuellement tout le fichier se terminant par Tests.php et se situant dans le même dossier que phpunit.xml... 


Pour n'effectuer que certains tests :
- exécuter qu’un seul fichier de tests : php-seolan10 phpunit  <nom_du_test.php>
- n'effectuer que certains tests (plusieurs fichiers) : php-seolab10 phpunit --filter <pattern>  
- n'effectuer des tests que pour le(s) groupe(s) spécifié(s) : php-seolab10 phpunit  --group ...
  Il n'y a pas de groupe actuellement définis
- n'effectuer qu'une suiote de tests : php-seolab10 phpunit --testsuite <name,...> 

Exécute chaque test dans un processus PHP distinct: php-seolab10 phpunit –process-isolation

! les tests qui dépendent d’un autre test ne seront pas effectués. Et cela peut crée d’autres erreurs.

phpunit.xml est le fichier de configuration de phpUnit.

la balise testsuite définit les suites de test 

<testsuites>
<testsuite name="datasources and fields">
<directory suffix="Tests.php">.</directory>
</testsuite>
</testsuites>
sur l’exemple si dessus on exécute tous les fichiers dont le nom termine par Tests.php
mais peut aussi rajouter des fichiers :
<file>tests/CurrencyTest.php</file>

Pour activer/désactiver les couleurs dans les résulats, utiliser l’attribut : 
colors="true" dans la première balise, "<phpunit"

- Rouge  : si un test a échoué.
- Jaune  : Cela veut dire que votre méthode de test ne teste rien.

Si jamais ça arrive vous pouvez rajouter :
php-seolab10 phpunit  --dont-report-useless-tests 

- Vert  : les tests on réussi.

A la fin vous aurez ce types de résultat :

FAILURES!
Tests: 14, Assertions: 102, Failures: 2, Skipped: 7, Incomplete: 1.
"Tests: 14" le nombre de méthode de test que contient notre classe de test.
"Assertions: 102"
"Failures: 2" correspond au nombre d'assertions qui on échoué
" Skipped: 7" Au nombre de test qui n'ont pas été passés donc pas effectués
"Incomplete: 1" cela signifie que le test a été déclarer comment étant incomplé dans le code probablement car celui na pas finie d'être codé.

ou ça:
OK (7 tests, 188 assertions)
cela veut dire que le test a réussi


Les @nnotations :

@depend :

les dépendances nous servent à ne pas exécuter un test si le test dont il dépend à échouer.
Elle permet également de récupérer les données renvoyer par les « return » des méthodes de test.

Ainsi si un test contient des dépendances il échouera si on exécute les tests dans un process PHP différent pour chaque test avec la commande suivante :
php-seolan10-7.3 phpunit --process-isolation 



@group :

Exécuter qu’un seul groupe de test avec la commande suivante :

php-seolan10-7.3 phpunit fichier_du_test.php --group nom_du_groupe


exclure un groupe du test :
php-seolan10-7.3 phpunit fichier_du_test.php  --exclude-group nom_du_groupe

-les groupes de test sont définis par un commentaire en-dessus de la méthode de test  :

/**
* @group non_du_groupe
*/


! :
Si on exécute un groupe de test qui dépend de test qui ne sont pas dans ce groupe  le test reverra un échec.

Par exemple le groupe testDate dans DataSourceAndFieldsTests.php, si on lance que ce groupe de test cela renverra un échec, car il dépende de test qui ne sont pas dans ce groupe.
 
Ce groupe est a utilisé uniquement si on veut exclure les tests des dates avec la commande suivante :
 php-seolan10-7.3 phpunit DataSourceAndFieldsTests.php --exclude-group  testDate

dans DataSourceAndFieldsTests.php :
Il y a le groupe  2 qui peut être exécuté tout seul sans créer échec :
-le groupe main
-Et il y a le groupe testTraduction qui permet d’exécuter uniquement les 3  test sur les traductions.

//

