<?php

use phpcouch\Phpcouch;
use phpcouch\Exception;
use phpcouch\connection;
use phpcouch\adapter;

error_reporting(E_ALL | E_STRICT);

set_include_path(get_include_path() . ':' . '/Users/dzuelke/Downloads/ZendFramework-1.0.2/library');

require('../lib/phpcouch/Phpcouch.php');
Phpcouch::bootstrap();

PhpCouch::registerConnection('default', $con = new connection\Connection(null, new adapter\PhpAdapter()));

var_dump($con->listDatabases());
var_dump('A UUID (from /_uuids): ' . $con->retrieveUuids()->uuids[0]);
var_dump('Database dir (from /_config): ' . $con->retrieveConfig()->couchdb->database_dir);
var_dump('CouchDB Version (from /): ' . $con->retrieveInfo()->version);
var_dump('Mean request time (from /_stats): ' . $con->retrieveStats()->couchdb->request_time->mean);

var_dump($db = $con->retrieveDatabase('testone'));

foreach($db->executeView('lolcats', 'allp') as $row) {
	var_dump($row->getDocument('doc')->_id);
}

foreach($db->listDocuments(true) as $row) {
	var_dump($row->getDocument()->_id);
}

die();

try {
	var_dump($con->createDatabase('hellohans2'));
	var_dump($con->deleteDatabase('hellohans2'));
} catch(Exception $e) {
}

try {
	$doc = $db->retrieveDocument('63A0B00A68EEBE4ECB4E0F8F9682F813');
	$doc->title = 'hello again';
	$doc->save();
} catch(Exception $e) {
}

$doc = $db->newDocument();
$doc->_id = uniqid();
$doc->type = 'Page';
$doc->title = 'Hello world again!';
$doc->content = 'Something more';
var_dump($doc, $doc->toArray(), $doc->dehydrate());

$doc->save();

$doc->title .= 'Snap';
$doc->save();

$doc = $db->newDocument();
$doc->type = 'Page';
$doc->title = 'An unnamed document';
$doc->content = 'Yay zomg! :>>';
$doc->save();
var_dump($doc);

?>