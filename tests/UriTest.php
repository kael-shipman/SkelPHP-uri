<?php

use PHPUnit\Framework\TestCase;

class UriTest extends TestCase {
  public function testQueryParsing() {
    $queryString = $this->getComplexQuery('string');
    $queryArray = \Skel\Uri::parseQueryString($queryString);

    $this->assertEquals($this->getComplexQuery('array'), $queryArray, 'Resulting query array doesn\'t match expected array', 0.0, 20, true);

    $resultString = \Skel\Uri::arrayToQueryString($queryArray);
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

  public function testSetFragment() {
    $u = $this->getStaticUri('array');
    $uri = new \Skel\Uri($this->getStaticUri('string'));
    $this->assertEquals($u['fragment'], $uri->getFragment(), 'The fragment portion of the Uri doesn\'t appear to have parsed correctly');

    $uri->setFragment('newFragment');
    $this->assertEquals('newFragment', $uri->getFragment(), 'Set fragment didn\'t work :(');
  }

  public function testUsableDefaults() {
    $uri = new \Skel\Uri();
    $this->assertEquals('/', $uri->toString(), 'An empty Uri object should return a single `/` character as a default uri');
  }

  public function testCanConstructNormalUriFromString() {
    $uriString = $this->getStaticUri('string');
    $u = $this->getStaticUri('array');
    $uri = new \Skel\Uri($uriString);
    $this->assertEquals($u['scheme'], $uri->getScheme(), 'Constructor didn\'t parse scheme correctly');
    $this->assertEquals($u['host'], $uri->getHost(), 'Constructor didn\'t parse host correctly');
    $this->assertEquals($u['port'], $uri->getPort(), 'Constructor didn\'t parse port correctly');
    $this->assertEquals($u['path'], $uri->getPath(), 'Constructor didn\'t parse path correctly');
    $this->assertEquals($u['query'], $uri->getQueryArray(), 'Constructor didn\'t parse query correctly', 0.0, 20, true);
    $this->assertEquals($u['fragment'], $uri->getFragment(), 'Constructor didn\'t parse fragment correctly');
  }

  public function testPartialUrisProduceExpectedResults() {
    $u = $this->getStaticUri('array');

    $uri = new \Skel\Uri($u['scheme'].'://');
    $this->assertEquals($u['scheme'].':///', $uri->toString(), 'When a URI has only a schema, a default path of `/` should be appended');

    $uri = new \Skel\Uri($u['host']);
    $this->assertEquals("$u[host]/", $uri->toString(), 'When a URI has only a host, a default path of `/` should be appended');

    $uri = new \Skel\Uri();
    $this->assertEquals('/', $uri->toString(), 'When a URI is empty, a default path of `/` should be returned');

    $uri = new \Skel\Uri($u['path']);
    $this->assertEquals($u['path'], $uri->toString(), 'When a URI has only a path, only that path should be returned');

    $uri = new \Skel\Uri('?'.$this->getComplexQuery('string'));
    $this->assertEquals('?'.$this->getComplexQuery('string'), $uri->toString(), 'When a URI has only a query, only the query string preceded by a `?` should be returned');

    $uri = new \Skel\Uri('#'.$u['fragment']);
    $this->assertEquals('#'.$u['fragment'], $uri->toString(), 'When a URI has only a fragment, only the fragment preceded by a `#` should be returned');

    $uri = new \Skel\Uri($u['scheme'].'://'.':'.$u['port'].$u['path']);
    try {
      $str = $uri->toString();
      $this->fail('Trying to render a URI with port and without a host should throw an exception. Output string was \''.$str.'\'');
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
      throw $e;
    } catch (Exception $e) {
      // Arriving here is correct, so there's nothing to do
    }
  }

  public function testRemoveNestedQueryVar() {
    $uri = $this->getStaticUri('object');
    $uri->updateQueryValues(array('two' => array('c' => array('ii' => null))));

    $expectedResult = $this->getStaticUri('array');
    $expectedResult = $expectedResult['query'];
    unset($expectedResult['two']['c']['ii']);

    $this->assertEquals($expectedResult, $uri->getQueryArray(), 'Passing a null value into updateQueryValues should delete that value', 0.0, 20, true);
  }

  public function testOverrideNestedQueryVar() {
    $uri = $this->getStaticUri('object');
    $uri->updateQueryValues(array('two' => array('c' => array('ii' => 'II'))));

    $expectedResult = $this->getStaticUri('array');
    $expectedResult = $expectedResult['query'];
    $expectedResult['two']['c']['ii'] = 'II';

    $this->assertEquals($expectedResult, $uri->getQueryArray(), 'Passing a nested value into updateQueryValues should override the current value', 0.0, 20, true);
  }

  public function testComplexOverrideNestedQueryVar() {
    $uri = $this->getStaticUri('object');
    $uri->updateQueryValues(array(
      'encval' => 'some new value',
      'one' => '2',
      'two' => array(
        'b' => 'BEE',
        'c' => null
      )
    ));

    $expectedResult = $this->getStaticUri('array');
    $expectedResult = $expectedResult['query'];
    $expectedResult['encval'] = 'some new value';
    $expectedResult['one'] = '2';
    $expectedResult['two']['b'] = 'BEE';
    unset($expectedResult['two']['c']);

    $this->assertEquals($expectedResult, $uri->getQueryArray(), 'Resulting array is different from the expected after attempt to merge new values', 0.0, 20, true);
  }

  public function testRemoveFromQuery() {
    $uri = $this->getStaticUri('object');
    $uri->removeFromQuery(array(
      'encval' => 'cha',
      'two' => array(
        'b' => 'something',
        'c' => 'nothing'
      )
    ));

    $expectedResult = $this->getStaticUri('array');
    $expectedResult = $expectedResult['query'];
    unset($expectedResult['encval'], $expectedResult['two']['b'], $expectedResult['two']['c']);

    $this->assertEquals($expectedResult, $uri->getQueryArray(), 'Resulting array is different from the expected after attempted deletion of values', 0.0, 20, true);
  }

  public function testDecodesUriPartsCorrectly() {
    $encodedPath = '/this/path%20is/a%20%2Bcomplex%2B%20%26%20very%20special/path';
    $encodedQuery = '%2Bq=a%20very%20%2Bspecial%2B%20query';
    $encodedFragment = '%2Bspecial%2B%20fragment';
    $fullEncodedUri = "$encodedPath?$encodedQuery#$encodedFragment";
    $uri = new \Skel\Uri($fullEncodedUri);
    $this->assertEquals(urldecode($encodedPath), $uri->getPath(), 'Path was not decoded correctly :(');
    $this->assertTrue(isset($uri->getQueryArray()['+q']), 'Special characters in query keys aren\'t decoded correctly');
    $this->assertEquals('a very +special+ query', $uri->getQueryArray()['+q'], 'Query values aren\'t decoded correctly');
    $this->assertEquals('+special+ fragment', $uri->getFragment(), 'Fragment isn\'t decoded correctly');
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
    elseif ($type == 'object') return new \Skel\Uri($uriString);
    else throw new RuntimeException('`$type` paramter must be `string`, `array`, or `object`');
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
