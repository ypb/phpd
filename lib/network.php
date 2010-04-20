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
  function __construct($id, $sock) {
	$this->id = $id;
	$this->socket = $sock;
  }
  function process($string) {
	return($string);
  }
}
?>
