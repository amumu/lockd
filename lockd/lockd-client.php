<?php

class lockc {

	var $host = "127.0.0.1";
	var $port = 2626;
	var $timeout = 0.1;
	var $fp = null;
	var $errno = 0;
	var $errst = '';

	function lockc( $host="127.0.0.1", $port=2626, $timeout=0.1 ) {
		$this->host = $host;
		$this->port = $port;
		$this->timeout = $timeout;
	}

	function connect( $try=0 ) {
		if ( is_resource( $this->fp ) )
			return $this->fp;
		if ( $try > 3 )
			return false;
		$this->fp = fsockopen( $this->host, $this->port, $this->errno, $this->errst, $this->timeout );
		return $this->connect( $try+1 );
	}

	function inspect( $string, $shared=false ) { return $this->is_locked( $string, $shared ); }
	function check( $string, $shared=false ) { return $this->is_locked( $string, $shared ); }
	function is_locked( $string, $shared=false ) {
		if ( !$fp = $this->connect() )
			return true;
		$c = 'i';
		if ( $shared )
			$c = 'si';
		fwrite( $fp, "$c $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

	function get( $string, $shared=false ) { return $this->lock( $string, $shared ); }
	function lock( $string, $shared=false ) {
		if ( !$fp = $this->connect() )
			return false;
		$c = 'g';
		if ( $shared )
			$c = 'sg';
		fwrite( $fp, "$c $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

	function release( $string, $shared=false ) { return $this->unlock( $string, $shared ); }
	function unlock( $string, $shared=false ) {
		if ( !$fp = $this->connect() )
			return false;
		$c = 'r';
		if ( $shared )
			$c = 'sr';
		fwrite( $fp, "$c $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

}
