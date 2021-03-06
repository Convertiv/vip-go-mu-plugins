# VIP Go mu-plugins

This is the development repo for mu-plugins on [VIP Go](https://wpvip.com/documentation/vip-go/),

## Development

### Local Dev

We recommend using the Lando-based development environment for local development: https://github.com/Automattic/vip-go-mu-dev

Follow the instructions in the `vip-go-mu-dev` repo to get set up (it includes a clone of this repo).

### Tests

##### PHP Lint

```bash
npm run phplint
```

##### PHPCS

We use eslines to incrementally scan changed code. It will automatically run on pre-commit (see `.huskyrc.json`).

This is also run on Circle CI for all PRs.

If you want too scan the entire codebase:

```bash
npm run phpcs
```

##### PHPUnit

If you're using the Lando-based environvment and it's already running, you can run unit tests by calling:

```bash
lando test
```

If you don't have the Lando-based environment running (e.g. in a CI context), we have a script that runs unit tests in a self-contained Docker environment.  To run these tests, execute the following from the project root:

```bash
./bin/phpunit-docker.sh [wp-version]
```

You can either pass a version number to test against a specific version, or leave it blank to test against the latest version.

You can also pass the path to a specific test as well as extra PHPUnit arguments:

```bash
./bin/phpunit-docker.sh tests/path/to/the/test-something.php --stop-on-failure [...args]
```

##### CI

PHP Linting and PHPUnit tests are run by Circle CI as part of PRs and merges. See [`.circleci/config.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.circleci/config.yml) for more.

##### Core tests

We run core tests as part of the CI pipeline. We run core tests both with and without mu-plugins installed. There are many failures when running with mu-plugins so we had to ignore several tests. To add another test there chack `bin/utils.sh`.

To investigate failing test locally you can do following (buckle up as this is not so easy:()):

1. While in your mu-plugins folder do `MU_PLUGINS_DIR=$(pwd)`

1. Switch to where you want to checkout core code e.g. `cd ~/svn/wp`

1. Checkout the core code (pick the latest version): `svn co --quiet --ignore-externals https://develop.svn.wordpress.org/tags/5.5.3 .`

1. Create test config: `cp wp-tests-config-sample.php wp-tests-config.php && sed -i 's/youremptytestdbnamehere/wordpress_test/; s/yourusernamehere/root/; s/yourpasswordhere//; s/localhost/127.0.0.1/' wp-tests-config.php`

1. Build core `npm ci && npm run build`

1. Export env variable `export WP_TESTS_DIR="$(pwd)/tests/phpunit"`

1. Start local DB: `docker run -d -p 3306:3306 circleci/mariadb:10.2`

1. Create empty DB `mysqladmin create wordpress_test --user="root" --password="" --host="127.0.0.1" --protocol=tcp`

1. Copy over MU-plugins `cp -r $MU_PLUGINS_DIR build/wp-content/mu-plugins`

1. Run the test you want (in this case `test_allowed_anon_comments`) `$MU_PLUGINS_DIR/vendor/bin/phpunit --filter test_allowed_anon_comments`

### PHPDoc

You can find selective PHPDoc documentation here: https://automattic.github.io/vip-go-mu-plugins/

These are generated via CI by the [`generate-docs.sh`]() script.

## Deployment

### Production

**For Automattic Use:** Instructions are in the FG :)

### vip-go-mu-plugins-built

This is a repo primarily meant for local non-development use.

Every commit merged into `master` is automatically pushed to the public copy at [Automattic/vip-go-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/). This is handled via CI by the [`deploy.sh` script](https://github.com/Automattic/vip-go-mu-plugins/blob/master/ci/deploy.sh) script, which builds pushes a copy of this repo and expanded submodules.

#### How this works

1. The private part of a deploy key for [Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/) is encrypted against this repository ([Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins/)), meaning it can only be decrypted by Travis running scripts related to this repo
2. This repository and it's submodules are checked out, again, to start the build
3. All VCS config and metadata is removed from the build
4. Various files are removed, including the [`.travis.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.travis.yml) containing the encrypted private part of the deploy key
5. The [Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/) repo is checked out
6. The `.git` directory from the `Automattic/vip-go-mu-plugins-built` repository is moved into the build directory, and a commit is created representing the changes from this build
7. The commit is pushed to the `Automattic/vip-go-mu-plugins-built` repository
