<?php


namespace Seolan\Module\PushNotification;


interface PushProvider {
  
  public function addMessage(array $push) : void;
  
  public function addRecipientsToMessage(string $oid, string $lang, array $recipients) : void;
  
  public function sendAllMessages() : array;
  
  public function updatePushReceipt() : void;
}