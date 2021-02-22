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
    $range = 'A2:I';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();
    $rules=array();
    if (empty($values)) {
        print "No data found.\n";
    } else {
        foreach ($values as $row) {
            // Print columns A and E, which correspond to indices 0 and 4.
            //printf("%s, %s\n", $row[0], $row[4]);
            array_push($rules,$row);
        }
    }
    return $rules;
}
