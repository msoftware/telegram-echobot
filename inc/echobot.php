<?php 

class EchoBot { 

	private $piwikTracker;
	private $log;
	private $telegram;
	private $sessions;

	function __construct($piwikTracker, $log, $telegram, $sessions) 
	{ 
		$this->log = $log;
		$this->telegram = $telegram;
		$this->piwikTracker = $piwikTracker;
		$this->sessions = $sessions;
	} 

	function handleStartMessage ($session, $chatId)
	{
		$this->log->debug ("Start command executed");
		$msg = "Hi, ich kann ein Echo Deiner Nachrichten senden.\r\n" .
			   "Über das Kommando /delay kannst Du Echos verzögern.\r\n" .
			   "Am besten Du probierst es direkt mal aus - ich freue mich schon.";
		$response = $this->sendMessage ($chatId, $msg);
		$session->set ("TASK",0);
		return $response;
	}

	function handleInfoMessage ($session, $chatId)
	{
		$delay = $this->getDelay ($chatId);
		$this->log->debug ("Info command executed (delay=" . $delay . ")");
		if ($delay == -1)
		{
			$msg = "Deine Echos sind noch ohne Verzögerung.\r\n" .
				   "Mit dem Kommando /delay kannst Du die Verzögerung einstellen.";
		} else {
			$msg = "Deine Verzögerung beträgt " . $delay . " Minuten.";
		}
		$response = $this->sendMessage ($chatId, $msg);
		$session->set ("TASK",0);
		return $response;
	}
				
	function handleDelayMessage ($session, $chatId)
	{
		$this->log->debug ("Delay command started");
		$msg = "Wähle die Verzögerung des Echos.";
		$keyboard = $this->getDelayOptionsKeyboard ();
		$response = $this->sendOptionsMessage ($chatId, $msg, $keyboard);
		$session->set ("TASK",1);
		return $response;
	}

	function handleTextMessage ($session, $chatId, $messageText)
	{
		if ($session->get ("TASK") == 1)
		{
			$this->log->debug ("Set delay to " . $messageText);
			$delay = $this->parseDelay ($messageText);
			if ($delay > -1)
			{
				$this->setDelay($chatId, $delay);
				$msg = "Du hast die Echo Verzögerung auf " . $delay . " " .
					   "Min. gesetzt.\r\nAlle Nachrichten werden nun mit " .
					   "einer Verzögerung von " . $delay . " Minuten " .
					   "beantwortet.\r\nDu kannst die Verzögerung jederzeit " .
					   "mit dem Befehl /delay ändern.";
				$response = $this->sendMessage ($chatId, $msg);
				$session->set ("TASK",0);
			} else {
				$keyboard = $this->getDelayOptionsKeyboard ();
				$msg = "Entschuldigung, aber ich habe Deine Eingabe nicht " .
					   "verstanden. Bitte versuche es noch einmal.";
				$response = $this->sendOptionsMessage ($chatId, $msg, $keyboard);
				$session->set ("TASK",1);
		 	}
		} else {
			$this->log->debug ("Add message to echo list " . $messageText);
			$this->addMessage ($chatId, $messageText);
			$session->set ("TASK",0);
			$response = null;
		}
		return $response;
	}

	function handleMessage ($message)
	{
		$messageId = $message->getMessageId();
		$chatId = $message->getChat()->getId();
		$session = $this->sessions->getSession($chatId, SESSION_TIMEOUT); // chatIt == sessionId
		$messageText = $message->getText();
		$this->piwikTracker->setUserId($chatId);
		$this->piwikTracker->doTrackEvent("Messages","incomingMessage",$messageText);
		$this->log->debug ("getMessage: text=" . $messageText);
		switch ($messageText)
		{
			case "/start":
				$response = $this->handleStartMessage ($session, $chatId);
				break;
			case "/info":
				$response = $this->handleInfoMessage ($session, $chatId);
				break;
			case "/delay":
				$response = $this->handleDelayMessage ($session, $chatId);
				break;
			default:
				$response = $this->handleTextMessage ($session, $chatId, $messageText);
				break;
		}
	}

	function processEchoes ()
	{
		$sessionIds = $this->sessions->getSessionIds();
		$this->log->debug ("Process echoes for " . count($sessionIds) . " sessions");
		foreach ($sessionIds as $sessionId)
		{
			$session = $this->sessions->getSession($sessionId);
			$messages = $session->get("MESSAGES");
			if ($messages == null)
			{
				$this->log->debug ("No echoes to process for Session " . $sessionId);
			} else {
				$this->log->debug ("Process echoes for Session " . $sessionId . " with " . count ($messages) );
				$sendMessages = array ();
				foreach ($messages as $messageKey => $message)
				{
					$chatId = $message["CHATID"];
					if ($this->hasToBeSendMessage($message, $chatId))
					{
						$msg = $message["MESSAGE"];
						$response = $this->sendMessage ($chatId, $msg);
						$sendMessages[] = $messageKey;
					}
				}
				foreach ($sendMessages as $sendMessageKey)
				{
					unset ($messages[$sendMessageKey]);
				}
				$session->set("MESSAGES", $messages);
			}
		}
	}

	function hasToBeSendMessage ($message, $chatId)
	{
		$delay = $this->getDelay ($chatId) * 60; // Sec.
		$this->log->debug ("hasToBeSendMessage Message with delay =" . $delay . "?");
		if ($delay < 1) return true; 
		$timestamp = $message["TIMESTAMP"];
		$now = time ();
		$this->log->debug ("hasToBeSend with " . ($timestamp + $delay) . " >= " . $now . "=" . ($timestamp + $delay >= $now));
		if ($timestamp + $delay <= $now)
		{
			return true;
		}
		return false;
	}

	function addMessage ($chatId, $messageText)
	{
		$now = time ();
		$session = $this->sessions->getSession($chatId);

		$messages = $session->get("MESSAGES");
		if ($messages == null)
		{
			$messages = array ();
		}
		$message = array(
			"CHATID" => $chatId,
			"TIMESTAMP" => $now,
			"MESSAGE" => $messageText,
		);
		$messages[] = $message;
		$this->log->debug ("Message list for chatid " . $chatId . " contains now " . count($messages) . " messages");
		$session->set("MESSAGES", $messages);
	}

	function getDelay ($chatId)
	{
		return $this->sessions->getSession($chatId)->get("DELAY", -1);
	}

	function setDelay ($chatId, $delay)
	{
		$this->sessions->getSession($chatId)->set("DELAY", $delay);
	}

	function parseDelay ($text)
	{
		$ret = -1;
		if (strpos($text, "St.") > 0)
		{
			$ret = intval ($text) * 60;
		} else if (strpos($text, "Min.") > 0)
		{
			$ret = intval ($text);
		}
		return $ret;
	}

	function getDelayOptionsKeyboard ()
	{
		$keyboard = array ();
		$keyboard[0][0] = "5 Min.";
		$keyboard[0][1] = "10 Min.";
		$keyboard[0][2] = "30 Min.";
		$keyboard[0][3] = "1 St.";
		return $keyboard;
	}

	function sendMessage ($chatId, $text, $parseMode = "Markdown")
	{
		$this->log->debug ("sendMessage " . $chatId . " " . $text . " sessions");
		$response = $this->telegram->sendMessage([
				"chat_id" =>  $chatId,
				"text" => $text,
				"parse_mode" => $parseMode
			]);
		return $response;
	}

	function sendOptionsMessage ($chatId, $text, $keyboard, $parseMode = "Markdown")
	{
		$reply_markup = $this->telegram->replyKeyboardMarkup([
			"keyboard" => $keyboard,
			"resize_keyboard" => true,
			"one_time_keyboard" => true
		]);
		$response = $this->telegram->sendMessage([
			"chat_id" =>  $chatId,
			"text"	   => $text,
			"parse_mode" => $parseMode,
			"reply_markup" => $reply_markup
		]);
		return $response;
	}
} 

?> 
