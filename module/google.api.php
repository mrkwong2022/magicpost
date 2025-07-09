<?php


class WB_MagicPost_Google_Api
{

    public $debug = false;
    public $errors = [];

    public $key = '';
    public $source = '';
    public $target = '';
    public $cnf = [];
    public $post = null;


    public function __construct() {}

    public function get_error()
    {
        return array_pop($this->errors);
    }


    /**
     * @param string $text
     * @param string $api
     * @return string
     * @throws Exception
     */
    private function _web_translate(string $text, string $api)
    {
        $fields = array(
            'sl' => $this->source,
            'tl' => $this->target,
            'q' => $text
        );

        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'body' => $fields,
            'user-agent' => 'AndroidTranslate/5.3.0.RC02.130475354-53000263 5.1 phone TRANSLATE_OPM5_TEST_1',
        ];

        $http = wp_remote_post($api, $param);
        if (is_wp_error($http)) {
            //print_r([$http->get_error_message()]);
            $this->txt_log(_x('Google翻译失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            throw new Exception(esc_html($http->get_error_message()));
        }
        $body = wp_remote_retrieve_body($http);

        $code = wp_remote_retrieve_response_code($http);
        if ($code !== 200) {
            $message = _x('Google翻译失败。HTTP CODE :', 'log', WB_MAGICPOST_TD) . $code;
            $this->txt_log($message);
            throw new Exception(esc_html($message));
        }

        $result = json_decode($body, true);

        if (empty($result) || !is_array($result) || !isset($result['sentences'])) {
            $message = _x('Google翻译失败。Parse result fail', 'log', WB_MAGICPOST_TD);
            $this->txt_log($message);
            throw new Exception(esc_html($message));
        }

        $trans = [];
        foreach ($result['sentences'] as $r) {
            if (!isset($r['trans'])) continue;
            $trans[] = $r['trans'];
        }
        return implode('', $trans);
    }

    public function web_translate(array $text)
    {
        $api_host = 'translate.google.com';
        if ($this->cnf['google2']['proxy'] == 'wbolt') {
            $api_host = 'translate.google.picpapa.com';
        }

        $api = 'https://' . $api_host . '/translate_a/single?client=at&dt=t&dt=ld&dt=qca&dt=rm&dt=bd&dj=1&hl=es-ES&ie=UTF-8&oe=UTF-8&inputm=2&otf=2&iid=1dd3b944-fa62-4b55-b330-74909a99969e';
        $trans = [];
        try {
            foreach ($text as $txt) {
                $trans[] = ['translatedText' => $this->_web_translate($txt, $api)];
            }
        } catch (Exception $e) {
            $this->txt_log($e->getMessage());
            return false;
        }

        return ['data' => ['translations' => $trans]];
    }

    public function api_translate(array $text)
    {
        $api_host = 'translation.googleapis.picpapa.com'; //translation.googleapis.com
        $api = 'https://' . $api_host . '/language/translate/v2?key=' . $this->key;
        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'q' => $text,
                'target' => $this->target,
            ]),
        ];

        $http = wp_remote_post($api, $param);
        if (is_wp_error($http)) {
            //print_r([$http->get_error_message()]);
            $this->txt_log(_x('Google翻译失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($http);

        if (empty($body)) {
            $this->txt_log(_x('Google翻译失败。响应为空', 'log', WB_MAGICPOST_TD));
            return false;
        }
        //$this->txt_log($body);
        $content_type = wp_remote_retrieve_header($http, 'content-type');
        $data = [];
        //$this->txt_log($content_type);
        if (preg_match('#application/json#i', $content_type)) {
            $data = json_decode($body, true);
        } else if (preg_match('#application/x-www-form-urlencoded#i', $content_type)) {
            $data = [];
            parse_str($body, $data);
        }
        if (empty($data)) {
            $this->txt_log(_x('Google翻译失败。数据解析为空', 'log', WB_MAGICPOST_TD));
            return false;
        }
        if (isset($data['error'])) {
            $this->txt_log(_x('Google翻译失败,原因:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($data['error']['message']);
            return false;
        }
        return $data;
    }

    public function translate(array $text, array $fields, $post = null)
    {
        if ($this->cnf['api'] == 'google2') {
            return $this->web_translate($text);
        }
        return $this->api_translate($text);
    }

    public function set_translate_result($fields, $result, $post)
    {
        global $wpdb;
        $ret = ['code' => 0, 'desc' => 'success'];

        if (!isset($result['data'])) {
            $ret['code'] = 1;
            $ret['desc'] = 'empty data';
            return $ret;
        }
        $data = $result['data'];
        if (!isset($data['translations']) || !is_array($data['translations'])) {
            $ret['code'] = 2;
            $ret['desc'] = 'empty data';
            return $ret;
        }

        $translations = $data['translations'];
        //$fields = ['post_title','post_content'];
        $up = [];
        foreach ($translations as $k => $v) {
            if (isset($fields[$k])) {
                $field = $fields[$k];
                $up[$field] = $v['translatedText'];
            }
        }
        if ($up) {
            $up['ID'] = $post->ID;
            wp_update_post($up);
        }

        return $ret;
    }

    public function txt_log($msg)
    {
        $this->errors[] = $msg;
        //clog($msg);
        if ($this->debug) {
            error_log(current_time('mysql') . " $msg \n", 3, MAGICPOST_ROOT . '/google.log');
        }
    }
}
