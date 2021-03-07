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
        "numeric_condition"=>false,
        "rule"=>$rule,
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
            $check_rule_object->error_message="Rule has no matching criteria.";

        }
    }
    else{
/*         print_r("Invalid rule because it does not contain neither tags nor categories. Rule: \n");
        print_r($rule); */
        $check_rule_object->error_message="Invalid rule because it does not contain neither tags nor categories.";

    } 
    return $check_rule_object;
}
