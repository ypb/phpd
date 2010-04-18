<?php
// evil global namespace...
$globvars = array();

$globvars['LIBDIR'] = dirname($_SERVER['argv'][0]);
$globvars['EXENAME'] = basename($_SERVER['argv'][0]);
$globvars['PID'] = posix_getpid();

// rather useless since as a daemon we'll be closing STDFDESs
function evac($msg = "terminating", $status = 0) {
  $say = "PHPD evac at " . __FILE__ . "(" . __LINE__ . "): " . $msg . ".\n";
  if($status != 0)
	fwrite(STDERR, $say);
  else
	fwrite(STDOUT, $say);
  exit($status);
}

//chdir to libexec, TODO form absolute with CWD?
if ($globvars['LIBDIR'] != ".") {
  if (!chdir($globvars['LIBDIR']))
	evac("could not chdir into " . $globvars['LIBDIR'], 1);
}

include("lib/logging.php");
include("lib/daemon.php");
include("lib/server.php");

$logos = new Logger($globvars['EXENAME']);
if (!$logos->init())
  evac("could not connect to syslogd", 1);

$logos->log("starting");
$logos->status();

$logos->log("forking");
//$demon = new Daemon($globvars['PID']);
daemonize(posix_getpid());

if (! ($socket = socket_create(AF_INET, SOCK_STREAM, 0)))
  socket_error();
// options? nonblock?
if (! socket_bind($socket, "0.0.0.0", 1234))
  socket_error();
if (! socket_listen($socket))
  socket_error();

//var $conn; //niet

pcntl_signal(SIGTERM, "shutdown");

$logos->log("listening");
$connections = array();
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
