#!/usr/bin/env php
<?php
/**
 * Generate a markdown changelog based on a GitHub milestone.
 *
 * @link      https://github.com/weierophinney/changelog_generator for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney (http://mwop.net/)
 * @license   https://github.com/weierophinney/changelog_generator/blob/master/LICENSE.md New BSD License
 */
ini_set('display_errors', true);
error_reporting(E_ALL | E_STRICT);

// Autoloading based on phpunit's approach
$autoloadLocations = array(
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../autoload.php',
);
$autoloaderLoaded  = false;

foreach ($autoloadLocations as $location) {
    if (!file_exists($location)) {
        continue;
    }
    require $location;
    $autoloaderLoaded  = true;
    break;
}

if (!$autoloaderLoaded) {
    file_put_contents('php://stderr', "Failed to discover autoloader; please install dependencies and/or install via Composer.\n");
    exit(1);
}

// Get configuration
$config    = getConfig();
$token     = $config['token'];       // Github API token
$user      = $config['user'];        // Your user or organization
$repo      = $config['repo'];        // The repository you're getting the changelog for
$milestone = $config['milestone'];   // The milestone ID

$client = new Zend\Http\Client();
$client->setOptions(array(
    'adapter' => 'Zend\Http\Client\Adapter\Curl',
));

$request = $client->getRequest();
$headers = $request->getHeaders();

$headers->addHeaderLine("Authorization", "token $token");

$client->setUri("https://api.github.com/repos/$user/$repo/issues?milestone=$milestone&state=closed&per_page=100");
$client->setMethod('GET');
$issues = array();
$done   = false;
$error  = false;
$i      = 0;
do {
    $response = $client->send();
    $json     = $response->getBody();
    $payload  = json_decode($json);
    if (!is_array($payload)) {
        $error = $payload;
    }
    if (is_array($payload)) {
        $issues = array_merge($issues, $payload);
        $linkHeader = $response->getHeaders()->get('Link');
        if (!$linkHeader) {
            $done = true;
        }
        if ($linkHeader) {
            $links = $linkHeader->getFieldValue();
            $links = explode(', ', $links);
            foreach ($links as $link) {
                $matches = array();
                if (preg_match('#<(?P<url>.*)>; rel="next"#', $link, $matches)) {
                    $client->setUri($matches['url']);
                }
            }
        }
    }
    $i += 1;
} while (!$done && !$error && ($i < 5));

if ($error) {
    file_put_contents('php://stderr', sprintf("Github API returned error message [%s]\n", $error->message));
    exit(1);
}

echo "Total issues resolved: **" . count($issues) . "**\n";

foreach ($issues as $index => $issue) {
    $title = $issue->title;
    $title = htmlentities($title, ENT_COMPAT, 'UTF-8');
    $title = str_replace(array('[', ']', '_'), array('&#91;', '&#92;', '&#95;'), $title);

    $issues[$issue->number] = sprintf('- [%d: %s](%s)', $issue->number, $title, $issue->html_url);
    unset($issues[$index]);
}
ksort($issues);
echo implode("\n", $issues) . "";

function getConfig()
{
    try {
        $opts = new Zend\Console\Getopt(array(
            'help|h'        => 'Help; this usage message',
            'config|c-s'    => 'Configuration file containing base (or all) configuration options',
            'token|t-s'     => 'GitHub API token',
            'user|u-s'      => 'GitHub user/organization name',
            'repo|r-s'      => 'GitHub repository name',
            'milestone|m-i' => 'Milestone identifier',
        ));
        $opts->parse();
    } catch (Zend\Console\Exception\ExceptionInterface $e) {
        file_put_contents('php://stderr', $e->getUsageMessage());
        exit(1);
    }

    if (isset($opts->h) || $opts->toArray() == array()) {
        file_put_contents('php://stdout', $opts->getUsageMessage());
        exit(0);
    }

    $config = array(
        'token'     => '',
        'user'      => '',
        'repo'      => '',
        'milestone' => 0,
    );

    if (isset($opts->c)) {
        $userConfig = include $opts->c;
        if (false === $userConfig) {
            file_put_contents('php://stderr', sprintf("Invalid configuration file specified ('%s')\n", $opts->c));
            exit(1);
        }
        if (!is_array($userConfig)) {
            file_put_contents('php://stderr', sprintf("Configuration file ('%s') did not return an array of configuration\n", $opts->c));
            exit(1);
        }
        $config = array_merge($config, $userConfig);
    }

    if (isset($opts->token)) {
        $config['token'] = $opts->token;
    }

    if (isset($opts->user)) {
        $config['user'] = $opts->user;
    }

    if (isset($opts->repo)) {
        $config['repo'] = $opts->repo;
    }

    if (isset($opts->milestone)) {
        $config['milestone'] = $opts->milestone;
    }

    if (empty($config['token'])
        || empty($config['user'])
        || empty($config['repo'])
        || empty($config['milestone'])
    ) {
        file_put_contents('php://stderr', sprintf("Some configuration is missing; please make sure each of the token, user/organization, repo, and milestone are provided.\nReceived:\n%s\n", var_export($config, 1)));
        exit(1);
    }
    return $config;
}
