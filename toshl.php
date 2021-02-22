<?php

require_once './google-sheets/google-api-php-client/vendor/autoload.php';

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

function get_entries($id="235733109")
{
    global $accesstoken;
    // create curl resource
    $ch = curl_init();
    // set url
    $endpoint = 'https://api.toshl.com/entries/';
    $url = $endpoint .$id;
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
            print_r($account->connection->name."\t");
            print_r($account->name."\t");
            print_r($account->id . "\n");
        }
    }
    // close curl resource to free up system resources
    curl_close($ch);
    return $output;
}

/* $accesstoken = get_accesstoken();
list_accounts(); */
//print_r(get_entries());

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
    $client->setApplicationName('Google Sheets API PHP Quickstart');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
    $client->setAuthConfig('credentials.json');
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


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Sheets($client);

// Prints the names and majors of students in a sample spreadsheet:
// https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
$spreadsheetId = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';
$range = 'Class Data!A2:E';
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

if (empty($values)) {
    print "No data found.\n";
} else {
    print "Name, Major:\n";
    foreach ($values as $row) {
        // Print columns A and E, which correspond to indices 0 and 4.
        printf("%s, %s\n", $row[0], $row[4]);
    }
}