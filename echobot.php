<?php

require 'inc/config.php';
require 'vendor/autoload.php';

use Telegram\Bot\Api;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

PiwikTracker::$URL = 'http://piwik.1br.de/';
$telegram = new Api(BOT_TOKEN);
$piwikTracker = new PiwikTracker( $idSite = SITE_ID );

$log = new Logger('name');
$log->pushHandler(new StreamHandler('logs/echobot.log', Logger::DEBUG));


$response = $telegram->getMe();
$botId = $response->getId();
$botname = $response->getFirstName();
$username = $response->getUsername();

$log->info('Starting: ' . $botname);

// Telegram bot main loop
while (true)
{
	try {

		// TODO main loop 

	} catch (Exception $e)
	{
		$log->error ('Internal Error: ' .  $e->getMessage());
		sleep (ERROR_DELAY);
	}
}

$log->info('Quit: ' . $botname);

?>
