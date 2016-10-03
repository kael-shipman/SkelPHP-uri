<?php

use PHPUnit\Framework\TestCase;

class UriTest extends TestCase {
  public function testQueryParsing() {
    $queryString = $this->getComplexQuery('string');
    $queryArray = \Skel\Uri::parseQueryString($queryString);

    $this->assertEquals($this->getComplexQuery('array'), $queryArray, 'Resulting query array doesn\'t match expected array', 0.0, 20, true);

    $resultString = \Skel\Uri::queryArrayToString($queryArray);
    $this->assertEquals($queryString, $resultString, 'Resulting query string doesn\'t match expected string');
  }

  public function testSetQueryFromString() {
    $uri = new \Skel\Uri();
    $queryString = $this->getComplexQuery('string');
    $uri->setQuery($queryString);
    $this->assertEquals($queryString, $uri->getQueryString(), 'The string representation of the parsed query string doesn\'t match the original');
  }

  public function testSetQueryFromArray() {
    $uri = new \Skel\Uri();
    $uri->setQuery($this->getComplexQuery('array'));
    $this->assertEquals($this->getComplexQuery('string'), $uri->getQueryString(), 'The string representation of the query array doesn\'t match the expected value');
  }

  public function testUsableDefaults() {
    $uri = new \Skel\Uri();
    $this->assertEquals('/', $uri->toString(), 'An empty Uri object should return a single `/` character as a default uri');
  }

  public function testCanConstructNormalUriFromString() {
    $uriString = $this->getStaticUri('string');
    $uri = new \Skel\Uri($uriString);
    $this->assertEquals($uriString, $uri->toString(), 'The resulting string representation of the URI doesn\'t match the expected string');
  }

  public function testCanConstructNormalUriFromParts() {
    $u = $this->getStaticUri('array');
    $uri = new \Skel\Uri();
    $uri
      ->setScheme($u['scheme'])
      ->setHost($u['host'])
      ->setPath($u['path'])
      ->setQuery($u['query'])
      ->setFragment($u['fragment'])
      ->setPort($u['port']);

    $this->assertEquals($this->getStaticUri('string'), $uri->toString(), 'The string representation of the constructed URI differs from the expected string');
  }

  public function testPartialUrisProduceExpectedResults() {
    $u = $this->getStaticUri('array');

    $uri = new \Skel\Uri();
    $uri->setScheme($u['scheme']);
    $this->assertEquals($u['scheme'].':///', $uri->toString(), 'When a URI has only a schema, a default path of `/` should be appended');

    $uri = new \Skel\Uri();
    $uri->setHost($u['host']);
    $this->assertEquals("$u[host]/", $uri->toString(), 'When a URI has only a host, a default path of `/` should be appended');

    $uri = new \Skel\Uri();
    $this->assertEquals('/', $uri->toString(), 'When a URI is empty, a default path of `/` should be returned');

    $uri = new \Skel\Uri();
    $uri->setPath($u['path']);
    $this->assertEquals($u['path'], $uri->toString(), 'When a URI has only a path, only that path should be returned');

    $uri = new \Skel\Uri();
    $uri->setQuery($u['query']);
    $this->assertEquals('?'.$this->getComplexQuery('string'), $uri->toString(), 'When a URI has only a query, only the query string preceded by a `?` should be returned');

    $uri = new \Skel\Uri();
    $uri->setFragment($u['fragment']);
    $this->assertEquals('#'.$u['fragment'], $uri->toString(), 'When a URI has only a fragment, only the fragment preceded by a `#` should be returned');

    $uri = new \Skel\Uri();
    $uri->setScheme($u['scheme']);
    $uri->setPath($u['path']);
    $uri->setPort($u['port']);
    try {
      $uri->toString();
      $this->fail('Trying to render a URI with port and without a host should throw an exception');
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
      throw $e;
    } catch (Exception $e) {
      // Arriving here is correct, so there's nothing to do
    }
  }







  // Utilities

  protected function getStaticUri($type) {
    $uriArray = array(
      'scheme' => 'https',
      'host' => 'beefchowmein.com',
      'port' => 8080,
      'path' => '/my/page',
      'query' => $this->getComplexQuery('array'),
      'fragment' => 'someFragment'
    );
    $uriString = 'https://beefchowmein.com:8080/my/page?'.$this->getComplexQuery('string').'#someFragment';
    if ($type == 'string') return $uriString;
    elseif ($type == 'array')  return $uriArray;
    elseif ($type == 'object') {
      $uri = new \Skel\Uri();
      $uri
        ->setScheme($uriArray['scheme'])
        ->setHost($uriArray['host'])
        ->setPath($uriArray['path'])
        ->setQuery($uriArray['query'])
        ->setFragment($uriArray['fragment'])
        ->setPort($uriArray['port']);
      return $uri;
    }
    else throw new RuntimeException('`$type` paramter must e either `string` or `array`');
  }

  protected function getComplexQuery($type) {
    if ($type == 'string') return 'pg=3&lang=en&encval=one+%26+another+%2B+complex+values+%3D+2&one=1&two%5Ba%5D=ey&two%5Bb%5D=bee&two%5Bc%5D%5Bi%5D=ay&two%5Bc%5D%5Bii%5D=ayay';
    elseif ($type == 'array') {
      return array(
        'pg' => '3',
        'lang' => 'en',
        'encval' => 'one & another + complex values = 2',
        'one' => '1',
        'two' => array(
          'a' => 'ey',
          'b' => 'bee',
          'c' => array(
            'i' => 'ay',
            'ii' => 'ayay'
          )
        )
      );
    } else throw new RuntimeException('`$type` parameter must be either `string` or `array`');
  }
}
