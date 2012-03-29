<?php
if(!defined('ABSPATH'))
{
	die('What are you doing?');
}

class UpYun {
	//currently ,max filesize allowed by Upyuh form API is 100MiB
	const FORM_API_MAX_CONTENT_LENGTH = 104857600;
	const VERION        = 'ihacklog_20120328';
	private $available_api_servers = array(
			'v1.api.upyun.com',
			'v2.api.upyun.com',
			'v3.api.upyun.com',
			'v0.api.upyun.com',
			);
	private $bucketname;
	private $username;
	private $password;
	private $api_domain = 'v0.api.upyun.com';
	private $content_md5 = null;
	private $file_secret = null;
	private $form_api_secret = '';
	private $form_api_content_max_length = 100;
	private $form_api_allowed_ext = 'jpg,jpeg,gif,png,doc,pdf,zip,rar,tar.gz,tar.bz2,7z';
	private $form_api_timeout = 300;
	public $timeout     = 30;
	public $debug       = false;
	public $errors;
	public static $http;
	
	/**
	 * 初始化 UpYun 存储接口
	 * $bucketname 空间名称
	 * $username 操作员名称
	 * $password 密码
	 * 
	 * @param $args unknown_type
	 *       	 return UpYun object
	 */
	public function __construct($args) {
		$new_args = array_merge ( 
			array (
				'api_domain' => $this->api_domain, 
				'bucketname' => '', 
				'username' => '', 
				'password' => '',
				'form_api_secret' => '',
				'timeout' => $this->timeout,
				'form_api_content_max_length'=> $this->form_api_content_max_length,
				'form_api_allowed_ext'=> $this->form_api_allowed_ext,
				'form_api_timeout'=> $this->form_api_timeout,
					), $args );
		
		$this->bucketname = $new_args ['bucketname'];
		$this->username = $new_args ['username'];
		$this->password = md5 ( $new_args ['password'] );
		$this->form_api_secret = $new_args['form_api_secret'];
		$this->set_form_api_content_max_length($new_args['form_api_content_max_length']);
		$this->form_api_allowed_ext = $new_args['form_api_allowed_ext'];
		$this->form_api_timeout = $new_args['form_api_timeout'];
		$this->set_timeout ( $new_args ['timeout'] );
		$this->set_api_domain ( $new_args ['api_domain'] );
		$this->errors = new WP_Error();
		$this->set_debug ( FALSE );
		$this->check_param ();
	}
	
	/**
	 * check the needed param
	 */
	public function check_param() 
	{
		if (empty ( $this->api_domain )) 
		{
			$this->errors->add ( 'empty_api_domain', __ ( 'api_domain is required' ) );
		}
		
		if (empty ( $this->bucketname )) 
		{
			$this->errors->add ( 'empty_bucketname', __ ( 'bucketname is required' ) );
		}
		
		if (empty ( $this->username )) 
		{
			$this->errors->add ( 'empty_username', __ ( 'username is required' ) );
		}
		if (empty ( $this->password )) 
		{
			$this->errors->add ( 'empty_password', __ ( 'password is required' ) );
		}
		
		if ($this->form_api_content_max_length > self::FORM_API_MAX_CONTENT_LENGTH ) 
		{
			$this->errors->add ( 'form_api_content_length_invalid', __ ( 'form API content length invalid.' ) );
		}		
	}
	
	/**
	 * 切换 API 接口的域名
	 * 
	 * @param $domain {默认
	 *       	 v0.api.upyun.com 自动识别, v1.api.upyun.com 电信, v2.api.upyun.com
	 *        	联通, v3.api.upyun.com 移动}
	 *       	 return null;
	 */
	public function set_api_domain($domain) {
		$this->api_domain = $domain;
	}
	
	public function get_api_domain()
	{
		return $this->api_domain;
	}
	
	public function get_available_api_servers()
	{
		return $this->available_api_servers;
	}
	/**
	 * 设置连接超时时间
	 * 
	 * @param $time 秒
	 *       	 return null;
	 */
	public function set_timeout($time) {
		$this->timeout = $time;
	}
	
	/**
	* 设置待上传文件的 Content-MD5 值（如又拍云服务端收到的文件MD5值与用户设置的不一致，将回报 406 Not Acceptable 错误）
	* @param $str （文件 MD5 校验码）
	* return null;
	*/
	public function set_content_m5($str){
		$this->content_md5 = $str;
	}

	public function set_form_api_content_max_length($MiB)
	{
		$MiB = $MiB > 0 ? $MiB : 20;
		$MiB =  1024 * 1024 * $MiB;
		$this->form_api_content_max_length = (int) $MiB;
	}
	
	/**
	 * 连接签名方法
	 * 
	 * @param $method 请求方式
	 *       	 {GET, POST, PUT, DELETE}
	 * @return 签名字符串
	 */
	private function sign($method, $uri, $date, $length) {
		$sign = "{$method}&{$uri}&{$date}&{$length}&{$this->password}";
		return 'UpYun ' . $this->username . ':' . md5 ( $sign );
	}
	
	public function get_bucketname()
	{
		return $this->bucketname;
	}

	private function get_form_api_secret()
	{
		return $this->form_api_secret;
	}

	public function check_form_api_internal_error()
	{
		if( md5("{$_GET['code']}&{$_GET['message']}&{$_GET['url']}&{$_GET['time']}&") == $_GET['non-sign'] )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	public function check_form_api_return_param()
	{
		if(	md5("{$_GET['code']}&{$_GET['message']}&{$_GET['url']}&{$_GET['time']}&". $this->get_form_api_secret() ) == $_GET['sign'] )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	/**
	 * md5(policy+'&'+表单API验证密匙)
	 */
	public function get_form_api_signature($policy)
	{
		return md5($policy . '&' . $this->get_form_api_secret() );
	}

	public function build_policy($args)
	{
		$default = array(
			'expire' => $this->form_api_timeout, // 300 s
			'path' => '/{year}/{mon}/{random}{.suffix}', // full relative path
			'allow_file_ext' => $this->form_api_allowed_ext,
			'content_length_range' =>'0,' . $this->form_api_content_max_length, // 10MB( 10485760) 20MB ( 20971520 ),最大为100MB ( 104857600 )
			'return_url' => WP_PLUGIN_URL . '/hacklog-remote-attachment-upyun/upload.php',
			'notify_url' => '',
			);
		$args = array_merge($default,$args);
		$policydoc = array(
		"bucket" => $this->get_bucketname(), /// 空间名
		"expiration" => time() + $args['expire'], /// 该次授权过期时间
		"save-key" => $args['path'], /// 命名规则，/2011/12/随机.扩展名
		"allow-file-type" => $args['allow_file_ext'], /// 仅允许上传图片
		"content-length-range" => $args['content_length_range'] , /// 文件在 100K 以下
		"return-url" => $args['return_url'] , /// 回调地址
		"notify-url" =>$args['notify_url'] /// 异步回调地址
		);
		//var_dump($policydoc);
		$policy = base64_encode(json_encode($policydoc));  /// 注意 base64 编码后的 policy字符串中不包含换行符！
		return $policy;
	}
	
	/**
	 * 连接处理逻辑
	 * @todo 添加output_file支持( 荒野无灯 2012-02-06 )
	 * 
	 * @param process_url 请求方式
	 *       	 {GET, POST, PUT, DELETE}
	 * @param process_url地址       	
	 * @param process_url如果是
	 *       	 POST 上传文件，传递文件内容 或 文件IO数据流
	 * @param process_url_file 如果是
	 *       	 GET 下载文件，可传递文件IO数据流
	 * @return 请求返回字符串，失败返回 null （打开 debug 状态下遇到错误将中止程序执行）
	 */
	private function http_action($method, $uri, $datas, $output_file = null) 
	{
		$http = self::get_http_object();
		$r = array();
		if( is_wp_error($http))
		{
			$this->errors = $http;
			return NULL;
		}
		//check if the relative path string is started with a '/' ,since the upyun API need this
		if ('/' != substr ( $uri, 0, 1 )) {
			$uri = '/' . $uri;
		}
		
		$uri = "/{$this->bucketname}{$uri}";
		$process_url = "http://{$this->api_domain}{$uri}";
// 		$process_url = curl_init("http://{$this->api_domain}{$uri}");
		$headers = array ('Expect'=>'' );
		if ($datas == 'folder:true') 
		{
			$headers['folder'] = 'true';
			$datas = "\n";
		}
		$length = @strlen ( $datas );
		if ($method == 'PUT' || $method == 'POST') 
		{
			if ($this->auto_mkdir == true) {
				$headers ['mkdir'] = 'true';
		}
			$method = 'POST';
// 			curl_setopt ( $process_url, CURLOPT_POST, 1 );
			if ($datas && $datas != 'folder:true') {
				if (is_resource ( $datas )) {
					$handle = $datas;
					fseek ( $handle, 0, SEEK_END );
					$length = ftell ( $handle );
					fseek ( $handle, 0 );
					$headers ['Content-Length'] = $length;
					
					$datas = fread($handle,$length);
					fclose($handle);
					$r['body'] = $datas ;
					
				} else
					$r['body'] = $datas;
			}
		}
		
		$date = gmdate ( 'D, d M Y H:i:s \G\M\T' );
		$headers ['Date'] = $date;
		$headers ['Authorization'] = $this->sign ( $method, $uri, $date, $length );
		
// 		var_dump($headers);
		$r['headers'] = $headers;
		$r['timeout'] = $this->timeout ;
// 		if (is_resource ( $output_file ))
// 			curl_setopt ( $process_url, CURLOPT_FILE, $output_file );
		$http_method = strtolower($method);
		$valid_method = array('head','get','post','put','delete');
		if( !in_array($http_method, $valid_method ))
		{
			$this->errors->add ( 'invalid_call_of_http_method_error', __ ( 'invalid http method(valid method can be: '. implode(',',$valid_method) .')' ) );
			return NULL;
		}
		@set_time_limit(300);
		$response = $http->$http_method( $process_url,$r );
		$communication_error = 'Can not connect to remote server!';
		if( is_wp_error($response))
		{
			$this->errors->add ( 'connect', __ ( $communication_error  ) );
			return NULL;
		}
// 		var_dump($response);
		$response_code = $response['response']['code'];
		
		if ($response_code != 200) {
			if ($this->debug) {
				throw new Exception ( $response['response']['message'], $response_code );
			} else {
				//store the error.
				//var_dump($response);
				if( !empty( $response['body'] ))
				{
					//the body may like: string '<h1>401 Unauthorized</h1>error (user not exists.1)'
					$error_message = str_replace(array('<h1>','</h1>'),array('<strong>','</strong>. '),$response['body']);
				}
				elseif(!empty($response['response']['message']))
				{
					$error_message = $response['response']['message'];
				}
				else
				{
					$error_message = 'Can not connect to remote server!';
				}
				//var_dump($error_message);
				$this->errors->add ( 'upyun_authentication_error', __ ( $error_message ) );
			}
			return NULL;
		}
		return $response['body'];
	}
	
	/**
	 * 获取总体空间的占用信息
	 * @return 空间占用量，失败返回 null
	 */
	public function get_bucket_usage() {
		return $this->get_folder_usage ( '/' );
	}
	
	/**
	 * 获取某个子目录的占用信息
	 * 
	 * @param $path 目标路径
	 * @return 空间占用量，失败返回 null
	 */
	public function get_folder_usage($path) {
		$r = $this->http_action ( 'GET', "{$path}?usage", null );
		if ( $r == '')
			return null;
		return floatval ( $r );
	}
	
	/**
	 * 上传文件
	 * 
	 * @param $file 文件路径（包含文件名）       	
	 * @param $datas 文件内容
	 *       	 或 文件IO数据流
	 * @param $auto_mkdir=false 是否自动创建父级目录
	 *       	 return true or false
	 */
	public function write_file($file, $datas, $auto_mkdir = false) {
		$this->auto_mkdir = $auto_mkdir;
		$r = $this->http_action ( 'PUT', $file, $datas );
		return ! is_null ( $r );
	}
	
	/**
	 * 读取文件
	 * 
	 * @param $file 文件路径（包含文件名）       	
	 * @param $output_file 可传递文件IO数据流（默认为
	 *       	 null，结果返回文件内容，如设置文件数据流，将返回 true or false）
	 *       	 return 文件内容 或 null
	 */
	public function read_file($file, $output_file = null) {
		return $this->http_action ( 'GET', $file, null, $output_file );
	}
	
	/**
	 * 读取目录列表
	 * 
	 * @param $path 目录路径
	 * @return array 数组 或 null
	 */
	public function read_dir($path) {
		$r = $this->http_action ( 'GET', $path, null );
		if (is_null ( $r ))
			return null;
		$rs = explode ( "\n", $r );
		$returns = array ();
		foreach ( $rs as $r ) {
			$r = trim ( $r );
			$l = new stdclass ();
			list ( $l->name, $l->type, $l->size, $l->time ) = explode ( "\t", $r );
			if (! empty ( $l->time )) {
				$l->type = ($l->type == 'N' ? 'file' : 'folder');
				$l->size = intval ( $l->size );
				$l->time = intval ( $l->time );
				$returns [] = $l;
			}
		}
		return $returns;
	}
	
	/**
	 * 删除文件
	 * 
	 * @param $file 文件路径（包含文件名）
	 * @return true or false
	 */
	public function delete_file($file) {
		$r = $this->http_action ( 'DELETE', $file, null );
		return ! is_null ( $r );
	}
	
	/**
	 * 创建目录
	 * 
	 * @param $path 目录路径       	
	 * @param $auto_mkdir=false 是否自动创建父级目录
	 *       	 return true or false
	 */
	public function mkdir($path, $auto_mkdir = false) {
		$this->auto_mkdir = $auto_mkdir;
		$r = $this->http_action ( 'PUT', $path, 'folder:true' );
		return ! is_null ( $r );
	}
	
	/**
	 * 删除目录
	 * 
	 * @param $path 目录路径
	 * @return true or false
	 */
	public function rmdir($dir) {
		$r = $this->http_action ( 'DELETE', $dir, null );
		return ! is_null ( $r );
	}
	
	////////////////////////compatible//////////////////////////
	
	/**
	 * 
	 * @param bool $debug 是否调试
	 */
	public function set_debug($debug) {
		$this->debug = ( bool ) $debug;
	}
	
	/**
	 * check if we can connect to the server correctly
	 * since Upyun does not have the API,here I just check if we can 
	 * get the total bucket space usage.
	 * @return bool
	 */
	public function check_connection_and_authentication() {
		if( gethostbyname($this->api_domain ) == $this->api_domain )
		{
			$this->errors->add( 'connect', __('Failed to connect to remote server!Could not resolve the hostname.') );
			return FALSE;
		}
		//return null if failed. otherwise it may be null string '' .
		$result = $this->http_action ( 'GET', "/", null );
		if ( is_null($result) ) 
		{
			//if there is NO upyun_authentication_error , set the default error.
			if( !$this->errors->get_error_message('upyun_authentication_error') )
			{
				$this->errors->add( 'connect', __ ( 'Could not communicate with upyun server!' ) );
			}
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/**
	 * fake function ,does nothing,since the upload API allows auto make dir.
	 */
	public function is_dir($dir) {
		return TRUE;
	}
	
	/**
	 * check if a file is exists.
	 * @param string $file the full file path relative to the bucket main dir. 
	 */
	public function is_file($file) {
		$file_content = $this->read_file ( $file );
		if (! empty ( $file_content )) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * get the remote file size
	 * @param string $file the full file path relative to the bucket main dir.
	 */
	public function size($file) {
		$file_content = $this->read_file ( $file );
		return strlen ( $file_content );
	}
	
	/**
	 * the same as is_file.used by hacklogra_upyun::decrease_filesize_used()
	 * @param unknown_type $file
	 */
	public function exists($file) {
		return $this->is_file ( $file );
	}
	
	/**
	 * for uploading file to remote server.used by class hacklogra_upyun
	 * @param string $file the full file path relative to the bucket main dir.
	 * @param unknown_type $data
	 * @param int $perm ,this param is not used by upyun server...
	 */
	public function put_contents($file, $data, $perm = 0744) {
		return $this->write_file ( $file, $data, TRUE );
	}
	
	/**
	 * for deleting file on remote server.used by class hacklogra_upyun
	 * @param string $file the full file path relative to the bucket main dir.
	 * @param unknown_type $notused this param is not used by upyun server...
	 * @param unknown_type $another_notused this param is not used by upyun server...
	 */
	public function delete($file, $notused = '', $another_notused = '') {
		return $this->delete_file ( $file );
	}
	
	static function &get_http_object() {
		if ( is_null(self::$http) )
		{
			self::$http = new hacklog_http();
		}
		return self::$http;
	}

}//end class UpYun




class hacklog_http extends WP_Http
{
	public function _get_first_available_transport( $args, $url = null ) {
		$request_order = array( 'curl', 'streams', 'fsockopen' );
	
		// Loop over each transport on each HTTP request looking for one which will serve this request's needs
		foreach ( $request_order as $transport ) {
			$class = 'hacklog_http_' . $transport;
	
			// Check to see if this transport is a possibility, calls the transport statically
			if ( !call_user_func( array( $class, 'test' ), $args, $url ) )
				continue;
	
			return $class;
		}
	
		return false;
	}
	
	function put($url, $args = array()) {
		$defaults = array('method' => 'PUT');
		$r = wp_parse_args( $args, $defaults );
		return $this->request($url, $r);
	}
	
	function delete($url, $args = array())
	{
		$defaults = array('method' => 'DELETE');
		$r = wp_parse_args( $args, $defaults );
		return $this->request($url, $r);
	}
	
}//end class hacklog_http

class hacklog_http_fsockopen extends WP_Http_Fsockopen
{
	
}

class hacklog_http_streams extends WP_Http_Streams
{
	
}

class hacklog_http_curl extends WP_Http_Curl
{
	/**
	 * private method can not be called from child class,so, I just copied it from the parent class.
	 * Grab the headers of the cURL request
	 *
	 * Each header is sent individually to this callback, so we append to the $header property for temporary storage
	 *
	 * @since 3.2.0
	 * @access private
	 * @return int
	 */
	private function stream_headers( $handle, $headers ) {
		$this->headers .= $headers;
		return strlen( $headers );
	}
	
	/**
	 * Send a HTTP request to a URI using cURL extension.
	 * added DELETE method.(荒野无灯 2012-02-06)
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string $url
	 * @param str|array $args Optional. Override the defaults.
	 * @return array 'headers', 'body', 'response', 'cookies' and 'filename' keys.
	 */
	function request($url, $args = array()) {
		$defaults = array(
				'method' => 'GET', 'timeout' => 5,
				'redirection' => 5, 'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(), 'body' => null, 'cookies' => array()
		);
	
		$r = wp_parse_args( $args, $defaults );
	
		if ( isset($r['headers']['User-Agent']) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset($r['headers']['User-Agent']);
		} else if ( isset($r['headers']['user-agent']) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset($r['headers']['user-agent']);
		}
	
		// Construct Cookie: header if any cookies are set.
		WP_Http::buildCookieHeader( $r );
	
		$handle = curl_init();
	
		// cURL offers really easy proxy support.
		$proxy = new WP_HTTP_Proxy();
	
		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) {
	
			curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $handle, CURLOPT_PROXY, $proxy->host() );
			curl_setopt( $handle, CURLOPT_PROXYPORT, $proxy->port() );
	
			if ( $proxy->use_authentication() ) {
				curl_setopt( $handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY );
				curl_setopt( $handle, CURLOPT_PROXYUSERPWD, $proxy->authentication() );
			}
		}
	
		$is_local = isset($args['local']) && $args['local'];
		$ssl_verify = isset($args['sslverify']) && $args['sslverify'];
		if ( $is_local )
			$ssl_verify = apply_filters('https_local_ssl_verify', $ssl_verify);
		elseif ( ! $is_local )
		$ssl_verify = apply_filters('https_ssl_verify', $ssl_verify);
	
	
		// CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT expect integers.  Have to use ceil since
		// a value of 0 will allow an unlimited timeout.
		$timeout = (int) ceil( $r['timeout'] );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );
	
		curl_setopt( $handle, CURLOPT_URL, $url);
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, ( $ssl_verify === true ) ? 2 : false );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, $ssl_verify );
		curl_setopt( $handle, CURLOPT_USERAGENT, $r['user-agent'] );
		curl_setopt( $handle, CURLOPT_MAXREDIRS, $r['redirection'] );
	
		switch ( $r['method'] ) {
			case 'HEAD':
				curl_setopt( $handle, CURLOPT_NOBODY, true );
				break;
			case 'POST':
				curl_setopt( $handle, CURLOPT_POST, true );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			case 'PUT':
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'PUT' );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			case 'DELETE':
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'DELETE' );
				curl_setopt( $handle, CURLOPT_NOBODY, true );
				break;					
		}
	
		if ( true === $r['blocking'] )
			curl_setopt( $handle, CURLOPT_HEADERFUNCTION, array( &$this, 'stream_headers' ) );
	
		curl_setopt( $handle, CURLOPT_HEADER, false );
	
		// If streaming to a file open a file handle, and setup our curl streaming handler
		if ( $r['stream'] ) {
			if ( ! WP_DEBUG )
				$stream_handle = @fopen( $r['filename'], 'w+' );
			else
				$stream_handle = fopen( $r['filename'], 'w+' );
			if ( ! $stream_handle )
				return new WP_Error( 'http_request_failed', sprintf( __( 'Could not open handle for fopen() to %s' ), $r['filename'] ) );
			curl_setopt( $handle, CURLOPT_FILE, $stream_handle );
		}
	
		// The option doesn't work with safe mode or when open_basedir is set.
		if ( !ini_get('safe_mode') && !ini_get('open_basedir') && 0 !== $r['_redirection'] )
			curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
	
		if ( !empty( $r['headers'] ) ) {
			// cURL expects full header strings in each element
			$headers = array();
			foreach ( $r['headers'] as $name => $value ) {
				$headers[] = "{$name}: $value";
			}
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}
	
		if ( $r['httpversion'] == '1.0' )
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
		else
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
	
		// Cookies are not handled by the HTTP API currently. Allow for plugin authors to handle it
		// themselves... Although, it is somewhat pointless without some reference.
		do_action_ref_array( 'http_api_curl', array(&$handle) );
	
		// We don't need to return the body, so don't. Just execute request and return.
		if ( ! $r['blocking'] ) {
			curl_exec( $handle );
			curl_close( $handle );
			return array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
		}
	
		$theResponse = curl_exec( $handle );
		$theBody = '';
		$theHeaders = WP_Http::processHeaders( $this->headers );
	
		if ( strlen($theResponse) > 0 && ! is_bool( $theResponse ) ) // is_bool: when using $args['stream'], curl_exec will return (bool)true
			$theBody = $theResponse;
	
		// If no response, and It's not a HEAD request with valid headers returned
		if ( 0 == strlen($theResponse) && ('HEAD' != $args['method'] || empty($this->headers)) ) {
			if ( $curl_error = curl_error($handle) )
				return new WP_Error('http_request_failed', $curl_error);
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array(301, 302) ) )
				return new WP_Error('http_request_failed', __('Too many redirects.'));
		}
	
		$this->headers = '';
	
		$response = array();
		$response['code'] = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		$response['message'] = get_status_header_desc($response['code']);
	
		curl_close( $handle );
	
		if ( $r['stream'] )
			fclose( $stream_handle );
	
		// See #11305 - When running under safe mode, redirection is disabled above. Handle it manually.
		if ( ! empty( $theHeaders['headers']['location'] ) && ( ini_get( 'safe_mode' ) || ini_get( 'open_basedir' ) ) && 0 !== $r['_redirection'] ) {
			if ( $r['redirection']-- > 0 ) {
				return $this->request( $theHeaders['headers']['location'], $r );
			} else {
				return new WP_Error( 'http_request_failed', __( 'Too many redirects.' ) );
			}
		}
	
		if ( true === $r['decompress'] && true === WP_Http_Encoding::should_decode($theHeaders['headers']) )
			$theBody = WP_Http_Encoding::decompress( $theBody );
	
		return array( 'headers' => $theHeaders['headers'], 'body' => $theBody, 'response' => $response, 'cookies' => $theHeaders['cookies'], 'filename' => $r['filename'] );
	}
	
}//end class hacklog_http_curl
