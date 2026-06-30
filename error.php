<?php

$code = (int)($_SERVER['REDIRECT_STATUS'] ?? 500);

$statuses = [
    400 => ['Bad Request', 'The request could not be understood.'],
    401 => ['Unauthorized', 'Authentication is required.'],
    402 => ['Payment Required', 'Payment is required.'],
    403 => ['Forbidden', 'Access denied.'],
    404 => ['Page not found', 'Page not found'],
    405 => ['Method Not Allowed', 'This action is not allowed.'],
    408 => ['Request Timeout', 'The request timed out.'],
    409 => ['Conflict', 'A conflict occurred.'],
    410 => ['Gone', 'This resource is no longer available.'],
    418 => ["I'm a Teapot", 'The server refuses to brew coffee.'],
    429 => ['Too Many Requests', 'Please try again later.'],
    500 => ['Internal Server Error', 'An unexpected error occurred.'],
    501 => ['Not Implemented', 'This feature is not available.'],
    502 => ['Bad Gateway', 'An upstream server returned an invalid response.'],
    503 => ['Service Unavailable', 'The service is temporarily unavailable.'],
    504 => ['Gateway Timeout', 'An upstream server timed out.'],
];

if (isset($statuses[$code])) {
    [$title, $message] = $statuses[$code];
} else {
    $group = (int) floor($code / 100);

    switch ($group) {
        case 4:
            $title = 'Client Error';
            $message = 'There is a problem with the request.';
            break;

        case 5:
            $title = 'Server Error';
            $message = 'The server encountered an error.';
            break;

        case 3:
            $title = 'Redirection';
            $message = 'This resource has moved.';
            break;

        default:
            $title = 'Unknown Error';
            $message = 'An unexpected condition occurred.';
    }
}

http_response_code($code);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars("$code - $title") ?></title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/css/error.css">
</head>
<body>
    <div id="header">
        <div class="main-wrapper notfound">
            <a href="/" class="logo"></a>

            <h2 class="text"><?= htmlspecialchars($message) ?></h2>
            <h2 class="link">Error <?= $code ?></h2>
        </div>
    </div>
</body>
</html>