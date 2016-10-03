<?php
namespace Skel;

class Uri implements Interfaces\Uri {
  protected $fragment;
  protected $host;
  protected $path;
  protected $port;
  protected $query = array();
  protected $scheme;

  public function __construct($uri=null) {
    if ($uri) {
      if (is_string($uri)) $this->parse($uri);
      //TODO: Implement clone constructor
      else throw new \RuntimeException('Unsupported object passed to Uri constructor');
    }
  }

  public function getFragment() { return $this->fragment; }

  public function getHost() { return $this->host; }

  public function getPath() { return $this->path; }

  public function getPort() { return $this->port; }

  public function getQueryArray(){
    return $this->query;
  }

  public function getQueryString() {
    return self::queryArrayToString($this->query);
  }

  public function getScheme() { return $this->scheme; }

  public function mergeIntoQuery(array $arrayToMerge) {
    $this->query = array_replace_recursive($this->query, $arrayToMerge);
    return $this;
  }

  public function parse(string $uri) {
    // Fragment
    if (strlen($uri) == 0) return;
    $parts = explode('#', $uri);
    if (count($parts) > 1) {
      $uri = array_shift($parts);
      $this->fragment = implode('#', $parts);
    } else {
      $this->fragment = '';
      $uri = $parts[0];
    }

    // Query
    if (strlen($uri) == 0) return;
    $parts = explode('?', $uri);
    if (count($parts) > 1) {
      $uri = array_shift($parts);
      $this->setQuery(implode('?', $parts));
    } else {
      $this->query = array();
      $uri = $parts[0];
    }

    // Scheme
    if (strlen($uri) == 0) return;
    $parts = explode('://',$uri);
    if (count($parts) > 1) {
      $this->scheme = $parts[0];
      array_shift($parts);
      $uri = implode('://', $parts);
    } else {
      $this->scheme = null;
      $uri = $parts[0];
    }

    // Path
    if (strlen($uri) == 0) return;
    $parts = explode('/', $uri);
    if (count($parts) > 1) {
      $uri = array_shift($parts);
      $parts = implode('/', $parts);

      if (substr($parts,0,1) != '/') $parts = '/'.$parts;
      $this->path = $parts;
    } else {
      $this->path = null;
      $uri = $parts[0];
    }
    
    // Host
    if (strlen($uri) == 0) return;
    $this->host = $uri;
  }

  public function removeFromQuery(array $arrayToRemove) {
    //TODO: Implement this
  }

  public function setFragment(string $frag) { $this->fragment = trim($frag, '#'); return $this; }

  public function setHost(string $host) { $this->host = trim($host, '/'); return $this; }

  public function setPath(string $path) { $this->path = $path; return $this; }

  public function setPort(int $port) { $this->port = $port; return $this; }

  public function setQuery($query) {
    if (is_array($query)) $this->query = $query;
    else $this->query = self::parseQueryString($query);
    return $this;
  }

  public function setScheme(string $scheme) { $this->scheme = $scheme; return $this; }

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

  public static function parseQueryString(string $str) {
    $q = array();
    $s = explode('&', $str);
    foreach ($s as $v) {
      $matches = array();
      $parts = explode('=', $v);
      $parts[0] = urldecode($parts[0]);
      $parts[1] = urldecode($parts[1]);
      if (!preg_match('/^([^\\[]+)(\\[.+\\])?$/', $parts[0], $matches)) throw new \RuntimeException('Query string passed to setQuery has produced an error.');
      if (count($matches) < 2) throw new \RuntimeException('The query string match regex didn\'t work!');

      if (isset($matches[2])) $keys = explode("][", trim($matches[2], '[]'));
      else $keys = array();

      array_unshift($keys, $matches[1]);
      self::__parseQueryString($q, $keys, $parts[1]);
    }
    return $q;
  }
  protected static function __parseQueryString(&$arr, $keys, $val) {
    if (count($keys) == 1) $arr[$keys[0]] = $val;
    else {
      $key = array_shift($keys);
      if (!isset($arr[$key])) $arr[$key] = array();
      self::__parseQueryString($arr[$key], $keys, $val);
    }
  }

  public static function queryArrayToString(array $queryArray, $keyTemplate=null) {
    $q = array();
    foreach ($queryArray as $k => $v) {
      if (is_array($v)) {
        if (!$keyTemplate) $next_template = $k;
        else $next_template = str_replace("##", $k, $keyTemplate);
        $next_template .= '[##]';
        $q[] = self::queryArrayToString($v, $next_template);
      } else {
        if ($keyTemplate) $key = str_replace("##", $k, $keyTemplate);
        else $key = $k;
        $q[] = urlencode($key).'='.urlencode($v);
      }
    }
    return implode('&', $q);
  }
}

?>
