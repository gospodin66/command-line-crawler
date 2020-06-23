#!/usr/bin/php
<?php
if (PHP_SAPI !== 'cli') { die("Script needs to be run as cli.\n"); }

$short = "p:s:x:";
$long  = ["path:", "scheme:", "torprox:"];

$opts = getopt($short,$long);
if(count($opts) < 2){ 
	die("Assign [-p]--path <example.com> [-s]--scheme <http/s> (optional) [-x]--torprox <1/0>\n");
}
$domain = array_key_exists("path", $opts)   ? trim($opts['path'])   : trim($opts['p']);
$scheme = array_key_exists("scheme", $opts) ? trim($opts['scheme']) : trim($opts['s']);
if(preg_match('/^((http|https)\:\/\/)/', $domain)){
	$domain = preg_replace('/^((http|https)\:\/\/)/', '', $domain);
}
// prox_opt is optional
if(count($opts) === 3){
	$prox_opt = array_key_exists("torprox", $opts)
			  ? intval(trim($opts['torprox']))
			  : intval(trim($opts['x']));
} else {
	$prox_opt = 0;
}

if($prox_opt !== 1){ $prox_opt = 0; }

$publicIp   = publicIp($prox_opt);
$parsed_url = parse_url($scheme."://".$domain);

if(!defined('DATA_DIR')){
	define("DATA_DIR", "crawled_websites/");
}
if(!file_exists(DATA_DIR)){
	mkdir(DATA_DIR);
}

$log  = "";
$results = call__curl($scheme, $domain, $prox_opt);
if(@!$results['status'] && !empty($results['error'])){
	die("> Error: ".$results['error'].",\r\n> Exit..\r\n");
	
}
if($results['info']['http_code'] === 403){
	while(readline('HTTP status 403, repeat? (y/n): ') === 'y'){
		$results = call__curl($scheme, $domain, $prox_opt);
		if(@!$results['status'] && !empty($results['error'])){
			die("> Error: ".$results['error'].",\r\n> Exit..\r\n");
		}
		if($results['info']['http_code'] !== 403){
			break;
		}
	}
}
$_time = date("Y-m-d H:i:s");
$log .= "> Public IP: [".trim($publicIp)."]\r\n\r\n";
$log .= "[".$_time."] > URL: ".$domain."\r\n";
$log .= "[".$_time."] > Host: ".$results['host']." | down/up speed: ".$results['speed']."\r\n";
$log .= "> Page size: [".strlen($results['page'])."] bytes.\r\n";
$log .= "> Content length: ".$results['info']['size_download']." bytes\r\n";
/*$log .= "> Cookies: \r\n";
foreach ($results['cookies'] as $c) {
	$log .= "\t > ".$c."\r\n";
}*/

print "\33[96m> Public IP: [".trim($publicIp)."]\33[0m\n";
print "> Content length: ".$results['info']['size_download']." bytes\n";

file_put_contents(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".txt", $log, FILE_APPEND);
$log = "";

$doc = new DOMDocument();
@$doc->loadHTML($results['page']);

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;

$header_links [] = _get_elements($doc,'meta');
$metas   	  [] = _get_elements($doc,'link');
$imgs 	 	  [] = _get_elements($doc,'img');
$scripts 	  [] = _get_elements($doc,'script');
$hrefs   	  [] = _get_elements($doc,'a');
$forms 	 	  [] = _get_elements($doc,'form');

$json = json_encode([$header_links,$metas,$hrefs,$imgs,$scripts,$forms]);

while(1){
	$time = date("H:i:s");
	print "\33[93mOptions:\ne -exit\np -print main webpage results\nf -follow webpage links\33[0m\n";
	switch (readline("> ")) {
	 	case 'p':
			print_r(json_decode($json));
			echo "\n";
			break;
	 	
		case 'e':
			break 2;

		case 'f':
			follow_links($opts,$doc,$parsed_url['host'],$scheme,$prox_opt);
			break;

	 	default:
			print "\033[31m> Invalid argument.\33[0m\n";
	 		break;
	 } 
}	
// print "\x07"; // beep 
print "\033[32m\nFinished.\n\033[0m";
exit(0);

/******************************************************************************/

function follow_links($opts,$doc,$domain,$scheme,$prox_opt){
	$links     = $doc->getElementsByTagName('a');
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/";
	$crawled   = $header_links = $metas = $hrefs = $imgs = $scripts = $forms = [];

	foreach ($links as $a)
	{	
		foreach ($a->attributes as $link)
		{	
			$log  = "";
			$url  = trim($link->nodeValue);
			$time = date("Y-m-d H:i:s");
			// skip if link empty
			if($url === '#' || empty($url)){
				break;	
			}


			// TODO :: filter links-to-crawl


			// do not download content!!!
			else if(preg_match("/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)/", $url))
			{
				print "\33[94m[".$time."] > Content URL >>> ".$url." >>> skipping..\33[0m\n";
				$log .= "[".$time."] > Content URL [".strlen($url)." bytes] >>> ".$url." >>> skipping..\r\n";
				file_put_contents(DATA_DIR."content_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
				break;
			}
			else if (preg_match("/(\.js$)|(\.php$)/", $url))
			{
				print "\33[95m[".$time."] > Script format >>> skipping..\n\33[0m";
				$log .= "[".$time."] > Script format >>> ".$url." >>> skipping..\r\n";
				break;
			}

			if(in_array($url, $crawled)){
			   break;  
			}
			$crawled [] = $url;
			$log .= "\r\n[".$time."] > URL: ".$url."\r\n";

			if(preg_match($url_regex, $url) === 0){ 
				print "\033[95m> ".$url." >>> Invalid link format..\n> Adapting..\033[0m \n";

				// save link to relative file
				file_put_contents(DATA_DIR."rel_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);

				if($url[0] == "/" && substr($url, 0, 2) != "//")
				{
					$url = $scheme."://".$domain.$url;
				}
				// TODO: test case
				else if(substr($url, 0, 2) == "./")
				{
					$url = $scheme."://".$domain."/".basename($url);
					var_dump($url);
				}
				else if(substr($url, 0, 3) == "../")
				{
					$url = $scheme."://".$domain."/".realpath($url).basename($url);
					var_dump($url);
				}
				else if (substr($url, 0, 11) == "javascript:")
				{
					$log .= "[".$time."] > javascript:  ".$url."\r\nScript format, skipping..\r\n";
					print "\033[95m> Script format, skipping..\033[0m\n";
					break;
				}
				else {
					$url = $scheme."://".$domain."/".$url;
				}
				echo "\33[32m> New url:  ".$url."\33[0m\n";
				$log .= "[".$time."] > New url:  ".$url."\r\n";

			} else {
				file_put_contents(DATA_DIR."abs_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
			}

			if(! check_base_domain($domain,$url,$scheme)){
				$log .= "> Invalid base domain, skipping..\r\n";
				print "\033[95m> Invalid base domain, skipping..\33[0m\n";
				continue;
			}
			$results = call__curl($scheme, $url, $prox_opt);
			if(@!$results['status'] && !empty($results['error'])){
				$log .= "> Error: ".$results['error'].", skipping..\r\n";
				print "> Error: ".$results['error'].", skipping..\n";
				continue;
			}
			$log .= "[".$time."] > Host: ".$results['host']." | down/up speed: ".$results['speed']."\r\n";
			$log .= "> Page size: [".strlen($results['page'])."] bytes.\r\n";
			$log .= "> Content length: ".$results['info']['size_download']." bytes\r\n";
			/*$log .= "> Cookies: \r\n";
			foreach ($results['cookies'] as $c) {
				$log .= "\t > ".$c."\r\n";
			}*/
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
			$log = "";
				
			$doc = new DOMDocument();
			@$doc->loadHTML($results['page']);

			print "> Content length: ".$results['info']['size_download']." bytes\n";
			print "\033[32m> Finished.\n\033[96m> Crawled webpages: ".(count($crawled)-1)."\33[0m\n";
			$log = "[".$time."] > Crawled webpages: ".(count($crawled)-1);
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
		}

		// TODO:: filter arrays!!

		$header_links [] = _get_elements($doc,'meta');
		$metas   	  [] = _get_elements($doc,'link');
		$imgs 	 	  [] = _get_elements($doc,'img');
		$scripts 	  [] = _get_elements($doc,'script');
		$hrefs   	  [] = _get_elements($doc,'a');
		$forms 	 	  [] = _get_elements($doc,'form');

	}
	$json = json_encode([$header_links,$metas,$hrefs,$imgs,$scripts,$forms]);
	print "\33[96mList of crawled pages:\n";
	print_r($crawled);
	print "\33[0m";
	while(1)
	{
		print "\33[93mOptions: \np print all results\nr return\ns save [JSON size: ".strlen($json)." bytes]\33[0m\n";
		switch (readline("> "))
		{
			case 'r':
				break 2;

			case 's':
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".json", $json, FILE_APPEND);
				print "\33[32mSaved.\33[0m\n";
				print "\33[95mResult file >>> ".__DIR__."/".DATA_DIR.$domain.".json\33[0m\n";
				break 2;

			case 'p':
				print_r(json_decode($json));
				echo "\n";	
				break;

		 	default:
			 	print "\033[31m> Invalid argument.\33[0m\n";
			 	break;
		} 
	}
	return;
}

function call__curl($scheme, $url, $prox_opt){
	$ch   = curl_init();
	$opts = select_opts($scheme,$url,$prox_opt);
	curl_setopt_array($ch, $opts);
	$page 	  = curl_exec($ch);
	$curlinfo = curl_getinfo($ch);
	$cookies  = curl_getinfo($ch, CURLINFO_COOKIELIST);
	$host     = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
	$speed    = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
	if(curl_errno($ch)){
		$error = "Crawler error: ".curl_error($ch);
		curl_close($ch);
		return [
			'status' => false,
			'error'  => $error,
		];
	}
	curl_close($ch);
	return [
		'page' 	  => $page,
		'info'    => $curlinfo,
		'host'    => $host,
		'speed'   => $speed,
		'cookies' => $cookies,
	];
}

function select_opts($scheme,$url,$use_prox){
	return [
		CURLOPT_URL 			=> preg_match('/^'.$scheme.'./', $url) ? $url : $scheme."://".$url,
	    CURLOPT_HEADER 			=> 1,
	    CURLOPT_VERBOSE 		=> 1,
	    CURLOPT_RETURNTRANSFER 	=> 1,
	    CURLOPT_CONNECTTIMEOUT 	=> 30,	
	    CURLOPT_TIMEOUT        	=> 30,	
	    CURLOPT_SSL_VERIFYPEER  => $scheme == 'https' ? 1 : 0,
	    CURLOPT_SSL_VERIFYHOST  => $scheme == 'https' ? 2 : 0,
	    CURLOPT_FOLLOWLOCATION	=> 1,
	    CURLOPT_MAXREDIRS 		=> 5,
		CURLOPT_COOKIEJAR		=> 'SessionCookies.txt',
		CURLOPT_COOKIEFILE		=> 'SessionCookies.txt',
		CURLOPT_PROXY 			=> $use_prox === 1 ? '127.0.0.1:9050' : false, 
	    CURLOPT_PROXYTYPE		=> CURLPROXY_SOCKS5_HOSTNAME,
	    CURLOPT_HTTPHEADER		=> [
		    'User-Agent: '.setUserAgent(),
		    'Accept: */*',
		    'Cache-Control: no-cache',
	    ]
	];
}

function check_base_domain($base_domain, $link_domain, $scheme){	

	$link_domain = parse_url($link_domain);

	if(substr($base_domain, 0,4) === "www."){
		$base_domain = substr($base_domain,4);
	}

	if(substr($link_domain['host'], 0,4) === "www."){
		$link_domain['host'] = substr($link_domain['host'],4);
	}

	// subdomain check
    if(strpos($link_domain['host'], $base_domain)){
    	return true;
    }

	return ($base_domain === $link_domain['host']); 
}

function _get_elements($doc,$tag){
	$eles = $doc->getElementsByTagName($tag);
	if(empty($eles[0])){
		return false;
	}
	$elements = [
		'Element' => $eles[0]->nodeName
	];
	if($tag === 'a'){
		foreach ($eles as $a) {
			foreach ($a->attributes as $attr) {
				if(!empty($attr->nodeValue) && $attr->nodeName == "href" && $attr->nodeValue != "#"){
					$elements [] = [
						'Node' 	=> $attr->nodeName,
						'Value' => $attr->nodeValue
					];
				}
			}							
		}
	} else if($tag === 'form'){
		foreach ($eles as $form) {
			foreach ($form->attributes as $attr) {
				if(!empty($attr->nodeValue)){
					$elements [] = [
						'Node' 	=> $attr->nodeName,
						'Value' => $attr->nodeValue
					];
				}
			}		
			if ($form->hasChildNodes()) {
				foreach($form->childNodes as $item){
					$elements [] = [
						'Child_node' => $item->nodeName,
						'Value' 	 => $item->nodeValue
					];
					if($item->attributes !== null){
						foreach($item->attributes as $attr){
							$elements [] = [
								'Child_attr' => $attr->nodeName,
								'Value' 	 => $attr->nodeValue
							];
						}
					}
				}  
			}
		}
	} else {
		foreach ($eles as $ele) {
			foreach ($ele->attributes as $attr) {
				if(!empty($attr->nodeValue)){
					$elements [] = [
						'Node' 	=> $attr->nodeName,
						'Value' => $attr->nodeValue
					];
				}
			}				
		}
	}
	return $elements;
}

function setUserAgent(){
	/*
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/602.1.50 (KHTML, like Gecko) Version/10.0 Safari/602.1.50",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:49.0) Gecko/20100101 Firefox/49.0",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/602.2.14 (KHTML, like Gecko) Version/10.0.1 Safari/602.2.14",
	    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12) AppleWebKit/602.1.50 (KHTML, like Gecko) Version/10.0 Safari/602.1.50",
	    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Safari/537.36 Edge/14.14393"
	    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0",
	    "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36",
	    "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0",
	    "Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko",
	    "Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0",
	    "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36",
	    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:49.0) Gecko/20100101 Firefox/49.0",
	*/
    $agentBrowser = [
        'Firefox',
        'Safari',
        'Opera',
        'Internet Explorer'
    ];
    $agentOS = [
		'Windows Vista',
		'Windows XP',
        'Windows 7',
        'Windows 10',
        'Redhat Linux',
        'Ubuntu',
        'Fedora'
    ];
    return $agentBrowser[rand(0,3)].'/'.rand(1,8).'.'.rand(0,9).'('.$agentOS[rand(0,6)].' '.rand(1,7).'.'.rand(0,9).'; en-US;)';
}

function publicIp($proxopt){
	$ch = curl_init();

	curl_setopt($ch,CURLOPT_URL, 'https://ipinfo.io/ip'); // alt-'https://httpbin.org/ip', 'ipv4.icanhazip.com'
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch,CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'X-Frame-Options: deny',
	    'User-Agent: '.setUserAgent(),
    	'Access-Control-Allow-Origin: null'
	));
    if($proxopt === 1){
		curl_setopt($ch,CURLOPT_PROXY, '127.0.0.1:9050');
    }
	$publicIp = curl_exec($ch);
	// $curlinfo = curl_getinfo($ch);
	$result   = curl_exec($ch);
	curl_close($ch);

	return $result;
}
?>