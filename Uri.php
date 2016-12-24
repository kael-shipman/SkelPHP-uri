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
  protected static $regexMap = array('generic' => '/^(?:([^:\/?#]+):)?(?:\/\/([^\/?:#]*))?(?::(\d+))?(\/?[^?#]*)(?:\?([^#]*))?(?:#(.*))?/');
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
    $uri = static::getPartsFromString($uri, $r);

    if ($r) $this->setRelativeParts($uri, $r);
    else $this->setAbsoluteParts($uri);
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

  /**
   * Function for parsing uris
   *
   * Note: This does not create a Uri object, nor set any properties. It is used internally by the
   * constructor to get the available parts of the uri. It is INCOMPLETE right now -- see notes below
   *
   */
  public static function getPartsFromString(string $uri, $r = null) {
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
      if (strlen($parts[0]) > 0 && !preg_match('/^([^\\[]+)(\\[.+\\])?$/', $parts[0], $matches)) { throw new \RuntimeException('Query string passed to setQuery has produced an error.'); }
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

  protected function setAbsoluteParts(array $uriParts) {
    // If there's no scheme, we can't go forward
    if (!$uriParts['scheme']) {
      if (!$uriParts['port'] || ($uriParts['scheme'] = array_search($uriParts['port'], static::$wellKnownPorts)) === false) throw new \InvalidArgumentException("You must specify a scheme for your uri, specify a port with a known scheme, or pass another valid uri object as a relative reference.");
      // If we make it past this, $uriParts['scheme'] has been set by a port lookup in $wellKnownPorts
    }

    // If there is a scheme, it's been explicitly set
    $this->explicitlySet['scheme'] = true;

    // If there's no port, we can only go forward if we're using a scheme with a known port
    if (!$uriParts['port']) {
      if (!isset(static::$wellKnownPorts[$uriParts['scheme']])) throw new \InvalidArgumentException("You must specify either a port, a scheme with a known port, or another valid uri as a relative reference from which to derive a port.");
      else $uriParts['port'] = static::$wellKnownPorts[$uriParts['scheme']];
    } else {
      if (!(int)$uriParts['port']) throw new \InvalidArgumentException("Port must be an integer");
      $this->explicitlySet['port'] = true;
    }

    // If there's no host, we can only go foward if we're using a scheme with an implied host ("file")
    if (!$uriParts['host']) {
      if (!isset(static::$wellKnownHosts[$uriParts['scheme']])) throw new \InvalidArgumentException("You must specify a host for a scheme with no known implied hosts (`$uriParts[scheme]`). Note that currently `file` is the only scheme with an implied host (`localhost`).");
      else $uriParts['host'] = static::$wellKnownHosts[$uriParts['scheme']];
    } else {
      $this->explicitlySet['host'] = true;
    }

    // If there's no path, default to root path
    if (!$uriParts['path']) $uriParts['path'] = '/';

    $this->scheme = urldecode($uriParts['scheme']);
    $this->host = urldecode($uriParts['host']);
    $this->port = $uriParts['port'];
    if ($this->port !== false) $this->port = (int)$this->port;
    $this->path = urldecode($uriParts['path']);
    $this->setQuery($uriParts['query']);
    $this->fragment = urldecode($uriParts['fragment']);
  }

  public function setFragment(string $frag) { $this->fragment = trim($frag, '#'); return $this; }

  public function setQuery($query) {
    if (is_array($query)) $this->query = $query;
    else $this->query = self::parseQueryString($query ?: '');
    return $this;
  }

  protected function setRelativeParts(array $uriParts, Interfaces\Uri $r) {
    // If we don't have a scheme, copy it over
    if (!$uriParts['scheme']) {
      $this->scheme = $r->getScheme();
      if (!$uriParts['port']) $uriParts['port'] = $r->getPort();
    }

    // If we do, make sure we also have a port
    else {
      $this->scheme = urldecode($uriParts['scheme']);
      if (!$uriParts['port']) {
        if (isset(static::$wellKnownPorts[$uriParts['scheme']])) $uriParts['port'] = static::$wellKnownPorts[$uriParts['scheme']];
        else throw new \InvalidArgumentException("Can't change to an unknown scheme without specifying the port");
      }
    }

    // Set port
    $this->port = ($uriParts['port'] ?: $r->getPort());
    if ($this->port !== false) $this->port = (int)$this->port;

    // If we get a new host, wipe everthing else clean
    if ($uriParts['host'] != '') {
      $this->host = urldecode($uriParts['host']);
      $this->path = urldecode(($uriParts['path'] == '' ? '/' : $uriParts['path']));
      $this->setQuery($uriParts['query'] ?: '');
      $this->fragment = urldecode($uriParts['fragment'] ?: '');
    } else {
      $this->host = $r->getHost();
      if ($uriParts['path']) {
        $this->path = urldecode($uriParts['path']);
        $this->setQuery('');
        $this->fragment = '';
      } else {
        $this->path = $r->getPath();
        $this->setQuery($uriParts['query'] ?: $r->getQueryArray());
        $this->fragment = urldecode($uriParts['fragment'] ?: $r->getFragment());
      }
    }

    // Resolve relative paths
    // TODO: Address unpredictible vulnerability: If someone passes in an encoded slash in a path, it will screw this up
    if (substr($this->path, 0, 1) != '/') {
      $relPath = explode('/', $this->path);
      $resPath = explode('/', $r->getPath());

      while (count($relPath)) {
        // If it's a dot path...
        if (current($relPath) == '.' || current($relPath) == '') array_shift($relPath);
        elseif (current($relPath) == '..') {
          array_pop($resPath);

          // The first element in the Resolved Path is blank because a resolved path always starts with the delimiter (/)
          if (count($resPath) == 1) throw new \InvalidArgumentException("Relative path has attempted to move beyond the root of the host");
          array_shift($relPath);
        } else {
          $resPath[] = array_shift($relPath);
        }
      }

      $this->path = implode('/', $resPath);
    }


    if (array_search($this->host, static::$wellKnownHosts) != $this->scheme) $this->explicitlySet['host'] = true;
    if (array_search($this->port, static::$wellKnownPorts) != $this->scheme) $this->explicitlySet['port'] = true;
    if (isset(static::$wellKnownPorts[$this->scheme]) && static::$wellKnownPorts[$this->scheme] != $this->port) $this->explicitlySet['scheme'] = true;
  }

  public function setWellKnownPort(string $scheme, int $port) {
    static::$wellKnownPorts[$scheme] = $port;
  }

  public function toRelativeString(Uri $r) {
    if ($this->scheme != $r->getScheme() || $this->port != $r->getPort()) return $this->toString();
    if ($this->host != $r->getHost()) return $this->toString('host', 'port', 'path', 'query', 'fragment');
    if ($this->path != $r->getPath()) return $this->toString('path', 'query', 'fragment');
    if ($this->getQueryString() != $r->getQueryString()) return $this->toString('query', 'fragment');
    if ($this->fragment != $r->getFragment()) return $this->toString('fragment');
    return '';
  }

  public function toString() {
    $a = func_get_args();
    $getAll = !count($a);
    $str = '';

    // Scheme
    if ($getAll || array_search('scheme', $a) !== false) $str .= $this->scheme.':';

    // Host
    if ($getAll || array_search('host', $a) !== false) {
      if (!isset(static::$wellKnownHosts[$this->scheme]) || $this->host != static::$wellKnownHosts[$this->scheme]) $str .= '//'.$this->host;
    }

    // Port
    if ($getAll || array_search('port', $a) !== false) {
      if ($this->explicitlySet['port'] || !isset(static::$wellKnownPorts[$this->scheme])) $str .= ':'.$this->port;
    }

    // Path
    if ($getAll || array_search('path', $a) !== false) $str .= $this->path;

    // Query
    if ($getAll || array_search('query', $a) !== false) {
      if (count($this->query) > 0) $str .= '?'.$this->getQueryString();
    }

    // Fragment
    if ($getAll || array_search('fragment', $a) !== false) {
      if ($this->fragment) $str .= '#'.$this->fragment;
    }

    return $str;
  }

  public function updateQueryValues(array $arrayToMerge) {
    $result = array_replace_recursive($this->query, $arrayToMerge);
    $this->query = $this->eliminateNullValues($result);
    return $this;
  }
}

?>
