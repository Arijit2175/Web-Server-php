<?php
$host = '127.0.0.1';
$port = 8080;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($sock, $host, $port);
socket_listen($sock);

echo "✅ PHP Web Server running at http://$host:$port\n";

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir);
}
$logFile = $logDir . '/server.log';

$routes = [
    "GET /" => "handleHome",
    "POST /submit" => "handleSubmit",
    "GET /about" => "handleAboutPage"
];

while (true) {
    $client = socket_accept($sock);
    $request = socket_read($client, 4096);

    $lines = explode("\r\n", $request);
    $requestLine = $lines[0] ?? '';
    $parts = explode(' ', $requestLine);
    $method = $parts[0] ?? '';
    $path = urldecode($parts[1] ?? '/');

    $routeKey = "$method $path";

    $status = "200 OK";
    $mimeType = "text/html";
    $responseBody = "";

    if (isset($routes[$routeKey])) {
        $handler = $routes[$routeKey];
        $responseBody = $handler($method, $path, $request, $lines);
    } else {
        $publicDir = realpath(__DIR__ . "/public");
        $requestedPath = $path === "/" ? "/index.html" : $path;
        $fullPath = realpath($publicDir . $requestedPath);

        if ($fullPath !== false && strpos($fullPath, $publicDir) === 0 && is_file($fullPath)) {
            $responseBody = file_get_contents($fullPath);
            $mimeType = mime_content_type($fullPath);
        } else {
            $status = "404 Not Found";
            $responseBody = "<h1>404 Not Found</h1>";
        }
    }

    $response = "HTTP/1.1 $status\r\n";
    $response .= "Content-Type: $mimeType\r\n";
    $response .= "Content-Length: " . strlen($responseBody) . "\r\n\r\n";
    $response .= $responseBody;

    socket_getpeername($client, $clientIp);
    logRequest($clientIp, $method, $path, explode(' ', $status)[0], $logFile);

    socket_write($client, $response);
    socket_close($client);
}

socket_close($sock);


function render($view, $vars = []) {
    extract($vars);
    ob_start();
    include __DIR__ . "/views/$view.php";
    return ob_get_clean();
}


function handleHome($method, $path, $request, $lines) {
    return render("home");
}

function handleSubmit($method, $path, $request, $lines) {
    $contentLength = 0;
    foreach ($lines as $line) {
        if (stripos($line, "Content-Length:") === 0) {
            $contentLength = (int)trim(explode(":", $line)[1]);
            break;
        }
    }

    $bodyPos = strpos($request, "\r\n\r\n");
    $postData = substr($request, $bodyPos + 4, $contentLength);
    parse_str($postData, $formData);
    $name = $formData['name'] ?? 'Guest';

    return render("submit", ['name' => $name]);
}

function handleAboutPage($method, $path, $request, $lines) {
    return render("about");
}


function logRequest($clientIp, $method, $path, $statusCode, $logFile) {
    $timestamp = date("Y-m-d H:i:s");
    $logLine = "[$timestamp] $clientIp \"$method $path\" $statusCode\n";
    echo $logLine; 
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
?>
