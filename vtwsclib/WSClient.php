<?php

global $coreBOS_Basedir;
if (empty($coreBOS_Basedir)) {
	$coreBOS_Basedir = dirname(__FILE__);
}
require_once $coreBOS_Basedir.'/Net/HTTP_Client.php';
require_once $coreBOS_Basedir.'/WSVersion.php';

/**
 * Vtiger Webservice Client
 */
class Vtiger_WSClient {
	// Webserice file
	var $_servicebase = 'webservice.php';

	// HTTP Client instance
	var $_client = false;
	// Service URL to which client connects to
	var $_serviceurl = false;

	// Webservice user credentials
	var $_serviceuser= false;
	var $_servicekey = false;

	// Webservice login validity
	var $_servertime = false;
	var $_expiretime = false;
	var $_servicetoken=false;

	// Webservice login credentials
	var $_sessionid  = false;
	var $_userid     = false;

	// Last operation error information
	var $_lasterror  = false;

	/**
	 * Constructor.
	 */
	function __construct($url) { 
		$this->_serviceurl = $this->getWebServiceURL($url);
		$this->_client = new cbHTTP_Client($this->_serviceurl);
	}

	/**
	 * Return the client library version.
	 */
	function version() {
		global $wsclient_version;
		return $wsclient_version;
	}

	/**
	 * Reinitialize the client.
	 */
	function reinitalize() {
		$this->_client = new cbHTTP_Client($this->_serviceurl);
	}

	/**
	 * Get the URL for sending webservice request.
	 */
	function getWebServiceURL($url) {
		if(stripos($url, $this->_servicebase) === false) {
			if(strripos($url, '/') != (strlen($url)-1)) {
				$url .= '/';
			}
			$url .= $this->_servicebase;
		}
		return $url;
	}

	/**
	 * Get actual record id from the response id.
	 */
	function getRecordId($id) {
		$ex = explode('x', $id);
		return $ex[1];
	}

	/**
	 * Check if result has any error.
	 */
	function hasError($result) {
		if(isset($result['success']) && $result['success'] === true) {
			$this->_lasterror = false;
			return false;
		}
		$this->_lasterror = $result['error'];
		return true;
	}

	/**
	 * Get last operation error
	 */
	function lastError() {
		return $this->_lasterror;
	}

	/**
	 * Perform the challenge
	 * @access private
	 */
	function __doChallenge($username) {
		$getdata = Array(
			'operation' => 'getchallenge',
			'username'  => $username
		);
		$resultdata = $this->_client->doGet($getdata, true);

		if($this->hasError($resultdata)) {
			return false;
		}

		$this->_servertime   = $resultdata['result']['serverTime'];
		$this->_expiretime   = $resultdata['result']['expireTime'];
		$this->_servicetoken = $resultdata['result']['token'];
		return true;
	}

	/**
	 * Check and perform login if requried.
	 */
	function __checkLogin() {
		/*if($this->_expiretime || (time() > $this->_expiretime)) {
			$this->doLogin($this->_serviceuser, $this->_servicepwd);
		}*/
	}

	/**
	 * Do Login Operation
	 */
	function doLogin($username, $userAccesskey) {
		// Do the challenge before login
		if($this->__doChallenge($username) === false) return false;
		
		$postdata = Array(
			'operation' => 'login',
			'username'  => $username,
			'accessKey' => md5($this->_servicetoken.$userAccesskey)
		);
		$resultdata = $this->_client->doPost($postdata, true);

		if($this->hasError($resultdata)) {
			return false;
		}
		$this->_serviceuser = $username;
		$this->_servicekey  = $userAccesskey;

		$this->_sessionid = $resultdata['result']['sessionName'];
		$this->_userid    = $resultdata['result']['userId'];
		return true;
	}

	/**
	 * Do Query Operation.
	 */
	function doQuery($query) {
		// Perform re-login if required.
		$this->__checkLogin();

		// Make sure the query ends with ;
		$query = trim($query);
		if(strripos($query, ';') != strlen($query)-1) $query .= ';';

		$getdata = Array(
			'operation' => 'query',
			'sessionName'  => $this->_sessionid,
			'query'  => $query
		);
		$resultdata = $this->_client->doGet($getdata, true);
		
		if($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Get Result Column Names.
	 */
	function getResultColumns($result) {
		$columns = Array();
		if(!empty($result)) {
			$firstrow= $result[0];
			foreach($firstrow as $key=>$value) $columns[] = $key;
		}
		return $columns;
	}

	/**
	 * List types available Modules.
	 */
	function doListTypes($fieldTypeList='') {
		// Perform re-login if required.
		$this->__checkLogin();

		if (is_array($fieldTypeList)) $fieldTypeList = json_encode($fieldTypeList);
		$getdata = Array(
			'operation' => 'listtypes',
			'sessionName'  => $this->_sessionid,
			'fieldTypeList' => $fieldTypeList
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if($this->hasError($resultdata)) {
			return false;
		}
		$modulenames = $resultdata['result']['types'];

		$returnvalue = Array();
		foreach($modulenames as $modulename) {
			$returnvalue[$modulename] = Array ( 'name' => $modulename );
		}
		return $returnvalue;
	}

	/**
	 * Describe Module Fields.
	 */
	function doDescribe($module) {
		// Perform re-login if required.
		$this->__checkLogin();

		$getdata = Array(
			'operation' => 'describe',
			'sessionName'  => $this->_sessionid,
			'elementType' => $module
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if($this->hasError($resultdata)) {
			return false;
		}		
		return $resultdata['result'];
	}

	/**
	 * Retrieve details of record.
	 */
	function doRetrieve($record) {
		// Perform re-login if required.
		$this->__checkLogin();

		$getdata = Array(
			'operation' => 'retrieve',
			'sessionName'  => $this->_sessionid,
			'id' => $record
		);
		$resultdata = $this->_client->doGet($getdata, true);
		if($this->hasError($resultdata)) {
			return false;
		}		
		return $resultdata['result'];
	}

	/**
	 * Do Create Operation
	 */
	function doCreate($module, $valuemap) {
		// Perform re-login if required.
		$this->__checkLogin();

		// Assign record to logged in user if not specified
		if(!isset($valuemap['assigned_user_id'])) {
			$valuemap['assigned_user_id'] = $this->_userid;
		}

		$postdata = Array(
			'operation'   => 'create',
			'sessionName' => $this->_sessionid,
			'elementType' => $module,
			'element'     => json_encode($valuemap)
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if($this->hasError($resultdata)) {
			return false;
		}		
		return $resultdata['result'];
	}

	function doUpdate($module, $valuemap) {
		// Perform re-login if required.
		$this->__checkLogin();
	
		// Assign record to logged in user if not specified
		if(!isset($valuemap['assigned_user_id'])) {
			$valuemap['assigned_user_id'] = $this->_userid;
		}
	
		$postdata = Array(
			'operation'   => 'update',
			'sessionName' => $this->_sessionid,
			'elementType' => $module,
			'element'     => json_encode($valuemap)
		);
		$resultdata = $this->_client->doPost($postdata, true);
		if($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

	/**
	 * Invoke custom operation
	 *
	 * @param String $method Name of the webservice to invoke
	 * @param Object $type null or parameter values to method
	 * @param String $params optional (POST/GET)
	 */
	function doInvoke($method, $params = null, $type = 'POST') {
		// Perform re-login if required
		$this->__checkLogin();
		
		$senddata = Array(
			'operation' => $method,
			'sessionName' => $this->_sessionid
		);
		if(!empty($params)) {
			foreach($params as $k=>$v) {
				if(!isset($senddata[$k])) {
					$senddata[$k] = $v;
				}
			}
		}

		$resultdata = false;
		if(strtoupper($type) == "POST") {
			$resultdata = $this->_client->doPost($senddata, true);
		} else {
			$resultdata = $this->_client->doGet($senddata, true);
		}

		if($this->hasError($resultdata)) {
			return false;
		}
		return $resultdata['result'];
	}

}
?>