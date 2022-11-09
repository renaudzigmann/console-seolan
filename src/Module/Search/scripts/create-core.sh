#!/bin/bash
#creation d'un coeur avec la configuration console
echo $0 :
if [ -z "$1$2" ]
then
    echo "paramètres : path 2 solr, nom du coeur"
   exit 1
fi
scriptdir=$(dirname "$0")

echo " path2bin  : " $1
echo " core      : " $2

echo "======================"
echo "Création du coeur solr"
echo "======================"
   
$1/bin/solr create -c $2 -d $scriptdir/solr-default-conf

#à voir si c'est nécessaire
#$1/bin/solr restart

echo '

Ajouter un user dans security.json :
====================================
	sous credentials, "aliasdunouvelutilisateur":"mot de passe encrypté + sel"
	sous user-role : "aliasdunouvelutilisateur":["csx-user"]

Utiliser python3 encode-password.py pour calculer "mot de passe encrypté + sel"


'

