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

    