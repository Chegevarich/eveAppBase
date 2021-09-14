<?php

namespace eve;

// You have to create and fill it!
include_once(dirname(__FILE__) . '/EveConf.php');

/**
 * Base class for work with EVE API
 *
 * @author I.V.Zhiradkov
 */
abstract class EveAuthBase {

    public $clientId = EVE_CLIENT_ID;
    public $secret = EVE_SECRET;
    public $scope = EVE_SCOPE;

    public $defaultPatterns = [
        'characterId' => '{CHARACTERID}',
        'accessToken' => '{ACCESSTOKEN}',
    ];
    public $baseUrl = 'https://esi.evetech.net/latest/';

    /**
     * Eve character ID
     * @var integer
     */
    public $characterId;

    /**
     * Ingame character name
     * @var string
     */
    public $characterName;

    /**
     * delimiter space (default by copying from app dev page)
     */
    public $tokenScopes;

    /**
     * auth url
     * @var string 
     */
    public $redirect = EVE_REDIRECT;

    /**
     * state for auth, random string
     * @var string
     */
    public $state = EVE_STATE;

    /**
     * After success auth where to go
     * @var string
     */
    public $baseRedirectUrl = EVE_BASE_REDIRECT_URL;

    /**
     * JWT access token
     * @var string
     */
    public $accessToken;

    /**
     * seconds to token expire
     * @var integer
     */
    public $expiresIn;

    /**
     * token type (JWT now)
     * @var string
     */
    public $tokenType = 'Bearer';

    /**
     * refresh token
     * @var string
     */
    public $refreshToken;

    /**
     * array of options name to build this class
     * @var array
     */
    protected $objVars = [
        'characterId',
        'characterName',
        'tokenScopes',
        'accessToken',
        'expiresIn',
        'refreshToken',
    ];

    /**
     * where is the auth data?
     */
    abstract public function readState();

    /**
     * where to store auth data?
     */
    abstract protected function storeState();

    /**
     * what to do when auth is failed
     */
    abstract public function authFailed();

    public function __construct($db = false) {

        if (isset($_GET['code']) && isset($_GET['state'])) {
            $response = $this->curl(
                    'https://login.eveonline.com/v2/oauth/token', [
                        'authorization: Basic ' . base64_encode($this->clientId . ':' . $this->secret),
                        'cache-control: no-cache',
                        'content-type: application/x-www-form-urlencoded',
                        'host: login.eveonline.com',
                    ], 'POST', [CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code=' . $_GET['code']]
            );

            $decoded = json_decode($response);
            if (json_last_error() == JSON_ERROR_NONE) {

                if (array_key_exists('error', $decoded)) {
                    print_r(['errorAnswer' => $decoded]);
                    $this->authFailed();
                } else {
                    $this->accessToken = $decoded->access_token;
                    $this->expiresIn = time() + $decoded->expires_in - 69;
                    $this->tokenType = $decoded->token_type;
                    $this->refreshToken = $decoded->refresh_token;

                    $this->jwtVerify();
                    $this->storeState();

                    header("Location: " . $this->baseRedirectUrl);
                }
            } else {
                $this->authFailed();
            }
        } else {
            if (!$this->readState()) {
                $this->authFailed();
            }
        }
    }

    /**
     * get new token in case of expired token
     * @return boolean
     */
    protected function refresh() {
        if ($this->expiresIn < time()) {
            $result = $this->curl(
                    'https://login.eveonline.com/v2/oauth/token', array(
                'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->secret),
                'Content-Type: application/x-www-form-urlencoded'
                    ), 'POST', [CURLOPT_POSTFIELDS, 'grant_type=refresh_token&refresh_token=' . urlencode($this->refreshToken) . '&scope=' . urlencode($this->scope)]
            );

            $decoded = $this->fromJson($result);
            if ($decoded) {
                $this->accessToken = $decoded->access_token;
                $this->refreshToken = $decoded->refresh_token;
                $this->expiresIn = time() + $decoded->expires_in - 69;
                $this->storeState();
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * read data from JWT token
     */
    protected function jwtVerify() {
        $result = $this->curl(
                'https://esi.evetech.net/verify', [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/x-www-form-urlencoded',
                ], 'GET'
        );

        $decoded = $this->fromJson($result);
        $this->characterId = $decoded->CharacterID;
        $this->characterName = $decoded->CharacterName;
        $this->tokenScopes = $decoded->Scopes;
        $this->expiresIn = time() + $decoded->ExpiresOn;
    }

    /**
     * Read from json, check errors, return from_json
     * @param json $data
     * @return mixed
     */
    protected function fromJson($data) {
        $decoded = json_decode($data);
        if (json_last_error() == JSON_ERROR_NONE) {
            if (is_array($decoded) && array_key_exists('error', $decoded)) {
                $this->authFailed();
            } else {
                return $decoded;
            }
        }
        $this->authFailed();
    }

    /**
     * Curl it
     * @param string $url
     * @param array $header
     * @param string $type
     * @param array $data
     * @return mixed
     */
    protected function curl($url, $header, $type, $data = []) {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_HTTPHEADER => $header,
        ));

        if ($data) {
            curl_setopt($curl, $data[0], $data[1]);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err) {
            echo 'curl error #' . $err;
        }

        return $response;
    }

    /**
     * 
     * @param string $url
     * @param array $patterns
     * @param string $type
     * @return mixed
     */
    public function getDataSimpleGetRequest($url, $patterns = [], $type = 'GET') {
        if ($this->refresh()) {
            foreach ($patterns as $var => $p) {
                $url = str_replace($p, $this->$var, $url);
            }

            return $this->fromJson($this->curl($url, [], $type));
        } else {
            return false;
        }
    }

    /**
     * 
     * @param string $url
     * @param array $patterns
     * @param string $type
     * @return mixed
     */
    public function getDataGetRequestByData($url, $patterns = [], $type = 'GET') {
        foreach ($patterns as $var => $p) {
            $url = str_replace($p, $var, $url);
        }

        return $this->fromJson($this->curl($url, [], $type));
    }

}
