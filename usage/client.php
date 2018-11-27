<?php

require_once('../SimpleHMACAuthClient.php');

$client = new SimpleHMACAuthClient('API_KEY', 'SECRET');

$client->host = 'localhost';
$client->port = 8000;
$client->ssl = false;

try {

  $data = array(
    'test' => true,
    'created' => microtime(true)
  );

  $query = array(
    'debug' => true
  );

  // Create a new item
  $client->call('POST', '/items/', $query, $data);

  // Get back list of items
  $items = $client->call('GET', '/items/');

  die('Success: <pre>' . json_encode($items, JSON_PRETTY_PRINT) . '</pre>');

} catch (AuthenticationException $e) {

  die('Failure: <pre>' . json_encode($e, JSON_PRETTY_PRINT) . '</pre>');
}

?>
