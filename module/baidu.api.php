<?php


class WB_MagicPost_Baidu_Api
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


    public function get_token()
    {
        $token = get_option('wb_magicpost_baidu_api_token', null);
        do {
            if (!$token || !is_array($token) || empty($token)) {
                break;
            }
            $time = current_time('U');
            $expire = $token['time'] + $token['expires_in'];
            if ($time > $expire) {
                break;
            }
            return $token['access_token'];
        } while (0);

        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
        ];
        $keys = $this->cnf['baidu'];

        $api = 'https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials&client_id=' . $keys['key'] . '&client_secret=' . $keys['secret'];
        $http = wp_remote_post($api, $param);
        if (is_wp_error($http)) {
            $this->txt_log(_x('BaiduAPI获取token失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($http);
        if (empty($body)) {
            $this->txt_log(_x('BaiduAPI获取token失败。响应为空', 'log', WB_MAGICPOST_TD));
            return false;
        }
        $token = json_decode($body, true);

        if (isset($token['error'])) {
            $this->txt_log(_x('BaiduAPI获取token失败。Err:', 'log', WB_MAGICPOST_TD) . $token['error'] . ',' . $token['error_description']);
            return false;
        }
        $token['time'] = current_time('U');

        update_option('wb_magicpost_baidu_api_token', $token, false);

        return $token['access_token'];
    }

    public function doc_translate_result($doc_id)
    {
        $this->txt_log(_x('文档查询:', 'log', WB_MAGICPOST_TD) . $doc_id);
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $api = 'https://aip.baidubce.com/rpc/2.0/mt/v2/doc-translation/query?access_token=' . $token;
        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'id' => $doc_id,
            ]),
        ];

        $http = wp_remote_post($api, $param);
        if (is_wp_error($http)) {
            $this->txt_log(_x('百度文档翻译查询结果失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($http);

        if (empty($body)) {
            $this->txt_log(_x('百度文档翻译查询失败。响应为空', 'log', WB_MAGICPOST_TD));
            return false;
        }

        $result = json_decode($body, true);

        //
        if (empty($result)) {
            $this->txt_log(_x('百度文档翻译查询失败。响应解析错误', 'log', WB_MAGICPOST_TD));
            return false;
        }
        if (isset($result['error_code'])) {
            $this->txt_log(_x('百度文档翻译查询错误,', 'log', WB_MAGICPOST_TD) . $result['error_code'] . ',' . $result['error_msg']);
            return false;
        }
        $this->txt_log(print_r($result, true));
        return $result;
    }

    public function doc_translate($filename, $text)
    {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $text = str_replace("\r", "", $text);
        $api = 'https://aip.baidubce.com/rpc/2.0/mt/v2/doc-translation/create?access_token=' . $token;
        $param = [
            'timeout' => 5,
            'sslverify' => false,
            'headers' => [
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'from' => $this->source,
                'to' => $this->target,
                'input' => [
                    'format' => 'html',
                    'content' => base64_encode('<!DOCTYPE html><html><body>' . $text . '</body></html>'),
                    'filename' => $filename,
                ],
            ]),
        ];

        //$this->txt_log(print_r($param,true));

        $http = wp_remote_post($api, $param);
        if (is_wp_error($http)) {
            $this->txt_log(_x('百度文档翻译提交文档失败。错误信息:', 'log', WB_MAGICPOST_TD));
            $this->txt_log($http->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($http);

        if (empty($body)) {
            $this->txt_log(_x('百度文档翻译失败。响应为空', 'log', WB_MAGICPOST_TD));
            return false;
        }

        $result = json_decode($body, true);

        //
        if (empty($result)) {
            $this->txt_log(_x('百度文档翻译失败。响应解析错误', 'log', WB_MAGICPOST_TD));
            return false;
        }
        if (isset($result['error_code'])) {
            $this->txt_log(_x('百度文档翻译错误,', 'log', WB_MAGICPOST_TD) . $result['error_code'] . ',' . $result['error_msg']);
            return false;
        }

        return $result;
    }

    public function translate(array $text, array $fields, $post = null)
    {
        $txt = [];
        foreach ($fields as $k => $f) {
            if ($f == 'post_title') {
                $txt[] = '<div id="magicpost-title">' . $text[$k] . '</div>';
                continue;
            }
            $txt[] = $text[$k];
        }
        $content = implode('', $txt);
        $filename = $post ? $post->ID . '.html' : time() . '-' . wp_rand(1, 10000) . '.html';

        return $this->doc_translate($filename, $content);
    }

    public function set_translate_result($fields, $result, $post)
    {
        $ret = ['code' => 0, 'desc' => 'success'];

        if (!isset($result['result'])) {
            $ret['code'] = 1;
            $ret['desc'] = 'empty data1';
            return $ret;
        }
        $data = $result['result'];
        if (!isset($data['id']) || empty($data['id'])) {
            $ret['code'] = 2;
            $ret['desc'] = 'empty data2';
            return $ret;
        }

        update_post_meta($post->ID, 'wbmpbdfydocid', ['id' => $data['id'], 'fields' => $fields]);

        /*$max_time = get_option('magicpost_baidu_doc_last_time',0);
        $time = current_time('U', 1);
        if(!$max_time || $max_time < $time){
            $max_time = $time;
        }

        $max_time += 60;//60s
        wp_schedule_single_event($max_time, 'magic_post_baidu_translate_post', array($post->ID));

        update_option('magicpost_baidu_doc_last_time',$max_time,false);*/


        return $ret;
    }

    public function txt_log($msg)
    {
        $this->errors[] = $msg;
        //clog($msg);
        if ($this->debug) {
            error_log(current_time('mysql') . " $msg \n", 3, MAGICPOST_ROOT . '/baidu.log');
        }
    }
}
