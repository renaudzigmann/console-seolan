<?php

namespace Seolan\Module\CRM;

/**
 * Interface des sources CRM
 */
interface CRMSourceInterface {

  /**
   * liste des champs à consolider
   * @return \Seolan\Core\Field\Field[]
   */
  public function getCRMFields();

  /**
   * liste des emails dont l'enregistrement a été modifié depuis $since
   * @param string $since datetime
   * @return string[]
   */
  public function getCRMEmails($since = TZR_DATETIME_EMPTY);

  /**
   * infos sur le contact
   * @param string $email
   * @return array|null contenant :
   * les champs à consolider (Nom => valeur),
   * les sources (Link ou Link[]),
   * les attributs boolean Marketting, Commercial, Technic si pertienet,
   * une liste de tags (string[]) si besoin
   */
  public function getCRMContactInfos($email);

}
