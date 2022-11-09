#!/bin/bash
cd ~/csx

if [ -f "/var/tzr/noscheduler" ];then
echo "$HOSTNAME:$HOME: aucune tâche de fond acceptée (fichier noscheduler)";
fi

myhead=`git rev-parse --abbrev-ref HEAD`

depot=`git remote get-url origin|grep depot`

if [ ! -n $depot ]
then
    echo "depot=" `git remote get-url origin|grep depot`
    echo "Le depot 'origin' doit être https://depot.seolan.com/console-seolan.git"
    echo "utiliser les commandes suivantes :"
    echo "	git remote remove origin"
    echo "	git remote add origin https://depot.seolan.com/console-seolan.git"
    exit 1
fi

git fetch -q origin $myhead

# recherche de l'état du repository local
mydiffs=`git status -uall --show-stash -s | sed 's/ *$//g'`

# s'il y a des diffs des stashs ou autre
if [ -n "$mydiffs" ]
then
    echo "$HOSTNAME:$HOME: modifs en attente de validation sur branche $myhead" | tee ~/../var/status/auto-upgrade-status
    echo $mydiffs
    exit 1
fi

# si on est en avance sur la branche
branchahead=`git status -b | grep ahead`
if [ -n "$branchahead" ]
then
    echo "$HOSTNAME:$HOME: branche avec des patchs spécifiques non remontés" | tee ~/../var/status/auto-upgrade-status
    exit 1
fi

# la branche locale et la branche distante ont divergé
divergedbranch=`git status -b | grep diverged`
if [ -n "$divergedbranch" ]
then
    echo "$HOSTNAME:$HOME: les branches ont divergé" | tee ~/../var/status/auto-upgrade-status
    exit 1
fi

mydifftohead=`git diff ..origin/$myhead | sed 's/ *$//g'`
if [ -n "$mydifftohead" ]
then
    echo "$HOSTNAME:$HOME: tentative mise à jour"
    upgrade=`git merge --ff-only -q 2>&1 | grep fatal`
    if [ -n "$upgrade" ]
    then
	echo "$HOSTNAME:$HOME: mise à jour impossible"  | tee ~/../var/status/auto-upgrade-status
    else
	cd scripts/cli
	php-seolan10-7.4 upgrades.php
	echo "$HOSTNAME:$HOME: mise à jour réussie"
	echo "OK" > ~/../var/status/auto-upgrade-status
    fi
else
    echo "OK" > ~/../var/status/auto-upgrade-status
fi
