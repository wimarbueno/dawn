<?php

namespace Styde\Dawn;

use Symfony\Component\DomCrawler\Crawler;
use Styde\Dawn\Concerns\InteractsWithPages;
use \Illuminate\Foundation\Testing\TestResponse as FoundationTestResponse;

class TestResponse extends FoundationTestResponse
{
    use InteractsWithPages;

    /**
     * Reference to the parent's response.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public $parent;

    /**
     * The current test case being executed.
     *
     * @var \Illuminate\Foundation\Testing
     */
    protected $test;

    /**
     * The current URL being viewed.
     *
     * @var string
     */
    protected $currentUri;

    /**
     * The DomCrawler instance.
     *
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler;

    /**
     * Nested crawler instances used by the "within" method.
     *
     * @var array
     */
    protected $subCrawlers = [];

    /**
     * All of the stored inputs for the current page.
     *
     * @var array
     */
    protected $inputs = [];

    /**
     * All of the stored uploads for the current page.
     *
     * @var array
     */
    protected $uploads = [];

    /**
     * Initialize the crawler and store the current URI and the Test Case class.
     *
     * @param  \Illuminate\Foundation\Testing  $testCase
     * @param  string  $currentUri
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setup($testCase, $currentUri)
    {
        $this->crawler = new Crawler($this->getContent(), $currentUri);

        $this->currentUri = $currentUri;

        $this->test = $testCase;

        return $this;
    }

    /**
     * Convert the given response into a TestResponse.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return static
     */
    public static function fromBaseResponse($response)
    {
        $testResponse = parent::fromBaseResponse($response);

        $testResponse->parent = $response;

        return $testResponse;
    }
}
