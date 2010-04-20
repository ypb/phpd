<?php

class Network {
  function __construct($logger, $addy, $port) {
	$this->logos = $logger;
	$this->addy = $addy;
	$this->port = $port;
	$this->socket = FALSE;
  }
  function listen() {
	// perhaps a short circuit with an OR...?
	if (! ($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
	  $this->logos->log("couldn't create socket: "  . socket_strerror(socket_last_error()));
	  return FALSE;
	}
	if (! socket_bind($sock, $this->addy, $this->port)) {
	  $this->logos->log("couldn't bind to socket: " . socket_strerror(socket_last_error($sock)));
	  return FALSE;
	}
	if (! socket_listen($sock)) {
	  $this->logos->log("failed to listen on socket: " . socket_strerror(socket_last_error($sock)));
	  return FALSE;
	}
	$this->socket = $sock;
	return TRUE;
  }
  function accept() {
	$read = array($this->socket);
	if (socket_select($read, $write = NULL, $excpt = NULL, 0, 1000)) {
	  return socket_accept($this->socket);
	} else {
	  return FALSE;
	}
  }
  function ready_to_read($sock_arr) {
	if (socket_select($sock_arr, $write = NULL, $excpt = NULL, 0, 10000)) {
	  return $sock_arr;
	} else {
	  return FALSE;
	}
  }
  // slightly redundant... but perhaps we'd like to change how we read...
  function read($socket) {
	return socket_read($socket, 1024, PHP_BINARY_READ);
  }
  function write($socket, $data) {
	$l = strlen($data);
	$wrote = socket_write($socket, $data);
	if ($wrote === FALSE) {
	  // need id?
	  $this->logos->debug("failed write: " . socket_strerror(socket_last_error($socket)));
	} else if ($wrote < $l) {
	  $this->logos->debug("short write (" . $wrote . "<" . $l . "): " . socket_strerror(socket_last_error($socket)));
	}
	return $wrote;
  }
  function stop() {
	$ret = TRUE;
	if ($this->socket != FALSE) {
	  // tell more about socket?
	  $this->logos->debug("closing main socket");
	  if (destroy_connection($this->socket)) {
		$this->logos->debug("main socket closed");
	  } else {
		$ret = FALSE;
		$this->logos->debug("failed to close main socket");
	  }
	}
	return $ret;
  }
}

class Client {
  function __construct($log, $id, $sock) {
	$this->id = $id;
	$this->clid = "client(" . $id . ")";
	$this->socket = $sock;
	$this->default_encoding = "UTF-8";
	// buffers...
	$this->in_buf = "";
	$this->continue = FALSE;
	$this->in_position = 0;
	$this->data = array();
	$this->out_buf = "";
	$this->out_position = 0; //unused
	// for now hacky logging...
	$this->logos = $log ;
  }
  function process_with_process($string) {
	$this->logos->debug($this->clid . " processing: " . $string);
	$this->in_buf .= $this->to_default_encoding($string);
	$this->logos->debug($this->clid . " in_buf=" . $this->in_buf);
	// TODO perhaps we should try to do more than MERELY convert?
	// there is a worse case scenario when a mb char will end up being split
	// between subsequent "packets"; mb_strcut has some heuristic for that...
	while ($found = $this->mb_gather_data()) {
	  $this->logos->debug($this->clid . " found=" . $found);
	  $this->data[] = $found;
	}
	foreach ($this->data as $key => $datum) {
	  $this->out_buf .= Process($datum);
	  unset($this->data[$key]);
	}
	if ($this->out_buf != "")
	  return($this->out_buf);
	else
	  return FALSE;
  }
  function confirm_write($len) {
	if ($len === FALSE)
	  return $len;
	if ($len < strlen($this->out_buf)) {
	  $this->out_buf = substr($this->out_buf, $len);
	  return FALSE;
	} else {
	  $this->out_buf = "";
	  return TRUE;
	}
  }
  function pending() {
	return $this->out_buf;
  }
  function mb_gather_data() {
	// == should be fine, too? still it runs once too often on simple \r \n etc...
	if ($this->in_buf === "")
	  return FALSE;
	$open = FALSE;
	$close = FALSE;
	if ($this->continue) {
	  $close = mb_strpos($this->in_buf, ">", $this->in_position, $this->default_encoding);
	  if ($close !== FALSE) {
		$open = $this->in_position;
		$len = mb_strlen($this->in_buf);
		$ret = mb_substr($this->in_buf, $open, ($close - $open)+1, $this->default_encoding);
		$this->in_buf = mb_substr($this->in_buf, $close + 1, $len - $close, $this->default_encoding);
		$this->continue = FALSE;
		$this->in_position = 0;
		return $ret;
	  }
	  return FALSE;
	} else {
	  $open = mb_strpos($this->in_buf, "<", 0, $this->default_encoding);
	  $this->logos->debug($this->clid . " in mb_gather_data() open=" . $open);
	  if ($open !== FALSE) {
		$close = mb_strpos($this->in_buf, ">", $open, $this->default_encoding);
		if ($close !== FALSE) {
		  $len = mb_strlen($this->in_buf);
		  $ret = mb_substr($this->in_buf, $open, ($close - $open)+1, $this->default_encoding);
		  $this->in_buf = mb_substr($this->in_buf, $close + 1, $len - $close, $this->default_encoding);
		  return $ret;
		}
		$this->continue = TRUE;
		$this->in_position = $open;
		return FALSE;
	  } else {
		// discard in_buf
		$this->in_buf = "";
		return FALSE;
	  }
	}
  }
  function to_default_encoding($x) {
	$from_encoding = mb_detect_encoding($x);
	$this->logos->debug($this->clid . " from_encoding=" . $from_encoding);
	if($from_encoding === $this->default_encoding){
	  return $x;
	} else {
	  //return utf8_encode($x);
	  if ($from_encoding !== FALSE) {
		return mb_convert_encoding($x, $this->default_encoding, $from_encoding);
		// TODO log conversion, also see ini_set at the top of phpd.php
	  } else {
		// TOFIX ... or rather ponder what to do MORE sensibly
		return mb_convert_encoding($x, $this->default_encoding, "auto");
		// if no $from_encoding is provided PHP uses 'mbinternal_encoding'
		// and since this should be $this->default_encoding we may as well return $x
	  }
	}
  }
}
?>
