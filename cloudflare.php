<?php
# I maade according to description of api document following address.
# https://api.cloudflare.com/#dns-records-for-a-zone-update-dns-record
#
# How to install
# -step 1-
# $ echo "
# [CloudFlare]
# 	modulepath=/usr/syno/bin/ddns/cloudflare.php
# 	queryurl=https://api.cloudflare.com/client/v4/" >> /etc.defaults/ddns_provider.conf
# -step 2-
# $ vi /usr/syno/bin/ddns/cloudflare.php
# copy this sourcecode.


if ($argc !== 5) {
	echo 'badparam';
	exit();
}

$auth_email = (string)$argv[1];
$auth_key = (string)$argv[2];
$hostname = (string)$argv[3];
$ip = (string)$argv[4];

// check the hostname contains '.'
if (strpos($hostname, '.') === false) {
	echo 'badparam';
	exit();
}
// only for IPv4 format
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
	echo "badparam";
	exit();
}

$sub_host = explode(".", $hostname,2)[0];
$zone_host = explode(".", $hostname,2)[1];
$api_url = 'https://api.cloudflare.com/client/v4/';
$zone_id = NULL;

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
		echo 'badparam';
		exit();
	}
	if(is_null($zone_id)){
		$res = api_query('zones',['name'=>$zone_host]);
		if(empty($res['result'])){
			echo 'badparam';
			exit();
		}
		$zone_id = $res['result'][0]['id'];
	}
	$endpoint = 'zones/'.$zone_id.'/'.$endpoint;
	return api_query($endpoint, $params, $request_type);
}


$res = zone_query('dns_records',['name'=>$hostname]);
$record_data = $res['result'][0];
$record_id = $record_data['id'];
$update_data = [
	'type'=>$record_data['type'],
	'name'=>$record_data['name'],
	'content'=>$ip
];
$res = zone_query('dns_records/'.$record_id, $update_data, 'PUT');

?>