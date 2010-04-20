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
?>
