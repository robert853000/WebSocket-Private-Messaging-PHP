<?php

function unmask($text)
{
    $length = ord($text[1]) & 127;
    if ($length == 126) {
        $masks = substr($text, 4, 4);
        $data = substr($text, 8);
    } elseif ($length == 127) {
        $masks = substr($text, 10, 4);
        $data = substr($text, 14);
    } else {
        $masks = substr($text, 2, 4);
        $data = substr($text, 6);
    }
    $text = "";
    for ($i = 0; $i < strlen($data); ++$i) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }
    return $text;
}

//Encode message for transfer to client.
function mask($text)
{
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($text);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);
    return $header . $text;
}
function broadcastClientUpdateMessage($connectedClients)
{
    $clientList = implode(", ", array_column($connectedClients, "id"));
    $messageWithCommand = "client_update:" . $clientList;

    foreach ($connectedClients as $connectedClient) {
        $clientSocket = $connectedClient["socket"];
        if ($connectedClient["id"] == "0") {
            continue;
        }
        //$response = chr(129) . chr(strlen($messageWithCommand)) . $messageWithCommand;
        $response = mask($messageWithCommand);
        if (socket_write($clientSocket, $response, strlen($response)) === false) {
            echo "Nepodařilo se odeslat zprávu klientovi " . $connectedClient["id"] . ". "  . socket_strerror(socket_last_error()) . "\n";
            continue;
        }
    }
    echo "Zpráva odeslána všem klientům: $messageWithCommand\n";
}
function sendMessageToAllClients($message, $connectedClients)
{
    foreach ($connectedClients as $connectedClient) {
        $clientSocket = $connectedClient["socket"];
        if ($connectedClient["id"] == "0") {
            continue;
        }
        //$response = chr(129) . chr(strlen($message)) . $message;
        $response = mask($message);
        if (socket_write($clientSocket, $response, strlen($response)) === false) {
            echo "Nepodařilo se odeslat zprávu klientovi " . $connectedClient["id"] . ". "  . socket_strerror(socket_last_error()) . "\n";
            continue;
        }
    }
    echo "Zpráva odeslána všem klientům: $message\n";
}
function processPrivateMessage($message, $senderID, $connectedClients)
{
    // Rozděl zprávu na části
    $parts = explode(",", $message);

    // Ověř, že zpráva má správný formát
    if (count($parts) !== 3) {
        echo "Neplatný formát zprávy: $message\n";
        return;
    }

    // Extrahuj číslo klienta a zprávu
    $clientNumber = trim($parts[1]);
    $messageContent = trim($parts[2]);

    // Odeslat zprávu pouze příslušnému klientovi
    foreach ($connectedClients as $connectedClient) {
        if ($connectedClient["id"] == "$clientNumber") {
            $clientSocket = $connectedClient["socket"];
            $response = mask("private_message:" . $senderID . ":" . $messageContent);

            if (socket_write($clientSocket, $response, strlen($response)) === false) {
                echo "Nepodařilo se odeslat zprávu klientovi s ID $clientNumber: " . socket_strerror(socket_last_error()) . "\n";
                return;
            }

            echo "Zpráva odeslána klientovi s ID $clientNumber od klienta s ID $senderID: $messageContent\n";
            return;
        }
    }

    // Pokud klient s daným číslem není nalezen
    echo "Klient s ID $clientNumber nenalezen\n";
}
// Funkce pro získání IP adresy připojeného klienta
function getRemoteClientIP($clientSocket)
{
    $remoteClientIP = '';
    socket_getpeername($clientSocket, $remoteClientIP);
    return $remoteClientIP;
}

function sendWebSocketHandshake($clientSocket)
{
    // Přečtení HTTP požadavku od klienta
    $request = socket_read($clientSocket, 5000);
    echo "HTTP požadavek od klienta:\n" . $request . "\n";

    // Získání klíče "Sec-WebSocket-Key" z požadavku
    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
    $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    // Vytvoření odpovědi WebSocket handshake headers
    $headers = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Version: 13\r\n" .
        "Sec-WebSocket-Accept: " . $key . "\r\n\r\n";
    socket_write($clientSocket, $headers, strlen($headers));
}

// Nastavení adresy a portu serveru
// Nastavení adresy a portu serveru
$address = 'localhost';
$port = 12345;
$serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);
// Přiřazení adresy a portu socketu
if (!socket_bind($serverSocket, $address, $port)) {
    echo "Nepodařilo se přiřadit adresu a port socketu: " . socket_strerror(socket_last_error()) . "\n";
    exit(1);
}
// Naslouchání na socketu
if (!socket_listen($serverSocket)) {
    echo "Nepodařilo se spustit naslouchání na socketu: " . socket_strerror(socket_last_error()) . "\n";
    exit(1);
}
$clientIDCounter = 0;
$serverClient = array(
    'socket' => $serverSocket,
    'id' => $clientIDCounter
);

$connectedClients = array($serverClient);
$null = null;



echo "WebSocket server běží na adrese $address:$port\n";

do {
    $readSockets = array_column($connectedClients, 'socket');
    $writeSockets = $exceptSockets = $null;

    if (socket_select($readSockets, $writeSockets, $exceptSockets, null) === false) {
        echo "Chyba při vykonávání socket_select: " . socket_strerror(socket_last_error()) . "\n";
        break;
    }

    foreach ($readSockets as $readSocket) {
        if ($readSocket === $serverSocket) {
            // Příchozí připojení na serverový socket - přidání nového klienta
            $clientSocket = socket_accept($serverSocket);

            if ($clientSocket === false) {
                echo "Nepodařilo se přijmout připojení klienta: " . socket_strerror(socket_last_error()) . "\n";
                continue;
            }
            $clientIDCounter++;
            $connectedClient = array(
                'socket' => $clientSocket,
                'id' => $clientIDCounter
            );
            $connectedClients[] = $connectedClient;
            echo "Klient připojen.\n";

            // Send WebSocket handshake headers.
            // Odeslání handshake
            sendWebSocketHandshake($clientSocket);

            $newClientIP = ''; // IP adresa nového klienta
            socket_getpeername($clientSocket, $newClientIP);
            $messagex = "Nový klient se připojil: $newClientIP. Socket ID: $clientIDCounter";
            sendMessageToAllClients($messagex, $connectedClients);
            broadcastClientUpdateMessage($connectedClients);

            //print_r($connectedClients);
        } else {
            // Data příchozí od klienta
            $clientSocket = $readSocket;

            $data = socket_read($clientSocket, 5000);

            $currentClient = null;
            $currentClientKey = "";
            foreach ($connectedClients as $key => $client) {
                if ($client['socket'] === $clientSocket) {
                    $currentClient = $client;
                    $currentClientKey =  $key;
                    break;
                }
            }
            if ($data === false || empty($data)) {
                // Chyba při čtení dat od klienta - odpojení klienta
                /*foreach ($connectedClients as $key => $client) {
                    if ($client['socket'] === $clientSocket) {
                        socket_close($client['socket']);
                        echo "Klient odpojen: " . $client['id'] . "\n";
                        unset($connectedClients[$key]);
                        break;
                    }
                }*/
                socket_close($currentClient['socket']);
                echo "Klient odpojen: " . $currentClient['id'] . "\n";
                unset($connectedClients[$currentClientKey]);
                break;
                continue;
            }

            // Zpracování přijatých dat od klienta
            // ...
            // Zpracování přijatých dat od klienta
            $data = trim($data); // Odstranění bílých znaků z přijatých dat
            $data = unmask($data);
            echo "Client Message : " . $data . "\n";
            if (substr($data, 0, 14) === 'privateMessage') {
                processPrivateMessage($data, $currentClient["id"], $connectedClients);
            } else if (substr($data, 0, 7) === 'clients') {
                // Seznam aktivně připojených klientů a jejich IP adresy
                $clientInfoList = array();
                foreach ($connectedClients as $connectedClient) {
                    //print_r($connectedClient);
                    $clientIP = '';
                    if ($connectedClient["id"] != "0") socket_getpeername($connectedClient["socket"], $clientIP);

                    $clientInfoList[] = "IP: $clientIP, ID:" . $connectedClient["id"] . ", Socket: " . $connectedClient["socket"] . "";
                }

                // Vytvoření zprávy se seznamem klientů a jejich IP adresami
                $message = "Seznam aktivně připojených klientů:\n";
                $message .= implode("\n", $clientInfoList);

                // Odeslání zprávy klientovi
                $response =  mask($message);
                if (socket_write($clientSocket, $response, strlen($response)) === false) {
                    echo "Nepodařilo se odeslat zprávu klientovi: " . socket_strerror(socket_last_error()) . "\n";
                    continue;
                }
                /*$response = chr(129) . chr(strlen($message)) . $message;
                if (socket_write($clientSocket, $response, strlen($response)) === false) {
                    echo "Nepodařilo se odeslat zprávu klientovi: " . socket_strerror(socket_last_error()) . "\n";
                    continue;
                }*/

                echo "Zpráva odeslána klientovi:\n $message\n";
            } elseif (strpos($data, 'message') === 0) {
                // Získání zprávy ze příkazu
                $message = substr($data, 7);

                // Odeslání zprávy klientovi
                $response = chr(129) . chr(strlen($message)) . $message;
                if (socket_write($clientSocket, $response, strlen($response)) === false) {
                    echo "Nepodařilo se odeslat zprávu klientovi: " . socket_strerror(socket_last_error()) . "\n";
                    continue;
                }

                echo "Zpráva odeslána klientovi: $message\n";
            } else {
                // Neznámý příkaz
                $errorMessage = "Neznámý příkaz: " . $data;
                echo $errorMessage . "\n";

                // Odeslání zprávy klientovi s chybovou hláškou
                $response = chr(129) . chr(strlen($errorMessage)) . $errorMessage;
                if (socket_write($clientSocket, $response, strlen($response)) === false) {
                    echo "Nepodařilo se odeslat zprávu klientovi: " . socket_strerror(socket_last_error()) . "\n";
                    continue;
                }
            }

            // Odeslání odpovědi klientovi
            // ...
        }
    }
} while (true);
