<?php
require 'inc/config.php';
require 'inc/echobot.php';
require 'vendor/autoload.php';

use Telegram\Bot\Api;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Session\SessionContainer;

PiwikTracker::$URL = PIWIK_URL;
$telegram = new Api(BOT_TOKEN);
$piwikTracker = new PiwikTracker( $idSite = SITE_ID );
$sessions = SessionContainer::getInstance ();

$log = new Logger('Echo bot');
$log->pushHandler(new RotatingFileHandler('logs/echobot.log', Logger::DEBUG));

$response = $telegram->getMe();
$botId = $response->getId();
$botname = $response->getFirstName();
$username = $response->getUsername();
$log->info('Starting: ' . $botname);
$bot = new EchoBot ($piwikTracker, $log, $telegram, $sessions);

while (true) // Telegram bot main loop
{
	try {
		$updates = $telegram->getUpdates($params);
		$log->debug ('getUpdates: anz updates=' . count($updates) . " offset=" . $params ['offset']);
        $sessions->cleanupInvalidSessions ();
		foreach ($updates as $update)
		{
			$updateId = $update->getUpdateId();
			if ($update->getMessage() != null)
			{
				$message = $update->getMessage();
				$bot->handleMessage ($message);
			} else { // Possible updates: edited_message, inline_query, chosen_inline_result, callback_query
				$piwikTracker->doTrackEvent('Messages','incomingUnknownUpdate');
			}
			$params ['offset'] = $updateId + 1;
		}

		$bot->processEchoes();

	} catch (Exception $e)
	{
		$log->error ('Internal Error: ' .  $e->getMessage());
		sleep (ERROR_DELAY);
	}
}
?>
