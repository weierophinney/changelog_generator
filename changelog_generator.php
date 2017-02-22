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
    __DIR__ . '/../../autoload.php',
    getcwd() . '/vendor/autoload.php',
);

foreach ($autoloadLocations as $location) {
    if (! file_exists($location)) {
        continue;
    }

    $autoloader = require $location;

    break;
}

if (empty($autoloader)) {
    file_put_contents(
        'php://stderr',
        "Failed to discover autoloader; please install dependencies and/or install via Composer." . PHP_EOL
    );
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


$client->setUri("https://api.github.com/repos/$user/$repo/milestones/$milestone");

$milestoneResponseBody = $client->send()->getBody();
$milestonePayload      = json_decode($milestoneResponseBody, true);

if (! isset($milestonePayload['title'])) {
    fwrite(STDERR, sprintf(
        'Provided milestone ID [%s] does not exist: %s%s',
        $milestone,
        $milestoneResponseBody,
        PHP_EOL
    ));

    $client->setUri(sprintf('https://api.github.com/repos/%s/%s/milestones', $user, $repo));
    $milestonesResponseBody = $client->send()->getBody();
    $milestonesPayload = json_decode($milestonesResponseBody, true);

    fwrite(STDERR, sprintf('Existing milestone IDs are:%s', PHP_EOL));
    foreach ($milestonesPayload as $milestone) {
        fwrite(STDERR, sprintf(
            'id: %s; title: %s; description: %s%s',
            $milestone['number'],
            $milestone['title'],
            $milestone['description'],
            PHP_EOL
        ));
    }
    exit(1);
}

$client->setUri(
    'https://api.github.com/search/issues?q=' . urlencode(
        'milestone:' . $milestonePayload['title']
        .' repo:' . $user . '/' . $repo
        . ' state:closed'
    )
);

$client->setMethod('GET');
$issues = array();
$error  = false;

do {
    $response = $client->send();
    $json     = $response->getBody();
    $payload  = json_decode($json, true);

    if (! (is_array($payload) && isset($payload['items']))) {
        file_put_contents(
            'php://stderr',
            sprintf("Github API returned error message [%s]%s", is_object($payload) ? $payload['message'] : $json, PHP_EOL)
        );

        exit(1);
    }

    if (isset($payload['incomplete_results']) && ! isset($payload['incomplete_results'])) {
        file_put_contents(
            'php://stderr',
            sprintf("Github API returned incomplete results [%s]%s", $json, PHP_EOL)
        );

        exit(1);
    }

    foreach ($payload['items'] as $issue) {
        $issues[$issue['number']] = $issue;
    }

    $linkHeader = $response->getHeaders()->get('Link');

    if (! $linkHeader) {
        break;
    }

    foreach (explode(', ', $linkHeader->getFieldValue()) as $link) {
        $matches = array();

        if (preg_match('#<(?P<url>.*)>; rel="next"#', $link, $matches)) {
            $client->setUri($matches['url']);

            continue 2;
        }
    }

    break; // yay for tail recursion emulation =_=
} while (true);

echo "Total issues resolved: **" . count($issues) . "**" . PHP_EOL;

$textualIssues = [];

foreach ($issues as $index => $issue) {
    $title = $issue['title'];
    $title = htmlentities($title, ENT_COMPAT, 'UTF-8');
    $title = str_replace(array('[', ']', '_'), array('&#91;', '&#92;', '&#95;'), $title);

    $textualIssues[$index] = sprintf('- [%d: %s](%s)', $issue['number'], $title, $issue['html_url']);
}

ksort($textualIssues);
echo implode(PHP_EOL, $textualIssues) . PHP_EOL;

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
            file_put_contents('php://stderr', sprintf(
                "Invalid configuration file specified ('%s')%s",
                $opts->c,
                PHP_EOL
            ));
            exit(1);
        }
        if (!is_array($userConfig)) {
            file_put_contents('php://stderr', sprintf(
                "Configuration file ('%s') did not return an array of configuration%s",
                $opts->c,
                PHP_EOL
            ));
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
        file_put_contents('php://stderr', sprintf(
            "Some configuration is missing; please make sure each of the token, "
            . "user/organization, repo, and milestone are provided.%sReceived:%s%s%s",
            PHP_EOL,
            PHP_EOL,
            var_export($config, 1),
            PHP_EOL
        ));
        exit(1);
    }
    return $config;
}
