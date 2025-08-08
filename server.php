<?php
$host = '127.0.0.1';
$port = 8080;

$masterSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($masterSock, $host, $port);
socket_listen($masterSock);
socket_set_nonblock($masterSock);

echo "✅ Multi-Client PHP Web Server running at http://$host:$port\n";

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir);
$logFile = $logDir . '/server.log';

$publicDir = realpath(__DIR__ . "/public");

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$allowedUploadExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$maxUploadSize = 2 * 1024 * 1024; 

$clients = [];

$routes = [
    "GET /" => "handleHome",
    "GET /about" => "handleAboutPage",
    "POST /submit" => "handleSubmit",
    "POST /upload" => "handleFileUpload"
];

$allowedExtensions = ['html', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'txt'];

while (true) {
    $readSockets = $clients;
    $readSockets[] = $masterSock;

    $write = NULL;
    $except = NULL;

    if (socket_select($readSockets, $write, $except, 0, 50000) > 0) {
        foreach ($readSockets as $sock) {
            if ($sock === $masterSock) {
                $client = socket_accept($masterSock);
                socket_set_nonblock($client);
                $clients[] = $client;
            } else {
                $data = @socket_read($sock, 4096);
                if ($data === false || $data === '') {
                    $index = array_search($sock, $clients);
                    socket_close($sock);
                    unset($clients[$index]);
                    continue;
                }

                $lines = explode("\r\n", $data);
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
                    $responseBody = $handler($method, $path, $data, $lines);
                } else {
                    $requestedPath = $path === "/" ? "/index.html" : $path;
                    $fullPath = realpath($publicDir . $requestedPath);

                    if ($fullPath !== false && strpos($fullPath, $publicDir) === 0 && is_file($fullPath)) {
                        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                        if (in_array($ext, $allowedExtensions)) {
                            $responseBody = file_get_contents($fullPath);
                            $mimeType = mime_content_type($fullPath);
                        } else {
                            $status = "403 Forbidden";
                            $responseBody = "<h1>403 Forbidden</h1>";
                        }
                    } else {
                        $status = "404 Not Found";
                        $responseBody = "<h1>404 Not Found</h1>";
                    }
                }

                $response = "HTTP/1.1 $status\r\n";
                $response .= "Content-Type: $mimeType\r\n";
                $response .= "Content-Length: " . strlen($responseBody) . "\r\n\r\n";
                $response .= $responseBody;

                socket_getpeername($sock, $clientIp);
                logRequest($clientIp, $method, $path, explode(' ', $status)[0], $logFile);

                socket_write($sock, $response);
                socket_close($sock);
                $index = array_search($sock, $clients);
                unset($clients[$index]);
            }
        }
    }
}

function render($view, $vars = []) {
    extract($vars);
    ob_start();
    include __DIR__ . "/views/$view.php";
    return ob_get_clean();
}

function handleHome($method, $path, $request, $lines) {
    return render("home");
}

function handleAboutPage($method, $path, $request, $lines) {
    return render("about");
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
    $name = htmlspecialchars($formData['name'] ?? 'Guest');
    return render("submit", ['name' => $name]);
}

function handleFileUpload($method, $path, $request, $lines) {
    global $uploadDir, $allowedUploadExtensions, $maxUploadSize;

    $contentLength = 0;
    foreach ($lines as $line) {
        if (stripos($line, "Content-Length:") === 0) {
            $contentLength = (int)trim(explode(":", $line)[1]);
            break;
        }
    }
    if ($contentLength > $maxUploadSize) {
        return "<h1>File too large. Max 2MB allowed.</h1>";
    }

    $bodyPos = strpos($request, "\r\n\r\n");
    $rawBody = substr($request, $bodyPos + 4);

    if (preg_match('/filename="([^"]+)"/', $request, $matches)) {
        $originalName = basename($matches[1]);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedUploadExtensions)) {
            return "<h1>403 Forbidden - File type not allowed</h1>";
        }

        $newName = uniqid("file_", true) . "." . $ext;
        $filePath = $uploadDir . "/" . $newName;

        file_put_contents($filePath, $rawBody);

        return "<h1>Upload successful!</h1><p>Saved as: $newName</p>";
    }

    return "<h1>Invalid upload request</h1>";
}

function logRequest($clientIp, $method, $path, $statusCode, $logFile) {
    $timestamp = date("Y-m-d H:i:s");
    $logLine = "[$timestamp] $clientIp \"$method $path\" $statusCode\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
?>
