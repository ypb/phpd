<?php

include("lib/logging.php");

class Daemon {

  function __construct($pi, $port, $addy) {
	$this->pathi = $pi;
	$this->port = $port;
	$this->addy = $addy;
	$this->logos = new Logger($this->pathi['filename']);
	$this->clients = array();
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
	  pcntl_signal(SIGTERM, array(&$this, "sig_shutdown"));
	  $this->logos->debug("forked");
	  // TODO close fdes and change user... after bind?
	  sleep(1);
	}
  }

  function sig_shutdown($sig) {
	$this->logos->log("caught SIGNAL=" . $sig);
	$this->shutdown();
  }
  function shutdown() {
	$this->logos->log("shutting down");
	// clean clients? SURE
	$this->cleanclients();
	$this->net->stop();
	exit(0);
  }

  function listen() {
	if ($this->net->listen()) {
	  $this->logos->log("listening");
	} else {
	  // STRONGLY RECONSIDER, bah, doesn't matter anyway...
	  $this->shutdown();
	}
  }
  function accept() {
	for (;;) {
	  if ($con = $this->net->accept())
		$this->addClient($con);
	  // LOL (PHP 5 >= 5.3.0)
	  // pcntl_signal_dispatch();
	}
  }
  function addClient($con) {
	socket_getpeername($con, $addr, $port);
	$id = $addr . ":" . $port;
	array_push($this->clients, array('id' => $id, 'sock' => $con));
	$this->logos->log("accepted connection from " . $id);
  }
  function cleanclients() {
	foreach ($this->clients as $client) {
	  $tmp = $client['sock'];
	  if (isset($tmp)) {
		socket_shutdown($tmp);
		socket_close($tmp);
		$client['sock'] = NULL;
	  }
	}
  }
}
?>
