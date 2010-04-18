<?php
function socket_error() {
  global $logos;
  $logos->log("exiting on socket failure: %m");
  exit(1);
}
function server($con, $scon) {
  global $logos;
  $pid = pcntl_fork();
  if ($pid == -1) {
	return FALSE;
  } else if ($pid) {
	//	return(array('pid' => $pid, 'con' => $con));
	return TRUE;
  } else {
	//silly GC...
	//	$connection = $acon[$scon];
	while($read = socket_read($con, 1024, PHP_BINARY_READ)) {
	  if($read != "") {
		$write = socket_write($con, process($read));
		if ($write === FALSE) {
		  $logos->log("failed writing back: %m");
		} else if ($write < strlen($read)) {
		  $logos->log("short write");
		}
	  }
	}
	// check error code? beforehand? if at all?
	$logos->log("widing down (" . $scon ."): " . socket_strerror(socket_last_error($con)));
	socket_clear_error($con);
	socket_shutdown($con);
	socket_close($con);
	
	//	unset($acon[$scon]);
	exit(0);
  }
}
function process($string) {
  return($string);
}
?>
