<?php

namespace Styde\Dawn\Concerns;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\DomCrawler\Form;
use Styde\Dawn\HttpException;
use Styde\Dawn\Constraints\HasText;
use Styde\Dawn\Constraints\HasLink;
use Styde\Dawn\Constraints\HasValue;
use Styde\Dawn\Constraints\HasSource;
use Styde\Dawn\Constraints\IsChecked;
use Styde\Dawn\Constraints\HasElement;
use Styde\Dawn\Constraints\IsSelected;
use PHPUnit_Framework_Assert as PHPUnit;
use Styde\Dawn\Constraints\HasInElement;
use Styde\Dawn\Constraints\PageConstraint;
use Styde\Dawn\Constraints\ReversePageConstraint;
use PHPUnit_Framework_ExpectationFailedException as PHPUnitException;

trait InteractsWithPages
{
    /**
     * Make a request to the application using the given form.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @param  array  $uploads
     * @return $this
     */
    protected function makeRequestUsingForm(Form $form, array $uploads = [])
    {
        $files = $this->convertUploadsForTesting($form, $uploads);

        return $this->test->makeRequest(
            $form->getMethod(), $form->getUri(), $this->extractParametersFromForm($form), [], $files
        );
    }

    /**
     * Extract the parameters from the given form.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @return array
     */
    protected function extractParametersFromForm(Form $form)
    {
        parse_str(http_build_query($form->getValues()), $parameters);

        return $parameters;
    }

    /**
     * Follow redirects from the last response.
     *
     * @return $this
     */
    protected function followRedirects()
    {
        while ($this->response->isRedirect()) {
            $this->test->makeRequest('GET', $this->response->getTargetUrl());
        }

        return $this;
    }

    /**
     * Clear the inputs for the current page.
     *
     * @return $this
     */
    protected function clearInputs()
    {
        $this->inputs = [];

        $this->uploads = [];

        return $this;
    }

    /**
     * Assert that the current page matches a given URI.
     *
     * @param  string  $uri
     * @return $this
     */
    public function assertPathIs($uri)
    {
        if (! Str::startsWith($uri, 'http')) {
            $uri = config('app.url').'/'.ltrim($uri, '/');
        }

        PHPUnit::assertEquals(
            $uri, $this->currentUri,
            "Did not land on expected page [{$uri}].\n"
        );

        return $this;
    }

    /**
     * Assert that the current page matches a given named route.
     *
     * @param  string  $route
     * @param  array|mixed  $parameters
     * @return $this
     */
    public function assertRouteIs($route, $parameters = [])
    {
        return $this->assertPathIs(route($route, $parameters, false));
    }

    /**
     * Assert that a given page successfully loaded.
     *
     * @param  string  $uri
     * @param  string|null  $message
     * @return $this
     *
     * @throws \Laravel\BrowserKitTesting\HttpException
     */
    public function assertPageLoaded($uri, $message = null)
    {
        $status = $this->getStatusCode();

        try {
            PHPUnit::assertEquals(200, $status);
        } catch (PHPUnitException $e) {
            $message = $message ?: "A request to [{$uri}] failed. Received status code [{$status}].";

            $responseException = isset($this->response->exception)
                ? $this->response->exception : null;

            throw new HttpException($message, null, $responseException);
        }

        return $this;
    }

    /**
     * Narrow the test content to a specific area of the page.
     *
     * @param  string  $element
     * @param  \Closure  $callback
     * @return $this
     */
    public function within($element, Closure $callback)
    {
        $this->subCrawlers[] = $this->crawler()->filter($element);

        $callback();

        array_pop($this->subCrawlers);

        return $this;
    }

    /**
     * Get the current crawler according to the test context.
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function crawler()
    {
        if (! empty($this->subCrawlers)) {
            return end($this->subCrawlers);
        }

        return $this->crawler;
    }

    /**
     * Assert the given constraint.
     *
     * @param  \Laravel\BrowserKitTesting\Constraints\PageConstraint  $constraint
     * @param  bool  $reverse
     * @param  string  $message
     * @return $this
     */
    public function assertInPage(PageConstraint $constraint, $reverse = false, $message = '')
    {
        if ($reverse) {
            $constraint = new ReversePageConstraint($constraint);
        }

        PHPUnit::assertThat($this->crawler(), $constraint, $message);

        return $this;
    }

    /**
     * Assert that a given string is seen on the current text.
     *
     * @param  string  $text
     * @return $this
     */
    public function assertSee($text)
    {
        return $this->assertInPage(new HasText($text));
    }

    /**
     * Assert that a given string is not seen on the current text.
     *
     * @param  string  $text
     * @return $this
     */
    public function assertDontSee($text)
    {
        return $this->assertInPage(new HasText($text), true);
    }

    /**
     * Assert that a given string is present on the current HTML.
     *
     * @param  string  $code
     * @return $this
     */
    public function assertSourceHas($code)
    {
        return $this->assertInPage(new HasSource($code));
    }

    /**
     * Assert that a given string is not present on the current HTML.
     *
     * @param  string  $code
     * @return $this
     */
    public function assertSourceMissing($code)
    {
        return $this->assertInPage(new HasText($code), true);
    }

    /**
     * Assert that an element is present on the page.
     *
     * @param  string  $selector
     * @param  array  $attributes
     * @param  bool  $negate
     * @return $this
     */
    public function assertElement($selector, array $attributes = [], $negate = false)
    {
        return $this->assertInPage(new HasElement($selector, $attributes), $negate);
    }

    /**
     * Assert that an element is not present on the page.
     *
     * @param  string  $selector
     * @param  array  $attributes
     * @return $this
     */
    public function assertElementMissing($selector, array $attributes = [])
    {
        return $this->assertInPage(new HasElement($selector, $attributes), true);
    }

    /**
     * Assert that a given string is seen inside an element.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    public function assertSeeIn($element, $text)
    {
        return $this->assertInPage(new HasInElement($element, $text));
    }

    /**
     * Assert that a given string is not seen inside an element.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    public function assertDontSeeIn($element, $text)
    {
        return $this->assertInPage(new HasInElement($element, $text), true);
    }

    /**
     * Assert that a given link is seen on the page.
     *
     * @param  string $text
     * @param  string|null $url
     * @return $this
     */
    public function assertSeeLink($text, $url = null)
    {
        return $this->assertInPage(new HasLink($text, $url));
    }

    /**
     * Assert that a given link is not seen on the page.
     *
     * @param  string  $text
     * @param  string|null  $url
     * @return $this
     */
    public function assertDontSeeLink($text, $url = null)
    {
        return $this->assertInPage(new HasLink($text, $url), true);
    }

    /**
     * Assert that an input field contains the given value.
     *
     * @param  string  $selector
     * @param  string  $expected
     * @return $this
     */
    public function assertInputValue($selector, $expected)
    {
        return $this->assertInPage(new HasValue($selector, $expected));
    }

    /**
     * Assert that an input field does not contain the given value.
     *
     * @param  string  $selector
     * @param  string  $value
     * @return $this
     */
    public function assertInputValueIsNot($selector, $value)
    {
        return $this->assertInPage(new HasValue($selector, $value), true);
    }

    /**
     * Assert that the expected value is selected.
     *
     * @param  string  $selector
     * @param  string  $value
     * @return $this
     */
    public function assertSelected($selector, $value)
    {
        return $this->assertInPage(new IsSelected($selector, $value));
    }

    /**
     * Assert that the given value is not selected.
     *
     * @param  string  $selector
     * @param  string  $value
     * @return $this
     */
    public function assertNotSelected($selector, $value)
    {
        return $this->assertInPage(new IsSelected($selector, $value), true);
    }

    /**
     * Assert that the given checkbox is selected.
     *
     * @param  string  $selector
     * @return $this
     */
    public function assertChecked($selector)
    {
        return $this->assertInPage(new IsChecked($selector));
    }

    /**
     * Assert that the given checkbox is not selected.
     *
     * @param  string  $selector
     * @return $this
     */
    public function assertNotChecked($selector)
    {
        return $this->assertInPage(new IsChecked($selector), true);
    }

    /**
     * Click a link with the given body, name, or ID attribute.
     *
     * @param  string  $name
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function click($name)
    {
        $link = $this->crawler()->selectLink($name);

        if (! count($link)) {
            $link = $this->filterByNameOrId($name, 'a');

            if (! count($link)) {
                throw new InvalidArgumentException(
                    "Could not find a link with a body, name, or ID attribute of [{$name}]."
                );
            }
        }

        return $this->test->visit($link->link()->getUri());
    }

    /**
     * Fill an input field with the given text.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    public function type($element, $text)
    {
        return $this->storeInput($element, $text);
    }

    /**
     * Check a checkbox on the page.
     *
     * @param  string  $element
     * @return $this
     */
    public function check($element)
    {
        return $this->storeInput($element, true);
    }

    /**
     * Uncheck a checkbox on the page.
     *
     * @param  string  $element
     * @return $this
     */
    public function uncheck($element)
    {
        return $this->storeInput($element, false);
    }

    /**
     * Select an option from a drop-down.
     *
     * @param  string  $element
     * @param  string  $option
     * @return $this
     */
    public function select($element, $option)
    {
        return $this->storeInput($element, $option);
    }

    /**
     * Attach a file to a form field on the page.
     *
     * @param  string  $element
     * @param  string  $absolutePath
     * @return $this
     */
    public function attach($element, $absolutePath)
    {
        $this->uploads[$element] = $absolutePath;

        return $this->storeInput($element, $absolutePath);
    }

    /**
     * Submit a form using the button with the given text value.
     *
     * @param  string  $buttonText
     * @return $this
     */
    public function press($buttonText)
    {
        return $this->submitForm($buttonText, $this->inputs, $this->uploads);
    }

    /**
     * Submit a form on the page with the given input.
     *
     * @param  string  $buttonText
     * @param  array  $inputs
     * @param  array  $uploads
     * @return $this
     */
    protected function submitForm($buttonText, $inputs = [], $uploads = [])
    {
        $this->makeRequestUsingForm($this->fillForm($buttonText, $inputs), $uploads);

        return $this;
    }

    /**
     * Fill the form with the given data.
     *
     * @param  string  $buttonText
     * @param  array  $inputs
     * @return \Symfony\Component\DomCrawler\Form
     */
    protected function fillForm($buttonText, $inputs = [])
    {
        if (! is_string($buttonText)) {
            $inputs = $buttonText;

            $buttonText = null;
        }

        return $this->getForm($buttonText)->setValues($inputs);
    }

    /**
     * Get the form from the page with the given submit button text.
     *
     * @param  string|null  $buttonText
     * @return \Symfony\Component\DomCrawler\Form
     *
     * @throws \InvalidArgumentException
     */
    protected function getForm($buttonText = null)
    {
        try {
            if ($buttonText) {
                return $this->crawler()->selectButton($buttonText)->form();
            }

            return $this->crawler()->filter('form')->form();
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                "Could not find a form that has submit button [{$buttonText}]."
            );
        }
    }

    /**
     * Store a form input in the local array.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    protected function storeInput($element, $text)
    {
        $this->assertFilterProducesResults($element);

        $element = str_replace(['#', '[]'], '', $element);

        $this->inputs[$element] = $text;

        return $this;
    }

    /**
     * Assert that a filtered Crawler returns nodes.
     *
     * @param  string  $filter
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function assertFilterProducesResults($filter)
    {
        $crawler = $this->filterByNameOrId($filter);;

        if (! count($crawler)) {
            throw new InvalidArgumentException(
                "Nothing matched the filter [{$filter}] CSS query provided for [{$this->currentUri}]."
            );
        }
    }

    /**
     * Filter elements according to the given name or ID attribute.
     *
     * @param  string  $name
     * @param  array|string  $elements
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function filterByNameOrId($name, $elements = '*')
    {
        $name = str_replace('#', '', $name);

        $id = str_replace(['[', ']'], ['\\[', '\\]'], $name);

        $elements = is_array($elements) ? $elements : [$elements];

        array_walk($elements, function (&$element) use ($name, $id) {
            $element = "{$element}#{$id}, {$element}[name='{$name}']";
        });

        return $this->crawler()->filter(implode(', ', $elements));
    }

    /**
     * Convert the given uploads to UploadedFile instances.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @param  array  $uploads
     * @return array
     */
    protected function convertUploadsForTesting(Form $form, array $uploads)
    {
        $files = $form->getFiles();

        $names = array_keys($files);

        $files = array_map(function (array $file, $name) use ($uploads) {
            return isset($uploads[$name])
                ? $this->getUploadedFileForTesting($file, $uploads, $name)
                : $file;
        }, $files, $names);

        $uploads = array_combine($names, $files);

        foreach ($uploads as $key => $file) {
            if (preg_match('/.*?(?:\[.*?\])+/', $key)) {
                $this->prepareArrayBasedFileInput($uploads, $key, $file);
            }
        }

        return $uploads;
    }

    /**
     * Store an array based file upload with the proper nested array structure.
     *
     * @param  array  $uploads
     * @param  string  $key
     * @param  mixed  $file
     */
    protected function prepareArrayBasedFileInput(&$uploads, $key, $file)
    {
        preg_match_all('/([^\[\]]+)/', $key, $segments);

        $segments = array_reverse($segments[1]);

        $newKey = array_pop($segments);

        foreach ($segments as $segment) {
            $file = [$segment => $file];
        }

        $uploads[$newKey] = $file;

        unset($uploads[$key]);
    }

    /**
     * Create an UploadedFile instance for testing.
     *
     * @param  array  $file
     * @param  array  $uploads
     * @param  string  $name
     * @return \Illuminate\Http\UploadedFile
     */
    protected function getUploadedFileForTesting($file, $uploads, $name)
    {
        return new UploadedFile(
            $file['tmp_name'], basename($uploads[$name]), $file['type'], $file['size'], $file['error'], true
        );
    }
}
