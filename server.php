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
    $request = socket_read($client, 1024);

    echo "📥 Request:\n$request\n";

    $lines = explode("\r\n", $request);
    $requestLine = $lines[0];
    $parts = explode(' ', $requestLine);
    
    $method = $parts[0];
    $path = $parts[1];

    if ($path == "/") {
        $body = "<h1>Welcome to Home Page</h1>";
        $status = "200 OK";
    } elseif ($path == "/about") {
        $body = "<h1>About Us</h1>";
        $status = "200 OK";
    } else {
        $body = "<h1>404 Not Found</h1>";
        $status = "404 Not Found";
    }

    $response = "HTTP/1.1 $status\r\n";
    $response .= "Content-Type: text/html\r\n\r\n";
    $response .= $body;

    socket_write($client, $response);
    socket_close($client);
}

socket_close($sock);
?>