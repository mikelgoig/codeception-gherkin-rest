<?php

declare(strict_types=1);

namespace MikelGoig\Codeception\Module;

use Behat\Gherkin\Node\PyStringNode;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Lib\Interfaces\PartedModule;
use Codeception\Module;
use Codeception\Module\REST as RestModule;
use Codeception\Util\JsonArray;
use Coduo\PHPMatcher\PHPUnit\PHPMatcherConstraint;
use JetBrains\PhpStorm\NoReturn;
use PHPUnit\Framework\Assert;

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
     * Send an HTTP request with body and headers.
     *
     * @param array<mixed> $request
     *
     * @part gherkin
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
     * Send an HTTP request with files.
     * `Content-Type` header is sent with `multipart/form-data`.
     *
     * @param array<mixed> $request
     *
     * @part gherkin
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
     * Set an HTTP header to be used for all subsequent requests.
     *
     * ```gherkin
     * Given the "Content-Type" request header is "application/json"
     * // all next requests will contain this header
     * ```
     *
     * @Given /^the "([^"]*)" request header is "([^"]*)"$/
     * @When /^the "([^"]*)" request header is "([^"]*)"$/
     *
     * @part gherkin
     */
    public function stepHaveHttpHeader(string $name, string $value): void
    {
        $this->restModule->haveHttpHeader($name, $value);
    }

    /**
     * Send an HTTP request.
     *
     * ```gherkin
     * When I send a "GET" request to "/users"
     * ```
     *
     * @Given /^I send a "([^"]*)" request to "([^"]*)"$/
     * @When /^I send a "([^"]*)" request to "([^"]*)"$/
     *
     * @part gherkin
     */
    public function stepSendHttpRequest(string $method, string $url): void
    {
        $this->restModule->send($method, $url);
    }

    /**
     * Send an HTTP request with body and headers.
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
     */
    public function stepSendHttpRequestWithBodyAndHeaders(string $method, string $url, PyStringNode $node): void
    {
        $request = (new JsonArray($node->getRaw()))->toArray();
        $encodedRequest = $this->buildEncodedRequest($request);
        $this->sendHttpRequestWithBodyAndHeaders($method, $url, $encodedRequest);
    }

    /**
     * Send an HTTP request with files.
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
     */
    public function stepSendHttpRequestAsFormWithFiles(string $method, string $url, PyStringNode $node): void
    {
        $request = (new JsonArray($node->getRaw()))->toArray();
        $encodedRequest = $this->buildEncodedRequest($request);
        $this->sendHttpRequestAsFormWithFiles($method, $url, $encodedRequest);
    }

    /**
     * Check that response code is equal to provided value.
     *
     * @Then /^the response code is "([^"]*)"$/
     *
     * @part gherkin
     */
    public function stepSeeResponseCodeIs(string $expected): void
    {
        $this->restModule->seeResponseCodeIs((int) $expected);
    }

    /**
     * Check that response code is 2xx.
     *
     * @Then /^the response is successful$/
     *
     * @part gherkin
     */
    public function stepSeeResponseCodeIsSuccessful(): void
    {
        $this->restModule->seeResponseCodeIsSuccessful();
    }

    /**
     * Check that the response header exists.
     *
     * @Then /^the "([^"]*)" response header exists$/
     *
     * @part gherkin
     */
    public function stepSeeHttpHeaderExists(string $name): void
    {
        $this->restModule->seeHttpHeader($name);
    }

    /**
     * Check that the value of the response header equals the provided value.
     *
     * @Then /^the "([^"]*)" response header is "([^"]*)"$/
     *
     * @part gherkin
     */
    public function stepSeeHttpHeaderIs(string $name, $value): void
    {
        $this->restModule->seeHttpHeader($name, $value);
    }

    /**
     * Check that response is empty.
     *
     * @Then /^the response body is empty$/
     *
     * @part gherkin
     */
    public function stepSeeResponseIsEmpty(): void
    {
        $this->restModule->seeResponseEquals('');
    }

    /**
     * Check whether the last JSON response contains the provided array.
     *
     * @Then /^the response body contains JSON:(.*)$/
     *
     * @part gherkin
     */
    public function stepSeeResponseContainsJson(PyStringNode $node): void
    {
        $json = (new JsonArray($node->getRaw()))->toArray();
        $this->restModule->seeResponseContainsJson($json);
    }

    /**
     * Check whether the last JSON response matches the provided array.
     * See https://github.com/coduo/php-matcher
     *
     * @Then /^the response body matches JSON:(.*)$/
     *
     * @part gherkin
     */
    public function stepSeeResponseMatchesJson(PyStringNode $node): void
    {
        $response = (new JsonArray($this->restModule->grabResponse()))->toArray();
        $pattern = (new JsonArray($node->getRaw()))->toArray();
        Assert::assertThat($response, new PHPMatcherConstraint($pattern));
    }

    /**
     * Print last response.
     *
     * @Then /^print last response$/
     *
     * @part gherkin
     */
    #[NoReturn] public function stepPrintLastResponse(): void
    {
        echo PHP_EOL;
        print_r($this->restModule->grabResponse());
        exit();
    }

    /**
     * Print last response as JSON.
     *
     * @Then /^print last response as JSON$/
     *
     * @part gherkin
     */
    #[NoReturn] public function stepPrintLastResponseAsJson(): void
    {
        echo PHP_EOL;
        print_r((new JsonArray($this->restModule->grabResponse()))->toArray());
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
