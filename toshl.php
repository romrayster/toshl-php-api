<?php
include("./google-sheets/get-google-sheets-rules.php");

function get_accesstoken($filename = "credentials.txt")
{

    $f = fopen($filename, 'r');
    $line = fgets($f);
    fclose($f);
    return $line;
}


function list_entries($from = "2020-12-01", $to = "2020-12-31")
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
    curl_setopt($ch, CURLOPT_USERPWD, $accesstoken);

    $output = curl_exec($ch);
    $output = json_decode($output);
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}

function get_entries($id = "235733109")
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/entries/';
    $url = $endpoint . $id;
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $accesstoken);

    $output = curl_exec($ch);
    $output = json_decode($output);
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}

function list_accounts()
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/accounts';
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $accesstoken);

    $output = curl_exec($ch);
    $output = json_decode($output);
    //print_r($output);
    foreach ($output as $account) {
        if (!empty($account->connection)) {
            print_r($account->connection->name . "\t");
            print_r($account->name . "\t");
            print_r($account->id . "\n");
        }
    }
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}
/**
 * @brief function which checks wether the rule object imported from Google Sheets is correct
 * @param rule an array which is supposed to have at least index 5 or index 6 defined with a category or tag
 * @return object as follows:
 * {
 *     valid_rule=>true|false,
 *     category_defined=>true|false,
 *     tags_defined=>true|false,
 * }
 */
function check_rule_object($rule)
{
    /**Tags come an index 6, check if tags are defined */

    $check_rule_object = (object)[
        "valid_rule" => false,
        "category_defined" => false,
        "tags_defined" => false
    ];
    if (count($rule) > 6) {
        if (!empty(trim($rule[6])) || !empty(trim($rule[5]))) {
            $check_rule_object->valid_rule = true;
        }
        if (!empty(trim($rule[6]))) {
            $check_rule_object->tags_defined = true;
        }
        if (!empty(trim($rule[5]))) {
            $check_rule_object->category_defined = true;
        }
    }
    /**Check if categoreis are defined */
    else if (count($rule) > 5) {
        if (!empty($rule[5])) {
            $check_rule_object->valid_rule = true;
            $check_rule_object->category_defined = true;
        }
    }
    
    if($check_rule_object->valid_rule ){
        echo("Valid Rule \n");
    }
    else{
        print_r("Invalid Rule \n");
    }
    return $check_rule_object;
}
$accesstoken = get_accesstoken();
$entries = list_entries();
$rules = get_toshl_rules();
$rule_match = array();
$errors = array();
/**We go through all the entries and check if any of the rules matches.  */
foreach ($entries as $entry) {
    //print_r($entry->desc . "\t" . $entry->amount . "\t" . $entry->id . "\n");
    /**Check if rules comply with the right standard */
    foreach ($rules as $rule) {
        check_rule_object($rule);
        
    }
}
