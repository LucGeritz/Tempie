<?php

include ('../vendor/autoload.php');
include ('../src/Tempie.php');

// In the constructor I pass the name of the config file 
// in this file the paths are specified where the Runner should look for test files
// It should also contain a file mask by which the Runner identifies the test files
//    .. you could of course also get the config file name from the command line ($argsv[1])
//    and pass this to the constructor.  
$runner = new Tigrez\IExpect\Runner(dirname(__FILE__).'/run.cfg');
	
if($runner->start()){
	// if start returns true it means running went well, not that all tests passed
		
	echo "\n\nSummary";
	echo "\ntests  : ".$runner->getTests();
	echo "\npassed : ".$runner->getPassed();
	echo "\nfailed : ".$runner->getFailed();
	echo "\noverall: ".($runner->getResult() ? "passed" : "failed");
	// pass result back to the command line
	exit($runner->getResult() ? 0 : 1);

}
else{
	// failing start usually means there's something wrong with the config file

	$configFile = $runner->getConfigFile();
	echo "\nConfig file $configFile not found or has incorrect content";
	
	// pass result back to the command line
	exit(1);

}
