<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Freshbooks API
 *
 * A Freshbooks API Library for CodeIgniter
 *
 * @package		CodeIgniter Freshbooks API
 * @author		LMB^Box (Thomas Montague)
 * @copyright	Copyright (c) 2009 - 2010, LMB^Box
 * @license		GNU Lesser General Public License (http://www.gnu.org/copyleft/lgpl.html)
 * @link		http://lmbbox.com/projects/ci-freshbooks-api/
 * @since		Version 0.1
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * Freshbooks API Class
 *
 * @package		CodeIgniter Freshbooks API
 * @subpackage	Libraries
 * @category	Freshbooks API
 * @author		LMB^Box (Thomas Montague)
 * @link		http://codeigniter.lmbbox.com/user_guide/libraries/freshbooks_api.html
 */
class Freshbooks_API {
	
	const API_URL						= 'https://%s.freshbooks.com/api/2.1/xml-in';
	const REQUEST_FORMAT_CURL			= 'curl';
	const REQUEST_FORMAT_FSOCK			= 'fsock';
	const REQUEST_FORMAT_HTTP			= 'http';
	
	protected $request_format			= '';
	protected $account					= '';
	protected $token					= '';
	protected $user_agent				= 'CodeIgniter Freshbooks API';
	protected $cache_use_db				= FALSE;
	protected $cache_table_name			= 'freshbooks_api_cache';
	protected $cache_expiration			= 600;
	protected $cache_max_rows			= 1000;
	protected $parse_response			= TRUE;
	protected $exit_on_error			= FALSE;
	protected $debug					= FALSE;
	protected $error_code				= FALSE;
	protected $error_message			= FALSE;
	protected $response;
	protected $parsed_response;
	protected $CI;
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array $params initialization parameters
	 * @return	void
	 */
	public function __construct($params = array())
	{
		// Set the super object to a local variable for use throughout the class
		$this->CI =& get_instance();
		$this->CI->lang->load('freshbooks_api');
		
		// Initialize Parameters
		$this->initialize($params);
		
		log_message('debug', 'Freshbooks_API Class Initialized');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize Preferences
	 *
	 * @access	public
	 * @param	array $params initialization parameters
	 * @return	void
	 */
	public function initialize($params = array())
	{
		if (is_array($params) && !empty($params))
		{
			// Protect restricted variables
			unset($params['CI']);
			unset($params['error_code']);
			unset($params['error_message']);
			unset($params['response']);
			unset($params['parsed_response']);
			
			foreach ($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
		
		// Start cache if enabled
		if (TRUE === $this->cache_use_db) $this->start_cache(TRUE);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set current account
	 * 
	 * @access	public
	 * @param	string $account
	 * @return	bool
	 */
	public function set_account($account)
	{
		if (empty($account))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$this->account = (string) $account;
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set current authentication token
	 * 
	 * @access	public
	 * @param	string $token authentication token
	 * @return	bool
	 */
	public function set_token($token)
	{
		if (empty($token))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$this->token = (string) $token;
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set Debugging
	 * 
	 * @access	public
	 * @param	bool $debug
	 * @return	void
	 */
	public function set_debug($debug)
	{
		$this->debug = (bool) $debug;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Reset Error
	 * 
	 * @access	protected
	 * @return	void
	 */
	protected function _reset_error()
	{
		$this->error_code = FALSE;
		$this->error_message = FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set Error
	 * 
	 * @access	protected
	 * @param	string $error_code
	 * @param	string $error_message
	 * @param	string $exit_message
	 * @return	void
	 */
	protected function _error($error_code, $error_message, $exit_message)
	{
		if (TRUE === $this->debug) log_message('debug', sprintf($exit_message, $error_code, $error_message));
		if (TRUE === $this->exit_on_error)
		{
			exit(sprintf($exit_message, $error_code, $error_message));
		}
		else
		{
			$this->error_code = $error_code;
			$this->error_message = $error_message;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get Error Code
	 * 
	 * @access	public
	 * @return	string
	 */
	public function get_error_code()
	{
		return $this->error_code;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get Error Message
	 * 
	 * @access	public
	 * @return	string
	 */
	public function get_error_message()
	{
		return $this->error_message;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Start Cache
	 * 
	 * @access	public
	 * @param	bool $run_cleanup
	 * @return	void
	 */
	public function start_cache($run_cleanup = FALSE)
	{
		$this->cache_use_db = TRUE;
		$this->_create_table_cache();
		if (TRUE === $run_cleanup) $this->cleanup_cache();
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Stop Cache
	 * 
	 * @access	public
	 * @return	void
	 */
	public function stop_cache()
	{
		$this->cache_use_db = FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Cleanup Cache
	 * 
	 * @access	public
	 * @return	bool
	 */
	public function cleanup_cache()
	{
		if (TRUE === $this->cache_use_db AND $this->cache_table_name != '')
		{
			if ($this->CI->db->count_all($this->cache_table_name) > $this->cache_max_rows)
			{
				$this->CI->db->where('expire_date <', time() - $this->cache_expiration);
				$this->CI->db->delete($this->cache_table_name);
				
				$this->CI->load->dbutil();
				$this->CI->dbutil->optimize_table($this->cache_table_name);
			}
			return TRUE;
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Create Cache Table
	 * 
	 * @access	protected
	 * @return	bool
	 */
	protected function _create_table_cache()
	{
		if (TRUE === $this->cache_use_db AND $this->cache_table_name != '')
		{
			$this->CI->load->database();
			if (FALSE === $this->CI->db->table_exists($this->cache_table_name))
			{
				$fields['request'] = array('type' => 'CHAR', 'constraint' => '35', 'null' => FALSE);
				$fields['response'] = array('type' => 'MEDIUMTEXT', 'null' => FALSE);
				$fields['expire_date'] = array('type' => 'INT', 'constraint' => '10', 'unsigned' => TRUE, 'null' => FALSE, 'default' => '0');
				
				$this->CI->load->dbforge();
				$this->CI->dbforge->add_field($fields);
				$this->CI->dbforge->add_key('request', TRUE);
				$this->CI->dbforge->create_table($this->cache_table_name, TRUE);
				
				$this->CI->db->query('ALTER TABLE `' . $this->CI->db->dbprefix . $this->cache_table_name . '` ENGINE=InnoDB;');
			}
			return TRUE;
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get Cached Request
	 * 
	 * @access	protected
	 * @param	string $request
	 * @return	string|bool
	 */
	protected function _get_cached($request)
	{
		if (empty($this->account) || empty($this->token))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_required_config_missing'), '%2$s');
			return FALSE;
		}
		
		if (empty($request))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		if (TRUE === $this->cache_use_db AND $this->cache_table_name != '')
		{
			$this->CI->db->select('response');
			$this->CI->db->where('request', md5($this->account . $this->token . $request));
			$this->CI->db->where('expire_date >=', time() - $this->cache_expiration);
			$query = $this->CI->db->get($this->cache_table_name);
			
			if ($query->num_rows() > 0)
			{
				$row = $query->result_array();
				return $row[0]['response'];
			}
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Cache Request
	 * 
	 * @access	protected
	 * @param	string $request
	 * @param	string $response
	 * @return	bool
	 */
	protected function _cache($request, $response)
	{
		if (FALSE === $this->cache_use_db)
		{
			return FALSE;
		}
		
		if (empty($this->cache_table_name) || empty($this->account) || empty($this->token))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_required_config_missing'), '%2$s');
			return FALSE;
		}
		
		if (empty($request) || empty($response))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$request_hash = md5($this->account . $this->token . $request);
		
		$this->CI->db->where('request', $request_hash);
		$query = $this->CI->db->get($this->cache_table_name);
		
		if ($query->num_rows() > 0)
		{
			$this->CI->db->set('response', $response);
			$this->CI->db->set('expire_date', time() + $this->cache_expiration);
			$this->CI->db->where('request', $request_hash);
			$this->CI->db->update($this->cache_table_name);
			
			if ($this->CI->db->affected_rows() == 1)
			{
				return TRUE;
			}
			else
			{
				log_message('error', __METHOD__ . ' - ' . sprintf($this->CI->lang->line('freshbooks_api_error_updating_cache'), $this->cache_table_name), '%2$s');
			}
		}
		else
		{
			$this->CI->db->set('request', $request_hash);
			$this->CI->db->set('response', $response);
			$this->CI->db->set('expire_date', time() + $this->cache_expiration);
			if (TRUE === $this->CI->db->insert($this->cache_table_name))
			{
				return TRUE;
			}
			else
			{
				log_message('error', __METHOD__ . ' - ' . sprintf($this->CI->lang->line('freshbooks_api_error_creating_cache'), $this->cache_table_name), '%2$s');
			}
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Request
	 * 
	 * @access	public
	 * @param	string $method freshbooks api method
	 * @param	array $params method arguments
	 * @param	bool $nocache use cache or not
	 * @return	mixed
	 */
	public function request($method, $params = array(), $nocache = FALSE)
	{
		if (empty($this->account) || empty($this->token))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_required_config_missing'), '%2$s');
			return FALSE;
		}
		
		if (empty($method) || !is_array($params))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		foreach ($params as $param => $value)
		{
			if (is_null($value))
			{
				unset($params[$param]);
			}
		}
		
		$this->_reset_error();
		$request = $this->_create_xml_request(SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request method="' . $method . '" />'), $params);
		$this->response = $this->_get_cached($request);
		
		if (FALSE === $this->response || TRUE === $nocache)
		{
			switch ($this->request_format)
			{
				case self::REQUEST_FORMAT_CURL:
					if (FALSE === $this->_send_curl($request))
					{
						return FALSE;
					}
					break;
				case self::REQUEST_FORMAT_FSOCK:
					if (FALSE === $this->_send_fsock($request))
					{
						return FALSE;
					}
					break;
				case self::REQUEST_FORMAT_HTTP:
					if (FALSE === $this->_send_http($request))
					{
						return FALSE;
					}
					break;
				default:
					$this->_error(TRUE, __METHOD__ . ' - ' . sprintf($this->CI->lang->line('freshbooks_api_invalid_request_format'), $this->request_format), '%2$s');
					return FALSE;
					break;
			}
		}
		return TRUE === $this->parse_response ? $this->parsed_response = $this->parse_response($this->response) : $this->response;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Create XML Request string
	 *
	 * @access	protected
	 * @param SimpleXMLElement $xml
	 * @param array $data
	 * @return string
	 */
	protected function _create_xml_request($xml, $data)
	{
		// turn off compatibility mode as simple xml throws a wobbly if you don't.
		if (ini_get('zend.ze1_compatibility_mode') == 1)
		{
			ini_set('zend.ze1_compatibility_mode', 0);
		}
		
		if (!is_a($xml, 'SimpleXMLElement') || !is_array($data))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		foreach ($data as $key => $value)
		{
			// no numeric keys in our xml please!
			if (is_numeric($key))
			{
				// make string key...
				$key = 'unknownNode_' . (string) $key;
			}
			
			// replace anything not alpha numeric
			$key = preg_replace('/[^a-z]/i', '', $key);
			
			if (is_array($value))
			{
				$node = $xml->addChild($key);
				$this->_create_xml_request($value, $node_name, $node);
			}
			else
			{
				// add single node.
				$value = htmlentities($value);
				$xml->addChild($key,$value);
			}
		}
		return $xml->asXML();
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Send API Call with cURL
	 * 
	 * @access	protected
	 * @param	array $request freshbooks api call
	 * @return	bool
	 */
	protected function _send_curl($request)
	{
		if (empty($this->account) || empty($this->token))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_required_config_missing'), '%2$s');
			return FALSE;
		}
		
		if (empty($request))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$session = curl_init(sprintf(self::API_URL, $this->account));
		curl_setopt($session, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($session, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($session, CURLOPT_HEADER, FALSE);
//		curl_setopt($session, CURLOPT_NOBODY, FALSE);
		curl_setopt($session, CURLOPT_POST, TRUE);
		curl_setopt($session, CURLOPT_POSTFIELDS, $request);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, TRUE);
//		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 1);
//		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($session, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($session, CURLOPT_USERPWD, $this->token);
		
		$this->response = curl_exec($session);
		if (TRUE === $this->debug) log_message('debug', __METHOD__ . ' - cURL Request Info: ' . print_r(curl_getinfo($session), TRUE));
		
		if (FALSE !== $this->response)
		{
			$this->_cache($request, $this->response);
			curl_close($session);
			return TRUE;
		}
		else
		{
			$this->_error(curl_errno($session), curl_error($session), $this->CI->lang->line('freshbooks_api_send_request_error'));
			curl_close($session);
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Send API Call with FSOCK
	 * 
	 * @access	protected
	 * @param	array $request freshbooks api call
	 * @return	bool
	 */
	protected function _send_fsock($request)
	{
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Send API Call with HTTP
	 * 
	 * @access	protected
	 * @param	array $request freshbooks api call
	 * @return	bool
	 */
	protected function _send_http($request)
	{
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Parse Response
	 * 
	 * @access	public
	 * @param	string $response freshbooks api call response
	 * @return	mixed
	 */
	public function parse_response($response)
	{
		if (empty($response))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		libxml_use_internal_errors(TRUE);
		$response = new SimpleXMLElement($response);
		
		if (FALSE === $response)
		{
			if (TRUE === $this->debug) log_message('debug', __METHOD__ . ' - SimpleXMLElement Load Errors: ' . print_r(libxml_get_errors(), TRUE));
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_simplexmlelement_error'), '%2$s');
			return FALSE;
		}
		
		if ($response['status'] == 'fail')
		{
			$this->_error($response['status'], (string) $response->error, $this->CI->lang->line('freshbooks_api_returned_error'));
			return FALSE;
		}
		
		$children = $response->childern();
		
		if (count($children) == 0)
		{
			return TRUE;
		}
		elseif (count($children) == 1)
		{
			return $children[0];
		}
		else
		{
			return $children;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get Datetime
	 * 
	 * @access	public
	 * @param	int $timestamp Unix timestamp
	 * @param	int $type 0 (default) returns date and time, 1 returns date, 2 returns time
	 * @return	string
	 */
	public function get_datetime($timestamp, $type = 0)
	{
		if (!is_numeric($timestamp))
		{
			log_message('error', __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		switch ($type)
		{
			case 2:
				return date('H:i:s', $timestamp);
				break;
			case 1:
				return date('Y-m-d', $timestamp);
				break;
			case 0:
			default:
				return date('Y-m-d H:i:s', $timestamp);
				break;
		}
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Category
	 * 
	 * Create a new category. If successful, returns the category_id of the 
	 * newly created item.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/categories/#category.create
	 * @param	string $name
	 * @return	mixed
	 */
	public function category_create($name)
	{
		if (empty($name))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('category.create', array('category' => array('name' => $name)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Category
	 * 
	 * Update an existing expense category with the given category_id. Any 
	 * category fields left out of the request will remain unchanged.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/categories/#category.update
	 * @param	int $category_id
	 * @param	string $name
	 * @return	mixed
	 */
	public function category_update($category_id, $name)
	{
		if (empty($category_id) || empty($name))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('category.update', array('category' => array('category_id' => $category_id, 'name' => $name)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Category
	 * 
	 * Return the complete category details associated with the given category_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/categories/#category.get
	 * @param	int $category_id
	 * @return	mixed
	 */
	public function category_get($category_id)
	{
		if (empty($category_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('category.get', array('category_id' => $category_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Category
	 * 
	 * Delete an existing expense category.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/categories/#category.delete
	 * @param	int $category_id
	 * @return	mixed
	 */
	public function category_delete($category_id)
	{
		if (empty($category_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('category.delete', array('category_id' => $category_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Category List
	 * 
	 * Returns a list of expense categories.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/categories/#category.list
	 * @return	mixed
	 */
	public function category_list()
	{
		return $this->request('category.list');
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Client
	 * 
	 * Create a new client and return the corresponding client_id. If a password 
	 * is not supplied, one will be created at random.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/clients/#client.create
	 * @param	string $first_name
	 * @param	string $last_name
	 * @param	string $organization
	 * @param	string $email
	 * @param	array $client
	 * @return	mixed
	 */
	public function client_create($first_name, $last_name, $organization, $email, $client = array())
	{
		if (empty($first_name) || empty($last_name) || empty($organization) || empty($email) || !is_array($client))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$client['first_name'] = $first_name;
		$client['last_name'] = $last_name;
		$client['organization'] = $organization;
		$client['email'] = $email;
		
		return $this->request('client.create', array('client' => $client));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Client
	 * 
	 * Update the details of the client with the given client_id. Any fields not 
	 * referenced in the request will remain unchanged.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/clients/#client.update
	 * @param	int $client_id
	 * @param	array $client
	 * @return	mixed
	 */
	public function client_update($client_id, $client = array())
	{
		if (empty($client_id) || !is_array($client))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$client['client_id'] = $client_id;
		
		return $this->request('client.update', array('client' => $client));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Client
	 * 
	 * Return the client details associated with the given client_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/clients/#client.get
	 * @param	int $client_id
	 * @return	mixed
	 */
	public function client_get($client_id)
	{
		if (empty($client_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('client.get', array('client_id' => $client_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Client
	 * 
	 * Delete the client with the given client_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/clients/#client.delete
	 * @param	int $client_id
	 * @return	mixed
	 */
	public function client_delete($client_id)
	{
		if (empty($client_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('client.delete', array('client_id' => $client_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Client List
	 * 
	 * Returns a list of client summaries in order of descending client_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/clients/#client.list
	 * @param	string $email
	 * @param	string $username
	 * @param	int|string $updated_from
	 * @param	int|string $updated_to
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function client_list($email = NULL, $username = NULL, $updated_from = NULL, $updated_to = NULL, $page = NULL, $per_page = NULL)
	{
		if (is_numeric($updated_from))
		{
			$updated_from = $this->get_datetime($updated_from);
		}
		
		if (is_numeric($updated_to))
		{
			$updated_to = $this->get_datetime($updated_to);
		}
		
		return $this->request('client.list', array('email' => $email, 'username' => $username, 'updated_from' => $updated_from, 'updated_to' => $updated_to, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Estimate
	 * 
	 * Create a new estimate and return the corresponding estimate_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.create
	 * @param	int $client_id
	 * @param	array $estimate
	 * @return	mixed
	 */
	public function estimate_create($client_id, $estimate = array())
	{
		if (empty($client_id) || !is_array($estimate))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$estimate['client_id'] = $client_id;
		
		return $this->request('estimate.create', array('estimate' => $estimate));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Estimate
	 * 
	 * Update an existing estimate.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.update
	 * @param	int $estimate_id
	 * @param	array $estimate
	 * @return	mixed
	 */
	public function estimate_update($estimate_id, $estimate = array())
	{
		if (empty($estimate_id) || !is_array($estimate))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$estimate['estimate_id'] = $estimate_id;
		
		return $this->request('estimate.update', array('estimate' => $estimate));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Estimate
	 * 
	 * Retrieve an existing estimate.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.get
	 * @param	int $estimate_id
	 * @return	mixed
	 */
	public function estimate_get($estimate_id)
	{
		if (empty($estimate_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('estimate.get', array('estimate_id' => $estimate_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Estimate
	 * 
	 * Delete an existing estimate.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.delete
	 * @param	int $estimate_id
	 * @return	mixed
	 */
	public function estimate_delete($estimate_id)
	{
		if (empty($estimate_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('estimate.delete', array('estimate_id' => $estimate_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Estimate List
	 * 
	 * Returns a list of estimates. You can optionally filter by client_id and date.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.list
	 * @param	int $client_id
	 * @param	int|string $date_from
	 * @param	int|string $date_to
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function estimate_list($client_id = NULL, $date_from = NULL, $date_to = NULL, $page = NULL, $per_page = NULL)
	{
		if (is_numeric($date_from))
		{
			$date_from = $this->get_datetime($date_from);
		}
		
		if (is_numeric($date_to))
		{
			$date_to = $this->get_datetime($date_to);
		}
		
		return $this->request('estimate.list', array('client_id' => $client_id, 'date_from' => $date_from, 'date_to' => $date_to, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Send Estimate By Email
	 * 
	 * Send an estimate to the associated client via e-mail.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/estimates/#estimate.sendByEmail
	 * @param	int $estimate_id
	 * @param	string $subject
	 * @param	string $message
	 * @return	mixed
	 */
	public function estimate_sendByEmail($estimate_id, $subject = NULL, $message = NULL)
	{
		if (empty($estimate_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('estimate.sendByEmail', array('estimate_id' => $estimate_id, 'subject' => $subject, 'message' => $message));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Expense
	 * 
	 * Create a new expense specifically for a client, and optionally one of 
	 * their projects, or keep it generalized for a number of clients. If 
	 * successful, returns the expense_id of the newly created item.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/expenses/#expense.create
	 * @param	int $category_id
	 * @param	float $amount
	 * @param	array $expense
	 * @param	int $staff_id is a required field only for admin users. It is ignored for staff using the API.
	 * @return	mixed
	 */
	public function expense_create($category_id, $amount, $expense = array(), $staff_id = NULL)
	{
		if (empty($category_id) || empty($amount) || !is_array($expense))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$expense['category_id'] = $category_id;
		$expense['amount'] = $amount;
		$expense['staff_id'] = $staff_id;
		
		return $this->request('expense.create', array('expense' => $expense));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Expense
	 * 
	 * Update an existing expense with the given expense_id. Any expense fields 
	 * left out of the request will remain unchanged.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/expenses/#expense.update
	 * @param	int $expense_id
	 * @param	array $expense
	 * @return	mixed
	 */
	public function expense_update($expense_id, $expense = array())
	{
		if (empty($expense_id) || !is_array($expense))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$expense['expense_id'] = $expense_id;
		
		return $this->request('expense.update', array('expense' => $expense));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Expense
	 * 
	 * Return the complete expense details associated with the given expense_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/expenses/#expense.get
	 * @param	int $expense_id
	 * @return	mixed
	 */
	public function expense_get($expense_id)
	{
		if (empty($expense_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('expense.get', array('expense_id' => $expense_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Expense
	 * 
	 * Delete an existing expense.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/expenses/#expense.delete
	 * @param	int $expense_id
	 * @return	mixed
	 */
	public function expense_delete($expense_id)
	{
		if (empty($expense_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('expense.delete', array('expense_id' => $expense_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Expense List
	 * 
	 * Returns a list of expense summaries. You can filter by client_id, 
	 * category_id, project_id optionally.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/expenses/#expense.list
	 * @param	int $client_id
	 * @param	int $category_id
	 * @param	int $project_id
	 * @return	mixed
	 */
	public function expense_list($client_id = NULL, $category_id = NULL, $project_id = NULL)
	{
		return $this->request('expense.list', array('client_id' => $client_id, 'category_id' => $category_id, 'project_id' => $project_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Invoice
	 * 
	 * Create a new invoice complete with line items. If successful, returns the 
	 * invoice_id of the newly created invoice.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.create
	 * @param	int $client_id
	 * @param	array $invoice
	 * @return	mixed
	 */
	public function invoice_create($client_id, $invoice = array())
	{
		if (empty($client_id) || !is_array($invoice))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$invoice['client_id'] = $client_id;
		
		return $this->request('invoice.create', array('invoice' => $invoice));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Invoice
	 * 
	 * Update an existing invoice with the given invoice_id. Any invoice fields 
	 * left out of the request will remain unchanged.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.update
	 * @param	int $invoice_id
	 * @param	array $invoice
	 * @return	mixed
	 */
	public function invoice_update($invoice_id, $invoice = array())
	{
		if (empty($invoice_id) || !is_array($invoice))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$invoice['invoice_id'] = $invoice_id;
		
		return $this->request('invoice.update', array('invoice' => $invoice));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Invoice
	 * 
	 * Return the complete invoice details associated with the given invoice_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.get
	 * @param	int $invoice_id
	 * @return	mixed
	 */
	public function invoice_get($invoice_id)
	{
		if (empty($invoice_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('invoice.get', array('invoice_id' => $invoice_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Invoice
	 * 
	 * Delete an existing invoice.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.delete
	 * @param	int $invoice_id
	 * @return	mixed
	 */
	public function invoice_delete($invoice_id)
	{
		if (empty($invoice_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('invoice.delete', array('invoice_id' => $invoice_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Invoice List
	 * 
	 * Returns a list of invoice summaries. Results are ordered by descending 
	 * invoice_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.list
	 * @param	int $client_id
	 * @param	int $recurring_id
	 * @param	string $status
	 * @param	int|string $date_from
	 * @param	int|string $date_to
	 * @param	int|string $updated_from
	 * @param	int|string $updated_to
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function invoice_list($client_id = NULL, $recurring_id = NULL, $status = NULL, $date_from = NULL, $date_to = NULL, $updated_from = NULL, $updated_to = NULL, $page = NULL, $per_page = NULL)
	{
		if (is_numeric($date_from))
		{
			$date_from = $this->get_datetime($date_from);
		}
		
		if (is_numeric($date_to))
		{
			$date_to = $this->get_datetime($date_to);
		}
		
		if (is_numeric($updated_from))
		{
			$updated_from = $this->get_datetime($updated_from);
		}
		
		if (is_numeric($updated_to))
		{
			$updated_to = $this->get_datetime($updated_to);
		}
		
		return $this->request('invoice.list', array('client_id' => $client_id, 'recurring_id' => $recurring_id, 'status' => $status, 'date_from' => $date_from, 'date_to' => $date_to, 'updated_from' => $updated_from, 'updated_to' => $updated_to, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Send Invoice By Email
	 * 
	 * Send an existing invoice to your client via e-mail. The invoice status 
	 * will be changed to sent.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.sendByEmail
	 * @param	int $invoice_id
	 * @param	string $subject
	 * @param	string $message
	 * @return	mixed
	 */
	public function invoice_sendByEmail($invoice_id, $subject = NULL, $message = NULL)
	{
		if (empty($invoice_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('invoice.sendByEmail', array('invoice_id' => $invoice_id, 'subject' => $subject, 'message' => $message));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Send Invoice By Snail Mail
	 * 
	 * Send an existing invoice to your client via snail mail. If you do not 
	 * have enough stamps, the request will fail. If successful, the invoice 
	 * status will be changed to sent. Be careful with this method. This 
	 * operation cannot be undone.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/invoices/#invoice.sendBySnailMail
	 * @param	int $invoice_id
	 * @param	string $subject
	 * @param	string $message
	 * @return	mixed
	 */
	public function invoice_sendBySnailMail($invoice_id)
	{
		if (empty($invoice_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('invoice.sendBySnailMail', array('invoice_id' => $invoice_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Item
	 * 
	 * Create a new item and return the corresponding item_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/items/#item.create
	 * @param	string $name
	 * @param	string $description
	 * @param	float $unit_cost
	 * @param	int $quantity
	 * @param	int $inventory
	 * @return	mixed
	 */
	public function item_create($name, $description = NULL, $unit_cost = NULL, $quantity = NULL, $inventory = NULL)
	{
		if (empty($name))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('item.create', array('item' => array('name' => $name, 'description' => $description, 'unit_cost' => $unit_cost, 'quantity' => $quantity, 'inventory' => $inventory)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Item
	 * 
	 * Update an existing item. All fields aside from the item_id are optional; 
	 * by omitting a field, the existing value will remain unchanged.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/items/#item.update
	 * @param	int $item_id
	 * @param	string $name
	 * @param	string $description
	 * @param	float $unit_cost
	 * @param	int $quantity
	 * @param	int $inventory
	 * @return	mixed
	 */
	public function item_update($item_id, $name = NULL, $description = NULL, $unit_cost = NULL, $quantity = NULL, $inventory = NULL)
	{
		if (empty($item_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('item.update', array('item' => array('item_id' => $item_id, 'name' => $name, 'description' => $description, 'unit_cost' => $unit_cost, 'quantity' => $quantity, 'inventory' => $inventory)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Item
	 * 
	 * Get an existing item with the given item_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/items/#item.get
	 * @param	int $item_id
	 * @return	mixed
	 */
	public function item_get($item_id)
	{
		if (empty($item_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('item.get', array('item_id' => $item_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Item
	 * 
	 * Delete an existing item.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/items/#item.delete
	 * @param	int $item_id
	 * @return	mixed
	 */
	public function item_delete($item_id)
	{
		if (empty($item_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('item.delete', array('item_id' => $item_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Item List
	 * 
	 * Returns a list of items, ordered by descending item_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/items/#item.list
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function item_list($page = NULL, $per_page = NULL)
	{
		return $this->request('item.list', array('page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Payment
	 * 
	 * Create a new payment and returns the corresponding payment_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/payments/#payment.create
	 * @param	int $client_id
	 * @param	int $invoice_id
	 * @param	int|string $date
	 * @param	float $amount
	 * @param	string $type must be one of: 'Check', 'Credit', 'Credit Card', 'Bank Transfer', 'Debit', 'PayPal', '2Checkout', 'VISA', 'MASTERCARD', 'DISCOVER', 'NOVA', 'AMEX', 'DINERS', 'EUROCARD', 'JCB' or 'ACH'.
	 * @param	string $notes
	 * @return	mixed
	 */
	public function payment_create($client_id = NULL, $invoice_id = NULL, $date = NULL, $amount = NULL, $type = NULL, $notes = NULL)
	{
		if (empty($client_id) && empty($invoice_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		if (is_numeric($date))
		{
			$date = $this->get_datetime($date, 1);
		}
		
		return $this->request('payment.create', array('payment' => array('client_id' => $client_id, 'invoice_id' => $invoice_id, 'date' => $date, 'amount' => $amount, 'type' => $type, 'notes' => $notes)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Payment
	 * 
	 * Update an existing payment. All fields besides payment_id are optional; 
	 * unpassed fields will retain their existing value.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/payments/#payment.update
	 * @param	int $payment_id
	 * @param	float $amount
	 * @param	string $notes
	 * @return	mixed
	 */
	public function payment_update($payment_id, $amount = NULL, $notes = NULL)
	{
		if (empty($payment_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('payment.update', array('payment' => array('payment_id' => $payment_id, 'amount' => $amount, 'notes' => $notes)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Payment
	 * 
	 * Retrieve payment details according to payment_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/payments/#payment.get
	 * @param	int $payment_id
	 * @return	mixed
	 */
	public function payment_get($payment_id)
	{
		if (empty($payment_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('payment.get', array('payment_id' => $payment_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Payment
	 * 
	 * Permanently delete a payment. This will modify the status of the 
	 * associated invoice if required.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/payments/#payment.delete
	 * @param	int $payment_id
	 * @return	mixed
	 */
	public function payment_delete($payment_id)
	{
		if (empty($payment_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('payment.delete', array('payment_id' => $payment_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Payment List
	 * 
	 * Returns a list of recorded payments. You can optionally filter by 
	 * invoice_id or client_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/payments/#payment.list
	 * @param	int $client_id
	 * @param	int $invoice_id
	 * @param	int|string $date_from
	 * @param	int|string $date_to
	 * @param	int|string $updated_from
	 * @param	int|string $updated_to
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function payment_list($client_id = NULL, $invoice_id = NULL, $date_from = NULL, $date_to = NULL, $updated_from = NULL, $updated_to = NULL, $page = NULL, $per_page = NULL)
	{
		if (is_numeric($date_from))
		{
			$date_from = $this->get_datetime($date_from);
		}
		
		if (is_numeric($date_to))
		{
			$date_to = $this->get_datetime($date_to);
		}
		
		if (is_numeric($updated_from))
		{
			$updated_from = $this->get_datetime($updated_from);
		}
		
		if (is_numeric($updated_to))
		{
			$updated_to = $this->get_datetime($updated_to);
		}
		
		return $this->request('payment.list', array('client_id' => $client_id, 'invoice_id' => $invoice_id, 'date_from' => $date_from, 'date_to' => $date_to, 'updated_from' => $updated_from, 'updated_to' => $updated_to, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Project
	 * 
	 * Create a new project. If you specify project-rate or flat-rate for 
	 * bill_method, you must supply a rate.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/projects/#project.create
	 * @param	string $name
	 * @param	string $bill_method must be one of: 'task-rate', 'flat-rate', 'project-rate' or 'staff-rate'
	 * @param	array $project
	 * @return	mixed
	 */
	public function project_create($name, $bill_method, $project = array())
	{
		if (empty($name) || empty($bill_method) || !is_array($project))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$project['name'] = $name;
		$project['bill_method'] = $bill_method;
		
		return $this->request('project.create', array('project' => $project));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Project
	 * 
	 * Update an existing project.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/projects/#project.update
	 * @param	int $project_id
	 * @param	array $project
	 * @return	mixed
	 */
	public function project_update($project_id, $project = array())
	{
		if (empty($project_id) || !is_array($project))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$project['project_id'] = $project_id;
		
		return $this->request('project.update', array('project' => $project));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Project
	 * 
	 * Retrieve an existing project. Staff IDs for staff members who are 
	 * assigned to a project will only appear for admins and project managers.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/projects/#project.get
	 * @param	int $project_id
	 * @return	mixed
	 */
	public function project_get($project_id)
	{
		if (empty($project_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('project.get', array('project_id' => $project_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Project
	 * 
	 * Delete an existing project.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/projects/#project.delete
	 * @param	int $project_id
	 * @return	mixed
	 */
	public function project_delete($project_id)
	{
		if (empty($project_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('project.delete', array('project_id' => $project_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Project List
	 * 
	 * Returns a list of projects in alphabetical order. Staff IDs for staff 
	 * members who are assigned to a project will only appear for admins and 
	 * project managers.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/projects/#project.list
	 * @param	int $client_id
	 * @param	int $task_id
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function project_list($client_id = NULL, $task_id = NULL, $page = NULL, $per_page = NULL)
	{
		return $this->request('project.list', array('client_id' => $client_id, 'task_id' => $task_id, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Recurring Profile
	 * 
	 * Create a new recurring profile. The method arguments are nearly identical 
	 * to invoice.create, but include four new fields.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/recurring/#recurring.create
	 * @param	int $client_id
	 * @param	string $frequency is rate at which to generate invoices - can be one of 'weekly', '2 weeks', '4 weeks', 'monthly', '2 months', '3 months', '6 months', 'yearly', '2 years'
	 * @param	array $recurring
	 * @return	mixed
	 */
	public function recurring_create($client_id, $frequency, $recurring = array())
	{
		if (empty($client_id) || empty($frequency) || !is_array($recurring))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$recurring['client_id'] = $client_id;
		$recurring['frequency'] = $frequency;
		
		return $this->request('recurring.create', array('recurring' => $recurring));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Recurring Profile
	 * 
	 * Update an existing recurring profile. Only supplied fields will be changed.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/recurring/#recurring.update
	 * @param	int $recurring_id
	 * @param	array $recurring
	 * @return	mixed
	 */
	public function recurring_update($recurring_id, $recurring = array())
	{
		if (empty($recurring_id) || !is_array($recurring))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		$recurring['recurring_id'] = $recurring_id;
		
		return $this->request('recurring.update', array('recurring' => $recurring));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Recurring Profile
	 * 
	 * Return the details of an existing recurring profile.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/recurring/#recurring.get
	 * @param	int $recurring_id
	 * @return	mixed
	 */
	public function recurring_get($recurring_id)
	{
		if (empty($recurring_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('recurring.get', array('recurring_id' => $recurring_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Recurring Profile
	 * 
	 * Delete a recurring profile. Once deleted, it will no longer generate invoices.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/recurring/#recurring.delete
	 * @param	int $recurring_id
	 * @return	mixed
	 */
	public function recurring_delete($recurring_id)
	{
		if (empty($recurring_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('recurring.delete', array('recurring_id' => $recurring_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Recurring Profile List
	 * 
	 * Returns a list of recurring profile summaries. Results are ordered by 
	 * descending recurring_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/recurring/#recurring.list
	 * @param	int $client_id
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function recurring_list($client_id = NULL, $page = NULL, $per_page = NULL)
	{
		return $this->request('recurring.list', array('client_id' => $client_id, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Staff
	 * 
	 * Return the complete staff details associated with the given staff_id.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/staff/#staff.get
	 * @param	int $staff_id
	 * @return	mixed
	 */
	public function staff_get($staff_id)
	{
		if (empty($staff_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('staff.get', array('staff_id' => $staff_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Staff List
	 * 
	 * Returns a list of staff.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/staff/#staff.list
	 * @return	mixed
	 */
	public function staff_list()
	{
		return $this->request('staff.list');
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Task
	 * 
	 * Create a new task.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/tasks/#task.create
	 * @param	string $name
	 * @param	bool $billable
	 * @param	float $rate
	 * @param	string $description
	 * @return	mixed
	 */
	public function task_create($name, $billable = NULL, $rate = NULL, $description = NULL)
	{
		if (empty($name))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('task.create', array('task' => array('name' => $name, 'billable' => $billable, 'rate' => $rate, 'description' => $description)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Task
	 * 
	 * Update an existing task.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/tasks/#task.update
	 * @param	int $item_id
	 * @param	string $name
	 * @param	bool $billable
	 * @param	float $rate
	 * @param	string $description
	 * @return	mixed
	 */
	public function task_update($task_id, $name = NULL, $billable = NULL, $rate = NULL, $description = NULL)
	{
		if (empty($task_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('task.update', array('task' => array('task_id' => $task_id, 'name' => $name, 'billable' => $billable, 'rate' => $rate, 'description' => $description)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Task
	 * 
	 * Retrieve an existing task.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/tasks/#task.get
	 * @param	int $task_id
	 * @return	mixed
	 */
	public function task_get($task_id)
	{
		if (empty($task_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('task.get', array('task_id' => $task_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Task
	 * 
	 * Delete an existing task.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/tasks/#task.delete
	 * @param	int $task_id
	 * @return	mixed
	 */
	public function task_delete($task_id)
	{
		if (empty($task_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('task.delete', array('task_id' => $task_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Task List
	 * 
	 * Returns a list of tasks in alphabetical order.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/tasks/#task.list
	 * @param	int $project_id
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function task_list($project_id = NULL, $page = NULL, $per_page = NULL)
	{
		return $this->request('task.list', array('project_id' => $project_id, 'page' => $page, 'per_page' => $per_page));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Create Time Entry
	 * 
	 * Create a new timesheet entry.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/time_entries/#time_entry.create
	 * @param	int $project_id
	 * @param	int $task_id
	 * @param	float $hours
	 * @param	string $notes
	 * @param	int|string $date
	 * @return	mixed
	 */
	public function time_entry_create($project_id, $task_id, $hours = NULL, $notes = NULL, $date = NULL)
	{
		if (empty($project_id) || empty($task_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		if (is_numeric($date))
		{
			$date = $this->get_datetime($date, 1);
		}
		
		return $this->request('time_entry.create', array('time_entry' => array('project_id' => $project_id, 'task_id' => $task_id, 'hours' => $hours, 'notes' => $notes, 'date' => $date)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Update Time Entry
	 * 
	 * Update an existing time_entry.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/time_entries/#time_entry.update
	 * @param	int $time_entry_id
	 * @param	int $project_id
	 * @param	int $task_id
	 * @param	float $hours
	 * @param	string $notes
	 * @param	int|string $date
	 * @return	mixed
	 */
	public function time_entry_update($time_entry_id, $project_id = NULL, $task_id = NULL, $hours = NULL, $notes = NULL, $date = NULL)
	{
		if (empty($time_entry_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		if (is_numeric($date))
		{
			$date = $this->get_datetime($date, 1);
		}
		
		return $this->request('time_entry.update', array('time_entry' => array('time_entry_id' => $time_entry_id, 'project_id' => $project_id, 'task_id' => $task_id, 'hours' => $hours, 'notes' => $notes, 'date' => $date)));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Get Time Entry
	 * 
	 * Retrieve a single time_entry record.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/time_entries/#time_entry.get
	 * @param	int $time_entry_id
	 * @return	mixed
	 */
	public function time_entry_get($time_entry_id)
	{
		if (empty($time_entry_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('time_entry.get', array('time_entry_id' => $time_entry_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Delete Time Entry
	 * 
	 * Delete an existing time_entry. This action is not recoverable.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/time_entries/#time_entry.delete
	 * @param	int $time_entry_id
	 * @return	mixed
	 */
	public function time_entry_delete($time_entry_id)
	{
		if (empty($time_entry_id))
		{
			$this->_error(TRUE, __METHOD__ . ' - ' . $this->CI->lang->line('freshbooks_api_params_error'), '%2$s');
			return FALSE;
		}
		
		return $this->request('time_entry.delete', array('time_entry_id' => $time_entry_id));
	}
	
	// --------------------------------------------------------------------------
	
	/**
	 * Time Entry List
	 * 
	 * Returns a list of timesheet entries in ordered according to date.
	 * 
	 * @access	public
	 * @link	http://developers.freshbooks.com/api/view/time_entries/#time_entry.list
	 * @param	int $project_id
	 * @param	int $task_id
	 * @param	int|string $date_from
	 * @param	int|string $date_to
	 * @param	int $page
	 * @param	int $per_page
	 * @return	mixed
	 */
	public function time_entry_list($project_id = NULL, $task_id = NULL, $date_from = NULL, $date_to = NULL, $page = NULL, $per_page = NULL)
	{
		if (is_numeric($date_from))
		{
			$date_from = $this->get_datetime($date_from);
		}
		
		if (is_numeric($date_to))
		{
			$date_to = $this->get_datetime($date_to);
		}
		
		return $this->request('time_entry.list', array('project_id' => $project_id, 'task_id' => $task_id, 'date_from' => $date_from, 'date_to' => $date_to, 'page' => $page, 'per_page' => $per_page));
	}
}

/* End of file Freshbooks_API.php */
/* Location: ./system/application/libraries/Freshbooks_API.php */