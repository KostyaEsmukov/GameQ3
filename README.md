GameQ3
======
A PHP Gameserver Status Query Library.

Information
===========
GameQ3 allows you to query multiple game servers at the same time. My goal is to make GameQ3 as fast as it is possible on PHP.
GameQ3 is influenced by GameQ (author Tom Buskens <t.buskens@deviation.nl>) and GameQ v2 (url https://github.com/Austinb/GameQ, author Austin Bischoff <austin@codebeard.com>).
The main differences from previous versions of GameQ are queue style of packets and smart socket class which uses native sockets for UDP handling instead of stream sockets.

Requirements
============
* PHP >= 5.3.0
* sockets extension for UDP handling (compile PHP with --with-sockets)
* cURL extension for HTTP handling (required for some protocols)
* Bzip2 extension for Source protocol (compile PHP with --with-bz2)

Example
=======
You may check /examples folder.

Simple usage example:

    $gq = new GameQ3\GameQ3();
    try {
        $gq->addServer(array(
            'id' => 'cs',
            'type' => 'cs',
            'host' => 'simhost.org:27015'
        ));
    }
    catch(Exception $e) {
        die($e->getMessage());
    }

    $results = $gq->requestAllData();

    var_dump($results);
