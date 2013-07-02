changelog\_generator.php
========================

This project provides a simple way to create an ordered list of issues and pull
requests closed with a given milestone on GitHub. It uses Zend Framework's
`Zend\Http` component to communicate with the GitHub API.

Installation
------------

Use [Composer](https://getcomposer.org) to install dependencies:

```sh
php /path/to/composer.phar install
```

Usage
-----

Copy `config/config.php.dist` to `config/config.php` and edit the new file to
add your GitHub API token, the user or organization, the repository, and the
milestone identifier for which you want to generate a changelog.

Once you have, simply invoke the `changelog_generator.php` script using your
PHP interpreter, and pipe the output to a file:

```sh
php changelog_generator.php > changelog.md
```

Note: You may need to scrub the generated changelog slightly, particularly if
any issue titles include any of the characters `][><`, as these may break the
generated markdown.
