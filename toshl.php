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

function string_contains($text, $contains)
{
    if (strpos($text, $contains) !== false) {
        return true;
    } else {
        return false;
    }
}

function string_starts_with($text, $contains)
{
    if (strpos($text, $contains) === 0) {
        return true;
    } else {
        return false;
    }
}
/**From: https://netkuup.com/en/card/1/18/php-check-if-strings-ends-with-a-specified-string */
function string_ends_with($haystack, $needle)
{
    return $needle === "" || (substr($haystack, -strlen($needle)) === $needle);
}


/**
 * @brief Checks if the rule imported from google sheets matches the decritpions of the expense
 * @param $rule -> rule in valid format
 * @param $entry -> expense entry
 * @return boolean true if match, false otherwise
 */
function description_matches_rule($rule, $entry)
{
    $description_matches = (object)[
        "contains" => false,
        "starts_with" => false,
        "ends_with" => false,
    ];
    if (trim($entry->desc)) {
        /**There are 3 types of description conditions, contains, starts with, ends with.
         * If more than one description condition us defined all have to be true. 
         */
        /**First check if the contains condtion is true. It becomes true if the value is undefined, since then it matched by default all descriptions */
        if (trim($rule[0])) {
            if (string_contains($entry->desc, $rule[0])) {
                //echo "Expense " . $entry->desc . " \n contains text: " . $rule[0] . " \n";
                $description_matches->contains = true;
            }
        }
        /**If rule has not contains defined, set it to default true. */
        else {
            $description_matches->contains = true;
        }
        /**Next check if start_with condition is true */
        if (trim($rule[1])) {
            if (string_starts_with($entry->desc, $rule[1])) {
                //echo "Expense " . $entry->desc . " \n starts with text: " . $rule[1] . " \n";
                $description_matches->starts_with = true;
            }
        }
        /**If rule has not contains defined, set it to default true. */
        else {
            $description_matches->starts_with = true;
        }
        /**Next check if ends_with condition is true. */
        if (trim($rule[2])) {
            if (string_ends_with($entry->desc, $rule[2])) {
                //echo "Expense " . $entry->desc . " \n ends with text: " . $rule[2] . " \n";
                $description_matches->ends_with = true;
            }
        }
        /**If rule has not contains defined, set it to default true. */
        else {
            $description_matches->ends_with = true;
        }
    }
    if ($description_matches->contains && $description_matches->starts_with && $description_matches->ends_with) {
        return true;
    } else {
        return false;
    }
}

function amount_matches_rule($rule, $entry)
{
    $amount_matches = (object)[
        "greater" => false,
        "smaller" => false,
    ];
    /**Check if entry amount is greater. */
    if (trim($rule[3])) {
        if (abs(floatval($entry->amount)) >= floatval($rule[3])) {
            $amount_matches->greater = true;
            //print_r("Rule match. Amount " . abs(floatval($entry->amount)) . " is greater or equals  " . floatval($rule[3]) . "\n");
        }
    }
    /**If rule greater is not defined, set it to default true. */
    else {
        $amount_matches->greater = true;
    }
    /**Check if amount is smaller */
    if (trim($rule[4])) {
        if (abs(floatval($entry->amount)) <= floatval($rule[4])) {
            $amount_matches->smaller = true;
            //print_r("Rule match. Amount " . abs(floatval($entry->amount)) . " is smaller or equals  " . floatval($rule[4]) . "\n");
        }
    }
    /**If rule greater is not defined, set it to default true. */
    else {
        $amount_matches->smaller = true;
    }
    if ($amount_matches->greater && $amount_matches->smaller) {
        return true;
    } else {
        false;
    }
}

function entry_matches_rule($valid_rule, $entry)
{
    global $dry_run_messages;
    /**We have to check if the entries matched any of the rules. 
     * 1. We check if the rule has a descritpion condition. If it does we check it matched the entry. 
     * 2. We check if the rule has an amount condition. If it does we check if it matched the entry.
     * If both conditions exist both conditions have to be true for the rule to apply.
     */
    /**1. */
    $rule = $valid_rule->rule;
    $rule_match = (object)[
        "description_match" => false,
        "amount_match" => false,
    ];
    if ($valid_rule->description_condition) {
        $rule_match->description_match = description_matches_rule($rule, $entry);
    }
    /**If there is no descritpion condition defined it is set by default to true, since it mathes all values. 
            */
    else {
        $rule_match->description_match = true;
    }
    /**If there is no numeric condition defined it is set by default to true, since it mathes all values. */
    if ($valid_rule->numeric_condition) {
        $rule_match->amount_match = amount_matches_rule($rule, $entry);
    } else {
        $rule_match->amount_match = true;
    }
    if ($rule_match->amount_match && $rule_match->description_match) {
        $debug_message = "The entry with the description " . $entry->desc .
            " and the amount: " . $entry->amount .
            " matches the rule at the google sheets row " . $valid_rule->google_sheet_row . "\n";
        array_push($dry_run_messages, $debug_message);
        return true;
    } else {
        return false;
    }
}

$accesstoken = get_accesstoken();


$entries = list_entries();
$rules = get_toshl_rules();
$rule_match = array();
$errors = array();
$dry_run_messages = array();


/**Array which has all valid rules and some extra info about the rule.*/
$valid_rules = array();
/**Check if rules comply with the right standard and push to valid_rules array*/
$index = 0;
$google_table_offset = 3;
foreach ($rules as $rule) {
    $valid_rule = check_rule_object($rule);
    $row = $index + $google_table_offset;
    if ($valid_rule->valid_rule) {
        $valid_rule->google_sheet_row = $row;
        array_push($valid_rules, $valid_rule);
    } else {
        $error_message = $valid_rule->error_message . "  Error at Google Sheet Rule Table at row " . $row;
        array_push($errors, $error_message);
    }
    $index = $index + 1;
}


$applied_rules = array();

/**We go through all the entries and check if any of the rules matches.  */
foreach ($entries as $entry) {
    //print_r($entry->desc . "\t" . $entry->amount . "\t" . $entry->id . "\n");
    foreach ($valid_rules as $valid_rule) {
        entry_matches_rule($valid_rule, $entry);
    }
}
