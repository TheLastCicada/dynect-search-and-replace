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

foreach($zones AS $zone => $zone_uri) {
	
            
//        
//  since the zone_uri is returned in this format /REST/Zone/domain.com/ 
//  we can take the basename(/REST/Zone/domain.com/); 
//  and use that. That's going to be the zone name
//	 
	 $zone_name = basename($zone_uri);  
	 # then get all the records of this zone name
	 $a_records = getARecords($resty, $headers, $zone_name); 	
        
         foreach($a_records AS $num => $record) {
             $data = getOneARecord($resty, $headers, $record); 
             if($data) {
                $fqdn = $data->fqdn; 
                $value = $data->rdata->address;
                
                // if OLD_IP is the value of rdata, then we go make the change
                if(OLD_IP == $value) {
                    $record_id = $data->record_id; 
                    echo "$fqdn record $record_id has a value of $value\n";
                    $changed = changeRecord($resty, $headers, $record); 
                    if($changed) {
                        publish($resty, $headers, $zone_name); 
                    }
                }
             }
         }

}


/**
 * This should publish any changes we've made while the script is running. 
 * 
 * @param type $resty
 * @param type $headers
 * @param type $zone
 */
function publish($resty, $headers, $zone) {
    $encoded = json_encode(array('publish' => true)); 
    $response = $resty->put("/REST/Zone/$zone/", $encoded, $headers); 
        
    return true; 
    
}

/**
 * This assumes we're just changing the rdata of whatever zone we've been passed.
 * It's not the nicest thing in the world, but neither is this API
 * 
 * @param type $resty
 * @param type $headers
 * @param type $record
 * @return boolean
 */
function changeRecord($resty, $headers, $record) {
    echo "\tGoing to change the IP of $record to " . NEW_IP . "\n"; 
    
    $data = Array();

    $data['rdata'] = Array();
    $data['rdata']['address'] = NEW_IP;
    $querydata = json_encode($data); 
    

    
    $response = $resty->put($record, $querydata, $headers);


    if($response['status'] == "200") {
        return true; 
    } else {
        return false; 
    }
}


/**
 * Gets an individual A record, so we have information to mess with. 
 * 
 * 
 * @param type $resty
 * @param type $headers
 * @param type $id
 * @return boolean
 */
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


/**
 * Gets all the zones we have at Dynect. Since we have several thousand, this takes a while
 * in testing it looks like 10 seconds, so we're telling the script to wait 10 seconds
 * so that we can go get the result of the job it just finished.
 * 
 * @param type $resty
 * @param type $headers
 * @return type
 */
function getAllZones($resty, $headers) {
	$zone_response = $resty->get('/REST/Zone/', "", $headers); 

	// because we have so many zones.. this sends us a job id.. 
        // which takes about 10 seconds to run

	$job_url = $zone_response['body'];
        
	echo "Waiting for Dynect to compile the list... wait 10 seconds\n"; 
	sleep(10); 

	// now get the list of zones from the last job. 

	$job_response = $resty->get($job_url, "", $headers); 


	$zone_list = $job_response['body']; 

	$zones = $zone_list->data; 
	
	return $zones; 


}
