<?php

require_once('../SimpleHMACAuthClient.php');

class SampleClient extends SimpleHMACAuthClient {

  public function __construct($apiKey, $secret) {
    parent::__construct($apiKey, $secret);

    // Define the host / port / SSL of your service in the constructor
    $this->host = 'localhost';
    $this->port = 8000;
    $this->ssl = false;
  }

  public function create($input = array()) {

    return $this->call('POST', '/items/', null, $input);
  }

  public function query($parameters = array()) {

    return $this->call('GET', '/items/', $parameters);
  }

  public function detail($id = null) {

    if ($id === null) {
      throw new AuthenticationException('Missing \'id\' parameter');
    }

    return $this->call('GET', '/items/' . rawurlencode($id));
  }

  public function update($id = null, $input = array()) {

    if ($id === null) {
      throw new AuthenticationException('Missing \'id\' parameter');
    }

    return $this->call('POST', '/items/' . rawurlencode($id), null, $input);
  }

  public function delete($id = null) {

    if ($id === null) {
      throw new AuthenticationException('Missing \'id\' parameter');
    }

    return $this->call('DELETE', '/items/' . rawurlencode($id));
  }
}


$client = new SampleClient('API_KEY', 'SECRET');

try {
  $items = $client->query();

  die('Success: <pre>' . json_encode($items, JSON_PRETTY_PRINT) . '</pre>');

} catch (AuthenticationException $e) {

  die('Failure: <pre>' . json_encode($e, JSON_PRETTY_PRINT) . '</pre>');
}

?>
