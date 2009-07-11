<?php

namespace phpcouch\record;

use phpcouch\Exception;
use phpcouch\http\HttpRequest;

class Database extends Record
{
	const URL_PATTERN_ALLDOCS = '%s/%s/_all_docs';
	const URL_PATTERN_ATTACHMENT = '%s/%s/%s/%s';
	const URL_PATTERN_DESIGNDOCUMENT = '%s/%s/_design/%s';
	const URL_PATTERN_DOCUMENT = '%s/%s/%s';
	const URL_PATTERN_VIEW = '%s/%s/_design/%s/_view/%s';
	
	public function __toString()
	{
		return $this->getName();
	}
	
	/**
	 * Get the name of this database.
	 *
	 * @return     string The database name.
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function getName()
	{
		return $this->db_name;
	}
	
	/**
	 * Create a new document on the server.
	 *
	 * @param      PhpcouchIDocument The document to store.
	 *
	 * @throws     ?
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function createDocument(DocumentInterface $document)
	{
		$values = $document->dehydrate();
		
		if(isset($values['_id'])) {
			// there is an id? nice, but we don't need it, the URL is enough
			unset($values['_id']);
		}
		
		try {
			if($document->_id) {
				// create a named document
				$uri = $this->getConnection()->buildUri($document->_id);
				$result = $this->getConnection()->getAdapter()->put($uri, $values);
			} else {
				// let couchdb create an ID
				$uri = $this->getConnection()->buildUri();
				$result = $this->getConnection()->getAdapter()->post($uri, $values);
			}
			
			if(isset($result->ok) && $result->ok === true) {
				// all cool.
				$document->hydrate(array(Document::ID_FIELD => $result->id, Document::REVISION_FIELD => $result->rev));
				return;
			} else {
				throw new Exception('Result not OK :(');
				// TODO: add $result
			}
		} catch(Exception $e) {
			throw new Exception($e->getMessage(), $e->getCode(), $e);
			// TODO: add $result
		}
	}
	
	/**
	 * Retrieve a document from the database.
	 *
	 * @param      string The ID of the document.
	 *
	 * @return     PhpcouchIDocument A document instance.
	 *
	 * @throws     ?
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function retrieveDocument($id)
	{
		$uri = $this->getConnection()->buildUri($id);
		
		// TODO: grab and wrap exceptions
		$result = $this->getConnection()->getAdapter()->get($uri);
		
		if(isset($result->_id)) {
			$document = $this->newDocument();
			$document->hydrate($result);
			return $document;
		} else {
			// error
		}
	}
	
	/**
	 * Retrieve an attachment of a document.
	 *
	 * @param      string The name of the attachment.
	 * @param      string The document ID.
	 *
	 * @return     string The attachment contents.
	 *
	 * @throws     ?
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function retrieveAttachment($name, $id)
	{
		// TODO: this doesn't work atm
		if($id instanceof DocumentInterface) {
			$id = $id->_id;
		}
		
		$uri = $this->getConnection()->buildUri($id, array('attachment' => $name));
		
		return $this->getConnection()->getAdapter()->get($uri);
	}
	
	/**
	 * Save a modified document to the database.
	 *
	 * @param      PhpcouchIDocument The document to save.
	 *
	 * @throws     ?
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function updateDocument(DocumentInterface $document)
	{
		$values = $document->dehydrate();
		
		$uri = $this->getConnection()->buildUri($document->_id);
		
		$result = $this->getConnection()->getAdapter()->put($uri, $values);
		
		if(isset($result->ok) && $result->ok === true) {
			$document->_rev = $result->rev;
		} else {
			// error
		}
	}
	
	/**
	 * Delete a document.
	 *
	 * @param      PhpcouchDocument The name of the document to delete.
	 *
	 * @return     PhpcouchIDocument The deletion stub document.
	 *
	 * @throws     ?
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @author     Simon Thulbourn <simon.thulbourn@bitextender.com>
	 * @since      1.0.0
	 */
	public function deleteDocument(DocumentInterface $doc)
	{
		if($doc instanceof DocumentInterface) {
			$headers = array('If-Match' => $doc->_rev);
			$id = $doc->_id;
		} else {
			throw new Exception('Parameter supplied is not of type PhpcouchDocument');
		}
		
		$uri = $this->getConnection()->buildUri($id);
		return $this->getConnection()->getAdapter()->delete($uri, $headers);
	}
	
	/**
	 * Make a new document instance with this connection set on it.
	 *
	 * @return     PhpcouchIDocument An empty document.
	 *
	 * @author     David Zülke <david.zuelke@bitextender.com>
	 * @since      1.0.0
	 */
	public function newDocument()
	{
		return new Document($this);
	}
	
	/**
	 * Get a list of all the documents in the database.
	 * 
	 * @param       array An associative array of view options.
	 *
	 * @return      AllDocsResult A list of documents in the database.
	 * 
	 * @author      David Zülke <david.zuelke@bitextender.com>
	 * @since       1.0.0
	 */
	public function listDocuments(array $options = array())
	{
		// only build basic URL
		// options etc are done in executeView()
		return $this->executeView(self::URL_PATTERN_ALLDOCS, array($this->getName()), $options, 'phpcouch\record\AllDocsResult');
	}
	
	public function callView($designDocument, $viewName, array $options = array())
	{
		if($designDocument instanceof DocumentInterface) {
			$designDocument = str_replace('_design/', '', $designDocument->getId());
		}
		
		// only build basic URL
		// options etc are done in executeView()
		return $this->executeView(self::URL_PATTERN_VIEW, array($this->getName(), $designDocument, $viewName), $options);
	}
	
	protected function executeView($urlPattern, array $urlPatternValues, array $options = array(), $viewResultClass = null)
	{
		$con = $this->getConnection();
		
		if($viewResultClass === null) {
			$viewResultClass = 'phpcouch\record\ViewResult';
		}
		
		$boolCleanup = function($value) { return var_export((bool)$value, true); };
		$cleanup = array(
			// 'keys' => 'json_encode',
			'key' => 'json_encode',
			'startkey' => 'json_encode',
			'endkey' => 'json_encode',
			'limit' => 'intval',
			'stale' => function($value) { if($value) return 'ok'; },
			'descending' => $boolCleanup,
			'skip' => 'intval',
			'group' => $boolCleanup,
			'group_level' => 'intval',
			'reduce' => $boolCleanup,
			'include_docs' => $boolCleanup,
		);
		
		array_walk($options, function(&$value, $key, $cleanup) { if(isset($cleanup[$key])) $value = $cleanup[$key]($value); }, $cleanup);
		
		$request = new HttpRequest();
		if(isset($options['keys'])) {
			$request->setContent(json_encode(array('keys' => (array)$options['keys'])));
			$request->setMethod(HttpRequest::METHOD_POST);
			$request->setContentType('application/json');
			unset($options['keys']);
		}
		$request->setDestination($con->buildUrl($urlPattern, $urlPatternValues, $options));
		
		$viewResult = new $viewResultClass($this);
		$viewResult->hydrate($con->sendRequest($request));
		
		return $viewResult;
	}
}

?>