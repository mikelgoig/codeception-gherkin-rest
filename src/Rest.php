<?php

declare(strict_types=1);

namespace MikelGoig\Codeception\Module;

use Behat\Gherkin\Node\PyStringNode;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\Module;
use Codeception\Module\REST as RestModule;
use JetBrains\PhpStorm\NoReturn;

/**
 * @phpstan-type TEncodedRequest array{
 *     body: array<string, string>,
 *     files: array<string, string>,
 *     headers: array<string, string>,
 *     query: array<string, string>,
 * }
 */
class Rest extends Module implements DependsOnModule, PartedModule
{
    protected RestModule $restModule;
    protected ?string $multipartBoundary;
    protected string $dependencyMessage = <<<EOF
Example configuring module:
--
modules:
    enabled:
        - MikelGoig\Codeception\Module\Rest:
            depends: REST
--
EOF;

    /**
     * @return array<class-string, string>
     */
    public function _depends(): array
    {
        return [
            RestModule::class => $this->dependencyMessage,
        ];
    }

    public function _parts(): array
    {
        return ['gherkin'];
    }

    public function _inject(RestModule $restModule): void
    {
        $this->restModule = $restModule;
    }

    public function _initialize(): void
    {
        $this->multipartBoundary = $this->config['multipart_boundary'] ?? null;
    }

    /**
     * Sends an HTTP request with body and headers.
     *
     * @param array<mixed> $request
     */
    public function sendHttpRequestWithBodyAndHeaders(string $method, string $url, array $request): void
    {
        $request = $this->buildEncodedRequest($request);

        foreach ($request['headers'] as $key => $value) {
            $this->restModule->haveHttpHeader($key, $value);
        }

        $this->restModule->send($method, $url, $this->extractParams($method, $request));

        foreach ($request['headers'] as $key => $value) {
            $this->restModule->unsetHttpHeader($key);
        }
    }

    /**
     * Sends an HTTP request with files.
     * `Content-Type` header is sent with `multipart/form-data`.
     *
     * @param array<mixed> $request
     */
    public function sendHttpRequestAsFormWithFiles(string $method, string $url, array $request): void
    {
        if ($this->multipartBoundary === null) {
            throw new \RuntimeException('Multipart boundary is not set. Please set it in config.');
        }

        $request = $this->buildEncodedRequest($request);

        $this->restModule->haveHttpHeader('Content-Type', 'multipart/form-data; boundary=' . $this->multipartBoundary);
        foreach ($request['headers'] as $key => $value) {
            $this->restModule->haveHttpHeader($key, $value);
        }

        $this->restModule->send(
            $method,
            $url,
            $this->extractParams($method, $request),
            $this->extractFiles($request),
        );

        $this->restModule->unsetHttpHeader('Content-Type');
        foreach ($request['headers'] as $key => $value) {
            $this->restModule->unsetHttpHeader($key);
        }
    }

    #--------------------------------------------------------------------------
    # Gherkin Steps
    #--------------------------------------------------------------------------

    /**
     * Sets an HTTP header to be used for all subsequent requests.
     *
     * ```gherkin
     * Given I have a "Content-Type" header set to "application/json"
     * // all next requests will contain this header
     * ```
     *
     * @Given /^I have a(?:n)? "([^"]*)" header set to "([^"]*)"$/
     * @When /^I have a(?:n)? "([^"]*)" header set to "([^"]*)"$/
     *
     * @part gherkin
     * @see RestModule::haveHttpHeader()
     */
    public function stepHaveHttpHeader(string $name, string $value): void
    {
        $this->restModule->haveHttpHeader($name, $value);
    }

    /**
     * Sends an HTTP request.
     *
     * ```gherkin
     * When I send a "GET" request to "/users"
     * ```
     *
     * @Given /^I send a "([^"]*)" request to "([^"]*)"$/
     * @When /^I send a "([^"]*)" request to "([^"]*)"$/
     *
     * @part gherkin
     * @see RestModule::send()
     */
    public function stepSendHttpRequest(string $method, string $url): void
    {
        $this->restModule->send($method, $url);
    }

    /**
     * Sends an HTTP request with body and headers.
     *
     * ```gherkin
     *  When I send a "POST" request to "/users" with:
     *      """
     *      {
     *          headers: {
     *              "foo": "bar"
     *          },
     *          body: {
     *              "foo": "bar"
     *          }
     *      }
     *      """
     *  ```
     *
     * @Given /^I send a "([^"]*)" request to "([^"]*)" with:(.*)$/
     * @When /^I send a "([^"]*)" request to "([^"]*)" with:(.*)$/
     *
     * @part gherkin
     * @see self::sendHttpRequestWithBodyAndHeaders()
     */
    public function stepSendHttpRequestWithBodyAndHeaders(string $method, string $url, PyStringNode $node): void
    {
        $request = json_decode($node->getRaw(), true, flags: \JSON_THROW_ON_ERROR);
        $encodedRequest = $this->buildEncodedRequest($request);
        $this->sendHttpRequestWithBodyAndHeaders($method, $url, $encodedRequest);
    }

    /**
     * Sends an HTTP request with files.
     * `Content-Type` header is sent with `multipart/form-data`.
     *
     * ```gherkin
     *  When I send a "POST" request to "/files" as FORM with:
     *      """
     *      {
     *          headers: {
     *              "foo": "bar"
     *          },
     *          body: {
     *              "foo": "bar"
     *          },
     *          files: {
     *              "file": "test.jpg"
     *          }
     *      }
     *      """
     *  ```
     *
     * @Given /^I send a "([^"]*)" request to "([^"]*)" as FORM with:(.*)$/
     * @When /^I send a "([^"]*)" request to "([^"]*)" as FORM with:(.*)$/
     *
     * @part gherkin
     * @see self::sendHttpRequestAsFormWithFiles()
     */
    public function stepSendHttpRequestAsFormWithFiles(string $method, string $url, PyStringNode $node): void
    {
        $request = json_decode($node->getRaw(), true, flags: \JSON_THROW_ON_ERROR);
        $encodedRequest = $this->buildEncodedRequest($request);
        $this->sendHttpRequestAsFormWithFiles($method, $url, $encodedRequest);
    }

    /**
     * Checks that response code is equal to provided value.
     *
     * @Then /^I should receive a "([^"]*)" response code$/
     *
     * @part gherkin
     * @see RestModule::seeResponseCodeIs()
     */
    public function stepSeeResponseCodeIs(string $expected): void
    {
        $this->restModule->seeResponseCodeIs((int) $expected);
    }

    /**
     * Checks that response code is 2xx.
     *
     * @Then /^I should receive a successful response code$/
     *
     * @part gherkin
     * @see RestModule::seeResponseCodeIsSuccessful()
     */
    public function stepSeeResponseCodeIsSuccessful(): void
    {
        $this->restModule->seeResponseCodeIsSuccessful();
    }

    /**
     * Checks that response is empty.
     *
     * @Then /^I should receive an empty response$/
     *
     * @part gherkin
     */
    public function stepSeeResponseIsEmpty(): void
    {
        $this->restModule->seeResponseEquals('');
    }

    /**
     * Checks whether the last JSON response contains the provided array.
     *
     * @Then /^I should receive a JSON response that contains:(.*)$/
     *
     * @part gherkin
     * @see RestModule::seeResponseContainsJson()
     */
    public function stepSeeResponseContainsJson(PyStringNode $node): void
    {
        $json = \json_decode($node->getRaw(), true, flags: \JSON_THROW_ON_ERROR);
        $this->restModule->seeResponseContainsJson($json);
    }

    /**
     * Prints last response.
     *
     * @Given /^I print last response$/
     * @When /^I print last response$/
     * @Then /^I print last response$/
     *
     * @part gherkin
     * @see RestModule::grabResponse()
     */
    #[NoReturn] public function stepPrintLastResponse(): void
    {
        echo PHP_EOL;
        print_r($this->restModule->grabResponse());
        exit();
    }

    /**
     * Prints last response as JSON.
     *
     * @Given /^I print last response as JSON$/
     * @When /^I print last response as JSON$/
     * @Then /^I print last response as JSON$/
     *
     * @part gherkin
     * @see RestModule::grabResponse()
     */
    #[NoReturn] public function stepPrintLastResponseAsJson(): void
    {
        echo PHP_EOL;
        print_r(json_decode($this->restModule->grabResponse(), true, flags: \JSON_THROW_ON_ERROR));
        exit();
    }

    #--------------------------------------------------------------------------

    /**
     * @param array<mixed> $request
     * @return TEncodedRequest
     */
    protected function buildEncodedRequest(array $request): array
    {
        if (isset($request['body'])) {
            \assert(is_array($request['body']));
        }

        if (isset($request['files'])) {
            \assert(is_array($request['files']));
        }

        if (isset($request['headers'])) {
            \assert(is_array($request['headers']));
        }

        if (isset($request['query'])) {
            \assert(is_array($request['query']));
        }

        return [
            'body' => $request['body'] ?? [],
            'files' => $request['files'] ?? [],
            'headers' => $request['headers'] ?? [],
            'query' => $request['query'] ?? [],
        ];
    }

    /**
     * @param TEncodedRequest $encodedRequest
     * @return array<string, string>|string
     */
    protected function extractParams(string $method, array $encodedRequest): array|string
    {
        if ($method === 'GET') {
            return $encodedRequest['query'];
        }

        if ($encodedRequest['files'] !== []) {
            return $encodedRequest['body'];
        }

        return $encodedRequest['body'] !== [] ? json_encode($encodedRequest['body'], \JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @param TEncodedRequest $encodedRequest
     * @return array<string, string>
     */
    protected function extractFiles(array $encodedRequest): array
    {
        return array_map(fn(string $filename) => codecept_data_dir($filename), $encodedRequest['files']);
    }
}
