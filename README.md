changelog\_generator.php
========================

This project provides a simple way to create a markdown ordered list of issues
and pull requests closed with a given milestone on GitHub. It uses Zend
Framework's `Zend\Http` component to communicate with the GitHub API, and
`Zend\Console\Getopt` to handle CLI options parsing.

The script ensures that any special characters that might break the generated
links are scrubbed and substituted with the corresponding HTML entities; as 
such, the script should be generally usable unattended.

Installation
------------

Use [Composer](https://getcomposer.org) to install dependencies:

```sh
php /path/to/composer.phar install
```

Alternately, you can compose it into an existing PHP application using Composer.
Add the following to your `composer.json`:

```json
{
    "require": {
        "phly/changelog-generator": "*"
    }
}
```

and either perform a `composer.phar install` or `composer.phar update`. This
will install the script in `vendor/bin/changelog_generator.php`.

Usage
-----

There are two primary ways to use the generator:

- Use CLI options to pass in configuration
- Create a configuration file, and pass that to the script

The standard CLI options are:

- **-t** or **--token**, to pass your GitHub API token
- **-u** or **--user**, to pass your GitHub username or organization
- **-r** or **--repo**, to pass your GitHub repository name
- **-m** or **--milestone**, to pass the identifier of the GitHub milestone for
  which to generate the changelog

As an example:

```sh
vendor/bin/changelog_generator.php -t MYgithubAPItoken -u weierophinney -r changelog_generator -m 1 > changelog.md
```

Alternately, create a configuration file. You can use `config/config.php.dist`
as a template; it simply needs to return an array with the keys "token",
"user", "repo", and "milestone". You then pass this to the script:

```sh
vendor/bin/changelog_generator.php -c path/to/config.php
```

You can also mix-and-match options -- for instance, you might place your token
in a configuration file, and then pass the other options via CLI.
