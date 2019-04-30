<?php

namespace App\Controller;

use App\AppBundle\Security\TokenAuthenticator;
use App\Controller\Traits\LoggerTrait;
use App\Controller\Traits\BaseTrait;
use FacebookAds\Api;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdSet;
use FacebookAds\Object\Fields\AdSetFields;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class FacebookAdsManagerController {

    use LoggerTrait;
    use BaseTrait;

    const FB_API_URL = 'https://graph.facebook.com/v3.2/';
    private $projectDir;

    public function __construct(string $projectDir) {
        $this->projectDir = $projectDir;
        Api::init(
            $this->loadFromEnv('fb_id'),
            $this->loadFromEnv('fb_key'),
            $this->loadFromEnv('el_marker')
        );
    }

    public function index (Request $request, $action) {
        if (TokenAuthenticator::manageToken($request) === false) {
            $response = new Response(json_encode([
                'ok' => false, 'message' => 'Invalid access token'
            ]), 401);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        try {
            switch ($action) {
                case 'info':
                    $result = $this->getAccInfo();
                    break;
                case 'adset_limit':
                    $result = $this->setAdSetLimit();
                    break;
                case 'acc_spend_cup':
                    $result = $this->setSpendCup();
                    break;
                default:
                    throw new \RuntimeException('Unsupported API action');
            }

            return $this->setResponse($result);
        } catch (\FacebookAds\Exception\Exception $fe) {
            $this->sendExceptionMessage($fe, 'Facebook API exception');
            return $this->setResponse(['ok' => false, 'error' => $fe->getMessage()]);
        } catch (Throwable $t) {
            $this->sendExceptionMessage($t, 'Error in getting acc info');
            return $this->setResponse(['ok' => false, 'error' => $t->getMessage()]);
        }
    }

    /**
     * Get acc info, add campain, adSets
     * @return array
     */
    private function getAccInfo () : array {
        $result = ['ok' => true];
        $api = Api::instance();
        $res = $api->call('/me', 'GET', [
            'fields' => 'id,name,adaccounts{name,account_status,balance,spend_cap,amount_spent,currency,id,created_time,min_daily_budget}'
        ]);
        $data = $res->getBody();
        $data = json_decode($data, true);

        if (isset($data['adaccounts']['data']) && is_array($data['adaccounts']['data'])) {
            $adAcc = $data['adaccounts']['data'][0];
            $result['adaccounts'] = $adAcc;
            $result['adaccounts']['adsets'] = $this->getAdSets($adAcc['id']);
        }
        return $result;
    }

    /**
     * @return array
     */
    private function setAdSetLimit () : array {
        $data = $this->getJSONFromRequest(['amount', 'ad_set_id', 'act_id']);
        $adset = new AdSet($data['ad_set_id'], $data['act_id']);
        $adset->setData(array(
            AdSetFields::DAILY_BUDGET => $data['amount'],
        ));
        $adset->update();

        return ['ok' => true];
    }

    private function setSpendCup () : array {
        $data = $this->getJSONFromRequest(['amount', 'act_id']);

        $response = $this->apiCallWraper(
            'POST',
            $data['act_id'] . '?' . http_build_query([
                'access_token' => $this->loadFromEnv('el_marker')
            ]),
            ['spend_cap' => (int)$data['amount']/100]
        );
        if (isset($response['success']) && $response['success']) {
            return ['ok' => true];
        } else {
            throw new \RuntimeException($response['error_user_msg'] ?? $response['message']);
        }

        /*$account = new AdAccount($data['act_id']);
        $account->setData(array(
            AdSetFields::DAILY_SPEND_CAP => $data['amount'],
        ));
        $account->update();

        return ['ok' => true];*/
    }

    /**
     * Get json data from request and validate mandatory fields exist
     * @param array $mandatoryFields
     * @return array
     */
    private function getJSONFromRequest (array $mandatoryFields) : array {
        $request = Request::createFromGlobals();
        if ($content = $request->getContent()) {
            $data = json_decode($content, true);
            $options = [
                'options' => ['default' => false]
            ];
            $result = [];
            foreach ($mandatoryFields as $sngField) {
                $tmp = filter_var($data[$sngField], FILTER_SANITIZE_STRING, $options);
                if ($tmp === false) {
                    throw new \RuntimeException('Request does not contain mandatory field');
                }
                $result[$sngField] = $tmp;
            }
            return $result;
        } else {
            throw new \RuntimeException('Request does not contain json data');
        }
    }

    /**
     * Get create adSets 4 ad company
     * @param string $adAccId facebook ad campain id
     * @return array
     */
    private function getAdSets (string $adAccId) : array {
        $api = Api::instance();
        $res = $api->call('/' . $adAccId . '/adsets', 'GET', ['fields' => 'id,name,status,targeting,created_time,daily_budget']);
        $data = $res->getBody();
        $data = json_decode($data, true);

        return isset($data['data']) ? $data['data'] : [];
    }

    private function setResponse (array $result) : Response {
        $response = new Response();
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    private function apiCallWraper ($method, $path, $params) {
        return $this->sendRequest($method, self::FB_API_URL.ltrim($path, '/'), $params);
    }
}