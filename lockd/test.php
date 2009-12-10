<?php

require dirname( __FILE__ ) . '/lockd-client.php';

$lockc1 = new lockc();
$lockc2 = new lockc();

echo "g  --- " . $lockc1->get( "foo" )           . " ( expected: 1: success )  \r\n";
echo "g  --- " . $lockc1->get( "foo" )           . " ( expected: 0: failure )  \r\n";
echo "i  --- " . $lockc1->inspect( "foo" )       . " ( expected: 1: locked )   \r\n";
echo "r  --- " . $lockc1->release( "foo" )       . " ( expected: 1: success )  \r\n";
echo "r  --- " . $lockc1->release( "foo" )       . " ( expected: 0: failure )  \r\n";
echo "i  --- " . $lockc1->inspect( "foo" )       . " ( expected: 0: unlocked ) \r\n";
echo "g  --- " . $lockc1->get( "foo" )           . " ( expected: 1: success )  \r\n";
echo "sg (1) " . $lockc1->get( "foo", true )     . " ( expected: 1: success )  \r\n";
echo "sg (1) " . $lockc1->get( "foo", true )     . " ( expected: 1: success )  \r\n";
echo "si --- " . $lockc1->inspect( "foo", true ) . " ( expected: 1: locked )   \r\n";
echo "sg (2) " . $lockc2->get( "foo", true )     . " ( expected: 2: success )  \r\n";
echo "sg (2) " . $lockc2->get( "foo", true )     . " ( expected: 2: success )  \r\n";
echo "si --- " . $lockc1->inspect( "foo", true ) . " ( expected: 2: locked )   \r\n";
echo "sr (1) " . $lockc1->release( "foo", true ) . " ( expected: 1: success )  \r\n";
echo "sr (1) " . $lockc1->release( "foo", true ) . " ( expected: 0: failure )  \r\n";
echo "si --- " . $lockc1->inspect( "foo", true ) . " ( expected: 1: locked )   \r\n";
echo "sr (2) " . $lockc2->release( "foo", true ) . " ( expected: 1: success )  \r\n";
echo "sr (2) " . $lockc2->release( "foo", true ) . " ( expected: 0: failure )  \r\n";
echo "si --- " . $lockc1->inspect( "foo", true ) . " ( expected: 0: unlocked ) \r\n";

sleep(5);
