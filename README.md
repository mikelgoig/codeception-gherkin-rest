<h1>
    Codeception Module: REST Gherkin Steps
</h1>

<p>
Created by <a href="https://mikelgoig.com">Mikel Goig</a>.
</p>

<p>
    <a href="https://github.com/mikelgoig/codeception-gherkin-rest">
        View Repository
    </a>
</p>

---

**This Codeception module provides you with [REST](https://github.com/Codeception/module-rest) steps in Gherkin format.**

## 😎 Installation

1. Install this package using Composer:

    ```bash
    composer require --dev mikelgoig/codeception-gherkin-rest
    ```

## 🛠️ Configuration

1. Add the Codeception module to your config file:

    ```yml
    modules:
      enabled:
        - Gherkin\REST:
            depends: [REST]
            multipart_boundary: foo
    ```
