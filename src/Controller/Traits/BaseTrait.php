<?php

namespace App\Controller\Traits;

trait BaseTrait {

    private $last_response_headers = null;

    protected function sendRequest (string $method, string $url, array $params = []) {
        $query = in_array($method, ['GET','DELETE']) ? $params : [];
        $payload = in_array($method, ['POST','PUT']) ? json_encode($params) : [];
        $request_headers = in_array($method, ['POST','PUT']) ? ["Content-Type: application/json; charset=utf-8", 'Expect:'] : [];

        $responseJson = $this->curlHttpRequest($method, $url, $query, $payload, $request_headers);
        $response = json_decode($responseJson, true);

        return isset($response['error']) || ($this->last_response_headers['http_status_code'] >= 400) ?
            $response['error'] : $response;
    }

    private function curlHttpRequest($method, $url, $query='', $payload='', $request_headers = []) {
        if (!empty($query)) {
            $url = is_array($query) ? "$url?".http_build_query($query) : "$url?$query";
        }
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'php-api-client');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (!empty($request_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        }

        if ($method != 'GET' && !empty($payload))  {
            if (is_array($payload)) {
                $payload = http_build_query($payload);
            }
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new \Exception($error . ', code' . $errno);
        }
        list($message_headers, $message_body) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $this->last_response_headers = $this->curlParseHeaders($message_headers);

        return $message_body;
    }

    private function curlParseHeaders($message_headers) {
        $header_lines = preg_split("/\r\n|\n|\r/", $message_headers);
        $headers = [];
        list($headers['http_status_code'], $headers['http_status_message']) = explode(' ', trim(array_shift($header_lines)), 3);
        foreach ($header_lines as $header_line) {
            list($name, $value) = explode(':', $header_line, 2);
            $name = strtolower($name);
            $headers[$name] = trim($value);
        }
        return $headers;
    }

    protected function getLastResponseHeaders () {
        return $this->last_response_headers;
    }
}