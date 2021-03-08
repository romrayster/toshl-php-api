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
 * @brief get array with all existing categories
 * @return array with the following objects:
 *           [id] => 50171653
 *           [name] => Food & Drinks
 *           [name_override] => 
 *           [modified] => 2016-04-04 18:50:17.653
 *           [type] => expense
 *           [deleted] => 
 *           [counts] => stdClass Object
 *               (
 *                   [entries] => 49
 *                   [tags_used_with_category] => 5
 *                   [tags] => 6
 *                   [budgets] => 0
 *               )
 */
function list_categories()
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/categories';
    $params = array('per_page' => 500);
    $url = $endpoint . '?' . http_build_query($params);
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $accesstoken);

    $output = curl_exec($ch);
    $output = json_decode($output);
    //print_r($output);
    /*     foreach ($output as $category) {
            print_r($category);
        } */
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}

function list_tags()
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/tags';
    $params = array('per_page' => 500);
    $url = $endpoint . '?' . http_build_query($params);
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $accesstoken);

    $output = curl_exec($ch);
    $output = json_decode($output);
    //print_r($output);
    /*     foreach ($output as $category) {
            print_r($category);
        } */
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}

$accesstoken = get_accesstoken();
$errors = array();
$dry_run_messages = array();

$entries = list_entries();
$rules = get_toshl_rules();
print_r(get_changing_entries($entries, $rules)); 
print_r($errors);

/**
 * Tags can exist as subtags of categories and as standalone global tags. 
 * An expense can have only one category but more than one tag. 
 * Global tags are a way to assign several "categories" to one expense
 * Category tags are a way to assign subcategories to an expense 
 */