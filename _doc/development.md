# Development

Install development dependencies with Composer:

```bash
composer install
```

Run unit and integration tests with Codeception:

```bash
php _vendor/bin/codecept run unit
php _vendor/bin/codecept run integration
```

Acceptance tests require the built-in PHP server and test databases.
Use the repository helper script to prepare caches, start the server on `localhost:8881`,
run the acceptance suite, and stop the server:

```bash
./test_sh
```
