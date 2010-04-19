<?php

declare(ticks = 1);

$pi = pathinfo($_SERVER['argv'][0]);

// rather useless since as a daemon we'll be closing STDFDs
function evac($msg = "terminating", $status = 0) {
  $say = "PHPD evac at " . __FILE__ . "(" . __LINE__ . "): " . $msg . ".\n";
  if($status != 0) {
	fwrite(STDERR, $say);
  } else {
	fwrite(STDOUT, $say);
  }
  exit($status);
}

$ab = realpath($pi['dirname']);
if (!chdir($ab)) {
  evac("could not chdir into '" . $ab . "'", 1);
}

include("config.php");
include("lib/daemon.php");
include("lib/network.php");
include("lib/server.php");

$daemon = new Daemon($pi, $port, $addy);
$daemon->init();
$daemon->daemonize();
$daemon->listen();
$daemon->accept();

while ($conn = socket_accept($socket)) {
  socket_getpeername($conn, $addr, $port);
  // is it unique? theoretically should be...
  $id = $addr . ":" . $port;
  // check connection limit... prune if needed
  //  $connections[$id] = $conn;
  $client = server($conn, $id);
  if ($client === FALSE) {
	// clean up here, then
	socket_close($conn);
	$logos->log("failed accepting connection from " . $id . ": %m");
  } else {
	//queue client in an array
	$logos->log("accepted connection from " . $id);
  }
}

$logos->log("exiting(PID=". $globvars['PID'] .")");
exit(0);
?>
