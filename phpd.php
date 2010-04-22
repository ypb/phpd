<?php

declare(ticks = 1);

ini_set('mbstring.internal_encoding', "UTF-8");
//ini_set('mbstring.substitute_character', "none");
//ini_set('mbstring.language', "uni");
//ini_set('mbstring.detect_order', "auto");

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
include("lib/processing.php");

$phpd_conf['pathinfo'] = $pi;

$daemon = new Daemon($phpd_conf);
$daemon->daemonize();
$daemon->listen();
// loop
$daemon->accepting();

// shouldn't be reaching this place
evac("exiting(weirdly)", 0);
?>
