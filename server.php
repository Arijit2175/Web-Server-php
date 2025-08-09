<?php
set_time_limit(0);

$host = '127.0.0.1';
$port = 8080;

$masterSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($masterSock, $host, $port);
socket_listen($masterSock);
socket_set_nonblock($masterSock);

echo "✅ Multi-Client PHP Web Server running at http://$host:$port\n";

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . '/server.log';

$publicDir = realpath(__DIR__ . '/public'); 
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowedExtensions = ['html','css','js','png','jpg','jpeg','gif','txt','pdf'];

$clients = [];

$routes = [
    "GET /"         => "handleHome",
    "GET /about"    => "handleAboutPage",
    "POST /submit"  => "handleSubmit",
    "POST /upload"  => "handleFileUpload",
    "GET /uploads"  => "handleUploadsList"
];

while (true) {
    $read = $clients;
    $read[] = $masterSock;

    $write = NULL;
    $except = NULL;

    $numChanged = @socket_select($read, $write, $except, 0, 50000);
    if ($numChanged === false) {
        usleep(100000);
        continue;
    }

    foreach ($read as $sock) {
        if ($sock === $masterSock) {
            $newsock = @socket_accept($masterSock);
            if ($newsock !== false) {
                socket_set_nonblock($newsock);
                $clients[] = $newsock;
            }
            continue;
        }

        $data = '';
        while (true) {
            $chunk = @socket_read($sock, 8192);
            if ($chunk === false) break; 
            if ($chunk === '') break;
            $data .= $chunk;

            if (strpos($data, "\r\n\r\n") !== false) {
                $headersEnd = strpos($data, "\r\n\r\n") + 4;
                $headers = substr($data, 0, $headersEnd);

                if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $m)) {
                    $contentLength = (int)$m[1];
                    $bodySoFar = strlen($data) - $headersEnd;
                    while ($bodySoFar < $contentLength) {
                        $chunk = @socket_read($sock, 8192);
                        if ($chunk === false || $chunk === '') break;
                        $data .= $chunk;
                        $bodySoFar = strlen($data) - $headersEnd;
                    }
                }
                break;
            }
        }

        if ($data === '') {
            $idx = array_search($sock, $clients);
            if ($idx !== false) {
                socket_close($sock);
                unset($clients[$idx]);
            }
            continue;
        }

        $lines = explode("\r\n", $data);
        $requestLine = $lines[0] ?? '';
        $parts = preg_split('/\s+/', $requestLine);
        $method = $parts[0] ?? '';
        $path = urldecode($parts[1] ?? '/');

        $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
        $routeKey = "$method $pathOnly";

        $status = "200 OK";
        $mimeType = "text/html";
        $responseBody = "";

        if (isset($routes[$routeKey])) {
            $handler = $routes[$routeKey];
            $responseBody = $handler($method, $pathOnly, $data, $lines, $sock);
        }
        elseif (preg_match("#^/uploads/(.+)$#i", $pathOnly, $m)) {
            $requestedFile = basename($m[1]); 
            $filePath = realpath(__DIR__ . "/uploads/" . $requestedFile);
            if ($filePath && strpos($filePath, realpath(__DIR__ . '/uploads')) === 0 && is_file($filePath)) {
                $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
                $responseBody = file_get_contents($filePath);
                $status = "200 OK";

                $response = "HTTP/1.1 $status\r\n";
                $response .= "Content-Type: $mimeType\r\n";
                $response .= "Content-Length: " . strlen($responseBody) . "\r\n";
                $response .= "Connection: close\r\n\r\n";
                socket_write($sock, $response . $responseBody);

                socket_getpeername($sock, $clientIp);
                logRequest($clientIp, $method, $pathOnly, explode(' ', $status)[0], $logFile);

                socket_close($sock);
                $idx = array_search($sock, $clients);
                if ($idx !== false) unset($clients[$idx]);
                continue;
            } else {
                $status = "404 Not Found";
                $responseBody = "<h1>404 Not Found</h1>";
            }
        }
        else {
            if ($publicDir !== false) {
                $requestedPath = $pathOnly === '/' ? '/index.html' : $pathOnly;
                $fullPath = realpath($publicDir . $requestedPath);
                if ($fullPath !== false && strpos($fullPath, $publicDir) === 0 && is_file($fullPath)) {
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExtensions)) {
                        $responseBody = file_get_contents($fullPath);
                        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
                        $status = "200 OK";
                    } else {
                        $status = "403 Forbidden";
                        $responseBody = "<h1>403 Forbidden</h1>";
                    }
                } else {
                    $status = "404 Not Found";
                    $responseBody = "<h1>404 Not Found</h1>";
                }
            } else {
                $status = "404 Not Found";
                $responseBody = "<h1>404 Not Found</h1>";
            }
        }

        $response = "HTTP/1.1 $status\r\n";
        $response .= "Server: PHP-MiniServer\r\n";
        $response .= "X-Content-Type-Options: nosniff\r\n";
        $response .= "X-Frame-Options: DENY\r\n";
        $response .= "Content-Type: $mimeType\r\n";
        $response .= "Content-Length: " . strlen($responseBody) . "\r\n";
        $response .= "Connection: close\r\n\r\n";
        socket_write($sock, $response . $responseBody);

        socket_getpeername($sock, $clientIp);
        logRequest($clientIp, $method, $pathOnly, explode(' ', $status)[0], $logFile);

        socket_close($sock);
        $idx = array_search($sock, $clients);
        if ($idx !== false) unset($clients[$idx]);
    }
}

function render($view, $vars = []) {
    extract($vars);
    ob_start();
    include __DIR__ . "/views/$view.php";
    return ob_get_clean();
}

function handleHome($method, $path, $request, $lines, $sock = null) {
    return render("home");
}

function handleAboutPage($method, $path, $request, $lines, $sock = null) {
    return render("about");
}

function handleSubmit($method, $path, $request, $lines, $sock = null) {
    $contentLength = 0;
    foreach ($lines as $line) {
        if (stripos($line, "Content-Length:") === 0) {
            $contentLength = (int)trim(explode(":", $line, 2)[1]);
            break;
        }
    }
    $bodyPos = strpos($request, "\r\n\r\n");
    $postData = $bodyPos !== false ? substr($request, $bodyPos + 4, $contentLength) : '';
    parse_str($postData, $formData);
    $name = htmlspecialchars($formData['name'] ?? 'Guest', ENT_QUOTES, 'UTF-8');
    return render("submit", ['name' => $name]);
}

function handleFileUpload($method, $path, $request, $lines, $sock) {
    $uploadDir = realpath(__DIR__ . '/uploads') ?: (__DIR__ . '/uploads');
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $contentLength = 0;
    $contentType = '';
    foreach ($lines as $line) {
        if (stripos($line, 'Content-Length:') === 0) {
            $contentLength = (int)trim(substr($line, strlen('Content-Length:')));
        }
        if (stripos($line, 'Content-Type:') === 0 && stripos($line, 'multipart/form-data') !== false) {
            $contentType = trim(substr($line, strlen('Content-Type:')));
        }
    }

    if ($contentLength <= 0) {
        return "<h1>Bad Request: missing Content-Length</h1>";
    }

    if (!preg_match('/boundary=(.*)$/', $contentType, $m)) {
        return "<h1>Bad Request: missing boundary</h1>";
    }
    $boundary = trim($m[1]);
    if (substr($boundary, 0, 1) === '"') $boundary = trim($boundary, '"');

    $bodyPos = strpos($request, "\r\n\r\n");
    if ($bodyPos === false) return "<h1>Bad Request</h1>";
    $body = substr($request, $bodyPos + 4);

    $received = strlen($body);
    while ($received < $contentLength) {
        $chunk = @socket_read($sock, $contentLength - $received);
        if ($chunk === false || $chunk === '') break;
        $body .= $chunk;
        $received = strlen($body);
    }

    $parts = explode("--" . $boundary, $body);
    foreach ($parts as $part) {
        if (trim($part) === '' || $part === "--\r\n") continue;

        if (strpos($part, 'filename="') !== false && strpos($part, 'Content-Disposition:') !== false) {
            if (!preg_match('/filename="([^"]*)"/', $part, $fn)) continue;
            $originalName = basename($fn[1]);
            if ($originalName === '') continue; 
            $hdrEnd = strpos($part, "\r\n\r\n");
            if ($hdrEnd === false) continue;
            $partHeaders = substr($part, 0, $hdrEnd);
            $fileData = substr($part, $hdrEnd + 4);

            $fileData = preg_replace("/\r\n--\z/", '', $fileData);
            $fileData = rtrim($fileData, "\r\n");

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','pdf'];
            if (!in_array($ext, $allowed)) {
                return "<h1>403 Forbidden - file type not allowed</h1>";
            }

            $safeName = uniqid('upload_', true) . '.' . $ext;
            $savePath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

            $written = file_put_contents($savePath, $fileData);
            if ($written === false) {
                return "<h1>500 Internal Server Error - could not save file</h1>";
            }

            $safeEsc = htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8');
            $out = "<h1>File uploaded successfully</h1>";
            $out .= "<p>Saved as: <a href=\"/uploads/$safeEsc\">$safeEsc</a></p>";
            return $out;
        }
    }

    return "<h1>No file uploaded</h1>";
}

function handleUploadsList($method, $path, $request, $lines, $sock = null) {
    $uploadDir = realpath(__DIR__ . '/uploads') ?: (__DIR__ . '/uploads');
    if (!is_dir($uploadDir)) return "<h1>No uploads</h1>";
    $files = array_values(array_diff(scandir($uploadDir), ['.','..']));
    $html = "<h1>Uploaded Files</h1>";
    if (empty($files)) {
        $html .= "<p>No files uploaded yet.</p>";
    } else {
        $html .= "<ul>";
        foreach ($files as $f) {
            $fSafe = htmlspecialchars($f, ENT_QUOTES, 'UTF-8');
            $html .= "<li><a href=\"/uploads/$fSafe\">$fSafe</a></li>";
        }
        $html .= "</ul>";
    }
    $html .= "<p><a href=\"/\">Back</a></p>";
    return $html;
}

function logRequest($clientIp, $method, $path, $statusCode, $logFile) {
    $timestamp = date("Y-m-d H:i:s");
    $logLine = "[$timestamp] $clientIp \"$method $path\" $statusCode\n";
    echo $logLine;
    file_put_contents($logFile, $logLine, FILE_APPEND);
}
?>
