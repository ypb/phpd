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

$daemon = new Daemon($pi, $port, $addy);
$daemon->init();
$daemon->daemonize();
$daemon->listen();
// loop
$daemon->accepting();

// shouldn't be reaching this place
evac("exiting(weirdly)", 0);
?>
