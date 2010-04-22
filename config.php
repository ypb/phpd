<?php
$phpd_conf['port'] = 8000;
$phpd_conf['addy'] = "0.0.0.0";
// for now hardcoding phpd but it may break (see Makefile, fixated)
$phpd_conf['logdir'] = "/var/log/phpd";
// log of data goes into its own $logdir/datalog file...
$phpd_conf['logopts']['logdata'] = TRUE;
// debug goes through syslog so its truthiness won't help if 'syslog' is false
$phpd_conf['logopts']['debug'] = FALSE;
// leave on for some lightway action
$phpd_conf['logopts']['syslog'] = TRUE;
?>
