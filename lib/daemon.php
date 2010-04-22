<?php

include("lib/logging.php");
include("lib/utils.php");
include("lib/network.php");

class Daemon {

  function __construct($conf) {
	$this->pathi = $conf['pathinfo'];
	$this->port = $conf['port'];
	$this->addy = $conf['addy'];
	//$this->config = $conf;
	$this->logdir = $conf['logdir'];
	$this->logopts = $conf['logopts'];
	$this->clientz = array();
   }

  function init() {
	$this->logos = new Logger($this->pathi['filename'], $this->logdir, $this->logopts);
	if (! $this->logos->init())
	  evac("failed to init logging subsystem", 1);
	$this->logos->log("starting");
	$this->status();
	$this->net = new Network($this->logos, $this->addy, $this->port);
	pcntl_signal(SIGTERM, array(&$this, "signal_handler"));
  }

  function status() {
	$this->logos->debug("cwd=" . realpath($this->pathi['dirname']));
	$this->logos->debug("port=" . $this->port);
	$this->logos->debug("addy=" . $this->addy);
	$this->logos->debug("logdir=" . $this->logdir);
	$this->logos->debug("logopts=" . print_r($this->logopts, TRUE));
  }

  function daemonize() {
	fork();
	$this->init();
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
	  $this->logos->log("failed to settle into listening state");
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
			// theoretically should have something tangible to read...
			if ($data = $this->net->read($socket)) {
			  if ($data != "") {
				// wanted to use array_filter, would have to search O(NxM)
				// not sure what's less expensive
				$id = r_addy_port($socket);
				// if we were able to read smth we should have been able to get remote addy, but...
				if (is_string($id)) {
				  $client = $this->clientz[$id];
				  if ($back = $client->process_with_process($data)) {
					// for now simply write back immediately
					$wrote = $this->net->write($socket, $back);
					while (! $client->confirm_write($wrote)) {
					  // sleep(1); some...
					  $wrote = $this->net->write($socket, $client->pending());
					}
					// where to error/length check... in the Client of course ;}
				  }
				} else {
				  //this is VERY BAD! not debug level BAD but critical level BAD
				  $this->logos->debug("got data but don't know who to tell: " . socket_strerror($id));
				}
			  }
			} else {
			  // ERR... clean up... close down...
			  $err = socket_last_error($socket);
			  $client = $this->findClient($socket);
			  // looks like we need O(NxM) search after all.
			  if ($client === FALSE) {
				//like this shouldn't ever happen
				$this->logos->debug("AWOL client! Send the MPs!");
				destroy_connection($socket);
			  } else {
				$cid = $client->id;
				$this->logos->debug("widing down client(" . $cid . ") on: " . socket_strerror($err));
				$err = destroy_connection($socket);
				if ($err === TRUE) {
				  $this->logos->debug("removed client(" . $cid . ") connection");
				} else {
				  $this->logos->debug("tearing down connection " . $cid . "faulted with: " . socket_strerror($err));
				}
				//would we want to keep client after all? this calls for serious investigative work...
				unset($this->clientz[$cid]);
			  }
			}
		  }
		}
	  }
	}
  }
  function addClient($con) {
	$id = r_addy_port($con);
	if (is_string($id)) {
	  $this->clientz[$id] = new Client($this->logos, $id, $con);
	  $this->logos->debug("accepted connection from " . $id);
	} else {
	  destroy_connection($con);
	  $this->logos->debug("failed to get remote address of new connection: " . socket_strerror($id));
	}
  }
  function findClient($con) {
	foreach ($this->clientz as $id => $client) {
	  if ($client->socket === $con)
		return $client;
	}
	return FALSE;
  }
  function cleanClients() {
	// TODO make it more gradual in time/phases? at least... making sure the pipes
	// are drained for sure will be more tricksy...
	$ret = TRUE;
	$failed = 0;
	foreach ($this->clientz as $id => $client) {
	  $tmp = $client->socket;
	  if (isset($tmp)) {
		$err = destroy_connection($tmp);
		if ($err === TRUE) {
		  $this->logos->debug("disconnected client(" . $id . ")");
		} else {
		  $ret = FALSE;
		  $failed += 1;
		  $this->logos->debug("client(" . $id . ") disconnection faulted with:" . socket_strerror($err));
		}
		// remove anyway
		unset($this->clientz[$id]);
	  } else {
		// TOFIX this is dead code since we unset whole client not his socket...
		$this->logos->debug("client(" . $id .") already socketless");
	  }
	}
	return $ret;
  }
}
?>
