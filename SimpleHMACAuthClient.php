<?php
/*
	Client.php
	Simple HMAC Auth
	Jesse T Youngblood
	11/27/18
*/

class SimpleHMACAuthClient {

  public $host = 'localhost';
  public $ssl = true;
  public $port = 443;

  public $algorithm = 'sha256';

  private $apiKey;
  private $secret;

  public function __construct($apiKey, $secret) {

    if (!isset($apiKey)) {
      throw new AuthenticationException('Missing \'apiKey\'.');
    }

    if (!isset($secret)) {
      // All requests will be sent unsigned.
    }

    $this->apiKey = $apiKey;
    $this->secret = $secret;
  }

  private function sign($secret = '', $algorithm, $method = 'GET', $uri = '/', $queryString = '', $headers = array(), $data = null) {

    $string = $this->stringForRequest($method, $uri, $queryString, $headers, $data);
    $signature = hash_hmac($algorithm, $string, $secret);

    //log($string);
    //log($signature);

    return $signature;
  }

  private function stringForRequest($method = 'GET', $uri = '/', $queryString = '', $headers = array(), $data = '') {

    $method = strtoupper($method);

    if ($data === null) {
      $data = '';
    }

    // Only sign these headers, no more
    $headersWhitelist = array(
      'authorization',
      'date',
      'content-length',
      'content-type'
    );

    // Create a new list of headers, with the keys all lower case. Do this before sorting them, to make sure we don't bork the sort.
    $newHeaders = array();

    foreach ($headers as $key => $value) {

      if (array_search($key, $headersWhitelist) === false) {
        continue;
      }

      if ($key === 'content-length' && $headers[$key] === '0') {
        return;
      }

      $newHeaders[strtolower($key)] = trim($headers[$key]);
    }

    // Sort the array by key
    ksort($newHeaders);

    $headerString = '';

    $count = 0;
    foreach ($newHeaders as $key => $value) {

      $headerString .= $key . ':' . trim($value);

      $count++;

      if ($count !== count($newHeaders)) {
        $headerString .= "\n";
      }
    }

    if ($data === null) {
      $data = '';
    }

    $dataHash =  hash('sha256', $data);

    /*
      The string format is:
        method + \n
        URL + \n
        Alphabetically sorted query string with individually escaped keys and values + \n
        Alphabetically sorted headers with lower case keys each on their own line
        sha256 hash of data, or blank string if data is not included
    */

    $string = '';

    $string .= $method . "\n";
    $string .= $uri . "\n";
    $string .= $queryString . "\n";
    $string .= $headerString . "\n";
    $string .= $dataHash;

    return $string;
  }

  public function call($method, $uri, $query = null, $body = null) {

    $path = $uri;

    $input = null;

    if ($body !== null) {

      try	{
        $input = json_encode($body);
      } catch (Exception $e) {
        throw new AuthenticationException('Could not serialize input data: ' . $e->getMessage());
      }

      if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {

        throw new AuthenticationException('Could not serialize input data: ' . json_last_error_msg());
      }
    }

    $queryString = '';

    if ($query !== null) {

      $count = 0;

      foreach ($query as $key => $value) {

        if ($count !== 0) {
          $queryString .= '&';
        }

        try	{
          $queryString .= rawurlencode($key) . '=' . rawurlencode(json_encode($value)); 
        } catch (Exception $e) {
          throw new AuthenticationException('Could not serialize input data: ' . $e->getMessage());
        }

        if (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE) {

          throw new AuthenticationException('Could not serialize parameter: ' . $key . ': ' . json_last_error_msg());
        }

        $count++;
      }
    }

    $headers = array(
      'date' => gmdate('D, d M Y H:i:s T'),
      'authorization' => 'api-key ' . $this->apiKey
    );

    if ($input !== null) {
      $headers['content-type'] = 'application/json';
      $headers['content-length'] =  strlen($input); 
    }

    if ($this->secret) {

      $signature = $this->sign($this->secret, $this->algorithm, $method, $path, $queryString, $headers, $input);

      $headers['signature'] = 'simple-hmac-auth ' . $this->algorithm . ' ' . $signature;
    }

    $http = 'https';

    if ($this->ssl === false) {
      $http = 'http';
    }

    $url =  $http . '://' . $this->host . ':' . $this->port . $path;

    if ($queryString !== '') {
      $url .= '?' . $queryString;
    }

    $headersArray = array();

    foreach ($headers as $key => $value) {
      array_push($headersArray, $key . ':' .  $value);
    }

    $ch = curl_init();

    if ($input !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7.5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headersArray);

    //log('Making request: ' . $method . ' ' . $url . ' Headers: "' . json_encode($headersArray) . '" and data: "' . $input . '"');

    $data = curl_exec($ch);

    $errorNumber = curl_errno($ch); 
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error || $errorNumber) {

      $message = 'Error while communicating with server';

      if ($error) {
        $message .= ': ' . $error;
      }

      if ($errorNumber) {
        $message .= ' (' . $errorNumber . ')';
      }

      throw new AuthenticationException($message);
    }

    // Parse the response

    $error = null;
    $code = null;
    $object = null;

    try	{
      $object = json_decode($data, true);
    } catch (Exception $e) {
      throw new AuthenticationException('Error interpreting server response: ' . $e->getMessage() . ':  "' . $data . '"');
    }

    if (function_exists('json_last_error') && json_last_error()) {

      $error = 'Error interpreting server response: ' . json_last_error_msg() . ':  "' . $data . '"';

    } else if (isset($object['error'])) {

      if (isset($object['error']['message'])) {
        $error = $object['error']['message'];
      }

      if (isset($object['error']['code'])) {
        $code = $object['error']['code'];
      }

    }

    if ($httpCode !== 200 && $error) {
      throw new AuthenticationException($error, $code);
    } else if ($httpCode !== 200) {
      throw new AuthenticationException('An error has occured', $httpCode);
    }

    return $object;
  }
}

// Exception codes can be strings as well as numbers
class AuthenticationException extends Exception {

  public $code = null;
  public $message = '';

  public function __construct($message, $code = null) {
    parent::__construct($message);

    if ($code !== null) {
      $this->code = $code;
    }
  }
}

?>
