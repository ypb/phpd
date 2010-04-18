<?php

class Logger {

  function __construct($name = "PHPD") {
	$this->ident = $name;
	$this->syslog_active = FALSE;
  }

  function init() {
	if (!openlog($this->ident, LOG_CONS | LOG_PID, LOG_DAEMON))
	  return(FALSE);
	return($this->syslog_active = TRUE);
  }

  function __destructor() {
	if ($this->syslog_active) {
	  $this->debug("closing syslog");
	  closelog();
	}
  }

  function info($msg) {
	$this->log($msg);
  }
  //getting complicatting(sic!)
  function log($msg, $level = LOG_INFO) {
	if ($this->syslog_active)
	  syslog($level, $msg);
	else
	  $this->stdout($msg);
  }

  function debug($msg) {
	$this->log($msg, LOG_DEBUG);
  }

  function stdout() {
	$args = func_get_args();

	print($this->ident . ":");
	foreach($args as $arg) {
	  print(" ");
	  print($arg);
	}
	print(".\n");
  }
  // aaa PFE!
  function status() {
	global $globvars;
	foreach($globvars as $key => $val) {
	  $this->log($key . "=" . $val);
	}
  }
}
?>