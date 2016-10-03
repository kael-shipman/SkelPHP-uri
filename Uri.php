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
    function array_to_query_string($arr, $key_template=null) {
      $q = array();
      foreach ($arr as $k => $v) {
        if (is_array($v)) {
          if (!$key_template) $next_template = urlencode($k);
          else $next_template = str_replace("##", urlencode($k), $key_template);
          $next_template .= '[##]';
          $q[] = array_to_query_string($v, $next_template);
        } else {
          if ($key_template) $key = str_replace("##", urlencode($k), $key_template);
          else $key = urlencode($k);
          $q[] = $key.'='.urlencode($v);
        }
      }
      return implode('&', $q);
    }

    return array_to_query_string($this->query);
  }

  public function getScheme() { return $this->scheme; }

  public function mergeIntoQuery(array $arrayToMerge) {
    $this->query = array_replace_recursive($this->query, $arrayToMerge);
    return $this;
  }

  public function parse(string $uri) {
    //TODO: Fix relative uri handling (e.g., passing '../app/content' doesn't work)
    $this->path = '/';

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
      $this->path = '/';
      $uri = $parts[0];
    }
    
    // Host
    if (strlen($uri) == 0) return;
    $this->host = $uri;
  }

  public function removeFromQuery(array $arrayToRemove) {
    //TODO: Implement this
  }

  public function setHost(string $host) { $this->host = trim($host, '/'); return $this; }

  public function setPath(string $path) {
    if (substr($path, 0, 1) != '/') $path = "/$path";
    $this->path = $path;
    return $this;
  }

  public function setPort(int $port) { $this->port = $port; return $this; }

  public function setQuery($query) {
    function populate_array_recursive(&$arr, $keys, $val) {
      if (count($keys) == 1) $arr[$keys[0]] = $val;
      else {
        $arr[$keys[0]] = array();
        array_shift($keys);
        populate_array_recursive($arr[$keys[0]], $keys, $val);
      }
    }

    if (is_array($query)) $this->query = $query;
    else {
      $q = array();
      $s = explode('&', $query);
      foreach ($s as $v) {
        $keys = array();
        $parts = explode('=', $v);
        if (!preg_match('/^([^\\[]+)(?:\\[([^\\]]+)\\])*$/', $parts[0], $keys)) throw new RuntimeException('Query string passed to setQuery has produced an error.');
        if (count($keys) < 2) throw new RuntimeException('The query string match regex didn\'t work!');
        array_shift($keys);
        populate_array_recursive($q, $keys, $parts[1]);
      }
    }

    $this->query = $q;
    return $this;
  }

  public function setScheme(string $scheme) { $this->scheme = $scheme; return $this; }

  public function toString() {
    return $this->schema.'://'.$this->host.$this->path.(count($this->query) ? '?'.$this->getQueryString() : '').($this->fragment ? '#'.$this->fragment : '');
  }
}

?>
