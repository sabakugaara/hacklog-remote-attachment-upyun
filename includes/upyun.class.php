<?php
if(!defined('ABSPATH'))
{
	die('What are you doing?');
}

class UpYun {
	//currently ,max filesize allowed by Upyuh form API is 100MiB
	const FORM_API_MAX_CONTENT_LENGTH = 104857600;
	const VERION        = 'ihacklog_20120328';
    const TOKEN_NAME = '_upt';
	private $available_api_servers = array(
			'v1.api.upyun.com' => '电信',
			'v2.api.upyun.com' => '联通网通',
			'v3.api.upyun.com' => '移动铁通',
			'v0.api.upyun.com' => '自动判断',
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
    private $anti_leech_token = '';
    //default anti-leech token timeout is 10 min
    private $anti_leech_timeout = 600;
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
                'anti_leech_token'=> $this->anti_leech_token,
                'anti_leech_timeout'=> $this->anti_leech_timeout,
					), $args );

		$this->bucketname = $new_args ['bucketname'];
		$this->username = $new_args ['username'];
		$this->password = md5 ( $new_args ['password'] );
		$this->form_api_secret = $new_args['form_api_secret'];
		$this->set_form_api_content_max_length($new_args['form_api_content_max_length']);
		$this->form_api_allowed_ext = $new_args['form_api_allowed_ext'];
		$this->form_api_timeout = $new_args['form_api_timeout'];
        $this->anti_leech_token = $new_args['anti_leech_token'];
        $this->anti_leech_timeout = $new_args['anti_leech_timeout'];
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
			'return_url' => plugins_url('upload.php', HACKLOG_RA_UPYUN_LOADER),
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
     * ge_anti_leech_token_sign
     *签名格式：MD5(密匙&过期时间&URI){中间8位}+(过期时间)
     * 发送cookie或通过get传递
     *过期时间格式: UNIX TIME
     * @param string $uri 文件路径，必须以/开头
     * @return void
     */
    public function get_anti_leech_token_sign($uri = '/')
    {
        $uri = '/' . ltrim($uri,'/');
        $end_time = time() + $this->anti_leech_timeout;
        $token_sign =md5($this->anti_leech_token . '&' .$end_time.'&' . $uri );
        $sign = substr($token_sign, 12,8).$end_time;
        return $sign;
    }


    /**
     * set_anti_leech_token_sign_url
     *
     * @param string $url
     * @return void
     */
    public function set_anti_leech_token_sign_uri($uri = '/')
    {
        $uri = ltrim($uri,'/');
        return $uri . '?' . self::TOKEN_NAME .'='. $this->get_anti_leech_token_sign($uri);
    }

    public function is_url_token_signed($url = '')
    {
    	if(strpos($url, self::TOKEN_NAME) > 0 )
    	{
    		return TRUE;
    	}
    	else
    	{
    		return FALSE;
    	}
    }

    /**
     * set_anti_leech_token_sign_cookie
     *
     * @param string $url
     * @return void
     */
    public function set_anti_leech_token_sign_cookie($uri='/',$cookie_path='/',$cookie_domain='')
    {
        $uri = ltrim($uri,'/');
        setcookie( self::TOKEN_NAME ,$this->get_anti_leech_token_sign($uri),time() + $this->anti_leech_timeout ,$cookie_path,$cookie_domain);
    }

	/**
	 * 连接处理逻辑g
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
	function put($url, $args = array()) {
		$defaults = array('method' => 'PUT');
		$r = wp_parse_args( $args, $defaults );
		return $this->request($url, $r);
	}

	function delete($url, $args = array())
	{
		$defaults = array('method' => 'DELETE', 'body' => null);
		$r = wp_parse_args( $args, $defaults );
		return $this->request($url, $r);
	}

}//end class hacklog_http
