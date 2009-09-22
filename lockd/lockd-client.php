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

	function inspect( $string ) { return $this->is_locked( $string ); }
	function check( $string ) { return $this->is_locked( $string ); }
	function is_locked( $string ) {
		if ( !$fp = $this->connect() )
			return true;
		fwrite( $fp, "i $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

	function get( $string ) { return $this->lock( $string ); }
	function lock( $string ) {
		if ( !$fp = $this->connect() )
			return false;
		fwrite( $fp, "g $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

	function release( $string ) { return $this->unlock( $string ); }
	function unlock( $string ) {
		if ( !$fp = $this->connect() )
			return false;
		fwrite( $fp, "r $string\r\n" );
		$rval = trim( fgets( $fp ) );
		return intval( $rval );
	}

}
