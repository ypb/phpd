<?php

function r_addy_port($socket) {
  if (socket_getpeername($socket, $addr, $port))
	return $addr . ":" . $port;
  else
	return FALSE;
}

function destroy_connection($socket) {
  if (socket_shutdown($socket)) {
	socket_close($socket);
	return TRUE;
  } else {
	return FALSE;
  }
}

function fork() {
  $pid = pcntl_fork();
  if ($pid == -1) {
	evac('could not fork', 1);
  } else if ($pid) {
	evac('fork succeded', 0);
  } else {
	posix_setsid();
	// TODO close fdes and change user... after bind?
	sleep(1);
  }
}
?>
