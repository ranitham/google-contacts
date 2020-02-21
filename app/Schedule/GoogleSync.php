<?php


namespace App\Schedule;

use App\GoogleUser;
use App\PbxPhoneBookEntry;
use Google_Client;
use Google_Service_People;
use Illuminate\Support\Facades\DB;

class GoogleSync
{

    private function matchPbxUser($email)
    {
        return DB::connection('pbx')
            ->table('voicemail')
            ->join('users', 'voicemail.fkiduser', '=', 'users.iduser')
            ->join('extension', 'users.fkidextension', '=', 'extension.fkiddn')
            ->join('dn', 'extension.fkiddn', '=', 'dn.iddn')
            ->select(['dn.iddn', 'dn.fkidtenant'])
            ->where('voicemail.email', 'ILIKE', strtolower($email))
            ->first();
    }

    private function clientFactory($google_client_token)
    {
        $client = new Google_Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setApplicationName("3CX Google Contacts Sync");
        $client->setDeveloperKey(env('GOOGLE_SERVER_KEY'));
        $client->setAccessToken($google_client_token);

        return $client;
    }

    private function insertConnections(\Google_Service_People_ListConnectionsResponse $people, $fkidtenant, $fkiddn)
    {
        /** @var \Google_Service_People_Person $person */
        $person = null;

        foreach ($people as $person) {
            $dbPerson = PbxPhoneBookEntry::where('pager', $person->getResourceName())->first();
            if ($dbPerson == null) {
                $dbPerson = new PbxPhoneBookEntry();
            }

            $dbPerson->pager = $person->getResourceName();

            $dbPerson->fkiddn = $fkiddn;
            $dbPerson->fkidtenant = $fkidtenant;

            /** @var \Google_Service_People_EmailAddress[] $emails */
            $emails = $person->getEmailAddresses();

            if ($emails !== null) {
                foreach ($emails as $email) {
                    /** @var \Google_Service_People_FieldMetadata $meta */
                    $meta = $email->getMetadata();
                    if ($meta->getPrimary() === true) {
                        $dbPerson->email = $email->getValue();
                    }
                }
            }

            /** @var \Google_Service_People_Name[] $names */
            $names = $person->getNames();
            if ($names !== null) {
                foreach ($names as $name) {
                    /** @var \Google_Service_People_FieldMetadata $meta */
                    $meta = $name->getMetadata();
                    if ($meta->getPrimary() === true) {
                        $dbPerson->firstname = $name->getGivenName();
                        $dbPerson->lastname = $name->getFamilyName();
                    }
                }
            }

            /** @var \Google_Service_People_Organization[] $orgs */
            $orgs = $person->getOrganizations();
            if ($orgs !== null) {
                foreach ($orgs as $org) {
                    /** @var \Google_Service_People_FieldMetadata $meta */
                    $meta = $org->getMetadata();
                    if ($meta->getPrimary() === true) {
                        $dbPerson->company = $org->getName();
                    }

                }
            }

            /** @var \Google_Service_People_PhoneNumber[] $phones */
            $phones = $person->getPhoneNumbers();
            if ($phones !== null) {
                foreach ($phones as $phone) {
                    switch ($phone->getType()) {
                        case "mobile":
                        case "workMobile":
                            $dbPerson->mobile = $phone->getCanonicalForm();
                            break;
                        case "home":
                            $dbPerson->home = $phone->getCanonicalForm();
                            break;
                        case "work":
                            $dbPerson->business = $phone->getCanonicalForm();
                            break;
                        case "main":
                            $dbPerson->business2 = $phone->getCanonicalForm();
                            break;
                        default:
                            $dbPerson->home2 = $phone->getCanonicalForm();
                            break;
                    }
                }
            }

            $dbPerson->save();
        }
    }

    public function syncUser($auth_user)
    {
        // Set token for the Google API PHP Client
        $google_client_token = [
            'access_token' => $auth_user->token,
            'refresh_token' => $auth_user->refresh_token,
            'expires_in' => $auth_user->expires_at->timestamp
        ];

        // Match email to iddn
        $pbxUserInfo = $this->matchPbxUser($auth_user->email);

        if ($pbxUserInfo === null) {
            return; // User not found in the PBX - Skip
        } else {
            $fkidtenant = $pbxUserInfo->fkidtenant;
            $fkiddn = $pbxUserInfo->iddn;

            $service = new Google_Service_People($this->clientFactory($google_client_token));

            $optParams = [
                'requestMask.includeField' => 'person.phone_numbers,person.names,person.email_addresses,person.organizations',
                'pageSize' => 200
            ];

            // Check if we have a sync token, if so send through otherwise request one
            if ($auth_user->sync_token != null) {
                $optParams['syncToken'] = $auth_user->sync_token;
                $optParams['requestSyncToken'] = true;
            } else {
                $optParams['requestSyncToken'] = true;
            }

            $nextPage = null;

            do {
                if ($nextPage !== null) {
                    $optParams['pageToken'] = $nextPage;
                }

                try {
                    $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
                } catch (\Google_Service_Exception $gse) {
                    if($gse->getCode() === 400 && strpos(strtolower($gse->getMessage()), 'sync') !== false) {
                        $optParams['syncToken'] = null;
                        $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
                    } else {
                        throw $gse;
                    }
                }

                $this->insertConnections($results, $fkidtenant, $fkiddn);
                $nextPage = $results->getNextPageToken();
            } while ($nextPage != null);

            $auth_user->sync_token = $results->getNextSyncToken(); //save
            $auth_user->save();
        }


    }

    public function __invoke()
    {
        $auth_users = GoogleUser::all();

        foreach ($auth_users as $auth_user) {
            $this->syncUser($auth_user);
        }
    }
}
