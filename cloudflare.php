#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php
# 
# version : 0.0.1 
#
# Followed this documentation : https://api.cloudflare.com/#dns-records-for-a-zone-update-dns-record
#
# How to install
# $ echo "
# [CloudFlare]
# 	modulepath=/usr/syno/bin/ddns/cloudflare.php
# 	queryurl=https://api.cloudflare.com/client/v4/" >> /etc.defaults/ddns_provider.conf
# $ vi /usr/syno/bin/ddns/cloudflare.php
# copy this sourcecode.
#

$api_url = 'https://api.cloudflare.com/client/v4/';
$zone_id = NULL;

function print_error($error_code){
	switch ($error_code) {
		case 9103:
			echo 'badauth';
			break;
		case 7000:
		case 7001:
		case 7003:
			echo 'nohost';
			break;
		case 90000:
			echo 'badparam';
			break;
		case 99999:
			echo 'internal error';
			break;
		default:
			echo "unknown($error_code)";
			break;
	}
	exit(1);
}

function print_result($res, $only_error=false){
	if($res['success']){
		if($only_error==false){
			echo 'good';
			exit(0);
		}
	}
	else{
		$error_code = $res['errors'][0][code];
		print_error($error_code);
	}
}

function api_query($endpoint, $params, $request_type='GET'){
	global $auth_email, $auth_key, $api_url;
	
	$curl = curl_init();
	$headers = array();
	$headers[] = 'X-Auth-Email: '.$auth_email;
	$headers[] = 'X-Auth-Key:'.$auth_key;
	$url = $api_url.$endpoint;
	switch ($request_type) {
		case 'POST':
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
			break;
		case 'PUT':
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
			$headers[] = 'Content-type: application/json';
			break;
		case 'PATCH':
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
			$headers[] = 'Content-type: application/json';
			break;
		case 'DELETE':
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
			$headers[] = 'Content-type: application/json';
			break;
		default:
			if ($params){
				$url .= '?'.http_build_query($params);
			}
			break;
	}
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	$res = curl_exec($curl);
	curl_close($curl);
	return json_decode($res, true);
}

function zone_query($endpoint, $params, $request_type='GET'){
	global $zone_id,$zone_host;
	if(empty($zone_host)){
		print_error(99999);
	}
	if(is_null($zone_id)){
		print_error(99999);
	}
	$endpoint = 'zones/'.$zone_id.'/'.$endpoint;
	return api_query($endpoint, $params, $request_type);
}

//////////////////////////////////////////////////////
if ($argc !== 5) {
	print_error(90000);
}

$auth_email = (string)$argv[1];
$auth_key = (string)$argv[2];
$hostname = (string)$argv[3];
$ip = (string)$argv[4];
// check the hostname contains '.'
if (strpos($hostname, '.') === false) {
	print_error(90000);
}
// only for IPv4 format
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
	print_error(90000);
}

$zone_host = $hostname;
if(substr_count($hostname, ".")!== 1){
	global $zone_id;
	$res = api_query('zones',['name'=>$zone_host]);
	print_result($res,true);
	if($res['result_info']['count'] !== 1){
		$splited_hostname = explode(".", $hostname,2);
		$zone_host = array_pop($splited_hostname);
		$res = api_query('zones',['name'=>$zone_host]);
		if($res['result_info']['count'] === 1){
			$zone_id = $res['result'][0]['id'];
		}
		else{
			print_error(7000);
		}
	}
	else{
		$zone_id = $res['result'][0]['id'];		
	}
}

// zone query
$res = zone_query('dns_records',['name'=>$hostname]);
print_result($res, true);
if($res['result_info']['count'] !== 1){
	print_error(7000);
}
$record_data = $res['result'][0];
$record_id = $record_data['id'];
if($record_data['type'] !== 'A'){
	print_error(90000);
}
$update_data = [
	'content'=>$ip
];
$res = zone_query('dns_records/'.$record_id, $update_data, 'PATCH');
print_result($res);
?>
