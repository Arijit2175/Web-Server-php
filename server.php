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

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$clients = [];

$routes = [
    "GET /" => "handleHome",
    "GET /about" => "handleAboutPage",
    "POST /submit" => "handleSubmit",
    "POST /upload" => "handleFileUpload",
    "GET /uploads" => "handleUploadsList"
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
                $data = '';
                while (true) {
                    $chunk = @socket_read($sock, 4096);
                    if ($chunk === false || $chunk === '') break;
                    $data .= $chunk;

                    if (strpos($data, "\r\n\r\n") !== false) {
                        $headersEnd = strpos($data, "\r\n\r\n") + 4;
                        $headers = substr($data, 0, $headersEnd);

                        if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
                            $contentLength = (int)$matches[1];
                            $bodyLength = strlen($data) - $headersEnd;

                            while ($bodyLength < $contentLength) {
                                $chunk = @socket_read($sock, 4096);
                                if ($chunk === false || $chunk === '') break;
                                $data .= $chunk;
                                $bodyLength = strlen($data) - $headersEnd;
                            }
                        }
                        break;
                    }
                }

                if (empty($data)) {
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
                    if (strpos($path, "/uploads/") === 0) {
                        $filePath = realpath($uploadDir . substr($path, 8));
                        if ($filePath && strpos($filePath, $uploadDir) === 0 && is_file($filePath)) {
                            $responseBody = file_get_contents($filePath);
                            $mimeType = mime_content_type($filePath);
                        } else {
                            $status = "404 Not Found";
                            $responseBody = "<h1>404 File Not Found</h1>";
                        }
                    } else {
                        $publicDir = realpath(__DIR__ . "/public");
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

function handleUpload($method, $path, $request, $lines) {
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

     $contentLength = 0;
    foreach ($lines as $line) {
        if (stripos($line, "Content-Length:") === 0) {
            $contentLength = (int)trim(substr($line, 15));
            break;
        }
    }

    $bodyPos = strpos($request, "\r\n\r\n");
    $body = substr($request, $bodyPos + 4);

    $currentLength = strlen($body);
    global $sock; 
    while ($currentLength < $contentLength) {
        $chunk = socket_read($sock, $contentLength - $currentLength);
        if ($chunk === false || $chunk === '') break;
        $body .= $chunk;
        $currentLength = strlen($body);
    }

    $boundary = "";
    foreach ($lines as $line) {
         return "<h1>Error: No boundary found</h1>";
    }

    $parts = explode("--$boundary", $body);

    foreach ($parts as $part) {
        if (strpos($part, 'Content-Disposition: form-data;') !== false &&
            strpos($part, 'filename="') !== false) {

            preg_match('/filename="([^"]+)"/', $part, $matches);
            $filename = $matches[1] ?? '';

            if ($filename) {
                $fileStart = strpos($part, "\r\n\r\n") + 4;
                $fileData = substr($part, $fileStart, -2);

                $safeName = uniqid() . "_" . basename($filename);
                file_put_contents("$uploadDir/$safeName", $fileData);
            }
        }
    }

    return "<h1>No file uploaded.</h1>";
}


function handleUploadsList($method, $path, $request, $lines) {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        return "<h1>No uploads directory found</h1>";
    }
    $files = array_diff(scandir($uploadDir), ['.', '..']);
    $html = "<h1>Uploaded Files</h1><ul>";
    foreach ($files as $file) {
        $html .= "<li><a href='/uploads/$file'>$file</a></li>";
    }
    $html .= "</ul>";
    return $html;
}

function logRequest($clientIp, $method, $path, $statusCode, $logFile) {
    $timestamp = date("Y-m-d H:i:s");
    $logLine = "[$timestamp] $clientIp \"$method $path\" $statusCode\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
?>
