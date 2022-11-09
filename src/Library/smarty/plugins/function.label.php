<?php
/**
 * Plugin smarty permettant de récupérer un label Séolan avec remplacement de
 * tokens et d'insérer automatiquement les labels n'existant pas.
 *
 * On peut par exemple écrire simplement dans le template :
 *   <%label t="Mon texte qui s'insère automatiquement dans la console" %>
 *
 * Ou en plus poussé :
 *   <%label v="nom_de_variable"
 *         [ t="Mon texte avec %token_1 et %token_2" ]  
 *         [ force=true ]
 *         [ escape="html|htmlall|url|urlpathinfo|quotes|hex|hexentity|javascript|mail" ]
 *         [ assign="smarty_variable" ]
 *         [ selector="global|mail|..." ]
 *         [ token_1="mon texte 1" ]
 *         [ token_2="mon texte 2" ]
 *         [ ... ] %>
 *
 * Ce qui insère dans le champ texte du label de la Console Séolan :
 *   "Mon texte avec %token_1 et %token_2"
 *
 * Et qui produit dans l'affichage :
 *   "Mon texte avec mon texte 1 et mon texte 2"
 *
 * @param array $parameters
 *   v        => (string) Nom de la variable
 *   t        => [optionnel] (string) Texte à insérer en cas de non existance du libellé
 *   force    => [optionnel] (boolean) Force l'insertion ou la mise à jour du libellé
 *   escape   => [optionnel] (string) (default:html) Permet d'échapper les caractères (voir Smarty: escape)
 *   assign   => [optionnel] (string) Permet d'assigner le label à une variable SMARTY réutilisable dans le template ultérieurement (voir Smarty: assign)
 *   selector => [optionnel] (string) (default:global) Sélecteur dans lequel se trouve le label
 *
 * @param \Seolan\Core\Template $smarty
 *   Objet Smarty
 *
 * @return string
 *   Label traduit correspondant à la variable demandée par le paramètre "v"
 */

use \Seolan\Core\Labels;

  // le $smarty est soit un smarty soit un iternal template, ne pas typer avec \Seolan\Core\Template 
function smarty_function_label(array $parameters, $smarty) {

  $t        = @$parameters['t'];
  $v        = @$parameters['v'];
  $force    = @$parameters['force'] === true;
  $escape   = @$parameters['escape'];
  $assign   = @$parameters['assign'];
  $selector = @$parameters['selector'] ?: 'global';

  try {

    // Vérifie les paramètres de la fonction
    if (empty($v) && empty($t)) {
      throw new \Exception("At least one of these parameters must be filled: v=variable or t=text");
    }

    // Permet de ne récupérer que les tokens sans les paramètres de la fonction
    $tokens = array_diff_key($parameters, array_fill_keys(['t', 'v', 'force', 'escape', 'assign', 'selector'], 1));

    // Si on demande un texte sans passer de variable
    if (empty($v)) {
      $label = Labels::getText($t, $selector, $tokens, $force);
    }

    // Cas standard de récupération via le tableau $labels.variable
    elseif ($selector == 'global' && $t == @$smarty->glob['labels'][$v]) {
      $label = Labels::applyTokens($t, $tokens);
    }

    // Si on demande à forcer la variable avec un texte
    elseif ($force && !empty($t)) {
      Labels::getInstance()->set_label($v, $t, $selector);
      $label = Labels::get($v, $selector, $tokens);
    }

    // Sinon c'est qu'on veut la variable
    else {
      try {
        $label = Labels::get($v, $selector, $tokens);
      } catch (\Exception $e) {
        // Si aucun texte n'est renseigné on ne peut vraiment rien afficher
        if (empty($t)) throw $e;
        // Sinon on insère le texte saisi
        Labels::getInstance()->set_label($v, $t, $selector);
        $label = Labels::applyTokens($t, $tokens);
      }
    }

  } catch (\Exception $e) {

    \Seolan\Core\Logs::critical('smarty_function_label', $e->getMessage());
    if (defined('TZR_LOG_LEVEL') && TZR_LOG_LEVEL == 'PEAR_LOG_DEBUG') {
      $label = $e->getMessage();
    }

  }

  // Retourne le texte échappé si demandé dans les paramètres
  if (!empty($escape)) {
    // en smartyIII les plugins sont optimisés ... voir les modifiercompiler
    // workaround  to improve ?
    require_once(SMARTY_PLUGINS_DIR . 'modifier.escape.php');
    return smarty_modifier_escape($label, $escape, \Seolan\Core\Lang::getCharset());
  }

  // Permet d'assigner le label à une variable smarty plutôt que de l'afficher
  if (!empty($assign)) {
    $smarty->assign($assign, $label);
    return;
  }

  return $label;
}
