<?php

require __DIR__ . "/vendor/autoload.php";

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$owner    = "TheSkymuTeam";
$repo     = "Skymu";
$branch   = "master";
$token    = "";                         // optional
$cacheDir = __DIR__ . "/cache/github";
$cacheTtl = 600;                        // seconds

$twig = new Environment(new FilesystemLoader(__DIR__ . "/templates"), [
    "cache" => false
]);

function fail($code)
{
    http_response_code($code);
    $_SERVER["REDIRECT_STATUS"] = $code;
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

function raw_url($owner, $repo, $branch, array $parts)
{
    return "https://raw.githubusercontent.com/"
        . rawurlencode($owner) . "/" . rawurlencode($repo)
        . "/refs/heads/" . rawurlencode($branch)
        . "/" . implode("/", array_map("rawurlencode", $parts));
}

function github_get($url, $token, $cacheDir, $ttl)
{
    $file = $cacheDir . "/" . sha1($url) . ".json";

    if (is_file($file) && time() - filemtime($file) < $ttl) {
        return [200, json_decode(file_get_contents($file), true)];
    }

    $headers = [
        "Accept: application/vnd.github+json",
        "User-Agent: skymu.app-repo-browser",
    ];
    if ($token !== "") {
        $headers[] = "Authorization: Bearer " . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 && $body !== false) {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($file, $body);
        return [200, json_decode($body, true)];
    }

    if (is_file($file)) { // stale copy is better than an error page
        return [200, json_decode(file_get_contents($file), true)];
    }

    return [$status ?: 502, null];
}

$urlPath = rawurldecode(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
$parts = array_values(array_filter(explode("/", $urlPath), "strlen"));
array_shift($parts); // drop "repo"

foreach ($parts as $part) {
    if ($part === "." || $part === "..") {
        fail(404);
    }
}

$apiUrl = "https://api.github.com/repos/$owner/$repo/contents";
if ($parts) {
    $apiUrl .= "/" . implode("/", array_map("rawurlencode", $parts));
}
$apiUrl .= "?ref=" . rawurlencode($branch);

[$status, $data] = github_get($apiUrl, $token, $cacheDir, $cacheTtl);

if ($status === 404) {
    fail(404);
}
if ($status !== 200 || !is_array($data)) {
    fail(502);
}

// A single file instead of a folder: send the visitor to the raw file
if (isset($data["type"])) {
    $target = ($data["type"] === "file")
        ? raw_url($owner, $repo, $branch, $parts)
        : ($data["html_url"] ?? "https://github.com/$owner/$repo");
    header("Location: " . $target, true, 302);
    exit;
}

// Build the entries
$entries = [];
foreach ($data as $item) {
    $isDir = $item["type"] === "dir";

    if ($isDir) {
        $href = encode_path(array_merge(["repo"], $parts, [$item["name"]])) . "/";
    } elseif ($item["type"] === "file") {
        $href = raw_url($owner, $repo, $branch, array_merge($parts, [$item["name"]]));
    } else {
        // submodules, symlinks
        $href = $item["html_url"] ?? "#";
    }

    $entries[] = [
        "name"     => $item["name"],
        "href"     => $href,
        "is_dir"   => $isDir,
        "size"     => $isDir ? "-" : format_size($item["size"] ?? 0),
        "modified" => "",
    ];
}

usort($entries, function ($a, $b) {
    if ($a["is_dir"] !== $b["is_dir"]) {
        return $a["is_dir"] ? -1 : 1;
    }
    return strnatcasecmp($a["name"], $b["name"]);
});

// Breadcrumbs
$crumbs = [
    ["name" => "Home", "href" => "/"],
    ["name" => "repo", "href" => "/repo/"],
];
foreach ($parts as $i => $part) {
    $crumbs[] = [
        "name" => $part,
        "href" => encode_path(array_merge(["repo"], array_slice($parts, 0, $i + 1))) . "/",
    ];
}

$parent = $parts
    ? encode_path(array_merge(["repo"], array_slice($parts, 0, -1))) . "/"
    : null;

echo $twig->render("listing.html.twig", [
    "path"          => "/repo/" . ($parts ? implode("/", $parts) . "/" : ""),
    "crumbs"        => $crumbs,
    "parent"        => $parent,
    "entries"       => $entries,
    "show_modified" => false,
]);