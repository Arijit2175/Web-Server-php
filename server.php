<?php
$host = 'Localhost';
$port = 8080;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($sock === false) {
    die("Error: socket_create() failed - " . socket_strerror(socket_last_error()) . "\n");
}

if (!socket_bind($sock, $host, $port)) {
    die("Error: socket_bind() failed - " . socket_strerror(socket_last_error($sock)) . "\n");
}

if (!socket_listen($sock)) {
    die("Error: socket_listen() failed - " . socket_strerror(socket_last_error($sock)) . "\n");
}

echo "✅ PHP Web Server running at http://$host:$port\n";

while (true) {
    $client = socket_accept($sock);
    $request = socket_read($client, 2048); 
    $lines = explode("\r\n", $request);
    $requestLine = $lines[0];
    $parts = explode(' ', $requestLine);

    $method = $parts[0];
    $path = urldecode($parts[1]);

    $responseBody = "";
    $status = "200 OK";
    $mimeType = "text/html";

    if ($method == "POST" && $path == "/submit") {
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
        $responseBody = "<h1>Hello, $name! (POST received)</h1>";
    }
    elseif ($method == "GET" && $path == "/") {
        $responseBody = '
            <form method="POST" action="/submit">
                <input type="text" name="name" placeholder="Enter your name">
                <button type="submit">Submit</button>
            </form>';
    } else {
        $filePath = __DIR__ . "/public" . ($path == "/" ? "/index.html" : $path);

    if (is_file($filePath)) {
        $responseBody = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
    } else {
        $responseBody = "<h1>404 Not Found</h1>";
        $status = "404 Not Found";
    }

    $response = "HTTP/1.1 $status\r\n";
    $response .= "Content-Type: $mimeType\r\n";
    $response .= "Content-Length: " . strlen($responseBody) . "\r\n\r\n";
    $response .= $responseBody;

    socket_write($client, $response);
    socket_close($client);
}


socket_close($sock);
?>