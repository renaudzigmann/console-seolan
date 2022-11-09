<?php
namespace Seolan\Core\Directory;
/**
 * user management methods which depend on directory
 * -> password
 * -> account update
 */
interface UserDirectoryInterface{
  /**
   * Process request and return help message for the user
   * and / or other informations 
   */
  public function prepareNewPassword($login=null, $oid=null, $wich):array;
  /**
   * Process new password registration
   */
  public function procNewPassword($ar=null):array;
  /**
   * list of fields (USERS table) which editable
   */
  public function getAccountFieldssec():?array;
  /**
   * Password input configuration
   */
  public function prepareNewPasswordInput(string $useroid, string $which, ?array $options=null):array;
  /**
   * hook for user account update
   */
  //  public function userEdit($oid);
  /**
   * hook for user password modification
   */
  //public function passwordChange($oid, $password);
}