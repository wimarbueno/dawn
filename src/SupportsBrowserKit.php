<?php

namespace Styde\Dawn;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;

trait SupportsBrowserKit
{
    /**
     * Store the last response
     *
     * @var \Styde\Dawn\TestResponse
     */
    public $lastResponse;

    /**
     * @see \Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication::actingAs
     */
    public function loginAs(UserContract $user, $driver = null)
    {
        return $this->actingAs($user, $driver);
    }

    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function visit($uri)
    {
        return $this->makeRequest('GET', $uri);
    }

    /**
     * Visit the given named route with a GET request.
     *
     * @param  string  $route
     * @param  array  $parameters
     * @return $this
     */
    public function visitRoute($route, $parameters = [])
    {
        return $this->makeRequest('GET', route($route, $parameters));
    }

    /**
     * Make a request to the application and create a Crawler instance.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @return $this
     */
    public function makeRequest($method, $uri, $parameters = [], $cookies = [], $files = [])
    {
        $uri = $this->prepareUrlForRequest($uri);

        $response = $this->call($method, $uri, $parameters, $cookies, $files);

        $response = $this->followRedirects($response);

        $uri = $this->app->make('request')->fullUrl();

        $response = TestResponse::fromBaseResponse($response)
            ->setup($this, $uri)
            ->assertPageLoaded($uri);

        return $this->lastResponse = $response;
    }

    /**
     * Follow redirects from the last response.
     *
     * @return $this
     */
    protected function followRedirects($response)
    {
        while ($response->parent->isRedirect()) {
            $response = $this->makeRequest('GET', $response->parent->getTargetUrl());
        }

        return $response;
    }

    /**
     * Create the test response instance from the given response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return \Illuminate\Foundation\Testing\TestResponse
     */
    protected function createTestResponse($response)
    {
        return TestResponse::fromBaseResponse($response);
    }

    public function __call($method, $parameters = [])
    {
        return $this->lastResponse->$method(...$parameters);
    }
}
