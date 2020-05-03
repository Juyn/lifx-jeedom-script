<?php

if (isset($argv)) {
    foreach ($argv as $arg) {
        $argList = explode('=', $arg);
        if (isset($argList[0]) && isset($argList[1])) {
            $_GET[$argList[0]] = $argList[1];
        }
    }
}

$action = $_GET['action'];
$device = $_GET['device'] ?? "all";

$lifx = new Lifx($action, $device);

if (method_exists($lifx, $action)) {
    call_user_func([$lifx, $action]);
}

class Lifx
{
    const ALLOWED_ACTIONS = [
        'toggle',
        'getState',
    ];

    const TOKEN = 'YOUR_TOKEN';
    const BASE_PATH = "https://api.lifx.com/v1/lights";

    /**
     * @var string
     */
    private $action;

    /**
     * @var string
     */
    private $device;

    /**
     * @var string
     */
    private $deviceId;

    /**
     * Lifx constructor.
     * @param string $action
     * @param string $device
     * @throws Exception
     */
    public function __construct(string $action = "", string $device = "all")
    {
        $this->action = $action;
        $this->device = $device;

        if (true === empty($this->action) || false === in_array($this->action, self::ALLOWED_ACTIONS)) {
            throw new \Exception('Action not allowed');
        }

        if ($device !== "all") {
            $this->deviceId = $this->getDeviceId($device);
        }
    }

    public function getDeviceId(string $label): string
    {
        $response = $this->request('all');

        if (false === in_array($response->getStatusCode(), [200])) {
            throw new \Exception("An error occurred while executing command 'toggle' for device 'device'");
        }

        $devices = json_decode($response->getBody(), true);

        foreach($devices as $device) {
            if ($device['label'] === $label) {
                return $device['id'];
            }
        }


        throw new HttpException('Device not found', 404);
    }


    public function toggle()
    {
        $response = $this->request("toggle", "POST");

        if (false === in_array($response->getStatusCode(), [207])) {
            throw new \Exception("An error occurred while executing command 'toogle' for device 'device'");
        }
    }

    private function request(string $endpoint, string $method = 'GET', bool $verbose = false): Response
    {
        $curl = curl_init();

        echo sprintf("%s/%s/%s %s", self::BASE_PATH, $this->deviceId ?? "all", $endpoint, PHP_EOL);
        curl_setopt_array($curl, array(
            CURLOPT_URL => sprintf("%s/%s/%s", self::BASE_PATH, $this->deviceId ?? "all", $endpoint),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [sprintf("Authorization: Bearer %s", self::TOKEN)]
        ));
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $response = new Response($httpCode, $response);
        curl_close($curl);

        return $response;
    }
}

class Response {
    private $statusCode;

    private $body;

    /**
     * Response constructor.
     * @param $statusCode
     * @param $body
     */
    public function __construct($statusCode, $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }
}
