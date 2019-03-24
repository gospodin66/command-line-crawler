<?php

if($argc < 2) die("Assign argument/s. -> php scrap.php <example.com>\n");
else if ($argc > 2) die("Only 1 argument needs to be asigned.\n");

if(strlen(file_get_contents("Log2.txt")) > 12000){
	unlink("Log2.txt");
	print "\33[93mLog2.txt cleared.\33[0m\n";
} 

include_once('constants.php');

$ch = curl_init();
$url = trim($argv[1]);//"https://inws.site/php/_login.php";//"https://inws.site/index.php";
$post_data = 'login=100000000';

$opts = array(
	CURLOPT_URL 			=> $url,
    CURLOPT_HEADER 			=> 1,
    CURLOPT_VERBOSE 		=> 1,
    CURLOPT_POST 			=> 1,
    CURLOPT_RETURNTRANSFER 	=> 1,	// return page content
    CURLOPT_CONNECTTIMEOUT 	=> 10,	// connect timeout
    CURLOPT_TIMEOUT        	=> 30,	// response timeout
    //CURLOPT_SSL_VERIFYPEER  => 1,
    //CURLOPT_SSL_VERIFYHOST  => 2,
    CURLOPT_FOLLOWLOCATION	=> 1,
    CURLOPT_AUTOREFERER		=> 1,
    CURLOPT_MAXREDIRS 		=> 10,
    CURLOPT_POSTFIELDS		=> $post_data,
    //CURLOPT_BINARYTRANSFER  => true,
	CURLOPT_COOKIEJAR		=> 'sess_cookie.txt', //cookiejar to dump cookie infos.
	CURLOPT_COOKIEFILE		=> 'sess_cookie.txt',
    //CURLOPT_COOKIE   		=> "S: ".$s_cookies['S']."; __cfduid: ".$s_cookies['__cfduid']."",
    CURLOPT_HTTPHEADER		=> array(
	    //'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:66.0) Gecko/20100101 Firefox/66.0',
	    'Accept: */*',
		//'Connection: keep-alive',
		//'Content-type: video/mp4'
		//'Upgrade-Insecure-Requests', '1',
		//"Accept-Encoding", "gzip, deflate, br",
		//"Accept-Language", "en-US,en;q=0.9",
    )
 );
curl_setopt_array($ch, $opts);

$page = curl_exec($ch);

if(curl_errno($ch)){ 							// check for execution errors
	$log2 .= "Scraper error: ".curl_error($ch)."\n";
	file_put_contents("Log2.txt", $log2, FILE_APPEND);
	die("Scraper error: ".curl_error($ch)."\n");
}

$curlinfo = curl_getinfo($ch);
curl_close($ch);

//$arg = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);

$host_ip =  $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
$speed = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
$log2 = "\n-----------------------------------------------------\n\n";
$log2 .= "Host IP: ".$host_ip." | down/up speed: ".$speed."\n\n";
$s_cookies = extract_sess_cookies($page,$log2);	// retrieve session cookies


$doc = new DOMDocument();
@$doc->loadHTML($page);

// # curl --header 'Content-Type: application/json' http://yoururl.com   -------->>> injecting header in url

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;

$string = "[".D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc)."]";
$string = str_replace("||", "\n", $string);
$string = substr_replace($string, '', -3, 2);

$json = str_replace("> ", "", $string);
$json = str_replace(D1, "", $json);
$json = str_replace(D2, "", $json);
$json = str_replace(D3, "", $json);
$json = str_replace(D4, "", $json);
$json = str_replace(D5, "", $json);


if(strlen(file_get_contents($title.".json") == strlen($json))) ///////////	!!!!!!!!!!!!!!!!!!!!!
	echo "\n!!! Files are the same !!! >>> ".strlen(file_get_contents($title.".json"))."/".strlen($json)."\n";

else{
	file_put_contents($title.".json", $json);
}

file_put_contents("Log2.txt", $json, FILE_APPEND);


while(1){
	$time = date("H:m:s");
	print "\33[93mOptions: (e -exit, p -output main webpage results, f -follow): \33[0m";
	switch (readline()) {
	 	case 'p':
		print $string."\n";
		break;
	 	
		case 'e':
		break 2;

		case 'f':
		$log2 .= "\n".$time."Following links..\n";
		follow_links($opts,$s_cookies,$doc,$url);
		break;

	 	default:
		print "\033[31m> Invalid argument.\33[0m\n";
	 	break;
	 } 
}	

print "\x07";	// beep 
print "\033[32m\nFinished.\n\033[0m";
//print `ls -l`; // backtick operator `` <=> shell_exec()

exit();

/*********************************************************************************/
/*********************************************************************************/
/*********************************************************************************/
/*********************************************************************************/
/*********************************************************************************/



function extract_sess_cookies($page,$log2){
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $page, $matches);
	$s_cookies = array();		// all cookies
	foreach($matches[1] as $item) {
	    parse_str($item, $cookie);
	    $s_cookies = array_merge($s_cookies, $cookie);
	    $log2 .= (!empty($s_cookies['S'])) ? "S: ".$s_cookies['S']."; __cfduid: ".$s_cookies['__cfduid']."\n" : "S: \"\"; __cfduid: ".$s_cookies['__cfduid']."\n";
	}
	$log2 .="\n";
	file_put_contents("Log2.txt", $log2, FILE_APPEND);
	return $s_cookies;
}






function follow_links($opts,$s_cookies,$doc,$arg){

	$links = $doc->getElementsByTagName('a');
	$scraped = array();
	$string = "[";
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	$cookies[] = $s_cookies;		// all cookies
	$log = "";
	$delimiter = "-----------------------------------------------------------------\n";



		foreach ($links as $a) {	// loop through elements
			foreach ($a->attributes as $link) {	// loop through attributes
					
				$url = trim($link->nodeValue);
				$time = date("H:m:s");

				if($url == "#" || empty($url))
					break;

				$log .= $delimiter;
				if(preg_match('/#.+/', $url) == 1 || $url != "href")	// '#' at index[0]
				{
					$log .= "[".$time."] > ".$url." --- Invalid link format.. >>> Adapting.. \n";
					print "\033[95m> ".$url." --- Invalid link format..\n> Adapting..\033[0m \n";

					if(!preg_match($url_regex, $url) || substr($url, 0, 9) == 'index.php')
					{
						$url = ($url[0] == '/') ? "https:/".$arg."/".$url : "https://".$arg."/".$url;
						echo "\33[32m> New url:  ".$url."\33[0m\n";
						$log .= "[".$time."] > New url:  ".$url."\n";
					}
				}

				if(in_array($url, $scraped)){
				   break;  
				}
				$scraped[] = $url;

				/***	cURL	**/
				$ch = curl_init();
				curl_setopt_array($ch, $opts);
				
				$page 	  = curl_exec($ch);
				$curlinfo = curl_getinfo($ch);
				$host_ip  = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
				$speed    = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
				$log     .= "Host IP: ".$host_ip." | down/up speed: ".$speed."\n";

				if(curl_errno($ch)){ 							// check for execution errors
					print "Scraper error: ".curl_error($ch)."\n";
					$log .= "[".$time."] Scraper error: ".curl_error($ch)."\n";
					curl_close($ch);
					break;
				}
				curl_close($ch);

				preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $page, $matches);
				foreach($matches[1] as $item) {
 				   parse_str($item, $cookie);
				    $cookies = array_merge($cookies, $cookie);
					$log .= (!empty($s_cookies['S'])) ? "S: ".$s_cookies['S']."; __cfduid: ".$s_cookies['__cfduid']."\n" : "S: \"\"; __cfduid: ".$s_cookies['__cfduid']."\n";
				}


				$doc = new DOMDocument();
				@$doc->loadHTML($page);

				$string .= D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc);
				$string = str_replace("||", "\n", $string);

				print $string."\n";
				print "\033[32m> Finished.\n\n";
				print "\n\033[96m>Scraped webpages: ".(count($scraped)-1)."\33[0m\n\n";
				sleep(1);


				$log .= "[".$time."] > Finished.\n".$delimiter."> Scraped webpages: ".(count($scraped)-1)."\n";


				file_put_contents("Log.txt", $log);
			}
		}
		print "\33[96mList of scraped pages:\n";
		print_r($scraped);
		print "\33[0m";

		while(1){
		print "\33[93mOptions: (r return, s save [size: ".strlen($string)." bytes]): \33[0m";
		switch (readline()) {
			case 'r':
			break 2;

			case 's':
			$string = substr_replace($string, ']', -2, 2);
			$json = str_replace("> ", "", $string);
			$json = str_replace(D1, "", $json);
			$json = str_replace(D2, "", $json);
			$json = str_replace(D3, "", $json);
			$json = str_replace(D4, "", $json);
			$json = str_replace(D5, "", $json);
			file_put_contents("ScrapedPages.json", $json);
			print "\33[32mSaved.\33[0m\n";
			break 2;

		 	default:
		 	print "\033[31m> Invalid argument.\33[0m\n";
		 	break;
		} 
	}
}






function header_links($doc){

	$links = $doc->getElementsByTagName('link');
	$fmtd = "";

	foreach ($links as $link) {	 // loop through elements
		$fmtd .= '> {"Element": "<'.$link->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($link->attributes as $attr) {	// loop through attributes
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes(trim($attr->nodeValue),'"').'"},||';
		}				
	}
	return $fmtd;
}

function hrefs($doc){

	$links = $doc->getElementsByTagName('a');
	$fmtd = "";

	foreach ($links as $a) {	// loop through elements
		$fmtd .= '> {"Element": "<'.$a->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($a->attributes as $attr) {	// loop through attributes

			$fmtd .= ($attr->nodeValue == "#" || $attr->nodeName != "href" || empty($attr->nodeValue))
				     ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function metas($doc){

	$metas = $doc->getElementsByTagName('meta');
	$fmtd = "";

	foreach ($metas as $meta) {
		$fmtd .= '> {"Element": "<'.$meta->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($meta->attributes as $attr) {	

			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function imgs($doc){

	$imgs = $doc->getElementsByTagName('img');
	$fmtd = "";

	foreach ($imgs as $img) {
		$fmtd .= '> {"Element": "<'.$img->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($img->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function scripts($doc){

	$scripts = $doc->getElementsByTagName('script');
	$fmtd = "";

	foreach ($scripts as $script) {
		$fmtd .= '> {"Element": "<'.$script->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($script->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addslashes($attr->nodeValue).'"},||';
		}							
	}
	return $fmtd;
}

function divs($doc){

	$scripts = $doc->getElementsByTagName('div');
	$fmtd = "";

	foreach ($scripts as $script) {
		$fmtd .= '> {"Element": "<'.$script->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($script->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addslashes($attr->nodeValue).'"},||';
		}							
	}
	return $fmtd;
}

?>
