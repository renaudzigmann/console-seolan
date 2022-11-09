#!/bin/bash
if [ -z "$1" ]
then
    echo "IP ? "
    exit 1
fi

ip=$1

if [ -z "$2$3$4" ]
then
    alias="solr-"$HOSTNAME
    host=$HOSTNAME
else
    alias=$2
    host=$3
    keypass=$4
fi
if [ -z "$alias" ]
then
    echo  "no alias"
    exit 1
fi
if [ -z "$host" ]
then
    echo "no host"
    exit 1
fi
if [ -z "$keypass" ]
then
    echo "no keypass"
    exit 1
fi

echo init ssl $alias $host
    
keytool -genkeypair -alias $alias  -keyalg RSA -keysize 2048 -keypass $keypass -storepass $keypass -validity 9999 -keystore solr-ssl.keystore.p12 -storetype PKCS12 -ext SAN=DNS:localhost,IP:$ip -dname "CN=localhost, OU=$host, O=xsalto, L=3804, ST=france, C=france"


echo convert
openssl pkcs12 -in solr-ssl.keystore.p12 -out solr-ssl.pem

