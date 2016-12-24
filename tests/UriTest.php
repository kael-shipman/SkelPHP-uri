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

  public function testSpecialFileUriConstruction() {
    try {
      $uri = new \Skel\Uri('file:///');
      $this->assertEquals('file', $uri->getScheme());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('localhost', $uri->getHost());
      $this->assertFalse($uri->getPort());
    } catch (InvalidArgumentException $e) { $this->fail("File scheme is special and should accept the minimalist `file:///` constructor"); }
  }

  public function testMinimalFileUriConstruction() {
    try {
      $uri = new \Skel\Uri('file:');
      $this->assertEquals('file', $uri->getScheme());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('localhost', $uri->getHost());
      $this->assertFalse($uri->getPort());
    } catch (InvalidArgumentException $e) { $this->fail("File scheme is special and should accept the minimalist `file:///` constructor"); }
  }

  public function testNormalFileUriConstruction() {
    try {
      $uri = new \Skel\Uri('file://localhost/some/major/path');
      $this->assertEquals('file', $uri->getScheme());
      $this->assertEquals('localhost', $uri->getHost());
      $this->assertEquals('/some/major/path', $uri->getPath());
      $this->assertFalse($uri->getPort());
    } catch (InvalidArgumentException $e) { $this->fail("Normal file path should have parsed correctly"); }
  }

  public function testImpliedHostFileConstructor() {
    try {
      $uri = new \Skel\Uri('file:///some/major/path');
      $this->assertEquals('file', $uri->getScheme());
      $this->assertEquals('localhost', $uri->getHost());
      $this->assertFalse($uri->getPort());
      $this->assertEquals('/some/major/path', $uri->getPath());
    } catch (InvalidArgumentException $e) { $this->fail("Normal file path with implied host should have parsed correctly"); }
  }

  public function testJustSchemeAndHostWorks() {
    // Just scheme and host defaults to known port and path `/`, blank query and blank fragment
    try {
      $scheme = 'http';
      $host = 'example.com';
      $uri = new \Skel\Uri("$scheme://$host");
      $this->assertEquals($scheme, $uri->getScheme());
      $this->assertEquals($host, $uri->getHost());
      $this->assertEquals(80, $uri->getPort());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString());
      $this->assertEquals('', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("`$scheme://$host` should default to known http port 80 and default path `/`, but throws exception instead"); }
  }

  public function testUnknownSchemeWithValidHostAndPort() {
    // Unknown scheme with valid host and port should work. Defaults to path `/`, blank query, blank fragment
    try {
      $scheme = 'kael';
      $host = 'example.com';
      $port = 7338;
      $uri = new \Skel\Uri("$scheme://$host:$port");
      $this->assertEquals($scheme, $uri->getScheme());
      $this->assertEquals($host, $uri->getHost());
      $this->assertEquals($port, $uri->getPort());
      $this->assertEquals('/', $uri->getPath(), "Should default to path `/` if valid scheme, host and port are given");
      $this->assertEquals('', $uri->getQueryString());
      $this->assertEquals('', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should NOT throw exception if scheme, host and port are provided"); }
  }

  public function testUriWithNoSchemeButKnownPort() {
    // Valid Uri with no scheme but known port should work. 
    try {
      $host = 'example.com';
      $port = 80;
      $uri = new \Skel\Uri("//$host:$port");
      $this->assertEquals('http', $uri->getScheme());
      $this->assertEquals($host, $uri->getHost());
      $this->assertEquals($port, $uri->getPort());
      $this->assertEquals('/', $uri->getPath(), "Should default to path `/` if valid scheme, host and port are given");
      $this->assertEquals('', $uri->getQueryString());
      $this->assertEquals('', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should NOT throw exception on valid uri without scheme, but with known port"); }
  }

  public function testUriWithKnownSchemeHostAndQueryWithoutPath() {
    // Known scheme, host, query should default to path `/` and blank fragment 
    $u = $this->getStaticUri('array');
    try {
      $queryString = $this->getComplexQuery('string');
      $uri = new \Skel\Uri("$u[scheme]://$u[host]?$queryString");
      $this->assertEquals($u['scheme'], $uri->getScheme());
      $this->assertEquals($u['host'], $uri->getHost());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals($queryString, $uri->getQueryString());
      $this->assertEquals('', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should accept known scheme, host and query, defaulting to path `/` and blank fragment"); }
  }

  public function testUriWithSchemeHostFragmentButNoPath() {
    // Known scheme, host, fragment should default to path `/` and blank query
    $u = $this->getStaticUri('array');
    try {
      $uri = new \Skel\Uri("$u[scheme]://$u[host]#frag");
      $this->assertEquals($u['scheme'], $uri->getScheme());
      $this->assertEquals($u['host'], $uri->getHost());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString());
      $this->assertEquals('frag', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should accept known scheme, host and fragment, defaulting to path `/` and blank query"); }
  }

  public function testCompleteValidUriString() {
    // Complete uri string should be valid
    try {
      $uriString = $this->getStaticUri('string');
      $u = $this->getStaticUri('array');
      $uri = new \Skel\Uri($uriString);
      $this->assertEquals($u['scheme'], $uri->getScheme(), 'Constructor didn\'t parse scheme correctly');
      $this->assertEquals($u['host'], $uri->getHost(), 'Constructor didn\'t parse host correctly');
      $this->assertEquals($u['port'], $uri->getPort(), 'Constructor didn\'t parse port correctly');
      $this->assertEquals($u['path'], $uri->getPath(), 'Constructor didn\'t parse path correctly');
      $this->assertEquals($u['query'], $uri->getQueryArray(), 'Constructor didn\'t parse query correctly', 0.0, 20, true);
      $this->assertEquals($u['fragment'], $uri->getFragment(), 'Constructor didn\'t parse fragment correctly');
    } catch(InvalidArgumentException $e) { $this->fail("Should NOT throw exception on valid absolute URI"); }
  }

  public function testNullArgumentException() {
    // Null throws exception
    try {
      $uri = new \Skel\Uri();
      $this->fail("Should throw InvalidArgumentException when passing null");
    } catch (TypeError $e) { $this->assertTrue(true); }
  }

  public function testJustSchemeWithoutDefaultHostThrowsException() {
    // Just scheme throws exception
    try {
      $uri = new \Skel\Uri('http:');
      $this->fail("Should throw InvalidArgumentException when passing just a scheme");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }
  }

  public function testUnknownSchemeAndHostWithoutPort() {
    // Just unknown scheme and host throws exception because no port can be assumed
    try {
      $uri = new \Skel\Uri("blah://example.com");
      $this->fail("Unknown scheme without specified port should throw exception");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }
  }

  public function testNoSchemeThrowsException() {
    // Any valid uri without scheme and port throws exception
    try {
      $uri = new \Skel\Uri("//example.com/test/string?something=nothing");
      $this->fail("Should throw exception if no scheme provided");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }
  }

  public function testCopyConstructor() {
    $r = $this->getStaticUri('object');

    // Copy constructor
    try {
      $uri = new \Skel\Uri('', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPath(), $uri->getPath());
      $this->assertEquals($r->getQueryString(), $uri->getQueryString());
      $this->assertEquals($r->getFragment(), $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when cloning"); }
  }

  public function testChangingToKnownScheme() {
    $r = $this->getStaticUri('object');
    $ftpPort = \Skel\Uri::getPortForScheme('ftp');
    // Changing to known scheme
    try {
      $uri = new \Skel\Uri('ftp:', $r);
      $this->assertEquals('ftp', $uri->getScheme());
      $this->assertEquals($ftpPort, $uri->getPort(), "Should automatically change port when changing scheme to known scheme");
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPath(), $uri->getPath());
      $this->assertEquals($r->getQueryString(), $uri->getQueryString());
      $this->assertEquals($r->getFragment(), $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing to known scheme"); }
  }

  public function testChangingHosts() {
    $r = $this->getStaticUri('object');
    // Changing hosts
    try {
      $uri = new \Skel\Uri('//new.example.com', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals('new.example.com', $uri->getHost());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString());
      $this->assertEquals('', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing hosts"); }
  }

  public function testRelativePath() {
    $r = $this->getStaticUri('object');
    // Relative path
    try {
      $uri = new \Skel\Uri('me', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPath().'/me', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString(), "Changing path should blank the query string");
      $this->assertEquals('', $uri->getFragment(), "Changing path should blank the fragment");
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing paths"); }
  }

  public function testChangeToPreviousDir() {
    $r = $this->getStaticUri('object');
    // Dot path
    try {
      $uri = new \Skel\Uri('../', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals('/my', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString(), "Changing path should blank the query string");
      $this->assertEquals('', $uri->getFragment(), "Changing path should blank the fragment");
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing to earlier directory within path"); }
  }

  public function testChangeToSiblingDir() {
    $r = $this->getStaticUri('object');
    // Changing to sibling directory
    try {
      $uri = new \Skel\Uri('../new/path', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals('/my/new/path', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString(), "Changing path should blank the query string");
      $this->assertEquals('', $uri->getFragment(), "Changing path should blank the fragment");
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing to sibling directory"); }
  }

  public function testChangeToRootDir() {
    $r = $this->getStaticUri('object');
    // Changing to root
    try {
      $uri = new \Skel\Uri('/', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals('/', $uri->getPath());
      $this->assertEquals('', $uri->getQueryString(), "Changing path should blank the query string");
      $this->assertEquals('', $uri->getFragment(), "Changing path should blank the fragment");
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing to root"); }
  }

  public function testChangeQuery() {
    $r = $this->getStaticUri('object');
    // Changing query
    try {
      $uri = new \Skel\Uri('?something=new', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals($r->getPath(), $uri->getPath());
      $this->assertEquals('something=new', $uri->getQueryString());
      $this->assertEquals($r->getFragment(), $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing query string"); }
  }

  public function testChangeFragment() {
    $r = $this->getStaticUri('object');
    // Changing fragment
    try {
      $uri = new \Skel\Uri('#newFrag', $r);
      $this->assertEquals($r->getScheme(), $uri->getScheme());
      $this->assertEquals($r->getHost(), $uri->getHost());
      $this->assertEquals($r->getPort(), $uri->getPort());
      $this->assertEquals($r->getPath(), $uri->getPath());
      $this->assertEquals($r->getQueryString(), $uri->getQueryString());
      $this->assertEquals('newFrag', $uri->getFragment());
    } catch (InvalidArgumentException $e) { $this->fail("Should not throw exception when changing fragment"); }
  }

  public function testChangingBeyondRootThrowsException() {
    $r = $this->getStaticUri('object');
    // Changing beyond root 
    try {
      $uri = new \Skel\Uri('../../../', $r);
      $this->fail("Should throw exception when changing beyond root");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }
  }

  public function testChangingToUnknownSchemeThrowsException() {
    // Changing to unknown scheme
    try {
      $uri = new \Skel\Uri('abc:', $r);
      $this->fail("Should throw error when changing to unknown scheme because port cannot be inferred");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }
  }

  public function testSetQueryFromString() {
    $uri = $this->getStaticUri('object');
    $queryString = $this->getComplexQuery('string');
    $uri->setQuery($queryString);
    $this->assertEquals($queryString, $uri->getQueryString(), 'The string representation of the parsed query string doesn\'t match the original');
  }

  public function testSetQueryFromInvalidString() {
    $u = $this->getStaticUri('array');
    $u['query'] = '=notvalid&&&more=less&one=two&=3';
    $uri = new \Skel\Uri($u['scheme'].'://'.$u['host'].':'.$u['port'].$u['path'].'?'.$u['query'].'#'.$u['fragment']);
    $this->assertEquals(array('more'=>'less', 'one' => 'two'), $uri->getQueryArray(), 'Invalid elements in query string should have been thrown out', 0.0, 10, true);
  }

  public function testSetQueryFromArray() {
    $uri = $this->getStaticUri('object');
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

  public function testWellKnownPortFunctions() {
    $this->assertEquals(80, \Skel\Uri::getPortForScheme('http'));
    $this->assertEquals(80, \Skel\Uri::getPortForScheme('HtTp'));
    $this->assertEquals('http', \Skel\Uri::getSchemeForPort(80));
  }

  public function testSchemeAndPortChanges() {
    $a = $this->getStaticUri('array');
    $r = $this->getStaticUri('object');
    $ftpPort = \Skel\Uri::getPortForScheme('ftp');

    // When scheme changes to known scheme, port should change, too
    try {
      $this->assertNotEquals('ftp', $r->getScheme(), "The original Uri has the same scheme as the one we're testing! You should change this to get an accurate test.");
      $u = new \Skel\Uri('ftp:', $r);
      $this->assertEquals($ftpPort, $u->getPort());
    } catch (InvalidArgumentException $e) { $this->fail("Should successfully change port to $ftpPort when changing scheme to FTP"); }

    // When scheme changes to unknown scheme without port specified, should throw exception
    try {
      $u = new \Skel\Uri('abc:', $r);
      $this->fail("When scheme changes to unknown scheme without port specified, should throw exception");
    } catch (InvalidArgumentException $e) { $this->assertTrue(true); }

    // Should be able to change to unknown scheme if port also provided
    try {
      $u = new \Skel\Uri('abc://'.$r->getHost().':123', $r);
      $this->assertEquals('abc', $u->getScheme());
      $this->assertEquals(123, $u->getPort());
    } catch (InvalidArgumentException $e) { $this->fail("Should be able to change to unknown scheme if port also provided"); }

    // Can't test these because can't change port and scheme separately
    //
    // When port changes to known port, scheme should change, too
    // Should be able to change to unknown port regardless of scheme
    // Should be able to change to known port and force nonstandard scheme
  }

  public function testPrintRelativeStrings() {
    $r = $this->getStaticUri('object');

    // Should print full string when scheme changes
    $u = new \Skel\Uri('ftp:', $r);
    $this->assertEquals($u->toString(), $u->toRelativeString($r), "Should print full string when scheme changes");

    // Should print full string minus scheme when host changes
    $u = new \Skel\Uri('//new-example.com', $r);
    $this->assertEquals(substr($u->toString(), strlen($r->getScheme().':')), $u->toRelativeString($r), "Should print full string minus scheme when host changes");

    // Should print full string when port changes
    $u = new \Skel\Uri(':443', $r);
    $this->assertEquals($u->toString(), $u->toRelativeString($r), "Should print full string when port changes");

    // Should print path/query/fragment when path changes
    $u = new \Skel\Uri('/new/different/path', $r);
    $this->assertEquals('/new/different/path', $u->toRelativeString($r), "Should print path when path changes");

    // Should print query/fragment when just query changes
    $u = new \Skel\Uri('?new=query', $r);
    $this->assertEquals('?new=query#'.$r->getFragment(), $u->toRelativeString($r), "Should print query/fragment when query changes");

    // Should print fragment when just fragment changes
    $u = new \Skel\Uri('#newFrag', $r);
    $this->assertEquals('#newFrag', $u->toRelativeString($r), "Should print just fragment when just fragment changes");
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
    $fullEncodedUri = "http://example.com$encodedPath?$encodedQuery#$encodedFragment";
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
