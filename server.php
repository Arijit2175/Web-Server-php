<?php
$host = '127.0.0.1';
$port = 8080;
// Create a TCP/IP socket
$masterSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($masterSock, $host, $port);
socket_listen($masterSock);
socket_set_nonblock($masterSock);

echo "✅ Multi-Client PHP Web Server running at http://$host:$port\n";
// Create directories for uploads and logs if they don't exist
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir);
$logFile = $logDir . '/server.log';

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$clients = [];
// Define routes
$routes = [
    "GET /" => "handleHome",
    "GET /about" => "handleAboutPage",
    "POST /submit" => "handleSubmit",
    "POST /upload" => "handleUpload",
    "GET /uploads" => "handleUploadsList"
];

$allowedExtensions = ['html', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'txt'];
// Main server loop
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
                    $responseBody = $handler($method, $path, $data, $lines, $sock);
                } else {
                    if (strpos($path, "/uploads/") === 0) {
    $fileName = basename(substr($path, 9)); 
    $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (is_file($filePath)) {
        $mimeType = mime_content_type($filePath);
        $fileSize = filesize($filePath);
        $fileContent = file_get_contents($filePath);

        $response  = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: $mimeType\r\n";
        $response .= "Content-Length: $fileSize\r\n";
        $response .= "Content-Disposition: attachment; filename=\"" . $fileName . "\"\r\n";
        $response .= "Connection: close\r\n\r\n";
        $response .= $fileContent;

        socket_write($sock, $response);
        socket_close($sock);
        $index = array_search($sock, $clients);
        unset($clients[$index]);
        continue;
    } else {
        $status = "404 Not Found";
        $responseBody = "<h1>404 File Not Found</h1>";
    }
}

                     else {
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
// Function to render views
function render($view, $vars = []) {
    extract($vars);
    ob_start();
    include __DIR__ . "/views/$view.php";
    return ob_get_clean();
}
// Route handlers
function handleHome($method, $path, $request, $lines) {
    return render("home");
}
// Function to handle the About page
function handleAboutPage($method, $path, $request, $lines) {
    return render("about");
}
// Function to handle form submission
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
// Function to handle file uploads
function handleUpload($method, $path, $request, $lines, $sock) {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
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
        if (stripos($line, "Content-Type: multipart/form-data;") === 0) {
            preg_match('/boundary=(.*)$/', trim($line), $matches);
            if (isset($matches[1])) {
                $boundary = $matches[1];
            }
            break;
        }
    }
    if (!$boundary) {
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

                $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($filename));

                if (strlen($fileData) > 5 * 1024 * 1024) {
                return "<h1>Error: File too large (max 5MB)</h1>";
                }

                $timestamp = date('Ymd_His');
                $base = pathinfo($cleanName, PATHINFO_FILENAME);
                $ext  = pathinfo($cleanName, PATHINFO_EXTENSION);

                $safeName = $base . '_' . $timestamp . ($ext !== '' ? '.' . $ext : '');

                $counter = 1;
                while (file_exists("$uploadDir/$safeName")) {
                    $safeName = $base . '_' . $timestamp . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
                    $counter++;
                }

                file_put_contents("$uploadDir/$safeName", $fileData);

                return "<h1>File uploaded successfully!</h1><p>Saved as: $safeName</p>";
            }
        }
    }

    return "<h1>No file uploaded</h1>";
}
// Function to handle listing uploaded files
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
// Function to log requests
function logRequest($clientIp, $method, $path, $statusCode, $logFile) {
    $timestamp = date("Y-m-d H:i:s");
    $logLine = "[$timestamp] $clientIp \"$method $path\" $statusCode\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
?>
