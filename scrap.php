<?php

if($argc < 3 || $argc > 4) die("Assign domain and scheme. -> php scrap.php <example.com> <http/https/ftp>\n");

//	1048576 bytes === 1 MB

//////////////////////////////////////////////////////////////////////////////////////
//		TODO: DODATI NAZIVE STRANICA KOJE SU NA REDU ZA SCRAP U LOG I CLI OUTPUT
//			  U LOG TREBA I REZULTAT CJELOKUPNOG SCRAP-ANJA
//			  FILTRIRATI REZULTATE ====> BRISATI DUPLIKATE
//
//		<chmod>
//		number 1  execute rights
//		number 2  file writeable
//		number 4  file readable 
//
//
//
//////////////////////////////////////////////////////////////////////////////////////

$domain = trim($argv[1]);	///////// FILTER
$scheme = trim($argv[2]);	///////// FILTER

if(file_exists($domain.".txt") && strlen(file_get_contents($domain.".txt")) > 200){
	unlink($domain.".txt");
	print "\33[93m".$domain.".txt cleared.\33[0m\n";
} 

define("D1", "\n\33[93m**********************************<link>**************************************\n\n\33[0m");
define("D2", "\n\33[93m**********************************<meta>**************************************\n\n\33[0m");
define("D3", "\n\33[93m************************************<a>***************************************\n\n\33[0m");
define("D4", "\n\33[93m***********************************<img>**************************************\n\n\33[0m");
define("D5", "\n\33[93m**********************************<script>************************************\n\n\33[0m");


$ch = curl_init();
$log = "\r\n********************************* main ************************************\r\n";

////**** 1	****////
$opts = isset($argv[3]) && $argv[3] == 1 ? select_opts(1,$scheme,$domain) : select_opts(0,$scheme,$domain);
curl_setopt_array($ch, $opts);
$page = curl_exec($ch);

if(curl_errno($ch)){ 	// check for execution errors
	$log .= "Scraper error: ".curl_error($ch)."\r\n";
	die("Scraper error: ".curl_error($ch)."\n");
}

$curlinfo = curl_getinfo($ch);
curl_close($ch);


$host_ip = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
$speed = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
$log .= "> Page size: [".strlen($page)."] bytes.\r\n\n";
$log .= "[".date("H:i:s")."] > Host IP: ".$host_ip." | down/up speed: ".$speed."\r\n";

file_put_contents($domain.".txt", $log, FILE_APPEND);
$log = "";

if(extract_cookies($page,$domain) === FALSE);	// retrieve session cookies
	print "\33[31mNo cookies extracted.\33[0m\n\n";

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


while(1){
	$time = date("H:i:s");
	print "\33[93mOptions:\ne -exit\np -print main webpage results\nf -follow webpage links\33[0m\n";
	switch (readline("> ")) {
	 	case 'p':
		print $string."\n";
		break;
	 	
		case 'e':
		break 2;

		case 'f':
		follow_links($opts,$cookies,$doc,$domain,$scheme);
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


/****************************************************************************************/
/****************************************************************************************/
/****************************************************************************************/


function extract_cookies($page,$domain){		

	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $page, $matches);
	$cookie = array();	// all cookies
	$log = "";
	foreach($matches[1] as $item) {

		$time = date("H:i:s");
	    parse_str($item, $cookie);
	    $_s_cookies = explode("#", file_get_contents('SessionCookies.txt'));
	    $_s_cookies = array_slice($_s_cookies, 4);	//	cut SessionCookies.txt header


	    // TODO: AKO NEMA COOKIE-A, ONDA SE UZIMA ZADNJI IZ .txt
	    // SessionCookies.txt position indexes
	   	$indexS=0;		
	    $indexcfduid=0;

	   	for($i=0;$i<count($_s_cookies);$i++){	
	   		
	   		//	53 - start of '__cfduid' => cut the rest
			if(!isset($cookie['__cfduid'])){
				if(preg_match('/'.$domain.'/', $_s_cookies[$i])){
					$cookie['__cfduid'] = trim(substr($_s_cookies[$i],-44));
					$indexcfduid = $i;
				}
			}
			////**** 1	****////
			//	36 - start of 'S' => cut the rest
			if(!isset($cookie['S'])){	
				if(preg_match('/'.$domain.'/', $_s_cookies[$i])){
					$cookie['S'] = $domain == substr($_s_cookies[$i], 10, strlen($domain)) ?: trim(substr($_s_cookies[$i],-26));
					// compare domain with .txt cookies ==> 1st GET req. (_login.php) sends no cookies 
				 	$indexS = $i;
				}
			}
	   	}
	   	// 3 scenarios when handling links
	   	if(isset($cookie['S']) && isset($cookie['__cfduid'])){
	   		$log .= "[".$time."] > S: ".$cookie['S']."; __cfduid: ".$cookie['__cfduid']."\r\n";
	   	}
	   	else if (!isset($cookie['S']) && isset($cookie['__cfduid'])){
			$log .= "[".$time."] > S: ".trim(substr($_s_cookies[$indexS],36))."; __cfduid: ".$cookie['__cfduid']."\r\n";
	   	}
	   	else if (!isset($cookie['__cfduid']) && isset($cookie['S'])){
	   		$log .= "[".$time."] > S: ".$cookie['S']."; __cfduid: ".trim(substr($_s_cookies[$indexcfduid],53))."\r\n";
	   	}

	}

	file_put_contents($domain.".txt", $log, FILE_APPEND);
}

/****************************************************************************************/

function follow_links($opts,$cookies,$doc,$domain,$scheme){		

	$links = $doc->getElementsByTagName('a');
	$crawled = array();
	$string = "[";
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

	foreach ($links as $a) {	// loop through elements
		foreach ($a->attributes as $link) {	// loop through attributes
			
			$log = "\r\n-----------------------------------------------------------------\r\n";
			$url = trim($link->nodeValue);
			$time = date("H:i:s");


			if($url == "#" || empty($url) || preg_match("/\/*[_logout\.php]+$/", $url))		//	prevent executing logout script
				break;	// skip if page == _logout.php 		////**** 1	****////


			////////		TODO: LISTA FORMATA

			if(preg_match("/(\.apk)|(\.ipa)|(\.mp3)|(\.mp4)|(\.jpg)|(\.png)$/", $url))		//	do not download content!!!
			{
				print "\33[94m[".$time."] > Content URL >>> ".$url." >>> skipping..\33[0m\n";
				$log .= "[".$time."] > Content URL [".strlen($url)." bytes] >>> ".$url." >>> skipping..";

				file_put_contents($domain.".txt", $log, FILE_APPEND);
				break;
			}


			if(in_array($url, $crawled)){
			   break;  
			}
			$crawled[] = $url;

			////**** 1	****////
			if(preg_match('/#.+/', $url) == 1 || $url != "href")	// '#' at index[0]
			{
				$log .= "[".$time."] <".$url.">\r\n";
				print "\033[95m> ".$url." >>> Invalid link format..\n> Adapting..\033[0m \n";

				if(!preg_match($url_regex, $url) || substr($url, 0, 9) == 'index.php')
				{
					// TODO: FILTRIRATI URL KOJI NIJE PATH


				



					if($url[0] == "/" && substr($url, 0, 2) != "//"){
						$url = $scheme."://".$domain.$url;
							var_dump(dirname($url));
					var_dump(realpath($url));
					sleep(1);
					}

					else if($url[0] != "/"){
						$url = $scheme."://".$domain."/".$url;
					}

					else if(substr($url, 0, 2) == "./"){
						$url = $scheme."://".dirname($url)."/".$url;	// TESTIRATI
						print "\n\n0\n\n";		// TEST
						print "\x07";	// beep 
						var_dump($url);
						sleep(2);
					}

					else if(substr($url, 0, 3) == "../"){
						$url = $scheme."://".$domain."/".realpath($url);	// TESTIRATI
						print "\n\n1\n\n";		// TEST
						print "\x07";	// beep 
						var_dump($url);
						sleep(2);
					}
					else if (substr($url, 0, 11) == "javascript:") {
						$log .= "[".$time."] > javascript:  ".$url."\r\n";
						break;
					} 

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
			$log     .= "\r\n> Page size: [".strlen($page)."] bytes.\r\n\n";		/// strlen == bytes?!?

			if(curl_errno($ch)){ 		// check for execution errors
				print "Scraper error: ".curl_error($ch)."\n";
				$log = "[".$time."] Scraper error: ".curl_error($ch)."\r\n";
				curl_close($ch);
				break;
			}
			curl_close($ch);

			file_put_contents($domain.".txt", $log, FILE_APPEND);
			$log = "";
			extract_cookies($page,$domain);
				
			$doc = new DOMDocument();
			@$doc->loadHTML($page);

			$string .= D1.header_links($doc).D2.metas($doc).D3.hrefs($doc).D4.imgs($doc).D5.scripts($doc);
			$string = str_replace("||", "\n", $string);

			print $string."\n";
			print "\033[32m> Finished.\n";
			print "\n\033[96m> Crawled webpages: ".(count($crawled)-1)."\33[0m\n";


			$log = "[".$time."] > Finished. >>> Crawled webpages: ".(count($crawled)-1);
			file_put_contents($domain.".txt", $log, FILE_APPEND);
		}
	}

	print "\33[96mList of crawled pages:\n";
	print_r($crawled);		///	TODO: TABLICA ILI STRINGOVI PO REDNIM BROJEVIMA
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
			
			file_put_contents($domain.".json", $json, FILE_APPEND);
			print "\33[32mSaved.\33[0m\n";
			print "\33[95mResult file >>> ".__DIR__."/".$domain.".json\33[0m\n";
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

/****************************************************************************************/

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
			CURLOPT_COOKIEJAR		=> 'SessionCookies.txt',
			CURLOPT_COOKIEFILE		=> 'SessionCookies.txt',
		    CURLOPT_HTTPHEADER		=> array(
			    //'User-Agent: 1977417',
			    'Accept: */*',
		    )
		 );
	}
	else if($flag == 1){
		$post_data = 'login=100000000';	////**** 1	****////
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
			CURLOPT_COOKIEJAR		=> 'SessionCookies.txt',
			CURLOPT_COOKIEFILE		=> 'SessionCookies.txt',
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

/****************************************************************************************/



///////		TODO: BOLJA LOGIKA SCRAP-AJNJA


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

/****************************************************************************************/

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

/****************************************************************************************/

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

/****************************************************************************************/

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

/****************************************************************************************/

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

/****************************************************************************************/

/*function divs($doc){

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
}*/

/****************************************************************************************/


?>
