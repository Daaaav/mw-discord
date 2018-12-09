<?php

class DiscordUtils {
	public static function handleDiscord ($msg) {
		global $wgDiscordWebhookURL;

		if ( !$wgDiscordWebhookURL ) {
			// There's nothing in here, so we won't do anything
			return false;
		}

		$json_data = [ 'content' => "$msg" ];
		$json = json_encode($json_data);
		$urls = [];

		if ( is_array( $wgDiscordWebhookURL ) ) {
			$urls = array_merge($urls, $wgDiscordWebhookURL);
		} else if ( is_string($wgDiscordWebhookURL) ) {
			$urls[] = $wgDiscordWebhookURL;
		} else {
			wfDebugLog( 'discord', 'The value of $wgDiscordWebhookURL is not valid and therefore no webhooks could be sent.' );
			return false;
		}

		// Set up cURL multi handlers
		$c_handlers = [];
		$result = [];
		$mh = curl_multi_init();

		foreach ($urls as &$value) {
			$c_handlers[$value] = curl_init( $value );
			curl_setopt( $c_handlers[$value], CURLOPT_POST, 1 ); // Send as a POST request
			curl_setopt( $c_handlers[$value], CURLOPT_POSTFIELDS, $json ); // Send the JSON in the POST request
			curl_setopt( $c_handlers[$value], CURLOPT_FOLLOWLOCATION, 1 );
			curl_setopt( $c_handlers[$value], CURLOPT_HEADER, 0 );
			curl_setopt( $c_handlers[$value], CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $c_handlers[$value], CURLOPT_CONNECTTIMEOUT, 10 ); // Add a timeout for connecting to the site
			curl_setopt( $c_handlers[$value], CURLOPT_TIMEOUT, 20 ); // Do not allow cURL to run for a long time
			curl_setopt( $c_handlers[$value], CURLOPT_USERAGENT, 'mw-discord/1.0 (github.com/jaydenkieran)' ); // Add a unique user agent
			curl_multi_add_handle( $mh, $c_handlers[$value] );
			$response = curl_exec( $ch );
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
		} while ($running);

		// Remove all handlers and then close the multi handler
		foreach($c_handlers as $k => $ch) {
			$result[$k] = curl_multi_getcontent($ch);
			curl_multi_remove_handle($mh, $ch);
		}

		curl_multi_close($mh);

		return $result;
	}

	/**
	 * Creates a formatted markdown link based on text and given URL
	 */
	public static function createMarkdownLink ($text, $url)  {
		return "[" . $text . "]" . "(" . self::encodeURL($url) . ")";
	}

	/**
	 * Creates links for a specific MediaWiki User object
	 */
	public static function createUserLinks ($user) {
		$userPage = DiscordUtils::createMarkdownLink(	$user, $user->getUserPage()->getFullUrl( $proto = PROTO_HTTP ) );
		$userTalk = DiscordUtils::createMarkdownLink( 't', $user->getTalkPage()->getFullUrl( $proto = PROTO_HTTP ) );
		$userContribs = DiscordUtils::createMarkdownLink( 'c', Title::newFromText("Special:Contributions/" . $user)->getFullURL( $proto = PROTO_HTTP ) );
		return sprintf( "%s (%s|%s)", $userPage, $userTalk, $userContribs );
	}

	/**
	 * Creates formatted text for a specific Revision object
	 */
	public static function createRevisionText ($revision) {
		$previous = $revision->getPrevious();
		$text = '(' . DiscordUtils::createMarkdownLink( 'diff', $revision->getTitle()->getFullUrl("diff=prev", ["oldid" => $revision->getID()], $proto = PROTO_HTTP) ) . ') ';
		if ($revision->isMinor()) {
			$text .= '(m) ';
		}
		if ($previous) {
			$text .= sprintf( "(%+d)", $revision->getSize() - $previous->getSize() );
		}
		return $text;
	}
	
	/**
	 * Strip bad characters from a URL
	 */
	public static function encodeURL($url) {
		$url = str_replace(" ", "%20", $url);
		$url = str_replace("(", "%28", $url);
		$url = str_replace(")", "%29", $url);
		return $url;
	}
	
	/**
	 * Formats bytes to a string representing B, KB, MB, GB, TB
	 */
	public static function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    $bytes /= (1 << (10 * $pow)); 

    return round($bytes, $precision) . ' ' . $units[$pow]; 
	} 
}

?>
