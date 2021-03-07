<?php

/**Modified Example from: https://developers.google.com/sheets/api/quickstart/php  */
require __DIR__ . '/google-api-php-client/vendor/autoload.php';
if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Toshl Google Sheets Categorizer');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $client->setAuthConfig('./google-sheets/credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}
function getSpreadSheetId($filename = "./google-sheets/google-sheet-id.txt")
{
    $f = fopen($filename, 'r');
    $line = fgets($f);
    fclose($f);
    return $line;
}

function get_toshl_rules()
{
    // Get the API client and construct the service object.
    $client = getClient();
    $service = new Google_Service_Sheets($client);


    $spreadsheetId = getSpreadSheetId();
    $range = 'A3:L';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    $rules = array();
    if (empty($values)) {
        print "No data found.\n";
    } else {
        foreach ($values as $row) {
            // Print columns A and E, which correspond to indices 0 and 4.
            //printf("%s, %s\n", $row[0], $row[4]);
            array_push($rules, $row);
        }
    }
    return $rules;
}
/**
 * @brief function which checks wether the rule object imported from Google Sheets is correct
 * @param rule which is supposed to meet the following conditions:
 * - have at least a description condition or an amount condition
 * - have at least one category or tags defined
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
        "tags_defined" => false,
        "description_condition" => false,
        "numeric_condition" => false,
        "rule" => $rule,
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
    if ($check_rule_object->valid_rule) {
        /**Now we check if any conditions are set.
         * There are two types of conditions at this point
         * 1. Check if description of expense contains/starts with / ends with a string
         * 2. Check if amount is greater or smaller than a certain amount. 
         */
        /**First we check if the description condition is set*/
        if (trim($rule[0]) || trim($rule[1]) || trim($rule[2])) {
            $check_rule_object->description_condition = true;
        }
        /**Then we check if the amount condition is true. 
         * ....
         */
        if (trim($rule[3])) {
            if (is_numeric(trim($rule[3]))) {
                $check_rule_object->numeric_condition = true;
            }
        }
        if (trim($rule[4])) {
            if (is_numeric(trim($rule[4]))) {
                $check_rule_object->numeric_condition = true;
            }
        }
        /**If both are false set valid rule to false because we need at least one condition. */

        if (!$check_rule_object->numeric_condition && !$check_rule_object->description_condition) {
            $check_rule_object->valid_rule = false;
            /*             print_r("Rule has no matching criteria. Rule:  \n");
            print_r($rule); */
            $check_rule_object->error_message = "Rule has no matching criteria.";
        }
    } else {
        /*         print_r("Invalid rule because it does not contain neither tags nor categories. Rule: \n");
        print_r($rule); */
        $check_rule_object->error_message = "Invalid rule because it does not contain neither tags nor categories.";
    }
    return $check_rule_object;
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

function get_changing_entries($entries, $rules)
{
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

    $changing_entries = array();
    /**We go through all the entries and check if any of the rules matches.  */
    foreach ($entries as $entry) {
        //print_r($entry->desc . "\t" . $entry->amount . "\t" . $entry->id . "\n");
        foreach ($valid_rules as $valid_rule) {
            if (entry_matches_rule($valid_rule, $entry)) {
                $changing_entry = (object)[
                    "rule" => $valid_rule,
                    "entry" => $entry
                ];
                array_push($changing_entries, $changing_entry);
            };
        }
    }
    if (count($changing_entries) > 0) {
        return $changing_entries;
    } else {
        return false;
    }
}
