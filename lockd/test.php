<?php

require dirname( __FILE__ ) . '/lockd_client.php';

$lockc = new lockc();

echo "get " . $lockc->get( "foo" )     . " ( expected: 1: success )  \r\n";
echo "get " . $lockc->get( "foo" )     . " ( expected: 0: failure )  \r\n";
echo "ins " . $lockc->inspect( "foo" ) . " ( expected: 1: locked )   \r\n";
echo "rel " . $lockc->release( "foo" ) . " ( expected: 1: success )  \r\n";
echo "rel " . $lockc->release( "foo" ) . " ( expected: 0: failure )  \r\n";
echo "ins " . $lockc->inspect( "foo" ) . " ( expected: 0: unlocked ) \r\n";
echo "get " . $lockc->get( "foo" )     . " ( expected: 1: success )  \r\n";

