<?php

use OpenSwoole\WebSocket\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\WebSocket\Frame;



$server = new Server("0.0.0.0", 9502);

$server->on("Start", function(Server $server)
{
    echo "OpenSwoole WebSocket Server is started at http://127.0.0.1:9502\n";
    echo "Initalizing cache";

});

 //Connecting to Redis server on localhost 
 $redis = new Redis(); 
 $redis->connect('127.0.0.1', 6379); 
 echo "Connection to server sucessfully"; 
 //check whether server is running or not 
 echo "Server is running: ".$redis->ping(); 

$server->on('Open', function(Server $server, OpenSwoole\Http\Request $request)
{
    echo "connection open: {$request->fd}\n";


    
});

$server->on('Message', function(Server $server, Frame $frame)
{


    global $redis;
    
    $message = json_decode($frame->data);
     //var_dump($message);
    
    if ($message->command == "newMessage") {
        $redis->lpush('m', json_encode($message->parameters));
        

        $jsonL = $redis->lrange('u', 0, 1000); $users = array(); foreach($jsonL as $j)  $users[] = json_decode($j);


        foreach($users as $user) {
             $server->push($user->fd, json_encode($message));   
        }
    }

    if ($message->command == "register") {
        $message->parameters->fd = $frame->fd;
        $redis->lpush('u', json_encode($message->parameters));

        // var_dump($users);

        // send user joined message to all other users
        $userJoinedMessage = new class{};
        $userJoinedMessage->command = "newUser";
        $userJoinedMessage->parameters = new class{};
        $userJoinedMessage->parameters->name = $message->parameters->userName;
        $userJoinedMessage->parameters->id = $message->parameters->userId;
        $userJoinedMessage->parameters->fd = $frame->fd;

        

        $jsonL = $redis->lrange('u', 0, 1000); $users = array(); foreach($jsonL as $j) $users[] = json_decode($j);

        foreach($users as $user) {
            $server->push($user->fd, json_encode($userJoinedMessage));   
        }


    }

    
});

$server->on('Close', function(Server $server, int $fd)
{

    global $redis;

    $jsonL = $redis->lrange('u', 0, 1000); $users = array(); foreach($jsonL as $j) $users[] = json_decode($j);

    echo "connection close: {$fd}\n";

    foreach ($users as $key => $user) {
        if($user->fd == $fd) {
            echo "unregister user with fd " .  $fd;
            unset($users[$key]);
        }
    }

});

$server->on('Disconnect', function(Server $server, int $fd)
{
    echo "connection disconnect: {$fd}\n";
});

$server->start();


