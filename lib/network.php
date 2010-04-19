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
  function stop() {
	if ($this->socket != FALSE) {
	  // tell more about socket?
	  $this->logos->debug("closing main socket");
	  socket_shutdown($this->socket);
	  socket_close($this->socket);
	}
  }
}
?>
