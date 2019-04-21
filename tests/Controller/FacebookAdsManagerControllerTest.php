<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FacebookAdsManagerControllerTest extends WebTestCase {

    public function testIndex () {

        $types = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $allowedActions = ['info', 'adset_limit', 'acc_spend_cup'];
        $path = '/api/facebook/ads/manager/';

        $client = static::createClient();

        foreach ($types as $rType) {
            $client->request($rType, $path);
            $this->assertEquals(404, $client->getResponse()->getStatusCode());

            foreach ($allowedActions as $allowedAction) {
                $client->request($rType, $path . $allowedAction);
                if ($rType === 'GET' || $rType === 'POST') {
                    $response = $client->getResponse();
                    $this->assertEquals(200, $response->getStatusCode());
                    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
                } else {
                    $this->assertEquals(405, $client->getResponse()->getStatusCode());
                }
            }
            $client->request('GET', $path . $this->generateString());
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    private function generateString ($length = 20) : string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}