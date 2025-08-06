<?php
$host = '127.0.0.1';
$port = 8080;

$masterSock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($masterSock, $host, $port);
socket_listen($masterSock);

socket_set_nonblock($masterSock); 

echo "✅ Multi-Client PHP Web Server running at http://$host:$port\n";

$clients = [];

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
                $data = @socket_read($sock, 2048, PHP_NORMAL_READ);
                if ($data === false) {
                    $index = array_search($sock, $clients);
                    socket_close($sock);
                    unset($clients[$index]);
                    continue;
                }

                $requestLine = trim($data);
                if ($requestLine !== '') {
                    $parts = explode(' ', $requestLine);
                    $method = $parts[0] ?? '';
                    $path = urldecode($parts[1] ?? '/');

                    $responseBody = "<h1>Simple PHP Server</h1><p>You requested: $path</p>";
                    $status = "200 OK";
                    $mimeType = "text/html";

                    $response = "HTTP/1.1 $status\r\n";
                    $response .= "Content-Type: $mimeType\r\n";
                    $response .= "Content-Length: " . strlen($responseBody) . "\r\n\r\n";
                    $response .= $responseBody;

                    socket_write($sock, $response);
                    socket_close($sock);
                    $index = array_search($sock, $clients);
                    unset($clients[$index]);
                }
            }
        }
    }
}
?>
