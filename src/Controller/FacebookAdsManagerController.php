<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class FacebookAdsManagerController {

    public function getAccInfo () {
        return $this->setResponse(['ok' => true]);
    }

    private function setResponse (array $result) : Response {
        $response = new Response();
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}