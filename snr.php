<?php

require_once './Resty.php'; 


define('OLD_IP', "198.101.164.14"); 
define('NEW_IP', "166.78.72.173"); 

require_once 'config.php'; 


$headers = array('Content-Type' => 'application/json'); 

$resty = new Resty();
$resty->debug(false);

$resty->setBaseURL('https://api2.dynect.net');

$resp = $resty->post("/REST/Session",json_encode($login_credentials), $headers); 



$obj = $resp['body'];
$token = $obj->data->token; 
$headers = array('Content-Type' => 'application/json', 'Auth-Token' => $token); 


// first get a list of all the zones
$zones = getAllZones($resty, $headers); 

// now for each zone we have, get it's individual resource. 
// Dyn's API is stupid. 

foreach($zones AS $zone => $zone_uri) {
	
        # find out what Dyn knows about this zone
    
#        getOneZone($zone_uri, $resty, $headers); 
        
        /** 
	 	since the zone_uri is returned in this format /REST/Zone/gapzip.com/ we can take the basename(/REST/Zone/gapzip.com/); 
	 	and use that. That's going to be the zone name
	 **/
	 $zone_name = basename($zone_uri);  
	 # then get all the records of this zone name
	 $a_records = getARecords($resty, $headers, $zone_name); 	
        
         foreach($a_records AS $num => $record) {
             $data = getOneARecord($resty, $headers, $record); 
             if($data) {
                $fqdn = $data->fqdn; 
                $value = $data->rdata->address; 
                if(OLD_IP == $value) {
                    echo "$fqdn has a value of $value\n";
                    $changed = changeRecord($resty, $headers, $record); 
                    if($changed) {
                        publish($resty, $headers, $zone_name); 
                    }
                }
             }
         }

}

function publish($resty, $headers, $zone) {
    $encoded = json_encode(array('publish' => true)); 
    $response = $resty->put($zone, $encoded, $headers); 
    print_r($response); 
    die;
}

function changeRecord($resty, $headers, $record) {
    echo "\tGoing to change the IP of $record to " . NEW_IP . "\n"; 
    $querydata = array ('rdata' => NEW_IP, 'ttl' => 0); 
    
    $encoded = json_encode($querydata); 
    
    $response = $resty->put($record, $encoded, $headers);
    print_r($response); 
    if($response['status'] == "200") {
        return true; 
    } else {
        return false; 
    }
}

function getOneARecord($resty, $headers, $id) {
        $response = $resty->get("$id", "", $headers); 
        if($response['status'] == "200") {
            $a_records = $response['body']->data; 
            return $a_records; 
        } else {
            return false; 
        }
}

/**
 * Returns an array of A records for a given zone
 * 
 * @param type $resty
 * @param type $headers
 * @param type $zone
 */
function getARecords($resty, $headers, $zone) {
	$response = $resty->get("/REST/ARecord/$zone/$zone", "", $headers); 
        if($response['status'] == "200") {
            $a_records = $response['body']->data; 
            return $a_records; 
        }
}


function getOneZone($zone_uri, $resty, $headers)
{
	echo "Getting information on $zone_uri\n";
	$response = $resty->get($zone_uri, "", $headers); 
	print_r($response); 
	die;  
}

// get a list of zones
function getAllZones($resty, $headers) {
	$zone_response = $resty->get('/REST/Zone/', "", $headers); 

	// because we have so many zones.. this sends us a job id.. which takes about 5 seconds to run

	$job_url = $zone_response['body']; 
	echo "Waiting for Dynect to compile the list... wait 10 seconds\n"; 
	sleep(10); 

	// now get the list of zones from the last job. 

	$job_response = $resty->get($job_url, "", $headers); 


	$zone_list = $job_response['body']; 

	$zones = $zone_list->data; 
	
	#print_r($zone_list); 

	return $zones; 
	#foreach($zones AS $zone => $name) {
	#	echo $name . "\n"; 
	#}


}
