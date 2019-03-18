<?php
/**
 *
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

/**
 * sends UTF8 encoded emails
 * @param string/false $from_email (if false: system email used)
 * @param string $to_email
 * @param string $subject
 * @param string $body
 */
function grape_send_mail($from_email,$to_email,$subject,$body){
	global $grape;
	if($from_email===false){
		$from_email = $grape->settings->sender_email;
	}
	$system_email = $grape->settings->sender_email;
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers.= "Content-type:text/html;charset=UTF-8" . "\r\n";
	$headers.= "From: $system_email\r\n" .
    "Reply-To: $from_email\r\n" .
	"Bcc: $system_email\r\n".
    "X-Mailer: PHP/" . phpversion();
	$formatted_body = "$body
	<p>&nbsp;</p>
	<hr/>
	<p>Diese E-Mail wurde von der Website <a href=\"".URL."\">".URL."</a> versendet.\n".((SALUTATION=="Sie")?"Ihre":"Deine")." E-Mail-Adresse wurde hierfür nicht weitergegeben.</p>";
	mail($to_email,$subject,$formatted_body,$headers);
}

/**
 * Format a number
 * @param float/decimal/... $number
 * @return string
 */
function grape_number($number){
	if(!is_float($number)) return number_format($number,0,"",".");
	else return number_format($number,1,",",".");
}
/**
 * @param array of objects $sql_result
 */
function grape_csv_dump($sql_result,$filename){
	$row = array();
	foreach ($sql_result[0] as $key => $value){
		array_push($row,str_replace('"',"'",$key));
	}
	$out = '"'.join('";"',$row).'"';
	foreach($sql_result as $result){
		$row = array();
		foreach($result as $key => $value){
			array_push($row,str_replace('"',"'",$value));
		}
		$out.= "\n".'"'.join('";"',$row).'"';
	}
	header("Content-type: application/octet-stream");
	header("Content-Disposition: attachment; filename=".date("Y-m-d_H-i-s")."_".$filename.".csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	print "$out";
	exit;
}
/**
 * @param string $cache_key
 * @param int $max_age Minutes
 */
function grape_cache_get($cache_key,$max_age=0){
	global $grape;
	$cache_key = grape_escape($cache_key);
	$max_age = intval($max_age);
	$sql = "SELECT *
			FROM `grape_caches`
			WHERE `cache_key` = \"$cache_key\"
			AND DATE_ADD(`timestamp`, INTERVAL $max_age MINUTE) > NOW()";
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	$grape->db->query($sql);
	//$grape->output->content->html.= $grape->output->dump_var($grape->db->num_rows);

	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		$results = str_replace("\\","",$db_results[0]->result);
		$results = unserialize($results);
		//$grape->output->content->html.= $grape->output->dump_var(array("cache_key"=>$cache_key,"in_cache"=>true,"timestamp"=>$db_results[0]->timestamp,"max_age"=>$max_age));
		return $results;
	}
	else{
		//$grape->output->content->html.= $grape->output->dump_var(array("cache_key"=>$cache_key,"in_cache"=>false,"max_age"=>$max_age));
		return false;	
	}
}
/**
 *
 */
function grape_cache_set($cache_key,$object){
	global $grape;
	$cache_key = grape_escape($cache_key);
	$serialized = grape_escape(serialize($object));
	$sql = "SELECT * FROM `grape_caches` WHERE `cache_key` = \"$cache_key\"";
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$sql = 'UPDATE `grape_caches` SET `result` = \''.$serialized.'\' WHERE `cache_key` = \''.$cache_key.'\'';
	}
	else{
		$sql = 'INSERT `grape_caches` (`cache_key`,`result`) VALUES(\''.$cache_key.'\',\''.$serialized.'\')';
	}
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	$grape->db->query($sql);
}
/**
 *
 */
function grape_escape($string){
	if (function_exists('mb_ereg_replace'))
	{
		return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F]', '\\\0', $string);
	}
	else {
		return preg_replace('~[\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F]~u', '\\\$0', $string);
	}
}
?>
