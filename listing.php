<?php

require __DIR__ . "/vendor/autoload.php";

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// PUT FOLDERS HERE TO BLOCK NAVIGATION!!!
$privateDirs = [
    "supersecretfolder"
];

// Files that should never appear in a listing.
$hiddenPattern = '/^\.|\.(log)$/i';

$twig = new Environment(new FilesystemLoader(__DIR__ . "/templates"), [
    "cache" => false
]);

function not_found()
{
    http_response_code(404);
    $_SERVER["REDIRECT_STATUS"] = 404;
    require __DIR__ . "/error.php";
    exit;
}

function format_size($bytes)
{
    $units = ["B", "KB", "MB", "GB", "TB"];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return ($i === 0 ? $bytes : number_format($bytes, 1)) . " " . $units[$i];
}

function encode_path(array $parts)
{
    return "/" . implode("/", array_map("rawurlencode", $parts));
}

// Work out which directory was requested
$urlPath = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
$parts = array_values(array_filter(explode("/", $urlPath), "strlen"));

if (!$parts) {
    not_found();
}

foreach ($parts as $part) {
    if (
        $part === ".." || $part[0] === "." ||
        in_array(strtolower($part), $privateDirs, true)
    ) {
        not_found();
    }
}

$rootReal = realpath(__DIR__);
$dir = realpath(__DIR__ . "/" . implode("/", $parts));

if (
    $rootReal === false || $dir === false || !is_dir($dir) ||
    strpos($dir, $rootReal . DIRECTORY_SEPARATOR) !== 0
) {
    not_found();
}

// Build 
$entries = [];
foreach (scandir($dir) as $name) {
    if (
        $name === "." || $name === ".." ||
        preg_match($hiddenPattern, $name) ||
        in_array(strtolower($name), $privateDirs, true)
    ) {
        continue;
    }

    $full = $dir . DIRECTORY_SEPARATOR . $name;
    $isDir = is_dir($full);

    $entries[] = [
        "name"     => $name,
        "href"     => encode_path(array_merge($parts, [$name])) . ($isDir ? "/" : ""),
        "is_dir"   => $isDir,
        "size"     => $isDir ? "-" : format_size(filesize($full)),
        "modified" => date("Y-m-d H:i", filemtime($full)),
    ];
}

// Folders first then alphabetical 
usort($entries, function ($a, $b) {
    if ($a["is_dir"] !== $b["is_dir"]) {
        return $a["is_dir"] ? -1 : 1;
    }
    return strnatcasecmp($a["name"], $b["name"]);
});

// Breadcrumbs
$crumbs = [["name" => "Home", "href" => "/"]];
foreach ($parts as $i => $part) {
    $crumbs[] = [
        "name" => $part,
        "href" => encode_path(array_slice($parts, 0, $i + 1)) . "/",
    ];
}

$parent = count($parts) > 1 ? encode_path(array_slice($parts, 0, -1)) . "/" : null;

echo $twig->render("listing.html.twig", [
    "path"    => "/" . implode("/", $parts) . "/",
    "crumbs"  => $crumbs,
    "parent"  => $parent,
    "entries" => $entries,
]);