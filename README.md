GameQ3
======
A PHP Gameserver Status Query Library.

## Introduction
GameQ3 allows you to query multiple game servers at the same time. My goal is to make GameQ3 as fast and reliable as it is possible on PHP (though I think this language is not good for this kind of work).
GameQ3 is influenced by GameQ and GameQ v2.
Design of this library is different from other GameQ versions.
The main differences are queue style of packets and *huge* socket class which uses native sockets for UDP handling instead of stream sockets.
Some protocols are like in GameQ v2, but protocol classes are not compatible.

## Requirements
* PHP >= 5.4.0
* sockets extension for UDP handling and AF_INET* constants (compile PHP with --enable-sockets flag)
* cURL extension for HTTP handling (required for some protocols)
* Bzip2 extension for Source protocol (compile PHP with --with-bz2 flag)

## Quickstart example
You may want to check [/examples](https://github.com/kostya0shift/GameQ3/tree/master/examples) folder.

Simple usage example:

    require "gameq3/gameq3.php";
    $gq = new \GameQ3\GameQ3();
    try {
        $gq->addServer(array(
            'id' => 'cs1',
            'type' => 'cs',
            'connect_host' => 'simhost.org:27015'
        ));
    }
    catch(\GameQ3\UserException $e) {
        die("addServer exception: " . $e->getMessage());
    }

    $results = $gq->requestAllData();

    var_dump($results);


## What about other PHP game query libraries?
* lgsl (url [www.greycube.com](http://www.greycube.com/site/download.php?view.56), author Richard Perry): poorly coded, currently unsupported, uses linear requests (which are very slow when you are dealing with large amount of servers), but has good admin webinterface and CMS modules.
* GameQ (author Tom Buskens <t.buskens@deviation.nl>): currently unsupported, poorly designed, supports many games.
* GameQ v2 (url [Austinb/GameQ](https://github.com/Austinb/GameQ), author Austin Bischoff <austin@codebeard.com>): most supported library for today, has many users, supports many games, good for querying small amount of servers.

## Why GameQ3?
You should basically compare GameQ3 to GameQ v2. So here is a checklist for you to make a decision:
* For large amount of servers you shoud definitely stick with GameQ3
* GameQ3 is designed to work in forever running daemons (but for single web page loads works also great)
* More complex abilities for protocols. You can implement nearly every protocol you can imagine in GameQ3
* If you need more control for sockets options (useful for ajustment for various environments) to keep requests reliable

## How to use
1. Require gameq3/gameq3.php script
1. Create instance of \GameQ3\GameQ3() class.
1. Call *setup* and *info* methods when not in request
1. Request data using **one** of provided *request* methods.

### *Info* methods
    getProtocolInfo($protocol)
    getAllProtocolsInfo()

### *Setup* methods
    setLogLevel($error, $warning = true, $debug = false, $trace = false)
    setOption($key, $value)
    setFilter($name, $args = array())
    unsetFilter($name)
    addServer($server_info)
    unsetServer($id)

### *Request* methods
    requestAllData()
    requestPartData()

## addServer options
**Bold** are mandatory.
* **id** - id of server
* **type** - protocol
* filters - array of (filter_name => args) for this server. This supersedes globally set filters using setFilter. If args is false, then this fillter will not be applied.
* debug - change all debug messages from this protocol to warnings
* unset - array of keys in response which should be ommited (to save memory and for some protocols send less packets)
* All other options are processed by protocols. They might require some other mandatory options. Options for networked protocols:
* connect_addr - host:port
* connect_host
* connect_port
* query_addr (alias addr) - host:port
* query_host (alias host)
* query_port (alias port)


## setOption options
Options you should tweak first are **bold**.
* servers_count (int, 2500) - number of servers to request at the same time
* **connect_timeout** (int, 1) - s. connect timeout for stream sockets
* **send_once_udp** (int, 5) - number of udp packets to send at once
* **send_once_stream** (int, 5) - number of stream packets to send at once
* usleep_udp (int, 100) - ns. pause between udp packet sends
* usleep_stream (int, 100) - ns. pause between stream packet sends
* **read_timeout** (int, 600) - ms. read timeout
* read_got_timeout (int, 30) - ms. how much of time to wait after latest received packet
* **read_retry_timeout** (int, 200) - ms. read_timeout for non-first attempts
* loop_timeout (int, 2) - ms. pause between socket operations
* socket_buffer (int, 8192) - bytes. socket buffer
* **send_retry** (int, 1) - count. number of retry attempts for packets which has timed out
* **curl_connect_timeout** (int, 1000) - ms. connect timeout for http requests
* **curl_total_timeout** (int, 1200) - ms. total page load timeout
* **curl_select_timeout** (int, 1500) - ms. maximum wait time for all curl requests (should be bigger than curl_total_timeout)
* curl_options (array, array()) - array for curl_setopt_array

## Filters
* colorize - currently this filter just strips all colors in responses, but it is possible to implement HTML (or whatever) translation
* sortplayers - sorts players list.
    Arguments:
    * sortkeys - array of sorting keys. They will be tested in the order they are given. This array consists of arrays like this: array('key' => $sortKeyName, 'order' => 'asc' || 'desc')
* strip_badchars - strip non-utf8 characters and trim whitespace in every string of the result.
