<?php
/*
Twitter2array — the Twitter scrape API
https://github.com/cosmocatalano/tweet-2-json

Originally by Cosmo Catalano - http://cosmocatalano.com
Updated by Soif - https://github.com/soif

version 1.0
*/

class Tweets2array {


	//here are the strings this script uses to find the data it wants. 
	//Twitter might change them—they're up here so you can update & repair easily.
	var $finders = array(
	    'onebox-find'			=>	    '/<div class="onebox[\s\S]*<h2 class="/U',
	    'onebox-replace' 		=>		'<h2 class="',

		'content-explode-top'	=> 	' tweet-text',    //relies on another class being assigned before it. very sketchy.
		'content-explode-bottom'=> 	'</p>',
		
		'profile_regexs'		=>		array(
											'name' 				=>'#"fullname\seditable-group">.*?"profile-field">([^<]+)#s',
											'bio' 				=>'#"bio\sprofile-field">([^<]+)#s',
											'location' 			=>'#"location\sprofile-field">([^<]+)#s',
											'url' 				=>'#"url editable-group".*?"profile-field">.*?title="([^"]+)#s',
											'img'				=>'#data-resolved-url-large="([^"]+)#s',
										),

		'stats_regexs'		=>		array(
											'id'				=>'#"stats\sjs-mini-profile-stats"\sdata-user-id="([^"]+)#s',
											'tweets_count'		=>'#data-nav=\'profile\'.*?<strong>([^<]+)#s',
											'followers_count'	=>'#data-nav=\'followers\'.*?<strong>([^<]+)#s',
											'following_count'	=>'#data-nav=\'following\'.*?<strong>([^<]+)#s',
										),

		'avatar-explode-top'	=>		'class="account-group', //actually, any explode scraping is kinda sketch
		'avatar-explode-bottom'	=>		'/strong>',
		'avatar_regexs' 		=> 	array(
											'user'   => '/href\="([\/A-z0-9-_]*)/',
											'id'     => '/data-user-id\="([0-9-_]*)/',
											'img'    => '/src\="([A-z0-9\-\_\:\/\/\.]*)/',
											'name'   => '/show-popup-with-id">([^<]*)/'
										),
									
		'links-regex'			=> 	'/<a class="details with-icn js-details" href="([\/A-z0-9]*)">/',
		'dates-regex'       	=> 	'/data\-time\="([0-9]*)"/',
		'cards-regex'			=> 	'/media-thumbnail[^&][\s\SA-z0-9\"\=\-\:\/\?\&\;\_]*>/U',
		'video-regex'			=>		'/<iframe class="card2-player-iframe"[\s\S]*<\/h3>/',
	
		'cards_data_regexs' 	=> 	array(
											'href'   => 'href',
											'src'    => 'data-url',
											'src-lg' => 'data-resolved-url-large'
									),
		'video_data_regexs' 	=> 	array(
											'iframe'   => '/(<iframe class="card2-player-iframe"[\s\S]*<\/iframe>)/',
											'href'    => '/href="([\S]*)"/',
									)							
	);

	var $output_format	='array'; 	// json | array

	//cache 
	var $use_cache		=FALSE;
	var $cache_path		='';
	var $cache_time		=3600;
	var $cache_mode		='out';
	var $cache_file		='';
	var $cache_id		='';
	
	//#######################################################################################################################
	// PUBLIC METHODS #######################################################################################################
	//#######################################################################################################################

	//----------------------------------------------------------------------------------------------------------------------
	function __construct($output_format='array'){
		$this->SetOutputFormat($output_format);
	}

	// ---------------------------------------------------------------------------------------------------------------------
	//These are the functions the user should actually call
	public function GetUser($username, $itr = 0, $find_cards = FALSE) {
		if($return = $this->scrape_spit($username, '', $find_cards, $itr)){
			return $this->FormatOutput($return);
		}
	}
	
	// ---------------------------------------------------------------------------------------------------------------------
	public function GetSearch($query, $itr = 0, $find_cards = FALSE, $realtime = TRUE) {
		if($return = $this->scrape_spit($query, 'search', $find_cards, $itr, $realtime)){
			return $this->FormatOutput($return);
		}
	}
	
	// ---------------------------------------------------------------------------------------------------------------------
	public function SetCache($cache_path='',$cache_time=3600,$cache_mode="out"){
		if($cache_path and $cache_path and $cache_mode){
			$this->use_cache= true;
		}
		if($cache_path and file_exists($cache_path)){
			if(!preg_match('#/$#',$cache_path)){$cache_path.="/";}
			$this->cache_path	=$cache_path;
		}
		if(in_array($cache_mode,array('in','out'))){
			$this->cache_mode=$cache_mode;
		}
		$this->cache_time	=$cache_time;
	}


	// ---------------------------------------------------------------------------------------------------------------------
	public function SetOutputFormat($output_format){
		if(in_array($output_format, array('array','json'))){
			$this->output_format=$output_format;
		}		
	}


	//#######################################################################################################################
	// PRIVATE METHODS #######################################################################################################
	//#######################################################################################################################


	// ---------------------------------------------------------------------------------------------------------------------
	//removes content-less onebox that fools avatar search
	private function kill_onebox ($source) {
		return preg_replace($this->finders['onebox-find'], $this->finders['onebox-replace'], $source);
	}
	

	// ---------------------------------------------------------------------------------------------------------------------
	//breaks the page into chunks of containing to tweets & data, more or less			
	private function tweet_content($source, $itr) {
		$scrubs  = array(
				0 => '/<\/?[sb]>/',
				1 => '/href="\//',
			);
		$ringers = array(
				0 => '',
				1 => 'href="http://twitter.com/',
			);
		$shards = explode($this->finders['content-explode-top'], $source);
		$tweets = array();
		if ($itr == FALSE) {
			$itr = count($shards);
		}
		for ($i = 1;  $i <= $itr; $i++) {
			$dirty_tweet = explode($this->finders['content-explode-bottom'], $shards[$i]);
			$clean_tweet = ltrim($dirty_tweet[0],'">' );
			$replaced = preg_replace($scrubs, $ringers, $clean_tweet);
			array_push($tweets, $replaced);
		}
		return $tweets;
	}


	// ---------------------------------------------------------------------------------------------------------------------
	//This pulls avatar src, username (of tweeter), name, id
	private function tweet_avatar($source, $itr) {
		$patterns = $this->finders['avatar_regexs'];
		$shards = explode($this->finders['avatar-explode-top'], $source);
		$avatars = array();
		if ($itr == FALSE) {
			$itr = count($shards);
		}
		for ($i = 1;  $i <= $itr; $i++) {
			$dirty_avatar = explode($this->finders['avatar-explode-bottom'], $shards[$i]);
			array_push($avatars, $dirty_avatar[0]);
		}
		$clean_data = array();
		foreach ($avatars as $avatar) {
			$avatar_data = array();
			foreach($patterns as $pattern) {
				preg_match($pattern, $avatar, $matches);
				array_push($avatar_data, $matches[1]);
			}
			array_push($clean_data, $avatar_data);
		}
		return $clean_data;			
	}

	// ---------------------------------------------------------------------------------------------------------------------
	// pulls the links from a tweet
	private function tweet_links($source) {
		preg_match_all($this->finders['links-regex'], $source, $links);
		return $links[1];
	}

	// ---------------------------------------------------------------------------------------------------------------------
	//pulls the timestamps from a tweet
	private function tweet_dates($source) {
		preg_match_all($this->finders['dates-regex'], $source, $timestamps);
		return($timestamps[1]);
	}

	// ---------------------------------------------------------------------------------------------------------------------
	//pulls profile infos
	private function extract_profile($source) {
		$out=array();
		foreach($this->finders['profile_regexs'] as $key => $pattern){
			preg_match($pattern, $source, $matches);
			$out[$key]=trim(html_entity_decode($matches[1]));
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------------------------------
	//pulls stats infos
	private function extract_stats($source) {
		$out=array();
		foreach($this->finders['stats_regexs'] as $key => $pattern){
			preg_match($pattern, $source, $matches);
			$out[$key]=trim(preg_replace('#[^\d]+#','',$matches[1]));
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------------------------------
	// need to be improved, using curl, supporting timeout, user-agent, etc....
	private function Fetch($url){
		if($this->cache_mode=='out'){
			return file_get_contents($url);
		}
		if(! $result=$this->LoadCache($url)){
			$result=file_get_contents($url);
			$this->StoreCache($result);
		}
		return $result;
	}

	// ---------------------------------------------------------------------------------------------------------------------
	private function LoadCache($url_or_username){
		if(!$this->use_cache){
			return false;
		}
		if($this->cache_mode=='in'){
			$this->cache_file=$this->cache_path.'in_'.md5($url_or_username).'.htm.txt';
		}
		else{ //out mode
			$this->cache_file=$this->cache_path.'out_'.$url_or_username.'_'.$this->cache_id.'.json.txt';
		}
		if(file_exists($this->cache_file) and (filemtime($this->cache_file) + $this->cache_time) > time() ){
			$cache=file_get_contents($this->cache_file);
			if($this->cache_mode=='out'){
				$cache=json_decode($cache,TRUE);
			}
			return $cache;
		}
	}

	// ---------------------------------------------------------------------------------------------------------------------
	private function StoreCache($result){
		if($this->use_cache and $this->cache_file){
			if($this->cache_mode=='out'){
				$result=json_encode($result);
			}
			file_put_contents($this->cache_file,$result);
		}
	}

	// ---------------------------------------------------------------------------------------------------------------------
	//pulls any twitter "cards" from tweets, including making up fake ones out of instagram
	private function get_cards($url) {
		$source = $this->Fetch($url);
		$pattern = $this->finders['cards-regex'];
		preg_match($pattern, $source, $matches);
	
	//looking for a youtube/vine/video generally link
		if ($matches === array()) {
			$vid_pattern = $this->finders['video-regex'];
			preg_match($vid_pattern, $source, $vid_matches);
			if ($vid_matches !== array()) {
				$needles = $this->finders['video_data_regexs'];
				$card_data = array();
				foreach ($needles as $eye => $needle) {
					preg_match($needle, $vid_matches[0], $vid_cards);
					$card_data[$eye] = $vid_cards[1];
				}
				return $card_data;
			}
		}	
	

	//if no matches are found, look for an instagram link
		if ($vid_matches === array()) {
			$pattern = '/instagram\.com\/p\/[A-z0-9\_\-]*\/?/';    //Instagram might change their URL structure sometime, requiring an update to this
			preg_match($pattern, $source, $ig_matches);
		

	//if an instagram link is found, suck its data into the $matches array
			if ($ig_matches !== array()) {
				$ig_url = 'http://'.$ig_matches[0];
				$ig_source = $this->Fetch($ig_url);
				$ig_pattern = '/<meta property="og:image" content="http:\/\/([a-z0-9\.\/\_]*)"/';  //Also unlikely, but this might change too
				preg_match($ig_pattern, $ig_source, $src_matches);

	//matching the Twitter classes for cards. Kept the same class assignments, but it's not necessary.
				$matches['href'] = $ig_url;
				$matches['data-url'] = 'http://'.str_replace('_7.jpg', '_5.jpg', $src_matches[1]);
				$matches['data-resolved-url-large'] = 'http://'.$src_matches[1];
				$card_data = $matches;

	//if nothing is found, set $matches to 0
			}
			else{
				$card_data = 0;
			}	
				
	//returning to a situation where standard twitter card matches are found	
		}
		else{
			$targets = $this->finders['cards_data_regexs']; 
			$card_data = array();
			foreach($targets as $target) {
				preg_match('/'.$target.'="([\S]*)"/', $matches[0], $card_attr); //this shouldn't need to change
				$card_data[$target] = $card_attr[1];
			}
		}
		return $card_data;	
	}

	// ---------------------------------------------------------------------------------------------------------------------
	private function scrape_spit ($user_target, $search, $find_cards, $itr, $realtime = FALSE) {
		$this->cache_id=md5("$user_target,$search,$find_cards,$itr,$realtime");

		if($cached=$this->LoadCache($user_target)){
			return $cached;
		}
		
		//cleaning up user inputs
		if ($search === '') {
			$dirty_target = str_replace('@', '', $user_target);
		}
		else {
			$search = 'search/';
			$dirty_target = $user_target;
		}
		if ($realtime == TRUE) {
			$search = 'search/realtime/';
			$dirty_target = $user_target;
		}
		$target = urlencode($dirty_target);

		//initial scrape
		$onebox_source = $this->Fetch("http://twitter.com/".$search.$target);

		// get profile
		$out=$this->extract_profile($onebox_source);
		$out=array_merge($out,$this->extract_stats($onebox_source));

		//extract tweets
		$source = $this->kill_onebox($onebox_source);

		//re-organizing the data with functions
		$avatars= $this->tweet_avatar($source, $itr);
		$tweets	= $this->tweet_content($source, $itr);
		$links	= $this->tweet_links($source, $itr);
		$dates	= $this->tweet_dates($source);
	
		//some characters that need attention
		$html_scrubs = array('&nbsp;','&#39;','&quot;', '&lt;', '&rt;');
		$html_ringers = array(    ' ',    "'",     '"',    '<',  '>');
	
		//Checking user preferences on how much data to send back
		$all_tweets = array();
		if ($itr == FALSE) {
			$real_itr = count($tweets) - 1;
		}
		else{
			$real_itr = $itr;
		}
	
		//Checking for RTs
		for ($i = 0; $i < $real_itr; $i++) {  
			if ($search === '' AND '/'.strtolower($target) === strtolower($avatars[$i][0])) {
				$is_rt = FALSE;
			}
			elseif ($search !== '') {
				$is_rt = FALSE;
			}
			else{
				$is_rt = TRUE;
			}
		
			//creating the return array for each tweet
			$each_tweet = array(
				'url'	 => 'http://twitter.com'.$links[$i],
				'text'   => html_entity_decode(str_replace($html_scrubs, $html_ringers, strip_tags($tweets[$i]))),
				'html'  => $tweets[$i],
				'date' 	 => $dates[$i],
				'user'   => preg_replace('#^/#','',$avatars[$i][0]),
				'id'     => $avatars[$i][1],
				'img'    => $avatars[$i][2],
				'name'   => $avatars[$i][3],
				'rt'     => $is_rt,
			);
	
			//because searching for cards takes FOREVER
			if ($find_cards != FALSE) {
				$card = $this->get_cards('http://twitter.com'.$links[$i]);
				if ($card !== 0) {
					$each_tweet['card'] = $card;
				}
				else{
					unset($each_tweet['card']);
				}
			}
			array_push($all_tweets, $each_tweet);
		}

		$out['tweets']=$all_tweets;
		$this->StoreCache($out);
		return $out;
	}


	// ---------------------------------------------------------------------------------------------------------------------
	private function FormatOutput($array) {
		if($this->output_format=='json'){
			return json_encode($array);	
		}
		return $array;
	}
}
?>