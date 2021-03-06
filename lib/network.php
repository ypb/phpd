<?php

class Network {
  function __construct($logger, $addy, $port) {
	$this->logos = $logger;
	$this->addy = $addy;
	$this->port = $port;
	$this->socket = FALSE;
  }
  function listen() {
	// perhaps a short circuit with an OR...?
	if (! ($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
	  $this->logos->debug("couldn't create socket: "  . socket_strerror(socket_last_error()));
	  return FALSE;
	}
	if (! socket_bind($sock, $this->addy, $this->port)) {
	  $this->logos->debug("couldn't bind to socket: " . socket_strerror(socket_last_error($sock)));
	  return FALSE;
	}
	if (! socket_listen($sock)) {
	  $this->logos->debug("failed to listen on socket: " . socket_strerror(socket_last_error($sock)));
	  return FALSE;
	}
	$this->socket = $sock;
	return TRUE;
  }
  function accept() {
	$read = array($this->socket);
	if (socket_select($read, $write = NULL, $excpt = NULL, 0, 1000)) {
	  return socket_accept($this->socket);
	} else {
	  return FALSE;
	}
  }
  function ready_to_read($sock_arr) {
	if (socket_select($sock_arr, $write = NULL, $excpt = NULL, 0, 10000)) {
	  return $sock_arr;
	} else {
	  return FALSE;
	}
  }
  // slightly redundant... but perhaps we'd like to change how we read...
  function read($socket) {
	return socket_read($socket, 1024, PHP_BINARY_READ);
  }
  function write($socket, $data) {
	$l = strlen($data);
	$wrote = socket_write($socket, $data);
	if ($wrote === FALSE) {
	  // need id?
	  $this->logos->debug("failed write: " . socket_strerror(socket_last_error($socket)));
	} else if ($wrote < $l) {
	  $this->logos->debug("short write (" . $wrote . "<" . $l . "): " . socket_strerror(socket_last_error($socket)));
	}
	return $wrote;
  }
  function stop() {
	$ret = TRUE;
	if ($this->socket != FALSE) {
	  // tell more about socket?
	  $this->logos->debug("closing main socket");
	  $err = destroy_connection($this->socket);
	  if ($err === TRUE) {
		$this->logos->debug("main socket closed");
	  } else {
		$ret = FALSE;
		$this->logos->debug("failed to close main socket with: " . socket_strerror($err));
	  }
	}
	return $ret;
  }
}

class Client {
  function __construct($log, $id, $sock) {
	$this->id = $id;
	$this->clid = "client(" . $id . ")";
	$this->socket = $sock;
	$this->default_encoding = "UTF-8";
	// buffers...
	$this->in_buf = "";
	$this->continue = FALSE;
	$this->in_position = 0;
	$this->data = array();
	$this->out_buf = "";
	$this->out_position = 0; //unused
	// for now hacky logging...
	$this->logos = $log ;
	// retrying under writing uncertainty... see $client->{confirm_write,pending,kill_me}
	$this->retrying = FALSE;
	$this->last_write = time();
	$this->wait = 15 + rand(0,30); //first wait at least 15 to 45 secs...
	$this->retry_count = 0; //upto 3-4?
  }
  function process_with_process($string) {
	$this->logos->datalog($this->clid . " processing=\n" . $string);
	// do not try to convert... what would happen on misaligned data?
	//$this->in_buf .= $this->to_default_encoding($string);
	// at least check encoding...?
	$this->in_buf .= $this->check_encoding($string);
	//$this->logos->datalog($this->clid . " in_buf=\n" . $this->in_buf);
	// TODO perhaps we should try to do more than MERELY convert?
	// there is a worse case scenario when a mb char will end up being split
	// between subsequent "packets"; mb_strcut has some heuristic for that...
	while ($found = $this->gather_data()) {
	  $this->logos->datalog($this->clid . " found=\n" . $found);
	  $this->data[] = $found;
	}
	foreach ($this->data as $key => $datum) {
	  $this->out_buf .= Process($datum);
	  unset($this->data[$key]);
	}
	if ($this->out_buf != "")
	  return($this->out_buf);
	else
	  return FALSE;
  }
  // this should be reogranized, but as a skeleton frst try [a bit messed up]
  function confirm_write($len) {
	// if there was a write error or wrote nothing: init retrying.
	if ($len === FALSE || $len == 0) {
	  $this->retrying_start();
	  return FALSE;
	}
	$this->retrying_stop();
	if ($len < strlen($this->out_buf)) {
	  $this->out_buf = substr($this->out_buf, $len);
	  return FALSE;
	} else {
	  $this->out_buf = "";
	  return TRUE;
	}
  }
  function pending() {
	if (($this->out_buf !== "") && $this->retry()) {
	  return $this->out_buf;
	} else {
	  return FALSE;
	}
  }
  function kill_me() {
	if ($this->retrying && ($this->retry_count > 3)) {
	  // we tried 3x and waited about half an hour... for confirm_write
	  // 0th 15-45s
	  // 1st + 2x0th = 30-90s
	  // 2nd + 4x1st = 120-360s
	  // 3rd + 8x2nd = 16-48m
	  // 4th wait would be 16x0.5h... 8 hours... kill me, plz.
	  return TRUE;
	} else {
	  return FALSE;
	}
  }
  // merely init stuff...
  function retrying_start() {
	if (! $this->retrying) {
	  $this->retrying = TRUE;
	  $this->last_write = time();
	  $this->wait = 15 + rand(0,30);
	  $this->retry_count = 0;
	}
  }
  // are we ready to retry?
  function retry() {
	if ($this->retrying) {
	  $ts = time();
	  if ($ts > ($this->last_write + $this->wait)) {
		$this->retry_count++;
		$this->last_write = $ts;
		$this->wait = $this->wait * pow(2, $this->retry_count);
		return TRUE;
	  } else {
		return FALSE;
	  }
	} else {
	  // ready if not retrying at all
	  return TRUE;
	}
  }
  function retrying_stop() {
	$this->retrying = FALSE;
  }
  // may be expensive?
  function check_encoding($x) {
	$from_encoding = mb_detect_encoding($x);
	if ($from_encoding === FALSE) {
	  $this->logos->datalog($this->clid . " failed to detect from_encoding");
	} else if ($from_encoding !== $this->default_encoding) {
	  $this->logos->datalog($this->clid . " from_encoding=" . $from_encoding . " <> " . "def_encoding=" . $this->default_encoding);
	}
	return $x;
  }
  // this is "exact" c&p of mb_gather_data that assuming incoming data is UTF-8
  // uses single byte PHP functions to search for the {<,>} markerks
  // we'd be in trouble in case of markers being mb (even only utf-8...)
  function gather_data() {
	// == should be fine, too? still it runs once too often on simple \r \n etc...
	if ($this->in_buf === "")
	  return FALSE;
	$open = FALSE;
	$close = FALSE;
	if ($this->continue) {
	  $close = strpos($this->in_buf, ">", $this->in_position);
	  $this->logos->datalog($this->clid . " continuing in gather_data() close=" . $close);
	  if ($close !== FALSE) {
		$open = $this->in_position;
		$len = strlen($this->in_buf);
		$ret = substr($this->in_buf, $open, ($close - $open)+1);
		$this->in_buf = substr($this->in_buf, $close + 1, $len - $close);
		$this->continue = FALSE;
		$this->in_position = 0;
		return $ret;
	  }
	  return FALSE;
	} else {
	  $open = strpos($this->in_buf, "<", 0);
	  $this->logos->datalog($this->clid . " in gather_data() open=" . $open);
	  if ($open !== FALSE) {
		$close = strpos($this->in_buf, ">", $open);
		if ($close !== FALSE) {
		  $len = strlen($this->in_buf);
		  $ret = substr($this->in_buf, $open, ($close - $open)+1);
		  $this->in_buf = substr($this->in_buf, $close + 1, $len - $close);
		  return $ret;
		}
		$this->continue = TRUE;
		$this->in_position = $open;
		return FALSE;
	  } else {
		// discard in_buf
		$this->in_buf = "";
		$this->logos->datalog($this->clid . " in gather_data() in_buf discarded");
		return FALSE;
	  }
	}
  }
  // this function tries to be too clever for its own good; use it in
  // conjunction with following to_default_encoding, but if default_encoding is
  // not utf-8 there is all the more probability of data corruption if we do not make
  // effort to align it between possible packates... see PHP docs about mb_strcut
  function mb_gather_data() {
	// == should be fine, too? still it runs once too often on simple \r \n etc...
	if ($this->in_buf === "")
	  return FALSE;
	$open = FALSE;
	$close = FALSE;
	if ($this->continue) {
	  $close = mb_strpos($this->in_buf, ">", $this->in_position, $this->default_encoding);
	  $this->logos->datalog($this->clid . " continuing in mb_gather_data() close=" . $close);
	  if ($close !== FALSE) {
		$open = $this->in_position;
		$len = mb_strlen($this->in_buf);
		$ret = mb_substr($this->in_buf, $open, ($close - $open)+1, $this->default_encoding);
		$this->in_buf = mb_substr($this->in_buf, $close + 1, $len - $close, $this->default_encoding);
		$this->continue = FALSE;
		$this->in_position = 0;
		return $ret;
	  }
	  return FALSE;
	} else {
	  $open = mb_strpos($this->in_buf, "<", 0, $this->default_encoding);
	  $this->logos->datalog($this->clid . " in mb_gather_data() open=" . $open);
	  if ($open !== FALSE) {
		$close = mb_strpos($this->in_buf, ">", $open, $this->default_encoding);
		if ($close !== FALSE) {
		  $len = mb_strlen($this->in_buf);
		  $ret = mb_substr($this->in_buf, $open, ($close - $open)+1, $this->default_encoding);
		  $this->in_buf = mb_substr($this->in_buf, $close + 1, $len - $close, $this->default_encoding);
		  return $ret;
		}
		$this->continue = TRUE;
		$this->in_position = $open;
		return FALSE;
	  } else {
		// discard in_buf
		$this->in_buf = "";
		$this->logos->datalog($this->clid . " in mb_gather_data() in_buf discarded");
		return FALSE;
	  }
	}
  }
  function to_default_encoding($x) {
	$from_encoding = mb_detect_encoding($x);
	$this->logos->datalog($this->clid . " from_encoding=" . $from_encoding);
	if($from_encoding === $this->default_encoding){
	  return $x;
	} else {
	  //return utf8_encode($x);
	  if ($from_encoding !== FALSE) {
		return mb_convert_encoding($x, $this->default_encoding, $from_encoding);
		// TODO log conversion, also see ini_set at the top of phpd.php
	  } else {
		// TOFIX ... or rather ponder what to do MORE sensibly
		return mb_convert_encoding($x, $this->default_encoding, "auto");
		// if no $from_encoding is provided PHP uses 'mbinternal_encoding'
		// and since this should be $this->default_encoding we may as well return $x
	  }
	}
  }
}
?>
