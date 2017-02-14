# Styde Dawn

Testing the idea of having a browser kit testing for laravel 5.4 with an API similar to the one provided by Laravel Dusk

# Usage:

Include the trait `Styde\Dawn\SupportsBrowserKit` in your `TestCase` class:

```
<?php

namespace Tests;

use TestCase;
use Styde\Dawn\SupportsBrowserKit;

class DawnTestCase extends TestCase
{
    use SupportsBrowserKit;
}
```

And that's it! Now you will be able to call `visit` which will return an instance of the `Styde\Dawn\TestResponse` class.

This test response class includes methods such as: `assertPathIs` or `assertSeeIn` that will allow you to test the response using the Browser kit testing.

The main difference / idea vs the current Browser Kit Testing package (https://github.com/laravel/browser-kit-testing) is reproducing the Laravel Dusk API, so it is easier to move the test between the two packages 

(i.e. use browser kit testing when JavaScript is not required to speed up the tests, or Dusk in order to be able to test JavaScript, get screenshots etc.

This is not suppose to be a legacy package either but to be actively maintained in case the community finds it useful, therefore I'm looking forward to read your thoughts. 
