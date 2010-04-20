<?php

include("lib/logging.php");
include("lib/utils.php");
include("lib/network.php");

class Daemon {

  function __construct($pi, $port, $addy) {
	$this->pathi = $pi;
	$this->port = $port;
	$this->addy = $addy;
	$this->logos = new Logger($this->pathi['filename']);
	$this->clientz = array();
   }

  function init() {
	if (! $this->logos->init())
	  evac("could not connect to syslogd", 1);
	$this->logos->log("starting");
	$this->status();
	$this->net = new Network($this->logos, $this->addy, $this->port);
  }

  function status() {
	$this->logos->debug("cwd=" . realpath($this->pathi['dirname']));
	$this->logos->debug("port=" . $this->port);
	$this->logos->debug("addy=" . $this->addy);
  }

  function daemonize() {
	$this->logos->debug("forking");
	$pid = pcntl_fork();
	if ($pid == -1) {
	  evac('could not fork', 1);
	} else if ($pid) {
	  exit(0);
	} else {
	  posix_setsid();
	  pcntl_signal(SIGTERM, array(&$this, "signal_handler"));
	  $this->logos->debug("forked");
	  // TODO close fdes and change user... after bind?
	  sleep(1);
	}
  }

  function signal_handler($sig) {
	$this->logos->log("caught SIGNAL=" . $sig);
	switch ($sig) {
	case SIGTERM:
	  $this->shutdown(0);
	  break;
	}
  }
  function shutdown($status) {
	$this->logos->log("shutting down");
	// clean clients? SURE and check return value if we should retry...
	$this->cleanClients();
	$this->net->stop();
	exit($status);
  }

  function listen() {
	if ($this->net->listen()) {
	  $this->logos->log("listening");
	} else {
	  // STRONGLY RECONSIDER, bah, doesn't matter anyway...
	  $this->shutdown(1);
	}
  }
  function accepting() {
	$clientsocks = create_function('$c', 'return $c->socket;');
	for (;;) {
	  if ($con = $this->net->accept())
		$this->addClient($con);
	  /* LOL (PHP 5 >= 5.3.0)
	     pcntl_signal_dispatch(); */
	  // TODO by analogy we should write removeClient()... counting each time ain't pretty...
	  if (count($this->clientz) > 0) {
		$socks = array_map($clientsocks, $this->clientz);
		if ($conarr = $this->net->ready_to_read($socks)) {
		  foreach ($conarr as $socket) {
			// wanted to use array_filter, would have to search O(NxM)
			// not sure what's less expensive
			$id = r_addy_port($socket);
			$client = $this->clientz[$id];
			// theoretically should have something tangible to read...
			if ($data = $this->net->read($socket)) {
			  if ($data != "") {
				$back = $client->process($data);
				// for now simply write back immediately
				$this->net->write($socket, $back);
				// where to error/length check... in the Client of course ;}
			  }
			} else {
			  // close down...
			  $this->logos->log("widing down client(" . $id . ") on: " . socket_strerror(socket_last_error($socket)));
			  if (destroy_connection($socket)) {
				unset($this->clientz[$id]);
				$this->logos->log("removed client(" . $id . ")");
			  } else {
				$this->logos->log("tearing down silent connection " . $id . "failed");
			  }
			}
		  }
		}
	  }
	}
  }
  function addClient($con) {
	if ($id = r_addy_port($con)) {
	  $this->clientz[$id] = new Client($id, $con);
	  $this->logos->log("accepted connection from " . $id);
	} else {
	  destroy_connection($con);
	  $this->logos->log("failed to get remote address of new connection");
	}
  }
  function cleanClients() {
	// TODO make it more gradual in time/phases? at least... making sure the pipes
	// are drained for sure will be more tricksy...
	$ret = TRUE;
	$failed = 0;
	foreach ($this->clientz as $id => $client) {
	  $tmp = $client->socket;
	  if (isset($tmp)) {
		if (destroy_connection($tmp)) {
		  unset($this->clientz[$id]);
		  $this->logos->debug("disconnected client(" . $id . ")");
		} else {
		  $ret = FALSE;
		  $failed += 1;
		  $this->logos->debug("failed disconnecting client(" . $id . ")");
		}
	  } else {
		$this->logos->debug("client(" . $id .") already socketless");
	  }
	}
	return $ret;
  }
}
?>
