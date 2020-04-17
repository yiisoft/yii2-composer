This directory contains scripts to run composer with the yii2-composer plugin.

### Run the tests

    sh run.sh

### Files

- `run.sh` runs all the `test_*.sh` files and print the results.
- `make_composer_json.php` generates a `composer.json` file that instructs composer to load the plugin from the current directory.
  This approach is explained at <https://blog.cebe.cc/posts/2020-04-17/testing-composer-plugins>.
