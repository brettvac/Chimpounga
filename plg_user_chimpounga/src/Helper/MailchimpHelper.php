<?php
/**
 * @package     Joomla.Site
 * @subpackage  plg_user_chimpounga
 *
 * @version     1.2
 * @license     GNU General Public License version 2
 */

namespace Naftee\Plugin\User\Chimpounga\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

/**
 * Mailchimp API v3 helper.
 *
 * Handles HTTP communication with the Mailchimp API using Joomla's HTTP client.
 *
 * @since  1.2
 */
class MailchimpHelper
{
    /**
     * API request timeout in seconds.
     *
     * @var int
     */
    private const TIMEOUT = 10;

    /**
     * Mailchimp API endpoint.
     *
     * @var string
     */
    private string $apiEndpoint;

    /**
     * Mailchimp API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Joomla HTTP client.
     *
     * @var \Joomla\CMS\Http\Http
     */
    private $http;

    /**
     * Whether the last request was successful.
     *
     * @var bool
     */
    private bool $requestSuccessful = false;

    /**
     * Last request error.
     *
     * @var string
     */
    private string $lastError = '';

    /**
     * Last API response.
     *
     * @var array
     */
    private array $lastResponse = [];

    /**
     * Constructor.
     *
     * @param   string  $apiKey  Mailchimp API key.
     *
     * @throws  \InvalidArgumentException
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = trim($apiKey);

        // Validate the API key and extract its data-center suffix.
        $separator = strrpos($this->apiKey, '-');

        if ($separator === false) {
            throw new \InvalidArgumentException(
                'Invalid Mailchimp API key supplied.'
            );
        }

        $dataCenter = substr($this->apiKey, $separator + 1);

        if (!preg_match('/^[a-z0-9]+$/i', $dataCenter)) {
            throw new \InvalidArgumentException(
                'Invalid Mailchimp API key supplied.'
            );
        }

        $this->apiEndpoint = 'https://' . $dataCenter . '.api.mailchimp.com/3.0';

        // Initialize Joomla HTTP client.
        $this->http = HttpFactory::getHttp([
            'timeout' => self::TIMEOUT,
        ]);
    }

    /**
     * Convert an email address into a Mailchimp subscriber hash.
     *
     * @param   string  $email  Subscriber email address.
     *
     * @return  string
     */
    public static function subscriberHash(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    /**
     * Make a GET request.
     *
     * @param   string  $method  Mailchimp API endpoint.
     * @param   array   $args    Query parameters.
     *
     * @return  array|false
     */
    public function get(string $method, array $args = []): array|false
    {
        return $this->makeRequest('get', $method, $args);
    }

    /**
     * Make a POST request.
     *
     * @param   string  $method  Mailchimp API endpoint.
     * @param   array   $args    Request payload.
     *
     * @return  array|false
     */
    public function post(string $method, array $args = []): array|false
    {
        return $this->makeRequest('post', $method, $args);
    }

    /**
     * Make a PATCH request.
     *
     * @param   string  $method  Mailchimp API endpoint.
     * @param   array   $args    Request payload.
     *
     * @return  array|false
     */
    public function patch(string $method, array $args = []): array|false
    {
        return $this->makeRequest('patch', $method, $args);
    }

    /**
     * Determine whether the last request was successful.
     *
     * @return  bool
     */
    public function success(): bool
    {
        return $this->requestSuccessful;
    }

    /**
     * Get the last request error.
     *
     * @return  string|false
     */
    public function getLastError(): string|false
    {
        return $this->lastError ?: false;
    }

    /**
     * Get the last API response.
     *
     * @return  array
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }

    /**
     * Log API request details when Joomla debugging is enabled.
     *
     * @param   string  $message  Debug message.
     *
     * @return  void
     */
    private function logDebug(string $message): void
    {
        if (defined('JDEBUG') && JDEBUG) {
            Log::add($message, Log::DEBUG, 'chimpounga-debug');
        }
    }

    /**
     * Execute a Mailchimp API request.
     *
     * @param   string  $verb    HTTP method.
     * @param   string  $method  Mailchimp API endpoint.
     * @param   array   $args    Request parameters or payload.
     *
     * @return  array|false
     */
    private function makeRequest(
        string $verb,
        string $method,
        array $args = []
    ): array|false {
        // Reset request state.
        $this->requestSuccessful = false;
        $this->lastError = '';
        $this->lastResponse = [];

        // Prevent malformed or absolute URLs.
        $method = ltrim($method, '/');

        if ($method === '' || str_contains($method, '://')) {
            $this->lastError = 'Invalid Mailchimp API endpoint.';

            return false;
        }

        $url = $this->apiEndpoint . '/' . $method;

        // Prepare request headers.
        $headers = [
            'Accept'        => 'application/vnd.api+json',
            'Content-Type'  => 'application/vnd.api+json',
            'Authorization' => 'apikey ' . $this->apiKey,
        ];

        // Support Mailchimp's optional language header.
        if (isset($args['language'])) {
            $headers['Accept-Language'] = $args['language'];
            unset($args['language']);
        }

        try {
            switch ($verb) {
                case 'get':
                    // Joomla's HTTP GET expects query parameters in the URL.
                    if ($args !== []) {
                        $url .= '?' . http_build_query(
                            $args,
                            '',
                            '&',
                            PHP_QUERY_RFC3986
                        );
                    }

                    $response = $this->http->get(
                        $url,
                        $headers,
                        self::TIMEOUT
                    );
                    break;

                case 'post':
                    $response = $this->http->post(
                        $url,
                        json_encode($args, JSON_THROW_ON_ERROR),
                        $headers,
                        self::TIMEOUT
                    );
                    break;

                case 'patch':
                    $response = $this->http->patch(
                        $url,
                        json_encode($args, JSON_THROW_ON_ERROR),
                        $headers,
                        self::TIMEOUT
                    );
                    break;

                default:
                    $this->lastError = 'Unsupported HTTP method.';

                    return false;
            }

            // Store the response using Joomla's response properties.
            $this->lastResponse = [
                'code'    => $response->code,
                'headers' => $response->headers,
                'body'    => $response->body,
            ];

            $result = json_decode($response->body, true);

            // Log every completed HTTP request, including unsuccessful responses.
            $this->logDebug(sprintf(
                'Mailchimp API %s %s returned HTTP %d.',
                strtoupper($verb),
                $method,
                $response->code
            ));

            // Reject invalid JSON responses.
            if (!is_array($result) && $response->body !== '') {
                $this->lastError = 'Invalid JSON response from Mailchimp.';

                return false;
            }

            // Check the HTTP response status.
            if ($response->code >= 200 && $response->code <= 299) {
                $this->requestSuccessful = true;

                return $result ?? [];
            }

            // Extract the error returned by Mailchimp.
            if (isset($result['detail'])) {
                $this->lastError = sprintf(
                    '%d: %s',
                    $result['status'] ?? $response->code,
                    $result['detail']
                );
            } else {
                $this->lastError = sprintf(
                    'Mailchimp API returned HTTP status %d.',
                    $response->code
                );
            }

            // Return the decoded error response when available.
            return $result ?: false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            $this->logDebug(sprintf(
                'Mailchimp API %s %s failed: %s',
                strtoupper($verb),
                $method,
                $e->getMessage()
            ));

            return false;
        }
    }
}