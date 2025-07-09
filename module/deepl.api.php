<?php


class WB_MagicPost_Deepl_Api
{

    public $debug = true;
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



    public function translate(array $text, array $fields, $post = null)
    {

        $keys = $this->cnf['deepl'] ?? ['apikey' => 'f0c82c1f-f708-4e82-9bb5-9806d727e8ad:fx', 'pro' => 0];

        $api = 'https://api-free.deepl.com';
        if (!empty($keys['pro'])) {
            $api = 'https://api.deepl.com';
        }

        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . ($keys['apikey'] ?? ''),
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'text' => $text,
                'source_lang' => $this->source,
                'target_lang' => $this->target,
                'tag_handling' => 'html'
            ]),
        ];

        $this->txt_log(print_r($param, true));

        $http = wp_remote_post($api . '/v2/translate', $param);
        if (is_wp_error($http)) {
            $this->txt_log(_x('DeepL翻译API请求失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($http);
        $code = wp_remote_retrieve_response_code($http);
        if ($code !== 200) {
            $response_msg = json_decode($body, true);
            $msg = '';
            if (!empty($response_msg['message'])) {
                $msg = '，' . $response_msg['message'];
            }
            $this->txt_log(_x('DeepL翻译失败。状态码：', 'log', WB_MAGICPOST_TD) . $code . $msg);

            return false;
        }


        if (empty($body)) {
            $this->txt_log(_x('DeepL翻译失败。响应为空', 'log', WB_MAGICPOST_TD));
            return false;
        }

        $result = json_decode($body, true);

        if (empty($result)) {
            $this->txt_log(_x('DeepL翻译失败。响应解析错误', 'log', WB_MAGICPOST_TD));
            return false;
        }
        return $result;
    }

    public function set_translate_result($fields, $result, $post)
    {
        $ret = ['code' => 0, 'desc' => 'success'];

        if (empty($result['translations'])) {
            $ret['code'] = 1;
            $ret['desc'] = 'empty data1';
            return $ret;
        }

        $up = [];
        foreach ($result['translations'] as $k => $v) {
            if (isset($fields[$k])) {
                $field = $fields[$k];
                $up[$field] = $v['text'];
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
            error_log(current_time('mysql') . " $msg \n", 3, MAGICPOST_ROOT . '/deepl.log');
        }
    }
}
