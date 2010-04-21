<?php

class Logger {

  function __construct($name = "PHPD", $logdir) {
	$this->ident = $name;
	$this->logdir = $logdir;
	$this->syslog_active = FALSE;
	$this->pid = posix_getpid();
  }

  function init() {
	if (! openlog($this->ident, LOG_CONS | LOG_PID, LOG_DAEMON))
	  return(FALSE);
	$logfile = $this->logdir . "/datalog";
	if (! ($datalogf = fopen($logfile, "ab"))) {
	  return(FALSE);
	} else {
	  chmod($logfile, 0640);
	  $this->datalogf=$datalogf;
	}
	return($this->syslog_active = TRUE);
  }

  function __destruct() {
	if (isset($this->datalogf)) {
	  $this->debug("closing datalog");
	  fclose($this->datalogf);
	}
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

  function datalog($msg) {
	if (isset($this->datalogf)) {
	  $time = date("Ymd-His");
	  // want smth like perhaps quoted_printable_encode on $msg but it's (PHP 5 >= 5.3.0)
	  fwrite($this->datalogf, $time . " " . $this->ident . "[" . $this->pid . "]: " . $msg . "\n");
	  // hmmm... individually wrapped "binary" data in some cases with bin2hex, oh well...
	}
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
}
?>
