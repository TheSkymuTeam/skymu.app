<?php

require __DIR__ . "/vendor/autoload.php";

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use League\CommonMark\CommonMarkConverter;

$loader = new FilesystemLoader(__DIR__ . "/templates");

$twig = new Environment($loader, [
    // XXX(omega): change to true when developing
    "cache" => false
]);

$markdown = new CommonMarkConverter();

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$path = trim($path, "/");

if ($path == "")
{
    echo $twig->render("index.html.twig");
    exit;
}

$path = preg_replace("/[^A-Za-z0-9_\/-]/", "", $path);

$file = __DIR__ . "/pages/" . $path . ".md";

if (!is_file($file))
{
    http_response_code(404);
    $_SERVER["REDIRECT_STATUS"] = 404;
    require __DIR__ . "/error.php";
    exit;
}

$pageTitle = ucwords(str_replace('-', ' ', $path));
$markdownText = file_get_contents($file);
$html = $markdown->convert($markdownText);
if (is_object($html))
    $html = $html->getContent();

echo $twig->render("page.html.twig", [
    "content" => $html,
    "page" => $pageTitle
]);