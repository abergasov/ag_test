<?php


namespace App\Controller\Traits;


trait LoggerTrait {

    /**
     * Send stack trace 2 telegram chat. If defined acess/error log - will also add history of requests/errors 4 user ip
     * @param \Exception $e
     * @param string $message
     * @return bool
     */
    protected function sendExceptionMessage (\Exception $e, string $message): bool {
        $chatId = $this->loadFromEnv('tg_chat_id');
        $tgToken = $this->loadFromEnv('tg_token');
        if ($chatId === false || $tgToken === false) {
            return false;
        }
        $preparedMessage = [$message, $this->populateTraceLog($e)];
        $response = $this->sendPostRequest('https://api.telegram.org/bot' . $tgToken . '/sendMessage', [
            'text' => implode("\n", $preparedMessage),
            'chat_id' => $chatId
        ]);
        if ($response === false) {
            return false;
        } else {
            $response = json_decode($response, true);
            return $response['ok'];
        }
    }

    /**
     * Parse server variables and save it on server
     * @param \Exception $e
     * @return bool|string
     */
    private function populateTraceLog (\Exception $e) {
        if (!$this->projectDir) {
            return false;
        }
        $domain = $this->loadFromEnv('tg_domain');
        $publicPath = '/trace/logs/' . time() . '_' . rand(1000, 10000) . '.txt';
        $filePath = $this->projectDir . '/public/' . $publicPath;

        $logData = [];
        $logData[] = 'Method: ' . filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING);

        $logData[] = '$_GET = ' . var_export(filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING),true);
        $logData[] = '$_POST = ' . var_export(filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING),true);
        $logData[] = $e;
        $logData[] = 'php://input = ' . var_export(file_get_contents('php://input'),true);
        $logData[] = '$_COOKIE = ' . var_export(filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_STRING),true);


        $logData[] = 'access_log = ' . var_export($this->parseLogFile('access_log'), true);
        $logData[] = 'error_log = ' . var_export($this->parseLogFile('error_log'), true);



        file_put_contents($filePath, "\xEF\xBB\xBF" . implode("\n\n", $logData));
        return $domain . $publicPath;
    }

    private function  parseLogFile ($logFile) {
        $logFile = $this->projectDir . '/' . $logFile;
        if (!file_exists($logFile)) {
            return [];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $iterator = $this->readTheFile($logFile);

        $buffer = [];
        foreach ($iterator as $iteration) {
            $position = strpos($iteration, $ip);
            if ($position === false) {
                continue;
            }
            if (count($buffer) === 50) {
                array_shift($buffer);
            }
            $buffer[] = $iteration;
        }
        return $buffer;
    }

    private function readTheFile($path) {
        $handle = fopen($path, "r");
        try {
            while(!feof($handle)) {
                yield trim(fgets($handle));
            }
        } finally {
            fclose($handle);
        }
    }

    protected function sendPostRequest (string $url, array $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function loadFromEnv (string $key) : string {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && strlen($_SERVER[$key]) > 0) {
            return filter_var($_SERVER[$key], FILTER_SANITIZE_STRING);
        } else {
            return false;
        }
    }
}