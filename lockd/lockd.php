#!/usr/local/bin/php
<?php

class openlockd {
	
	var $sock = null;
	var $lockd_port = 2626;
	var $lockd_addr = '127.0.0.1';
	var $lockd_usleep = 5000;
	var $lockd_pidfile = '/var/run/openlockd.pid';
	var $lockd_uid = 65534;
	var $lockd_gid = 65534;
	var $processing = 0;

	var $stat_connects = 0;
	var $stat_orphans  = 0;
	var $stat_commands = 0;
	var $gs = 0;
	var $rs = 0;
	var $is = 0;
	var $qs = 0;

	var $answering = true;
	var $connections = array();
	var $locks = array();

	function openlockd( $args=array() ) {
		foreach ( $args as $i => $v ) {
			$i = "lockd_$i";
			$this->$i = $v;
		}
		
		set_time_limit( 0 );
		pcntl_signal( SIGTERM,  array( $this, 'sig_handler' ) );
		pcntl_signal( SIGHUP,   array( $this, 'sig_handler' ) );
		pcntl_signal( SIGUSR1,  array( $this, 'sig_handler' ) );
		
		$this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option( $this->sock, SOL_SOCKET, SO_REUSEADDR, 1 );
		socket_set_option( $this->sock, SOL_SOCKET, SO_LINGER, array( 'l_onoff' => 1, 'l_linger' => 0 ) );
		socket_set_nonblock( $this->sock );
		if ( !socket_bind( $this->sock, $this->lockd_addr, $this->lockd_port ) )
			die( "Could not bind socket...\r\n" );
		if ( !socket_listen( $this->sock, 100 ) )
			die( "Could not listen on socket...\r\n" );

		if ( !defined( 'DAEMONIZE' ) || DAEMONIZE ) {		
			$pid = pcntl_fork();
			if ( $pid == -1 ) 
				die( "Error Forking...\r\n" );
			if ( $pid )
				die( "Detaching...\r\n" );
			usleep( $this->lockd_usleep );
			fclose( STDIN );  // only seems to work in php5.2+
		}
		
		fflush( STDOUT );
		fflush( STDERR );

		global $pidfilefd;
		if ( !( $pidfilefd = @fopen( $this->lockd_pidfile, 'a+' ) ) )
			die( "Could not open $this->lockd_pidfile\r\n" );
		if ( !@flock( $pidfilefd, LOCK_EX ) )
			die( "Could not lock $this->lockd_pidfile\r\n" );
		ftruncate( $pidfilefd, 0 );
		fwrite( $pidfilefd,  getmypid() );
		
		// Drop privileges if appropriate
		if ( function_exists( 'posix_getuid' ) && posix_getuid() === 0 ) {
			if ( $this->lockd_uid ) { 
				echo "Dropping user privileges\r\n";
				@posix_setuid( $this->lockd_uid );
			}
			if ( $this->lockd_gid ) {
				echo "Dropping group privileges\r\n";
				@posix_setgid( $this->lockd_gid );
			}
		}

		if ( !defined( 'DAEMONIZE' ) || DAEMONIZE ) {
			fclose( STDOUT ); // only seems to work in php5.2+
			fclose( STDERR ); // only seems to work in php5.2+
		}

		register_tick_function( array( &$this, 'process' ) );
		
		declare(ticks = 1);

		while ( true )
			$this->accept_loop();

	}

	function accept_loop() {
		if ( $this->answering ) {
			if ( ( $c = @socket_accept( $this->sock ) ) ) {
				set_time_limit( 0 );
				// echo "Accepting connection #".count( $this->connections )." $c ".chr(10);
				socket_set_block( $c );
				socket_set_option( $c, SOL_SOCKET, SO_KEEPALIVE, 1 );
				socket_set_option( $c, SOL_SOCKET, SO_RCVLOWAT, 2 );
				socket_set_option($c, SOL_SOCKET, SO_LINGER, array( 'l_onoff' => 1, 'l_linger' => 0 ) );
				$this->connections[] = &$c;
			} else {
				usleep( $this->lockd_usleep );
			}
		} else {
			usleep( $this->lockd_usleep );
		}
	}

	function sig_handler( $sig ) {
		global $pidfilefd;
		$this->answering = false;
		foreach ( $this->connections as $i => $c ) {
			socket_shutdown( $this->connections[$i], 2);
			socket_close( $this->connections[$i] );
			unset( $this->connections[$i] );
		}
		socket_shutdown(  $this->sock, 2);
		socket_close( $this->sock );
		unset( $this->sock );
		ftruncate( $pidfilefd, 0 );
		die();
	}

	function process() {
		set_time_limit( 0 );
		// begin function locking
		if ( $this->processing )
			return;
		$this->processing++;
		if ( $this->processing > 1 ) {
			$this->processing--;
			return;
		}
		// end function locking

		if ( !count( $this->connections ) ) {
			$this->processing--;
			return;
		}

		// process reads
		$r = $this->connections;
		$w = null;
		$e = null; //$this->connections;

		$num_changed_sockets = @socket_select($r, $w, $e, 0);
		if ( $num_changed_sockets === false ) {
			$this->processing--;
			return;
		}
		if ( $num_changed_sockets < 1 ) {
			$this->processing--;
			return;
		}
		foreach ( $r as $c ) {
			if ( !socket_get_option( $c, SOL_SOCKET, SO_RCVBUF ) )
				continue;
			$n = array_search( $c, $this->connections );
			$d = socket_read( $c, 4096 );
			if ( !$d ) {
				// echo "<< #$c EOF\r\n";
				socket_close( $this->connections[$n] );
				unset( $this->connections[$n] );
				foreach ( array_keys( $this->locks, $c ) as $lock ) {
					$this->stat_orphans++;
					unset( $this->locks[$lock] );
				}
				continue;

			}
			
			$cmd = $d{0};
			$hash = md5( substr( $d, 1 ) );
			$this->stat_commands++;
			switch ( $cmd ) {
				case 'g': // get a lock
					$this->gs++;
					if ( isset( $this->locks[$hash] ) ) {
						socket_write( $c, "0 Cannot Get Lock\r\n" );
						break;
					}
					$this->locks[$hash] = $c;
					socket_write( $c, "1 Got Lock\r\n" );
					break;
				case 'r': // release lock
					$this->rs++;
					if ( isset( $this->locks[$hash] ) && $this->locks[$hash] == $c ) {
						unset( $this->locks[$hash] );
						socket_write( $c, "1 Released Lock\r\n" );
						break;
					}
					socket_write( $c, "0 Cannot Release Lock\r\n" );
					break;
				case 'i': // inspect lock
					$this->is++;
					if ( isset( $this->locks[$hash] ) )
						socket_write( $c, "1 Locked\r\n" );
					else
						socket_write( $c, "0 Not Locked\r\n" );
					break;
				case 'q': // get system stats
					$this->qs++;
					socket_write( $c, print_r( array(
						'conns' => count( $this->connections ),
						'locks' => count( $this->locks ),
						'orphans' => $this->stat_orphans,
						'commands' => $this->stat_commands,
						'command_g' => $this->gs,
						'command_r' => $this->rs,
						'command_i' => $this->is,
						'command_q' => $this->qs,
					), true ) );
					break;
			}
				
			// echo "$c << $d"; 
		}
		$this->processing--;
	}
}

// ex:
// $lockd = new openlockd();
// or:
// $lockd = new openlockd( array( 'port' => 2626, 'addr' => '127.0.0.1', 'usleep' => 50000, 'pidfile' => '/var/run/lockd.pid' ) );


