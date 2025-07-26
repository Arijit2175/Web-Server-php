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
    if ($client === false) continue;

    $request = socket_read($client, 1024);
    echo "📥 Received Request:\n$request\n";

    $response = "HTTP/1.1 200 OK\r\n";
    $response .= "Content-Type: text/html\r\n\r\n";
    $response .= "<h1>Hello from PHP Server!</h1>";

    socket_write($client, $response);

    socket_close($client);
}

socket_close($sock);
?>