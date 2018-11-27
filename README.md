# simple-hmac-auth-php
PHP library for interfacing with APIs that implement HMAC signatures. Designed for use with a JSON API that implements [simple-hmac-auth](https://github.com/jessety/simple-hmac-auth).

### Usage

Instantiate a client object and point it at your service

```php

require_once('SimpleHMACAuthClient.php');

$client = new SimpleHMACAuthClient('API_KEY', 'SECRET');

$client->host = 'localhost';
$client->port = 8000;
$client->ssl = false;

```

GET request

```php
try {
  
  $items = $client->call('GET', '/items/');

  die('Success: <pre>' . json_encode($items, JSON_PRETTY_PRINT) . '</pre>');

} catch (AuthenticationException $e) {

  die('Failure: <pre>' . json_encode($e, JSON_PRETTY_PRINT) . '</pre>');
}
```

POST request with body data and query string

```php
try {

  $query = array(
    'debug' => true
  );

  $data = array(
    'test' => true,
    'created' => microtime(true)
  );

  // Create a new item
  $client->call('POST', '/items/', $query, $data);
  
  die('Success!');

} catch (AuthenticationException $e) {

  die('Failure: <pre>' . json_encode($e, JSON_PRETTY_PRINT) . '</pre>');
}
```

### Client Subclass

To write a client for your service, simply extend the class and add functions that match your API routes.

```php
class SampleClient extends SimpleHMACAuthClient {

  public function __construct($apiKey, $secret) {
    parent::__construct($apiKey, $secret);

    // Define the host / port / SSL of your service in the constructor
    $this->host = 'localhost';
    $this->port = 8000;
    $this->ssl = false;
  }

  public function create($data = array()) {

    return $this->call('POST', '/items/', null, $data);
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

  public function update($id = null, $data = array()) {

    if ($id === null) {
      throw new AuthenticationException('Missing \'id\' parameter');
    }

    return $this->call('POST', '/items/' . rawurlencode($id), null, $data);
  }

  public function delete($id = null) {

    if ($id === null) {
      throw new AuthenticationException('Missing \'id\' parameter');
    }

    return $this->call('DELETE', '/items/' . rawurlencode($id));
  }
}
```

Because this client's constructor specified the host, port, and SSL status of the service, it can be instantiated without any parameters beyond `apiKey` and `secret`. 

```php
$client = new SampleClient('API_KEY', 'SECRET'); 

try {

  $parameters = array(
    'debug' => true,
    'limit' => 42
  );

  $items = $client->query($parameters);

  die('Success: <pre>' . json_encode($items, JSON_PRETTY_PRINT) . '</pre>');

} catch (AuthenticationException $e) {

  die('Failure: <pre>' . json_encode($e, JSON_PRETTY_PRINT) . '</pre>');
}
```
