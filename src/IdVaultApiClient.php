<?php

namespace Conduction\IdVaultApi\src;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Throwable;

class IdVaultApiClient {
    /**
     * Endpoint of the API
     */
    const API_ENDPOINT = 'https://id-vault.com';

    /**
     * HTTP Methods
     */
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';

    private $client;

    private $headers;

    public function __construct()
    {

        $this->headers = [
            'Accept'        => 'application/ld+json',
            'Content-Type'  => 'application/json',
        ];

        $this->client = new Client([
            'headers'  => $this->headers,
            'base_uri' => self::API_ENDPOINT,
            'timeout'  => 20.0,
        ]);

    }

    /**
     * This function sends mail from id-vault to provided receiver
     *
     * @param string $applicationId id of your id-vault application.
     * @param string $body html body of the mail.
     * @param string $subject subject of the mail.
     * @param string $receiver receiver of the mail.
     * @param string $sender sender of the mail.
     *
     * @return array|false returns response from id-vault or false if wrong information provided for the call
     */
    public function sendMail(string $applicationId, string $body, string $subject, string $receiver, string $sender)
    {
        try {

            $body = [
                'applicationId' => $applicationId,
                'body'          => $body,
                'subject'       => $subject,
                'receiver'      => $receiver,
                'sender'        => $sender,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/mails', [
                'json'         => $body,
            ]);

            $response = json_decode($response->getBody()->getContents(), true);

        } catch (Throwable $e) {
            return false;
        }

        return $response;
    }

    /**
     * This function retrieve's user information from id-vault.
     *
     * @param string $applicationId id of your id-vault application.
     * @param string $secret secret of your id-vault application.
     * @param string $code the code received by id-vault oauth endpoint.
     * @param string $state (optional) A random string used by your application to identify a unique session
     *
     * @return array|false returns response from id-vault or false
     */
    public function authenticateUser(string $code, string $applicationId, string $secret, string $state = '')
    {
        try {

            $response = $this->client->request(self::HTTP_POST, '/api/access_tokens', [
                'json'         => [
                    'client_id'          => $applicationId,
                    'client_secret'      => $secret,
                    'code'              => $code,
                    'grant_type'         => 'authorization_code',
                ]
            ]);

        } catch (Throwable $e) {
            return false;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function requests additional scopes from user that they must authorize.
     *
     * @param array $scopes scopes you wish to request from the user.
     * @param string $accessToken accessToken received from id-vault.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function getScopes(array $scopes, string $accessToken)
    {
        try {

            $json = base64_decode(explode('.', $accessToken)[1]);
            $json = json_decode($json, true);

            $body = [
                'scopes'            => $scopes,
                'authorization'     => $json['jti'],

            ];

            $response = $this->client->request(self::HTTP_POST, '/api/get_scopes', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function requests sendLists from id-vault BS, the resource filter only works when a clientSecret is also given.
     *
     * @param string|null $clientSecret An id of an id-vault wac/application. This is used to get the sendLists of a specific application.
     * @param string|null $resource An url of a resource that a sendList can also be connected to. For example an organization within the application this SendList is connected to. This is why this search filter only works if a clientSecret is also given.
     *
     * @return array|Throwable returns response from id-vault with an array of SendLists.
     */
    public function getSendLists(string $clientSecret = null, string $resource = null)
    {
        try {

            $body['action'] = 'getLists';

            if (isset($clientSecret)) {
                $body['clientSecret'] = $clientSecret;
                if (isset($resource)) {
                    $body['resource'] = $resource;
                }
            }

            $response = $this->client->request(self::HTTP_POST, '/api/send_lists', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true)['result'];
    }

    /**
     * this function creates a new sendList in id-vault BS.
     *
     * @param string $clientSecret An id of an id-vault wac/application. This should be the UC/provider->configuration->secret of the application from where this sendList is saved.
     * @param array $sendList An array with information of the sendList. This should contain at least the key 'name' with a value when creating a new Sendlist. Or at least the key 'id' with the id of an id-vault sendList when updating a sendList. It can also contain the following keys: 'description', 'resource', (bool) 'mail', (bool) 'phone' and (array with wac/group id's) 'groups'.
     *
     * @return array|Throwable returns response from id-vault with the created or updated BS/sendList (and BS/subscriber @id's).
     */
    public function saveSendList(string $clientSecret, array $sendList)
    {
        try {

            $body = [
                'action' => 'saveList',
                'clientSecret' => $clientSecret
            ];

            if (isset($sendList['id'])) {
                $body['sendList'] = 'https://id-vault.com/api/v1/bs/send_lists/'.$sendList['id'];
            }
            if (isset($sendList['name'])) {
                $body['name'] = $sendList['name'];
            }
            if (isset($sendList['description'])) {
                $body['description'] = $sendList['description'];
            }
            if (isset($sendList['resource'])) {
                $body['resource'] = $sendList['resource'];
            }
            if (isset($sendList['mail'])) {
                $body['mail'] = $sendList['mail'];
            }
            if (isset($sendList['phone'])) {
                $body['phone'] = $sendList['phone'];
            }
            if (isset($sendList['groups'])) {
                $body['groups'] = $sendList['groups'];
            }

            $response = $this->client->request(self::HTTP_POST, '/api/send_lists', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true)['result'];
    }

    /**
     * this function deletes a sendList in id-vault BS and also removes any connections to this sendList in all subscribers.
     *
     * @param string $sendListId The id of an id-vault sendList that is going to be deleted.
     *
     * @return array|Throwable returns response from id-vault with all the affected id-vault BS subscribers and true if this sendList was correctly deleted.
     */
    public function deleteSendList(string $sendListId)
    {
        try {

            $body = [
                'action' => 'deleteList',
                'sendList' => 'https://id-vault.com/api/v1/bs/send_lists/'.$sendListId
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/send_lists', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true)['result'];
    }

    /**
     * this function creates subscribers in id-vault BS, connecting email addresses and/or id-vault wac/groups to the given sendList. Note that at least one of the arrays emails or groups needs to be set!
     *
     * @param string $sendListId The id of an id-vault sendList that all email addresses will subscribe to.
     * @param array $emails An array with email addresses that will be subscribed to the given sendList (id).
     * @param array $groups An array with id-vault wac/group id's that will be subscribed to the given sendList (id).
     *
     * @return array|Throwable returns response from id-vault with all the affected id-vault BS subscribers. Will return false if emails and groups are both empty.
     */
    public function addSubscribersToSendList(string $sendListId, array $emails = null, array $groups = null)
    {
        try {

            $body = [
                'action' => 'addSubscribersToList',
                'sendList' => 'https://id-vault.com/api/v1/bs/send_lists/'.$sendListId
            ];

            if (!isset($emails) and !isset($groups)) {
                return false;
            }
            if (isset($emails)) {
                $body['emails'] = $emails;
            }
            if (isset($groups)) {
                $body['groups'] = $groups;
            }

            $response = $this->client->request(self::HTTP_POST, '/api/send_lists', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true)['result'];
    }

    /**
     * this function sends emails to all subscribers of an id-vault BS sendList
     *
     * @param string $sendListId The id of an id-vault sendList.
     * @param array $mail An array with information for the email. This should contain at least the keys title (email title), html (email content) and sender (an email address) and can also contain the following keys: message, text.
     *
     * @return array|Throwable returns response from id-vault with an array of @id's of all send emails.
     */
    public function sendToSendList(string $sendListId, array $mail)
    {
        try {

            $body = [
                'action' => 'sendToList',
                'sendList' => 'https://id-vault.com/api/v1/bs/send_lists/'.$sendListId,
                'title' => $mail['title'],
                'html' => $mail['html'],
                'sender' => $mail['sender']
            ];

            if (isset($mail['message'])) {
                $body['message'] = $mail['message'];
            }
            if (isset($mail['text'])) {
                $body['text'] = $mail['text'];
            }

            $response = $this->client->request(self::HTTP_POST, '/api/send_lists', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true)['result'];
    }

    /**
     * This function add a dossier to an id-vault user.
     *
     * @param array $scopes scopes the dossier is blocking (scopes must be authorized by the user).
     * @param string $accessToken accessToken received from id-vault.
     * @param string $name name of the dossier.
     * @param string $goal the goal of the Dossier.
     * @param string $expiryDate Expiry date of the Dossier (example: "27-10-2020 12:00:00").
     * @param string $sso valid URL with which the user can view this Dossier.
     * @param string $description (optional) description of the dossier.
     * @param bool $legal (default = false) whether or not this Dossier is on legal basis.
     *
     * @return array|string response from id-vault if dossier created was successful, error message otherwise.
     */
    public function createDossier(array $scopes, string $accessToken, string $name, string $goal, string $expiryDate, string $sso, string $description = '', bool $legal = false)
    {
        if (!filter_var($sso, FILTER_VALIDATE_URL)) {
            throw new \ErrorException('Url invalid', 500);
        }

        $json = base64_decode(explode('.', $accessToken)[1]);
        $json = json_decode($json, true);

        try {

            $headers = $this->headers;
            $headers['authentication'] = $json['jti'];

            $body = [
                'scopes'            => $scopes,
                'name'              => $name,
                'goal'              => $goal,
                'expiryDate'        => $expiryDate,
                'sso'               => $sso,
                'description'       => $description,
                'legal'             => $legal,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/dossiers', [
                'json'         => $body,
                'headers'      => $headers,
            ]);

        } catch (Throwable $e) {
            return false;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function creates a userGroup linked to the id-vault application
     *
     * @param string $clientId id of the id-vault application.
     * @param string $name name of the group.
     * @param string $description description of the group.
     * @param string $organization (optional) uri of an organization object.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function createGroup(string $clientId, string $name, string $description, string $organization = '')
    {
        try {

            $body = [
                'clientId' => $clientId,
                'name' => $name,
                'description' => $description,
                'organization' => $organization,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/create_groups', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function deletes a userGroup linked to the id-vault application
     *
     * @param string $clientId id of the id-vault application.
     * @param string $organization uri of an organization object.
     * @param string $groupId (optional) id of the id-vault group.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function deleteGroup(string $clientId, string $organization, string $groupId = null)
    {
        try {

            $body = [
                'clientId' => $clientId,
                'organization' => $organization,
            ];
            if (isset($groupId)) {
                $body['groupId'] = $groupId;
            }

            $response = $this->client->request(self::HTTP_POST, '/api/delete_groups', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function get all the groups and the users in those groups that are linked to an application
     *
     * @param string $clientId id of the id-vault application.
     * @param string $organization uri of the organization linked to the groups.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function getGroups(string $clientId, string $organization)
    {
        try {
            $body = [
                'clientId' => $clientId,
                'organization' => $organization,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/groups', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function get all the groups that are linked to a user in the application
     *
     * @param string $clientId id of the id-vault application.
     * @param string $username email of the user you want to get the groups from.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function getUserGroups(string $clientId, string $username)
    {
        try {
            $body = [
                'clientId' => $clientId,
                'username' => $username,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/user_groups', [
                'json'         => $body,
            ]);

        } catch (ConnectException $e) {
            var_dump($e);
            return $e;
        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function invites a id-vault user to the provided group
     *
     * @param string $clientId id of the id-vault application.
     * @param string $groupId id of the id-vault group.
     * @param string $username username of the user you wish to invite.
     * @param bool $accepted whether the user already accepted the invited (default = false).
     *
     * @return array|Throwable returns response from id-vault
     */
    public function inviteUser(string $clientId, string $groupId, string $username, bool $accepted = false)
    {
        try {

            $body = [
                'clientId' => $clientId,
                'groupId'  => $groupId,
                'username' => $username,
                'accepted' => $accepted,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/group_invites', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function removes a membership of an id-vault user to the provided group, if it exists.
     *
     * @param string $username username of the user.
     * @param string $clientId id of the id-vault application.
     * @param string $groupId id of the id-vault group.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function removeUser(string $username, string $clientId, string $groupId)
    {
        try {

            $body = [
                'username' => $username,
                'clientId' => $clientId,
                'groupId'  => $groupId,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/group_remove_users', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function accepts the group invite for the user.
     *
     * @param string $clientId id of the id-vault application.
     * @param string $groupId id of the id-vault group.
     * @param string $username username of the user that wants to accept his invite
     *
     * @return array|Throwable returns response from id-vault
     */
    public function acceptGroupInvite(string $clientId, string $groupId, string $username)
    {
        try {

            $body = [
                'clientId' => $clientId,
                'groupId'  => $groupId,
                'username' => $username,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/accept_invites', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * this function tries to create an id-vault user and return an authorization code.
     *
     * @param string $clientId id of the id-vault application.
     * @param string $username username of the user that wants to accept his invite
     * @param array $scopes scopes requested from the user.
     *
     * @return array|Throwable returns response from id-vault
     */
    public function createUser(string $clientId, string $username, array $scopes)
    {
        try {

            $body = [
                'clientId' => $clientId,
                'username' => $username,
                'scopes'   => $scopes,
            ];

            $response = $this->client->request(self::HTTP_POST, '/api/users', [
                'json'         => $body,
            ]);

        } catch (Throwable $e) {
            return $e;
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
