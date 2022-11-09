<?php
namespace Seolan\Core;
/**
 * traitement du rewriting dans la console
 */
interface IRewriting {
    /**
     * decodage de l'url quand on arrive avec une url de la forme
     * /toto.html ou toto est un alias, par exemple
     * @note voir \Seolan\Core\Shell::decodeRewriting
     * @return void
     */
    public function decodeRewriting($url);
    /**
     * encodage d'une url d'une url dynamique vers une url statique
     * @note voir \Seolan\Core\Shell::encodeRewriting
     * @return void
     */
    public function encodeRewriting(&$html);
}