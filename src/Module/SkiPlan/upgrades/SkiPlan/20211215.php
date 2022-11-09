 <?php
function SkiPlan_comment_20211215() {
  return "Mise en place de la valeur par défaut du champ PUBLISH à 1 pour tous les modules Skiplan.";
}

function SkiPlan_20211215() {
  \Seolan\Module\SkiPlan\Wizard::updateSchema();
}
