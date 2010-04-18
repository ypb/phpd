<?php

function exit_on_fork($signo) {
  global $logos;
  $logos->log("fork parent exiting with signal " . $signo);
  evac("parent of the fork exiting with signal " . $signo);
}

function shutdown($sig) {
  global $logos, $conn;
  $logos->log("shutting down on SIG=" . $sig);
  socket_shutdown($conn);
  socket_close($conn);
  exit(0);
}

/* class Daemon { */

/*   function __constructor($pid) { */
/* 	$this->dpid = $pid; */
/*   } */

  function daemonize($ppid) {
	global $logos;
	$pid = pcntl_fork();
	if ($pid == -1) {
	  evac('could not fork', 1);
	} else if ($pid) {
	  // we are the parent
	  exit_on_fork(-1); // not exit status
	  pcntl_signal(SIGTERM, "exit_on_fork");
	  $wait = 0;
	  //	  pcntl_wait($stat); //Protect against Zombie children
	  while($wait < 21) {
		sleep(1);
		$logos->log("waited " . $wait);
		$wait += 1;
	  }
	  $logos->log("waiting done(". $stat . "), exiting");
	  exit(0);
	} else {
	  // we are the child
	  $logos->log("forked from " . $ppid);
	  $globvars['PID'] = posix_getpid();
	  $logos->status();
	  sleep(1);
	  posix_setsid();
	  // TODO close fdes and change user... after bind?
	  sleep(1);
	  //	  posix_kill($ppid, SIGTERM);
	  //	  sleep(6);
	}
  }
/* } */
?>
