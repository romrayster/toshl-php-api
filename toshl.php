<?php
function get_accesstoken($filename = "credentials.txt")
{

    $f = fopen($filename, 'r');
    $line = fgets($f);
    fclose($f);
    return $line;
}


function get_entries($from = "2020-12-01", $to = "2020-12-31")
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/entries';
    $params = array('from' => $from, "to" => $to);
    $url = $endpoint . '?' . http_build_query($params);
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD,$accesstoken);

    $output = curl_exec($ch);
    echo $output;
    // close curl resource to free up system resources
    curl_close($ch);
}
$accesstoken = get_accesstoken();
get_entries();