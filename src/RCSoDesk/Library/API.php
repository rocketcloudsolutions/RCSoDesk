<?php

namespace RCSoDesk\Library;

final class API {

    const URL_LOGIN     = 'https://www.odesk.com/login';
    const URL_AUTH      = 'https://www.odesk.com/services/api/auth';
    const URL_TOKENS    = 'https://www.odesk.com/api/auth/v1/keys/tokens.xml';
    const URL_FROBS     = 'https://www.odesk.com/api/auth/v1/keys/frobs.xml';
    const COOKIE_TOKEN  = 'odesk_api_token';

    static
    public $api_key     = null,
        $secret      = null;

    static
    private $api_token  = null,
        $mode       = 'web',
        $verify_ssl = FALSE, // option
        $cookie_file= './cookie.txt', // option
        $proxy      = null, // option
        $proxy_pwd  = null; //option

    /**
     * __construct
     *
     * @param   string  $secret     Secret key
     * @param   string  $api_key    Application key
     * @access  public
     */
    function __construct($secret, $api_key) {
        if (!$secret)
            throw new \Exception('You must define "secret key".');
        else
            self::$secret = (string) $secret;

        if (!$api_key)
            throw new \Exception('You must define "application key".');
        else
            self::$api_key = (string) $api_key;
    }

    /**
     * Set option
     *
     * @param   string  $option Option name
     * @param   mixed   $value  Option value
     * @access  public
     * @return  boolean
     */
    public static function option($option, $value) {
        $r = new \ReflectionClass('\\'.__CLASS__);
        try {
            $r->getProperty($option);
            self::$$option = $value;
            return TRUE;
        } catch (\ReflectionException $e) {
            return FALSE;
        }
    }

    /**
     * Auth process
     *
     * @param   string  $user   Auth user, for nonweb apps
     * @param   string  $pass   Auth pass, for nonweb apps
     * @access  public
     * @return  string
     */
    public function auth($user = null, $pass = null) {
        $frob = (isset($_GET['frob']) && !empty($_GET['frob']))
            ? $_GET['frob']
            : null;

        if (isset($_COOKIE[self::COOKIE_TOKEN]) && !empty($_COOKIE[self::COOKIE_TOKEN]))
            self::$api_token = $_COOKIE[self::COOKIE_TOKEN];

        if (self::$api_token == null && $frob == null) {
            // if frob first
            $api_sig = self::calc_api_sig(self::$secret, array());

            if (self::$mode === 'web') {
                // authorize web application via browser
                header('Location: ' . self::URL_AUTH . self::get_api_keys_uri($api_sig));
            } else if (self::$mode === 'nonweb') {
                // authorize nonweb application
                // 1. login
                self::send_request(self::URL_LOGIN . self::merge_params_to_uri(null, array('login' => $user, 'password' => $pass, 'action' => 'login'), FALSE), 'post');

                // 2. get frob
                $data = self::send_request(self::URL_FROBS . self::get_api_keys_uri($api_sig), 'post');
                $response = new \SimpleXMLElement($data['response']);
                $frob = (string) $response->frob;
                if (empty($frob))
                    throw new \Exception('Can not get frob, due to error: '.$response['error']);

                // 3. authorize
                $api_sig = self::calc_api_sig(self::$secret, array('frob' => $frob));
                self::send_request(self::URL_AUTH . self::merge_params_to_uri(self::get_api_keys_uri($api_sig), array('do' => 'agree', 'frob' => $frob)), 'post');

                // 4. get token
                self::$api_token = self::get_api_token($frob);
            }
        } else if (self::$api_token == null && $frob != null) {
            // get api token by frob
            self::$api_token = self::get_api_token($frob);
            setcookie(self::COOKIE_TOKEN, self::$api_token, time()+3600); // save for 1 hour
        } else {
            // api_token isset
        }

        return self::$api_token;
    }

    /**
     * Do GET request
     *
     * @param   string      $url    API URL
     * @param   array|null  $params Additional parameters
     * @access  public
     * @return  mixed
     */
    public function get_request($url, $params = array()) {
        return self::request('get', $url, $params);
    }

    /**
     * Do POST request
     *
     * @param   string      $url    API URL
     * @param   array|null  $params Additional parameters
     * @access  public
     * @return  mixed
     */
    public function post_request($url, $params = array()) {
        return self::request('post', $url, $params);
    }

    /**
     * Do PUT request
     *
     * @param   string      $url    API URL
     * @param   array|null  $params Additional parameters
     * @access  public
     * @return  mixed
     */
    public function put_request($url, $params = array()) {
        return self::request('put', $url, $params);
    }

    /**
     * Do DELETE request
     *
     * @param   string      $url    API URL
     * @param   array|null  $params Additional parameters
     * @access  public
     * @return  mixed
     */
    public function delete_request($url, $params = array()) {
        return self::request('delete', $url, $params);
    }

    /**
     * Do request
     *
     * @param   string  $type   Type of request
     * @param   string  $url    URL
     * @param   array   $params Parameters
     * @static
     * @access  public
     * @return  mixed
     */
    static public function request($type, $url, $params = array()) {
        $params['api_token'] = self::$api_token;

        switch ($type) {
            case 'put':
                $params['http_method'] = 'put';
                break;
            case 'delete':
                $params['http_method'] = 'delete';
                break;
        }

        $api_sig = self::calc_api_sig(self::$secret, $params);
        $url = $url . self::merge_params_to_uri(self::get_api_keys_uri($api_sig), $params);

        $data = self::send_request($url, $type);
        if ($data['error'] && !empty($data['error'])) {
            throw new \Exception('Can not execute request due to error: '. print_r($data,1));
        } else if (!isset($data['info']['http_code'])) {
            $d = print_r($data, true);
            throw new \Exception('API does not return anything or request could not be finished. Response: '.(empty($t) ? '&lt;EMPTY&gt;' : $t));
        } else if ($data['info']['http_code'] != 200) {
            throw new \Exception('API return code - '.$data['info']['http_code'].'. Can not create '.strtoupper($type).' request. HTTP ' . $data['info']['http_code'] ." returned.");
        } else {
            return $data['response'];
        }
    }

    /**
     * Return API's URI with signature and app key
     *
     * @param   string  $api_sig    Signature
     * @static
     * @access  private
     * @return  string
     */
    static private function get_api_keys_uri($api_sig) {
        return '?api_key=' . self::$api_key . '&api_sig=' . $api_sig;
    }

    /**
     * Return auth token
     *
     * @param   string  $frob   Auth frob
     * @static
     * @access  private
     * @return  mixed
     */
    static private function get_api_token($frob = null) {
        if (self::$api_token)
            return self::$api_token;

        if ($frob === null)
            self::auth();

        $params = array(
            'frob' => $frob
        );
        $api_sig = self::calc_api_sig(self::$secret, $params);

        $url = self::URL_TOKENS . self::merge_params_to_uri(self::get_api_keys_uri($api_sig), $params);

        $data = self::send_request($url, 'get');
        if (!isset($data['info']['http_code'])) {
            throw new \Exception('API does not return anything or request could not be finished.');
        } else if ($data['info']['http_code'] != 200) {
            throw new \Exception('API return code - '.$data['info']['http_code'].'. Can not get token.');
        } else {
            $response = new \SimpleXMLElement($data['response']);
            return (string) $response->token;
        }
    }

    /**
     * Send request via CURL
     *
     * @param   string  $url    URL to request
     * @param   string  $type   Type of request
     * @static
     * @access  private
     * @return  array
     */
    static private function send_request($url, $type = 'get') {
        $ch = curl_init();
        if ($type != 'get')
            list($url, $pdata) = explode('?', $url, 2);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if (self::$mode != 'web') {
            $headers[] = 'Connection: Keep-Alive';
            $headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING , 'gzip');
            // setup cookie
            self::set_cookie_file(self::$cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookie_file);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP oDeskAPI library client/1.0');
        if (preg_match('/^https:\/\//', $url) && !self::$verify_ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); //do not verify crt, if selfsigned
        }
        if ($type != 'get') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $pdata);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        if (self::$proxy) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($ch, CURLOPT_PROXY, self::$proxy);
        }
        if (self::$proxy_pwd) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, self::$proxy_pwd);
        }
        $response = curl_exec($ch);
        $data['response']= $response;
        $data['info']    = curl_getinfo($ch);
        $data['error']   = curl_error($ch);

        $header_size    = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $data['header'] = substr($response, 0, $header_size);
        $data['body']   = substr($response, $header_size);
        curl_close($ch);

        return $data;
    }

    private static function set_cookie_file($cookie_file) {
        if (!file_exists($cookie_file)) {
            if (!($fh = fopen($cookie_file, 'w')))
                throw new \Exception('Can not create cookie file, possible not enough permissions.');
            fclose($fh);
        }
    }

    /**
     * Merge parameters to URI
     *
     * @param   string  $uri        URI
     * @param   array   $params     Parameters
     * @param   boolean $encode     Whether to encode url params
     * @static
     * @access  private
     * @return  string
     */
    static private function merge_params_to_uri($uri, $params, $encode = true) {
        $uri = ($uri) ? $uri . '&' : '?';

        $uri .= http_build_query($params);

        return $uri;
    }

    /**
     * Normalize requested params, sort in alphabetical order
     *
     * @param   array   $params Array of requested params
     * @param   string  $rkey   Node key, used for rekursive calling
     * @static
     * @access  public
     * @return  void
     */
    static private function normalize_params($params, $rkey = '') {
        $line  = '';

        if (!is_array($params))
            return $line;

        ksort($params);
        foreach ($params as $k=>$v) {
            $line .= (is_array($v))
                ? self::normalize_params($v, $k)
                : $rkey.$k.urldecode($v);
        }

        return $line;
    }

    /**
     * Calculate API signature for public API
     *
     * @param   string  $app_secret Secret key
     * @param   array   $params     Array of requested params
     * @static
     * @access  public
     * @return  string
     */
    static private function calc_api_sig($app_secret, $params) {
        $params['api_key'] = self::$api_key;
        return md5($app_secret.self::normalize_params($params));
    }
}

