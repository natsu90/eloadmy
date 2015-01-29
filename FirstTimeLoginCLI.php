<?php
require( 'vendor/autoload.php');

if (php_sapi_name() == 'cli') {
    
	echo "EloadMy First Time Login CLI.\nTo get AgentUserName cookie value.\n";

	$eloadmy = new EloadMy();

	$isClientInvalid = true;

	while($isClientInvalid) {

		echo "\nEnter Web Reload URL: ";
		$url = trim(fgets(STDIN));

		$foo = $eloadmy->setUrl($url);

		if(!$foo) {

			echo "Invalid Client URL!\n";
			continue;
		}
			
		$isClientInvalid = false;
	}

	$isLoginFailed = true;
	// attempt login
	while($isLoginFailed) {

		echo "\nEnter your web reload username: ";
		$username = trim(fgets(STDIN));

		echo "Enter your web reload password: ";
		$password = trim(fgets(STDIN));

		$foo = $eloadmy->auth($username, $password);

		if(!$foo) {

			echo "Login Failed!\n";

			continue;

		}

		$isLoginFailed = false;
	}

	echo "\nA TAC number have been sent to your mobile.";

	$isTACInvalid = true;
	// attempt TAC
	while($isTACInvalid) {

		echo "\nEnter the TAC number: ";
		$tacNumber = trim(fgets(STDIN));

		$agentUserName = $eloadmy->setTac($tacNumber);

		if(!$agentUserName) {

			echo "Invalid TAC Number!\n";

			continue;
		}

		$isTACInvalid = false;
	}

	$info = $eloadmy->getInfo();

	echo "\nYour agentUserName value is, {$agentUserName}\n".
			"\n/*********************************************/\n";

	print_r($info);
	
	echo	"\n/*********************************************/";

} else {

	echo 'You have to run this from CLI';
}
