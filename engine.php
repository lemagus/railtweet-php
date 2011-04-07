#! /usr/bin/php
<?php
	
	date_default_timezone_set('Europe/Brussels');
	
	define('APPLICATION_PATH', realpath(dirname(__FILE__) ));
	set_include_path(implode(PATH_SEPARATOR, array(
    	realpath(APPLICATION_PATH . '/phprail/'),
    	realpath(APPLICATION_PATH . '/twitteroauth/'),
    	get_include_path(),
	)));
	
	include_once "classes/Database.php";
	include_once "classes/StringUtils.php";
	
	include_once "twitteroauth.php";
	include_once "IRail.php";
	
	define("TWITTER_LIMIT_CHAR", 140);
	define("STATION_OFFSET_MINUTES", 5);
	
	define("APP_NAME", "railtweet");
	define("APP_LANGUAGE", "en");
	
	define("API_KEY",""); // API KEY REMOVED
	define("CONSUMER_KEY",""); // KEY REMOVED
	define("CONSUMER_SECRET",""); // KEY REMOVED
	define("ACCESS_TOKEN", ""); // KEY REMOVED
	define("ACCESS_SECRET", ""); // KEY REMOVED
	
	define("MENTIONS_API", "http://api.twitter.com/1/statuses/mentions.json");
	define("UPDATE_API", "http://api.twitter.com/1/statuses/update.json");
	define("DIRECT_MESSAGE_API", "http://api.twitter.com/1/direct_messages/new.json");
	define("IRAIL_URL", "http://api.irail.be");
	
	define("BITLY_LOGIN", "railtweet");
	define("BITLY_KEY",""); // KEY REMOVED
	define("MOBILE_URL","http://m.railtweet.be/?time=%s&dep=%s&arr=%s");
	
	define("TEMPLATE", "%s Dep: %s %s platform %s %sArr: %s %s");
	
	
	/* URLShortener */
	
	function shortenURL($url,$login,$appkey,$format = 'json',$version = '2.0.1'){
	
		$bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$login.'&apiKey='.$appkey.'&format='.$format;
		$response = file_get_contents($bitly);
		
		if(strtolower($format) == 'json'){
			$json = @json_decode($response,true);
			return $json['results'][$url]['shortUrl'];
		}
		else{
			$xml = simplexml_load_string($response);
			return 'http://bit.ly/'.$xml->results->nodeKeyVal->hash;
		}
	}

	/**/
	
	$db	= new Database();
	
	$iRail = new IRail(IRAIL_URL, APP_LANGUAGE);
	$iRail->setAgent(APP_NAME);
	
	$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_SECRET);
	$mentions = $twitter->get(MENTIONS_API);
	
	foreach($mentions as $mention){
		
		$text 		= $mention->text." ";
		$time		= @date( "Y-m-d H:i:s" ,strtotime($mention->created_at));
		$from		= $mention->user->screen_name;
		$tweetid	= $mention->id_str;
		
		$retweeted = $db->fetchOne("SELECT retweeted FROM mentions WHERE `name` = ".$db->quote($from)." AND `time` = ".$db->quote($time));
		
		if(!$retweeted){
			
			preg_match_all("`#([a-z\-]+)\s`i", StringUtils::removeAccents($text) , $matches);			
			
			$departure 	= @trim($matches[1][0]);
			$arrival 	= @trim($matches[1][1]);
			
			if($from && $departure && $arrival){
				
				$connections = $iRail->getConnections(strtoupper($departure), strtoupper($arrival));
				foreach($connections as $connection){
				
					$dep 		= $connection->getDeparture();
					$arr 		= $connection->getArrival();
					
					$vehicle	= $dep->getVehicle();
					$platform	= $dep->getPlatform();
					
					$depStation = $dep->getStation()->getName();
					$arrStation = $arr->getStation()->getName();
					
					$depTime	= $dep->getTime()->format('H:i');
					$arrTime	= $arr->getTime()->format('H:i');
					
					$t			= $dep->getTime();
					$ts			= mktime ($t->format("H"), $t->format("i"), $t->format("s"), $t->format("n"), $t->format("j"), $t->format("Y"));
					if($ts < time() + (60*STATION_OFFSET_MINUTES) ) continue; 
					
					$depDelay	= intval($dep->getDelay()) / 60;
					
					$vehType	= $vehicle->getType();
					$vehNumber	= $vehicle->getNumber();
				
					$delay		= ($depDelay > 0) ? "(delay " . $depDelay."min) " : "";
					$tweet		= "@". $from." " .sprintf(TEMPLATE,
												$vehType."-".$vehNumber,
												$depStation,
												$depTime,
												$platform,
												$delay,
												$arrStation,
												$arrTime
											);
											
					if(strlen($tweet) < TWITTER_LIMIT_CHAR){
					
						$url = shortenURL(
						sprintf(MOBILE_URL, $ts, $departure, $arrival)
						,BITLY_LOGIN
						,BITLY_KEY);
					
						if(strlen($tweet. " ".$url) < TWITTER_LIMIT_CHAR ) $tweet = $tweet. " ".$url;
						if(strlen($tweet. " #".APP_NAME) < TWITTER_LIMIT_CHAR ) $tweet = $tweet. " #".APP_NAME;						
						
						$db->insert("mentions", array(
							"name" 			=> $from,
							"departure"		=> $depStation,
							"arrival"		=> $arrStation,
							"text"			=> trim($text),
							"time"			=> $time,
							"tweetId"		=> $tweetid,
							"retweeted"		=> 1,
						));
						
						$r = $twitter->post(UPDATE_API, array(
							"status" 				=> $tweet,
							"in_reply_to_status_id"	=> $tweetid
						));
						
						echo "Replied to ".$from." (".$tweetid.")\n";				

					}
				
					break;
				}
				
			}
			
		}
		
		
	}		 
	
?>