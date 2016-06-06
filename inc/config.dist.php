<?php

/* Your Telegram messenger bot token */
define ("TOKEN", "Enter your token here");

/* Your piwik Site id */
define ("SITE_ID", "Enter your piwik site here");

/* Delay after main loop error in sec. */
define ("ERROR_DELAY", 30);

/* Session timout in seconds */
define ("ERROR_DELAY", 3600 * 24 * 7); // 1 Week

/* getUpdate Params */
$params = array ();
$params ['offset'] = 0;
$params ['limit'] = 10;
$params ['timeout'] = 1; // You can increase timeout, but then the delay can get inaccurate

?>
