<h1>
    Codeception Module for testing REST services
</h1>

<p>
Created by <a href="https://mikelgoig.com">Mikel Goig</a>.
</p>

<p>
    <a href="https://github.com/mikelgoig/codeception-rest">
        View Repository
    </a>
</p>

---

![Packagist Version](https://img.shields.io/packagist/v/mikelgoig/codeception-rest)
![Packagist Downloads](https://img.shields.io/packagist/dt/mikelgoig/codeception-rest)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/mikelgoig/codeception-rest/php)

**This Codeception module provides you with actions for testing REST services.**

It extends the Codeception's official [REST](https://codeception.com/docs/modules/REST) module, adding some helpers
and [Gherkin format](https://codeception.com/docs/BDD) support.

## 😎 Installation

1. Install this package using Composer:

    ```bash
    composer require --dev mikelgoig/codeception-rest
    ```

## 🛠️ Configuration

1. Add the Codeception module to your config file:

    ```yml
    modules:
      enabled:
        - MikelGoig\Codeception\Module\Rest:
            depends: REST
            multipart_boundary: foo
    ```

    * `multipart_boundary` *optional* - the boundary parameter for multipart requests

2. To set up Gherkin steps, enable the `gherkin` part of the module:

    ```yml
    modules:
      enabled:
        - MikelGoig\Codeception\Module\Rest:
            # ...
            part: gherkin
    ```
