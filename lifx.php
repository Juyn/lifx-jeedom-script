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
} else {
    throw new Exception(sprintf("Method %s does not exist or is not yet implemented. If you want, feel free to contribute: https://github.com/Juyn/lifx-jeedom-script"));
}

class Lifx
{
    const ALLOWED_ACTIONS = [
        'toggle',
        'getState',
    ];

    const TOKEN = '<PERSONNAL_ACCESS_TOKEN>'; // To get an API key, visit https://cloud.lifx.com/settings and generate a new Personal access tokens
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
     * Lifx constructor
     *
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

    /**
     * Fetch the device ID from Lifx
     *
     * @param string $label
     *
     * @return string
     *
     * @throws HttpException
     */
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

    /**
     * Toggle power on device
     *
     * @throws Exception
     */
    public function toggle(): void
    {
        $response = $this->request($this->getUri("toggle"), "POST");

        if (false === in_array($response->getStatusCode(), [207])) {
            throw new \Exception("An error occurred while executing command 'toogle' for device 'device'");
        }
    }

    /**
     * Create an URI with the appropriate DeviceId
     *
     * @param string $endpoint
     *
     * @return string
     */
    private function getUri(string $endpoint): string
    {
        return sprintf('%s/%s', $this->deviceId ?? 'all', $endpoint);
    }


    /**
     * Send the Request to Lifx API using a Bearer Auth
     *
     * @param string $uri URI to call
     * @param string $method Default GET
     * @param bool $verbose
     *
     * @return Response
     */
    private function request(string $uri, string $method = 'GET', bool $verbose = false): Response
    {
        $curl = curl_init();

        if (true === empty($uri)) {
            throw new \http\Exception\InvalidArgumentException('Argument "URI" should not be empty');
        }

        if ($verbose) {
            echo sprintf("%s/%s", self::BASE_PATH, $uri);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => sprintf("%s/%s", self::BASE_PATH, $uri),
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
    /**
     * @var int|null
     */
    private $statusCode;

    /**
     * @var string|null
     */
    private $body;

    /**
     * Response constructor.
     * @param int|null $statusCode
     * @param string|null $body
     */
    public function __construct($statusCode, $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    /**
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }
}
