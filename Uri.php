<?php
namespace Skel;

/**
 * A URI class largely derived from the Java Uri class
 *
 * Note that this is not meant to be a comprehensive implementation, and there are known
 * cases in which it will not produce the correct results. These are left unresolved for now
 * because the application in which this class is used does not require this functionality.
 * Parsing and string output, for example, will fail for schemes that do not typically have
 * `://` as their separator (like `mailto:`). Also, cases in which certain parts of the URI
 * are set and others are not may not produce usable results.
 *
 * In short, don't blindly rely on this class. It's small enough to read all the way through,
 * so if you need something non-standard, do read through it.
 *
 * Any functions that don't have documentation here in this file should be documented in
 * Skel\Interfaces, the header file that defines the Uri interface.
 *
 * @author Kael Shipman <kael.shipman@gmail.com>
 * @license GPL3
 */

class Uri implements Interfaces\Uri {
  protected $fragment;
  protected $host;
  protected $path;
  protected $port;
  protected $query = array();
  protected $scheme;
  protected static $regexMap = array('generic' => '/^(?:([^:\/?#]+):)?(?:\/\/([^\/?:#]*))?(?::(\d+))?([^?#]*)(?:\?([^#]*))?(?:#(.*))?/');
  protected static $wellKnownPorts = array('file' => false, 'ftp' => 21, 'ssh' => 22, 'telnet' => 23, 'time' => 37, 'dns' => 53, 'http' => 80, 'pop3' => 110, 'ldap' => 389, 'https' => 443, 'dhcp' => 547);
  protected static $wellKnownHosts = array('file' => 'localhost');
  protected static $explicitlySet = array('scheme' => false, 'host' => false, 'port' => false);

  /**
   * Internal method used to flatten a query array into a uri query string
   *
   * While this method is technically intended to be internal, it is made public in recognition
   * of the fact that it will surely be useful outside the direct context of this Uri class.
   *
   * @param array $queryArray  The source array
   * @param string|null $keyTemplate  An internal argument used to pass on a growing uri key. This
   * takes the form of `first-key[sub-key][sub-sub-key][sub-sub-sub-key]`, for example.
   * @return string  The complete, url-encoded uri string representation of the input array
   */
  public static function arrayToQueryString(array $queryArray, $keyTemplate=null) {
    $q = array();
    foreach ($queryArray as $k => $v) {
      if (is_array($v)) {
        if (!$keyTemplate) $next_template = $k;
        else $next_template = str_replace("##", $k, $keyTemplate);
        $next_template .= '[##]';
        $q[] = self::arrayToQueryString($v, $next_template);
      } else {
        if ($keyTemplate) $key = str_replace("##", $k, $keyTemplate);
        else $key = $k;
        $q[] = urlencode($key).'='.urlencode($v);
      }
    }
    return implode('&', $q);
  }

  public function __construct(string $uri, Interfaces\Uri $r=null) {
    $uri = static::parse($uri, $r);

    // If we've got a reference point...
    if ($r) {

      // If we don't have a scheme, copy it over
      if ($uri['scheme'] == '') $this->scheme = $r->getScheme();

      // If we do, make sure we also have a port
      else {
        $this->scheme = $uri['scheme'];
        if ($uri['port'] == '') {
          if (isset(static::$wellKnownPorts[$uri['scheme']])) $uri['port'] = static::$wellKnownPorts[$uri['scheme']];
          else throw new \InvalidArgumentException("Can't change to an unknown scheme without specifying the port");
        }
      }

      // If we get a new host, wipe everthing else clean
      if ($uri['host'] != '') {
        $this->host = $uri['host'];
        $this->path = ($uri['path'] == '' ? '/' : $uri['path']);
        $this->setQuery($uri['query'] ?: '');
        $this->fragment = $uri['fragment'] ?: '';
      } else {
        $this->host = $r->getHost();
        $this->path = $uri['path'] ?: $r->getPath();
        $this->setQuery($uri['query'] ?: $r->getQueryArray());
        $this->fragment = $uri['fragment'] ?: $r->getFragment();
      }

      // Set port
      $this->port = (int)($uri['port'] ?: $r->getPort());

      if (array_search($this->host, static::$wellKnownHosts) != $this->scheme) $this->explicitlySet['host'] = true;
      if (array_search($this->port, static::$wellKnownPorts) != $this->scheme) $this->explicitlySet['port'] = true;
      if (isset(static::$wellKnownPorts[$this->scheme]) && static::$wellKnownPorts[$this->scheme] != $this->port) $this->explicitlySet['scheme'] = true;

    // If we're parsing an absolute URI....
    } else {
      // If there's no scheme, we can't go forward
      if (!$uri['scheme']) {
        if (!$uri['port'] || ($uri['scheme'] = array_search($uri['port'], static::$wellKnownPorts)) === false) throw new \InvalidArgumentException("You must specify a scheme for your uri, specify a port with a known scheme, or pass another valid uri object as a relative reference.");
        // If we make it past this, $uri['scheme'] has been set by a port lookup in $wellKnownPorts
      }

      // If there is a scheme, it's been explicitly set
      $this->explicitlySet['scheme'] = true;

      // If there's no port, we can only go forward if we're using a scheme with a known port
      if (!$uri['port']) {
        if (!isset(static::$wellKnownPorts[$uri['scheme']])) throw new \InvalidArgumentException("You must specify either a port, a scheme with a known port, or another valid uri as a relative reference from which to derive a port.");
        else $uri['port'] = static::$wellKnownPorts[$uri['scheme']];
      } else {
        $this->explicitlySet['port'] = true;
      }

      // If there's no host, we can only go foward if we're using a scheme with an implied host ("file")
      if (!$uri['host']) {
        if (!isset(static::$wellKnownHosts[$uri['scheme']])) throw new \InvalidArgumentException("You must specify a host for a scheme with no known implied hosts (`$uri[scheme]`). Note that currently `file` is the only scheme with an implied host (`localhost`).");
        else $uri['host'] = static::$wellKnownHosts[$uri['scheme']];
      } else {
        $this->explicitlySet['host'] = true;
      }

      // If there's no path, default to root path
      if (!$uri['path']) $uri['path'] = '/';

      $this->scheme = $uri['scheme'];
      $this->host = $uri['host'];
      $this->port = $uri['port'];
      $this->path = $uri['path'];
      $this->setQuery($uri['query']);
      $this->fragment = $uri['fragment'];
    }
  }

  /**
   * Internal function for eliminating null values in the query array
   *
   * @param array $arr  The array in which to eliminate null values
   * @return array
   * @internal
   */
  protected function eliminateNullValues(array $arr) {
    $result = array();
    foreach ($arr as $k => $v) {
      if (is_array($v)) {
        $r = $this->eliminateNullValues($v);
        if (count($r) > 0) $result[$k] = $r;
      } else {
        if ($v !== null) $result[$k] = $v;
      }
    }
    return $result;
  }

  public function getFragment() { return $this->fragment; }
  public function getHost() { return $this->host; }
  public function getPath() { return $this->path; }
  public function getPort() { return $this->port; }

  public static function getPortForScheme(string $scheme) {
    $scheme = strtolower($scheme);
    if (!isset(static::$wellKnownPorts[$scheme])) return null;
    else return static::$wellKnownPorts[$scheme];
  }

  public function getQueryArray(){ return $this->query; }
  public function getQueryString() { return self::arrayToQueryString($this->query); }
  public function getScheme() { return $this->scheme; }

  public static function getSchemeForPort(int $port) {
    if (($scheme = array_search($port, static::$wellKnownPorts)) === false) return null;
    else return $scheme;
  }

  /**
   * Function for parsing uris
   *
   * Note: This does not create a Uri object, nor set any properties. It is used internally by the
   * constructor to get the available parts of the uri. It is INCOMPLETE right now -- see notes below
   *
   */
  public static function parse(string $uri, $r = null) {
    $regex = false;
    if (preg_match('/^([^:\/?#]+)/', $uri, $scheme)) {
      if (isset(static::$regexMap[$scheme[1]])) $regex = static::$regexMap[$scheme[1]];
    } elseif ($r) {
      if (isset(static::$regexMap[$r->getScheme()])) $regex = static::$regexMap[$r->getScheme()];
    }

    // NOTE: This is not actually a regex for a vanilla URI; it is a regex for a URL, the difference
    // being that an optional port may follow the host part.
    if (!$regex) $regex = static::$regexMap['generic'];

    if (preg_match($regex, $uri, $matches)) {
      //TODO: Make this so that it can actually handle various different URI parsers with differing results
      return array(
        'scheme' => $matches[1],
        'host' => $matches[2],
        'port' => $matches[3],
        'path' => $matches[4],
        'query' => $matches[5],
        'fragment' => $matches[6],
      );
    } else {
      throw new \InvalidArgumentException("Can't parse the uri `$uri`");
    }
  }

  /**
   * Parses a complex query string into an array, decoding values along the way.
   *
   * This is public only because it's a utility function that may be useful outside
   * of the context of this class.
   *
   * @param string $str  The query string to parse
   * @return array
   */
  public static function parseQueryString(string $str) {
    $q = array();

    if ($str == '') return $q;

    $s = explode('&', $str);
    foreach ($s as $v) {
      $matches = array();
      $parts = explode('=', $v);
      $parts[0] = urldecode($parts[0]);
      $parts[1] = urldecode($parts[1]);
      if (!preg_match('/^([^\\[]+)(\\[.+\\])?$/', $parts[0], $matches)) { throw new \RuntimeException('Query string passed to setQuery has produced an error.'); }
      if (count($matches) < 2) throw new \RuntimeException('The query string match regex didn\'t work!');

      if (isset($matches[2])) $keys = explode("][", trim($matches[2], '[]'));
      else $keys = array();

      array_unshift($keys, $matches[1]);
      self::__parseQueryString($q, $keys, $parts[1]);
    }
    return $q;
  }

  /**
   * Internal implementation of parseQueryString, used to facilitate recursion
   * after processing the initial string
   *
   * @param array $arr  The target array, passed by reference
   * @param array $keys  A single-dimensional array of keys, as parsed from something like
   * `first-key[sub-key][sub-sub-key][sub-sub-sub-key]`
   * @param string $val  The value to assign when the structure is built
   * @return void  (This function modifies the target array directly, so doesn't return a value)
   */
  protected static function __parseQueryString(&$arr, $keys, $val) {
    if (count($keys) == 1) $arr[$keys[0]] = $val;
    else {
      $key = array_shift($keys);
      if (!isset($arr[$key])) $arr[$key] = array();
      self::__parseQueryString($arr[$key], $keys, $val);
    }
  }

  /**
   * This is simply a functional front-end for updateQueryValues. All it does is set its parameter's
   * values to null and pass it along to `updateQueryValues`.
   *
   * @inheritDoc
   */
  public function removeFromQuery(array $arrayToRemove) {
    $setValuesToNull = function($arr) use (&$setValuesToNull) {
      foreach($arr as $k => $v) {
        if (is_array($v)) $arr[$k] = $setValuesToNull($arr[$k]);
        else $arr[$k] = null;
      }
      return $arr;
    };
    $arrayToRemove = $setValuesToNull($arrayToRemove);
    $this->updateQueryValues($arrayToRemove);
  }

  public function setFragment(string $frag) { $this->fragment = trim($frag, '#'); return $this; }

  public function setQuery($query) {
    if (is_array($query)) $this->query = $query;
    else $this->query = self::parseQueryString($query ?: '');
    return $this;
  }

  public function setWellKnownPort(string $scheme, int $port) {
    static::$wellKnownPorts[$scheme] = $port;
  }

  public function toRelativeString(Uri $r) {
    return $this->toString();
  }

  public function toString() {
    $str = '';
    // Add scheme, if present
    if ($this->scheme) $str .= $this->scheme.'://';

    // Add host, if present
    if ($this->host) {
      $str .= $this->host;

      // Add port if both host and port are present
      if ($this->port) $str .= ':'.$this->port;
    } elseif ($this->port) {
      throw new \RuntimeException('You can\'t render a URI with a port unless it also has a host');
    }

    // Add path if present
    if ($this->path) $str .= $this->path;

    // If path not present, add default '/' if either scheme or host is present or if nothing else is present
    elseif ($this->scheme || $this->host || (!$this->scheme && !$this->host && !$this->port && count($this->query) == 0 && !$this->fragment)) $str .= '/';

    // Add query if present
    if (count($this->query) > 0) $str .= '?'.$this->getQueryString();

    // Add fragment if present
    if ($this->fragment) $str .= '#'.$this->fragment;
    return $str;
  }

  public function updateQueryValues(array $arrayToMerge) {
    $result = array_replace_recursive($this->query, $arrayToMerge);
    $this->query = $this->eliminateNullValues($result);
    return $this;
  }
}

?>
