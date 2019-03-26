<?php

if($argc < 3 || $argc > 4) die("Assign domain and scheme. -> php scrap.php <example.com> <http/https/ftp>\n");

//	1048576 bytes === 1 MB





/*


 	extract_sess_cookies($page,$url) 	>>> 	FIX!!!

 	example lovitalk.fun 	>> 	content links 	>>>		FIX!!!






*/









$domain = trim($argv[1]);	///////// FILTER!!!
$scheme = trim($argv[2]);	///////// FILTER!!!

if(file_exists($domain.".txt") && strlen(file_get_contents($domain.".txt")) > 200){
	unlink($domain.".txt");
	print "\33[93m".$domain.".txt cleared.\33[0m\n";
} 

define("D1", "\n\33[93m**********************************<link>**************************************\n\n\33[0m ");
define("D2", "\n\33[93m**********************************<meta>**************************************\n\n\33[0m ");
define("D3", "\n\33[93m************************************<a>***************************************\n\n\33[0m ");
define("D4", "\n\33[93m***********************************<img>**************************************\n\n\33[0m ");
define("D5", "\n\33[93m**********************************<script>************************************\n\n\33[0m ");


$ch = curl_init();
$log = "\r\n********************************* main page ********************************\r\n";


$opts = isset($argv[3]) && $argv[3] == 1 ? select_opts(1,$scheme,$domain) : select_opts(0,$scheme,$domain);
curl_setopt_array($ch, $opts);
$page = curl_exec($ch);

if(curl_errno($ch)){ 							// check for execution errors
	$log .= "Scraper error: ".curl_error($ch)."\r\n";
	die("Scraper error: ".curl_error($ch)."\n");
}

$curlinfo = curl_getinfo($ch);
curl_close($ch);

//$arg = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);

$host_ip = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
$speed = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
$log .= "> Page size: [".strlen($page)."] bytes.\r\n\n";
$log .= "[".date("H:i:s")."] > Host IP: ".$host_ip." | down/up speed: ".$speed."\r\n";

file_put_contents($domain.".txt", $log, FILE_APPEND);
$log = "";
$s_cookies = extract_sess_cookies($page,$domain);	// retrieve session cookies

$doc = new DOMDocument();
@$doc->loadHTML($page);

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

//file_put_contents($title.".json", $json);
file_put_contents($domain.".json", $json, FILE_APPEND);
print "\33[95mResult file >>> ".__DIR__."/".$domain.".json\33[0m\n";


while(1){
	$time = date("H:i:s");
	print "\33[93mOptions:\ne -exit\np -print entered url webpage results\nf -follow webpage links\33[0m\n";
	switch (readline("> ")) {
	 	case 'p':
		print $string."\n";
		break;
	 	
		case 'e':
		break 2;

		case 'f':
		follow_links($opts,$s_cookies,$doc,$domain,$scheme);
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



function extract_sess_cookies($page,$domain){
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $page, $matches);
	$s_cookies = array();		// all cookies
	$log = "";
	foreach($matches[1] as $item) {

		$time = date("H:i:s");
	    parse_str($item, $cookie);
	    $s_cookies = array_merge($s_cookies, $cookie);
	    $_s_cookies = explode("#", file_get_contents('sess_cookie.txt'));
	    $_s_cookies = array_slice($_s_cookies, 4);



	    $_key___cfduid = ($domain == arrayname) key($_s_cookies);////////////////////////////////
	    $_key_s = strpos($_s_cookies, $s_cookies['S']);//////////////////////////////////////////



	    if(!isset($s_cookies['__cfduid'])){
			$s_cookies['__cfduid'] = trim(substr($_s_cookies[NEEEEED INDEX!!!], 10));

			if(preg_match_all("/^(\w+)\.(\w{2,5})\s+/", $s_cookies['__cfduid'], $matches)){
				$key___cfduid = trim($matches[0][0]);
			}

			
	    }

	    if(!isset($s_cookies['S'])){
			$s_cookies['S'] = trim(substr($_s_cookies[1], 10));
			
			if(preg_match_all("/^(\w+)\.(\w{2,5})\s+/", $s_cookies['S'], $matches)){
				$key_s = trim($matches[0][0]);
			}
			if($)
	    }

	    $log .= empty($s_cookies['S']) || empty($s_cookies['__cfduid'])  ? "" : "[".$time."] > S: ".$s_cookies['S']."; __cfduid: ".$s_cookies['__cfduid']."\r\n";
	}
	
	file_put_contents($domain.".txt", $log, FILE_APPEND);
	return $s_cookies;
}





function follow_links($opts,$s_cookies,$doc,$_url,$scheme){		// $_url <=> domain name

	$links = $doc->getElementsByTagName('a');
	$scraped = array();
	$string = "[";
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	$cookies[] = $s_cookies;		// all cookies

	foreach ($links as $a) {	// loop through elements
		foreach ($a->attributes as $link) {	// loop through attributes
			
			$log = "\r\n-----------------------------------------------------------------\r\n";
			$url = trim($link->nodeValue);
			$time = date("H:i:s");

			if($url == "#" || empty($url) || preg_match("/\/*[_logout\.php]+$/", $url))		//	prevent executing logout script
				break;	// skip if page == _logout.php


			if(preg_match("/(\.apk)|(\.ipa)|(\.mp3)|(\.mp4)|(\.jpg)|(\.png)$/", $url))		//	do not download content!!!
			{
				print "\33[94m[".$time."] > Content URL >>> ".$url." >>> skipping..\33[0m\n";
				$log .= "[".$time."] > Content URL [".strlen($url)." bytes] >>> ".$url." >>> skipping..";
				file_put_contents($_url.".txt", $log, FILE_APPEND);
				break;
			}


			if(in_array($url, $scraped)){
			   break;  
			}
			$scraped[] = $url;


			if(preg_match('/#.+/', $url) == 1 || $url != "href")	// '#' at index[0]
			{
				$log .= "[".$time."] > ".$url." >>> Invalid link format.. >>> Adapting.. \r\n";
				print "\033[95m> ".$url." >>> Invalid link format..\n> Adapting..\033[0m \n";

				if(!preg_match($url_regex, $url) || substr($url, 0, 9) == 'index.php')
				{
					$url = $url[0] == '/' ? $scheme.":/".$_url."/".$url : $scheme."://".$_url."/".$url;
					echo "\33[32m> New url:  ".$url."\33[0m\n";
					$log .= "[".$time."] > New url:  ".$url."\r\n";
				}
			}

			/***	cURL	**/
			$ch   = curl_init();
			$opts = select_opts(0,$scheme,$url);
			curl_setopt_array($ch, $opts);
			
			$page 	  = curl_exec($ch);
			$curlinfo = curl_getinfo($ch);
			$host_ip  = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
			$speed    = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
			$log     .= "[".$time."] > Host IP: ".$host_ip." | down/up speed: ".$speed."\n";
			$log     .= "\r\n> Page size: [".strlen($page)."] bytes.\r\n\n";

			if(curl_errno($ch)){ 							// check for execution errors
				print "Scraper error: ".curl_error($ch)."\n";
				$log = "[".$time."] Scraper error: ".curl_error($ch)."\r\n";
				curl_close($ch);
				break;
			}
			curl_close($ch);

			file_put_contents($_url.".txt", $log, FILE_APPEND);
			$log = "";
			$s_cookies = extract_sess_cookies($page,$_url);

			$doc = new DOMDocument();
			@$doc->loadHTML($page);

			$string .= D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc);
			$string = str_replace("||", "\n", $string);

			print $string."\n";
			print "\033[32m> Finished.\n";
			print "\n\033[96m> Scraped webpages: ".(count($scraped)-1)."\33[0m\n";


			$log = "[".$time."] > Finished. >>> Scraped webpages: ".(count($scraped)-1);
			file_put_contents($_url.".txt", $log, FILE_APPEND);
		}
	}

	print "\33[96mList of scraped pages:\n";
	print_r($scraped);
	print "\33[0m";

	while(1)
	{
		print "\33[93mOptions: \np print all results\nr return\ns save [size: ".strlen($string)." bytes]\33[0m\n";
		switch (readline("> ")) {
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
			file_put_contents($_url.".json", $json, FILE_APPEND);
			print "\33[32mSaved.\33[0m\n";
			break 2;

			case 'p':
			echo $string."\n";
			break;

		 	default:
		 	print "\033[31m> Invalid argument.\33[0m\n";
		 	break;
	} 
}
}

function select_opts($flag,$scheme,$url){

	if($flag == 0){
		return array(
			CURLOPT_URL 			=> preg_match('/^'.$scheme.'./', $url) ? $url : $scheme."://".$url,
		    CURLOPT_HEADER 			=> 1,
		    CURLOPT_VERBOSE 		=> 1,
		    CURLOPT_POST 			=> 0,
		    CURLOPT_RETURNTRANSFER 	=> 1,	// return page content
		    CURLOPT_CONNECTTIMEOUT 	=> 10,	// connect timeout
		    CURLOPT_TIMEOUT        	=> 30,	// response timeout
		    CURLOPT_SSL_VERIFYPEER  => $scheme == 'https' ? 1 : 0,
		    CURLOPT_SSL_VERIFYHOST  => $scheme == 'https' ? 2 : 0,
		    CURLOPT_FOLLOWLOCATION	=> 1,
		    CURLOPT_AUTOREFERER		=> 1,
		    CURLOPT_MAXREDIRS 		=> 10,
			CURLOPT_COOKIEJAR		=> 'sess_cookie.txt',
			CURLOPT_COOKIEFILE		=> 'sess_cookie.txt',
		    CURLOPT_HTTPHEADER		=> array(
			    //'User-Agent: 1977417',
			    'Accept: */*',
		    )
		 );
	}
	else if($flag == 1){
		$post_data = 'login=100000000';
		return array(
			CURLOPT_URL 			=> preg_match('/^'.$scheme.'./', $url) ? $url."/php/_login.php" : $scheme."://".$url."/php/_login.php",
		    CURLOPT_HEADER 			=> 1,
		    CURLOPT_VERBOSE 		=> 1,
		    CURLOPT_POST 			=> 1,
		    CURLOPT_RETURNTRANSFER 	=> 1,	// return page content
		    CURLOPT_CONNECTTIMEOUT 	=> 10,	// connect timeout
		    CURLOPT_TIMEOUT        	=> 30,	// response timeout
		    CURLOPT_SSL_VERIFYPEER  => $scheme == 'https' ? 1 : 0,
		    CURLOPT_SSL_VERIFYHOST  => $scheme == 'https' ? 2 : 0,
		    CURLOPT_FOLLOWLOCATION	=> 1,
		    CURLOPT_AUTOREFERER		=> 1,
		    CURLOPT_MAXREDIRS 		=> 10,
		    CURLOPT_POSTFIELDS		=> $post_data,
			CURLOPT_COOKIEJAR		=> 'sess_cookie.txt',
			CURLOPT_COOKIEFILE		=> 'sess_cookie.txt',
		    CURLOPT_HTTPHEADER		=> array(
			   //'User-Agent: 1977417',
			    'Accept: */*',
				//'Connection: keep-alive',
				//'Content-type: video/mp4'
				//'Upgrade-Insecure-Requests', '1',
				//"Accept-Encoding", "gzip, deflate, br",
				//"Accept-Language", "en-US,en;q=0.9",
		    )
		 );
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
