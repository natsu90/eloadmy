# Malaysia Eload Library Wrapper

## Requirement

An account credential from one of [SMS Reload System](http://smsreloadsystem.com/) (tested with futureeload, 67topup, 68topup)

## Install

Add following values to `composer.json`

    ...
    "repositories": [
    	...
    	{
    		"type": "git",
        	"url": "https://github.com/natsu90/eloadmy"
        },
        ...
    ],
    ...
    "require": {
        ...
        "natsu90/eloadmy": "dev-master",
        ...
    }
    ...

then run `composer update natsu90/eloadmy`

## Usage

Run `php vendor/natsu90/eloadmy/FirstTimeLoginCLI.php` from CLI for first time to get the AgentUserName cookie value

    $eloadmy = new EloadMy(array('url' => 68topup.com, 'mobileno' => '0171234567', 'password' => '******', 'agentusername' => $AgentUserName));

    print_r($eloadmy->getCreditBalance());

    print_r($eloadmy->getAvailableReloadProducts());

    print_r($eloadmy->getAvailableReloadAmount('maxis'));

    print_r($eloadmy->reload('maxis',5,'0171234567'));

    print_r($eloadmy->getStatus('0171234567'));

    print_r($eloadmy->getTxHistory());

    *check out the source code for other functions*