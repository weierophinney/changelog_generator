<?php
require __DIR__ . '/vendor/autoload.php';
ini_set('display_errors', true);
error_reporting(E_ALL | E_STRICT);

$config = include __DIR__ . '/config/config.php';
if (false === $config) {
    echo "Failed to load config; please create a config/config.php file based on config/config.php.dist.";
    exit(1);
}

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
    fwrite(STDERR,sprintf("Github API returned error message [%s]\n",$error->message));
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
