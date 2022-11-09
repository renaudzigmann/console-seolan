<?php

namespace Seolan\Library;

/**
 * Client Redis
 * configuration  $REDIS_HOST, $REDIS_PORT, $REDIS_PASSWORD, $REDIS_PREFIX, $REDIS_DATABASE
 */

use Seolan\Core\Logs;

class Redis extends \Predis\Client {

  protected static $client = null;
  protected static $online = null;

  public function __construct($options = []) {
    $defaults = [
      'prefix' => $GLOBALS['REDIS_PREFIX'],
      'parameters' => []
    ];
    if (!empty($GLOBALS['REDIS_PASSWORD'])) {
      $defaults['parameters']['password'] = $GLOBALS['REDIS_PASSWORD'];
    }
    if (isset($GLOBALS['REDIS_DATABASE'])) {
      $defaults['parameters']['database'] = $GLOBALS['REDIS_DATABASE'];
    }

    return parent::__construct([
        'scheme' => 'tcp',
        'host' => $GLOBALS['REDIS_HOST'] ?? '127.0.0.1',
        'port' => $GLOBALS['REDIS_PORT'] ?? '6379',
        ], array_merge_recursive($defaults, $options)
    );
  }

  static public function client($options = []) {
    if (self::$client === null) {
      try {
        self::$client = new Redis($options);
      } catch (\Exception $e) {
        Logs::critical(__METHOD__ . ' exception ' . $e->getMessage());
        self::$client = false;
        if (empty($options['exceptions'])) {
          throw $e;
        }
      }
    }
    return self::$client;
  }

  static public function isOnline($options = []) {
    if (isset(self::$online)) {
      return self::$online;
    }
    try {
      Redis::client($options)->ping();
    } catch (\Exception $e) {
      Logs::debug(__METHOD__ . ' connection exception ' . $e->getMessage());
      return self::$online = false;
    }
    return self::$online = true;
  }

}
