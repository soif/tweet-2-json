#tweets2array Documentation

##About 

tweets2array is a Twitter scraper that functions like an API. It takes data publicly available on Twitter.com and reformats it into programming-friendly Array or JSON for your hacking needs.
###But doesn't Twitter already have an API?

Yep--and if you're OK with their terms of use you should use it. 

Some reasons you might want to use this instead:

*	You don't like restrictive display guidelines
*	You don't like authenticating
*	You don't like rate limits
*	You want tweeted images from Instagram to show up as cards like every other service
*	You're punk rock

##Setup

tweets2array is stupidly easy to install: download the script and put it where you want to run it. It uses `json_encode()` and so requires PHP 5.2 or better.

Comments in the code and the [Usage](#usage) section below will indicate how to target the tweets you want. By default, tweets2array.php returns an array.

 If you're using PHP and integrating tweets2array into an existing script, then
	
	include('tweets2array.class.php'); 

will do it, provided the file path in your `include()` is correct.

<a id="usage"></a>
##Usage


###Init

	$tw=new tweets2array();

will initialize the class, and results will be arrays object.

If you want to init the class like the original (legacy) tweet2json.php , do this:

	$tw=new tweets2array('json');

The output will be JSON encoded.

###Methods
tweets2array has two main public methods: `GetUser()`, which returns tweet data from a public account by username, and `GetSearch()`, which returns public tweet data from Twitter search based on a given query.

you might cache queries by using the `SetCache()` method. (See example.php file)

####GetUser Method

`GetUser()`  returns tweet data from a public account by username. It accepts four arguments, only of which is required.

>GetUser(**username** [string, required], **results** [integer, default = 0 (returns all), max = ~20], **cards** [boolean, default = FALSE])

**username** is the username for the public twitter account you're targeting. As a string, it needs to be set off with single or double quotes.

**number of results** is the number of tweets you want the function to return. The default value is 0, which returns all the tweets displayed on the user's Twitter page--usually around 20. You can enter any value in here, but it will just return blank tweet objects after the script returns responses for all the tweets on the website.

**cards**, when set to TRUE, returns results with something resembling Twitter cards; basically, programmatically useful representations of rich media in tweets. **cards** adds an extra page grab to each tweet, slowing down the function's response time substantially.  That's why it defaults to FALSE.

An example command:

	$tw->GetUser('cosmocatalano', 1, TRUE);

#####Return

`GetUser()` Returns an Array object with an entry for each tweet. It looks like this:

    [3] => Array
        (
            [url] => http://twitter.com/cosmocatalano/status/384801341216133120
            [text] => Rx-only toothpaste I had an Rx for but bought on Amazon (w/o proof of Rx) instead. Rx is fucking dumb.… http://instagram.com/p/e5pCcyondH/ 
            [html] => Rx-only toothpaste I had an Rx for but bought on Amazon (w/o proof of Rx) instead. Rx is fucking dumb.… <a href="http://t.co/WjxmxSLYUQ" rel="nofollow" dir="ltr" data-expanded-url="http://instagram.com/p/e5pCcyondH/" class="twitter-timeline-link" target="_blank" title="http://instagram.com/p/e5pCcyondH/" ><span class="tco-ellipsis"></span><span class="invisible">http://</span><span class="js-display-url">instagram.com/p/e5pCcyondH/</span><span class="invisible"></span><span class="tco-ellipsis"><span class="invisible">&nbsp;</span></span></a>
            [date] => 1380578763
            [user] => /cosmocatalano
            [id] => 14503633
            [img] => https://si0.twimg.com/profile_images/2225916199/image_normal.jpg
            [name] => 
            [rt] => 
            [card] => Array
                (
                    [href] => http://instagram.com/p/e5pCcyondH/
                    [data-url] => http://distilleryimage11.ak.instagram.com/3b7840722a1c11e390e022000a1fd202_8.jpg
                    [data-resolved-url-large] => http://distilleryimage11.ak.instagram.com/3b7840722a1c11e390e022000a1fd202_8.jpg
                )

        )
 
**url** is the permalink of the tweet.

**text** is the plaintext contents of the tweet.

**html** is the HTML of the tweet, escaped and with Twitter's classes preserved.

**date** is the Unix timestamp of the tweet.

**user** is the username of the tweet author, with a leading slash.

**id** is the user id of the tweet author.

**img** is the location of the tweet author's avatar.

**name** is the human-friendly name of the tweet author.

**rt** indicates whether or not the tweet is a retweet.

**card** is the array of rich-media data associated with a tweet. 

For _images_ (Twitpic, Instagram (yes!) etc.):

*	**href** the location of the page containing the rich media
*	**data-url** the location of a smaller-sized version of the image itself
*	**data-resolved-url-large** the location of the full-size version of the image itself

For _video_ (Vine, YouTube, Vimeo):

*	**iframe** the HTML iframe that displays the video.
*	**href** the location of the page containing the video.

####GetSearch Method

`GetSearch()` returns public tweet data from Twitter search based on a given query. It accepts four arguments, one of which is required.

>GetSearch(**query** [string, required],**results** [integer, default = 0 (returns all), max = ~20], **cards** [boolean, default = FALSE], **realtime** [boolean, default = TRUE])

**query** is the string you're searching Twitter for. As a string, it needs to be set off with single or double quotes. You _can_ still use double quotes to match a string exactly. The command also accepts spaces, octothorps ('#') to look for hashtags, at-signs ('@') to search for replies, but may gag on other special characters, especially if they're HTML code. You've been warned.

**number of results** is the number of tweets you want the function to return. The default value is 0, which returns all the tweets displayed on the user's Twitter page--usually around 20. You can enter any value in here, but it will just return blank tweet objects after the script returns responses for all the tweets on the website.

**cards**, when set to TRUE, returns results with something resembling Twitter cards; basically, programmatically useful representations of rich media in tweets. **cards** adds an extra page grab to each tweet, slowing down the function's response time substantially.  That's why it defaults to FALSE.

**realtime**, when set to TRUE, returns a real-time result of Tweets. Setting this to FALSE will return the "Top Tweets" based on whatever Twitter uses to make that designation.

An example command:

	$tw->GetSearch('obama', 1, TRUE, FALSE);
	
#####Return

`GetSearch()` Returns a JSON object with an entry for each tweet, with the same values as `user_tweets()` listed above.


##FAQ

###Won't Twitter just block this?

They could, but it would be hard to do because of user-agent spoofing, distribution across different IPs and the like. 

###Isn't this scrape subject to failing at any time?

Yes--Twitter is extremely likely to break it with design updates from time to time, which is why all the regexes and explode strings that it uses are stored in an array at the front of the script for easy repair. I plan to maintain it as closely as I can.

###You used regex for parsing HTML?

Father forgive me for I have sinned.

