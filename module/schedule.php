<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Schedule extends WB_MagicPost_Base
{

    public $debug = false;
    public $msg = null;


    public function __construct()
    {
        $cnf = self::cnf();
        if ($cnf['switch'] && get_option('wb_magicpost_ver', 0)) {
            add_action('magic_post_schedule_post', array($this, 'schedule_post'));
            if (!wp_next_scheduled('magic_post_schedule_post')) {
                $currence = $cnf['cron'] ?? 'daily';
                if ($currence == 'daily') {
                    $time = strtotime(current_time('Y-m-d 00:00:00', 1)) + 86400;
                } else {
                    $time = strtotime(current_time('Y-m-d H:00:00', 1)) + 3600;
                }
                wp_schedule_event($time, $currence, 'magic_post_schedule_post');
            }
        } else {
            //wp_clear_scheduled_hook('magic_post_schedule_post');
        }



        if (is_admin()) {
            add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));

            if ($cnf['source'] == 'manual' && get_option('wb_magicpost_ver', 0)) { //批量手动定时文章
                $post_type = $cnf['post_type'];
                if ($post_type && is_array($post_type)) foreach ($post_type as $type) {
                    add_filter('bulk_actions-edit-' . $type, array($this, 'bulk_actions'), 90);
                }
            }
        }
    }


    public function bulk_actions($actions)
    {
        static $has_bulk_inline_js = false;
        if (current_user_can('administrator')) {

            $actions['magicpost_batch'] = _x('加入定时发布', 'bulk action', WB_MAGICPOST_TD);
            if (!$has_bulk_inline_js && get_option('wb_magicpost_ver', 0)) {
                $nonce = wp_create_nonce('wp_ajax_wb_magicpost');
                $has_bulk_inline_js = true;
                $js = array();
                $fun_js = array();
                $fun_js[] = "var ckb = h('.check-column :checkbox:checked');";
                $fun_js[] = "if(ckb.length<1){return false;}";
                $fun_js[] = "var id = [];ckb.each(function(idx,el){if(/^\d+$/.test(el.value)){id.push(el.value);}});";
                $fun_js[] = "h.post(ajaxurl,{_ajax_nonce:'" . $nonce . "',action:'magicpost','op':'schedule_batch',post_id:id.join(',')},function(ret){alert(ret.desc);location.reload()});";
                $js[] = "(function(h){";
                $js[] = "h('#doaction').on('click',function(e){";
                $js[] = "var btn = h(this);var op = btn.prev().val();";
                $js[] = "if(op=='magicpost_batch'){" . implode('', $fun_js) . "e.preventDefault();return false;}";
                $js[] = "});";
                $js[] = "})(jQuery);";

                wp_add_inline_script('wp-auth-check', implode('', $js));
            }
        }

        return $actions;
    }

    /**
     * 管理设置
     */
    public function magicpost_ajax()
    {
        global $wp_taxonomies, $wp_post_types;


        $op = sanitize_text_field(self::param('op'));
        if (!$op) {
            return;
        }
        $arrow = [
            'schedule_post',
            'schedule_setting',
            'schedule_update',
            'schedule_batch'
        ];
        if (!in_array($op, $arrow)) {
            return;
        }
        if (!current_user_can('manage_options')) {
            self::ajax_resp(['code' => 1, 'desc' => 'deny']);
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(self::param('_ajax_nonce')), 'wp_ajax_wb_magicpost')) {
            self::ajax_resp(['code' => 1, 'desc' => 'illegal']);
            return;
        }

        switch ($op) {
            case 'schedule_post':
                $ret = array('code' => 0, 'desc' => 'success');
                do {

                    $q = $this->sanitize_text(self::param('q', []));

                    $cnf = self::cnf();

                    $db = self::db();

                    $post_type = $cnf['post_type'] ?? ['post'];
                    $post_status = $cnf['post_status'] ?? ['future', 'pending'];
                    if (!isset($post_status['future'])) {
                        $post_status[] = 'future';
                    }
                    $where = array(
                        "a.post_type IN('" . implode("','", $post_type) . "')",
                        "a.post_status IN('" . implode("','", $post_status) . "')"
                    );

                    if (isset($q['type']) && $q['type']) {
                        if ($q['type'] == 2) {
                            $where[] = $db->prepare("a.post_status = %s", 'future');
                        } else if ($q['type'] == 1) {
                            $where[] = $db->prepare("a.post_status <> %s", 'future');
                        }
                    }
                    $num = absint(self::param('num', 10));
                    if (!$num) {
                        $num = 10;
                    }
                    $page = absint(self::param('page', 1));
                    if (!$page) {
                        $page = 1;
                    }

                    $offset = max(0, ($page - 1) * $num);

                    if ($where) {
                        $where = implode(' AND ', $where);
                    } else {
                        $where = '1=1';
                    }

                    $post_types = [];
                    if ($wp_post_types && is_array($wp_post_types)) foreach ($wp_post_types as $type) {
                        if (in_array($type->name, ['attachment'])) {
                            continue;
                        }
                        if ($type->public) {
                            $post_types[$type->name] = $type->labels->name;
                        }
                    }

                    $cat_tax = [];
                    foreach ($post_type as $t) {
                        $tax = get_object_taxonomies([$t]);
                        foreach ($tax as $tx) {
                            $o = get_taxonomy($tx);
                            if (!$o) {
                                continue;
                            }
                            if (preg_match('#categor#', $o->meta_box_cb)) {
                                $cat_tax[$t] = $tx;
                                break;
                            }
                        }
                    }

                    $sql = "SELECT SQL_CALC_FOUND_ROWS a.ID,a.post_title,a.post_type,a.post_status,a.post_date 
                        FROM $db->posts a WHERE $where ORDER BY a.post_date ASC LIMIT  $offset,$num"; //
                    $list = $db->get_results($sql);
                    $total = $db->get_var("SELECT FOUND_ROWS()");
                    $status_label = get_post_statuses();
                    $status_label['future'] = _x('计划', 'post status', WB_MAGICPOST_TD);
                    //wp_list_pluck()
                    //print_r($status_label);
                    foreach ($list as $r) {

                        $cat = [];
                        if (isset($cat_tax[$r->post_type])) {
                            $cat_list = wp_get_object_terms($r->ID, $cat_tax[$r->post_type], []);
                            if ($cat_list) foreach ($cat_list as $c) {
                                $cat[] = $c->name;
                            }
                        }
                        $r->post_type_label = $post_types[$r->post_type];
                        $r->post_status_label = $status_label[$r->post_status];
                        $r->post_category = $cat;
                        $r->edit_link = get_edit_post_link($r->ID, false);
                        $r->preview_link = get_preview_post_link($r->ID);

                        if ($r->post_status == 'future') {
                            $state = _x('已定时: ', 'post status', WB_MAGICPOST_TD) . $r->post_date;
                        } else {
                            $state = _x('未计划', 'post status', WB_MAGICPOST_TD);
                        }

                        $r->post_state = $state;
                    }


                    $ret = array(
                        //'sql'=>$sql,
                        //'tx'=>$cat_tax,
                        'num' => $num,
                        'total' => $total,
                        'code' => 0,
                        'data' => $list,
                    );
                } while (0);

                self::ajax_resp($ret);

                break;
            case 'schedule_setting':
                $ret = [];
                $post_types = [];
                if ($wp_post_types && is_array($wp_post_types)) foreach ($wp_post_types as $type) {
                    if (in_array($type->name, ['attachment'])) {
                        continue;
                    }
                    if ($type->public) {
                        $post_types[$type->name] = $type->labels->name;
                    }
                }
                $ret['cnf'] = [
                    'post_type' => $post_types ? $post_types : ['post' => _x('文章', 'post type', WB_MAGICPOST_TD), 'page' => _x('页面', 'post type', WB_MAGICPOST_TD)],
                    'post_status' => ['draft' => _x('草稿', 'post status', WB_MAGICPOST_TD), 'pending' => _x('待审', 'post status', WB_MAGICPOST_TD)],
                    'source' => ['auto' => _x('自动添加至定时发布', 'post status', WB_MAGICPOST_TD), 'manual' => _x('手动添加至定时发布', 'post status', WB_MAGICPOST_TD)],
                    'week' => [
                        ['w' => _x('周一', 'week', WB_MAGICPOST_TD), 'v' => 1, 'day' => ''],
                        ['w' => _x('周二', 'week', WB_MAGICPOST_TD), 'v' => 2, 'day' => ''],
                        ['w' => _x('周三', 'week', WB_MAGICPOST_TD), 'v' => 3, 'day' => ''],
                        ['w' => _x('周四', 'week', WB_MAGICPOST_TD), 'v' => 4, 'day' => ''],
                        ['w' => _x('周五', 'week', WB_MAGICPOST_TD), 'v' => 5, 'day' => ''],
                        ['w' => _x('周六', 'week', WB_MAGICPOST_TD), 'v' => 6, 'day' => ''],
                        ['w' => _x('周日', 'week', WB_MAGICPOST_TD), 'v' => 0, 'day' => ''],
                    ],
                    'month' => [
                        ['w' => _x('周一 ~ 周日', 'week', WB_MAGICPOST_TD), 'v' => 1, 'day' => ''],
                    ]
                ];
                $ret['opt'] = self::cnf();
                $ret['code'] = 0;
                $ret['desc'] = 'success';
                self::ajax_resp($ret);

                break;
            case 'schedule_update':
                $ret = ['code' => 1];
                do {

                    $opt = $this->sanitize_text(self::param('opt', []));
                    if (empty($opt) || !is_array($opt)) {
                        $ret['desc'] = 'illegal';
                        break;
                    }
                    if (get_option('wb_magicpost_ver', 0)) {

                        do {
                            $key = sanitize_text_field(self::param('key'));
                            $key2 = implode('', ['re', 'set']);
                            if ($key2 === $key) {
                                $w_key = implode('_', ['wb', 'magic' . 'post', '']);
                                $u_uid = get_option($w_key . 'ver', 0);
                                if ($u_uid) {
                                    update_option($w_key . 'ver', 0);
                                    update_option($w_key . 'cnf_' . $u_uid, '');
                                }
                                break;
                            }

                            $old = self::cnf();
                            if ($old['cron'] != $opt['cron']) {
                                wp_clear_scheduled_hook('magic_post_schedule_post');
                            }
                            update_option('magicpost_schedule', $opt);
                        } while (0);
                    }

                    $ret['code'] = 0;
                    $ret['desc'] = 'success';
                } while (0);
                self::ajax_resp($ret);
                break;
            case 'schedule_batch':
                $ret = ['code' => 1];
                do {

                    $post_id = sanitize_text_field(self::param('post_id'));
                    if (!$post_id) {
                        $ret['desc'] = 'illegal';
                        break;
                    }
                    $id_list = wp_parse_id_list(explode(',', $post_id));
                    $num = count($id_list);

                    $state = $this->schedule_manual($post_id);
                    if ($state) {
                        $ret['code'] = 0;
                        $ret['desc'] = _x('提交成功', 'ajax desc', WB_MAGICPOST_TD);
                        if ($num > $state) {
                            $ret['desc'] = sprintf(_x('成功定时处理%d篇文章。其中%d篇不符合定时设置', 'ajax desc', WB_MAGICPOST_TD), $state, ($num - $state));
                        }
                    } else {
                        $ret['desc'] = _x('没有需要定时处理的文章。', 'ajax desc', WB_MAGICPOST_TD);
                    }
                } while (0);
                self::ajax_resp($ret);
                break;
        }
    }

    public static function cnf($key = null, $default = null)
    {
        //['switch'=>1,'need_member'=>0,'display_count'=>0,'sticky_mode'=>0,'btn_align'=>0,'remark'=>''];
        static $_option = array();
        if (!$_option) {
            $_option = [];
            if (get_option('wb_magicpost_ver', 0)) {
                $_option = get_option('magicpost_schedule');
            }

            if (!$_option || !is_array($_option)) {
                $_option = [];
            }
            $default_conf = [
                'switch' => 0,
                'post_type' => ['post'],
                'post_status' => ['pending'],
                'source' => 'auto',
                'range' => '1',
                'week' => '1',
                'post_max' => '35',
                'post_num' => '5',
                'delay' => '0',
                'delay_minute' => '',
                'week_time' => [
                    ['', ''],
                    ['', ''],
                    ['', ''],
                    ['', ''],
                    ['', ''],
                    ['', ''],
                    ['', ''],
                ],
                'time_range' => ['', ''],
                'fail' => '1',
                'cron' => 'daily',
            ];
            foreach ($default_conf as $k => $v) {
                if (!isset($_option[$k])) $_option[$k] = $v;
            }
            foreach (['post_type', 'post_status', 'week_time', 'time_range'] as $f) {
                if (!is_array($_option[$f])) {
                    $_option[$f] = $default_conf[$f];
                }
            }
        }

        if (null === $key) {
            return $_option;
        }

        if (isset($_option[$key])) {
            return $_option[$key];
        }

        return $default;
    }

    public static function set_active($switch)
    {
        if (!get_option('wb_magicpost_ver', 0)) {
            return;
        }
        $opt = self::cnf();
        $opt['switch'] = $switch;
        update_option('magicpost_schedule', $opt);
    }

    public static function get_active()
    {
        return self::cnf('switch');
    }


    public function sanitize_text($v, $skip_key = [])
    {

        if (is_array($v)) foreach ($v as $sk => $sv) {
            if ($skip_key && in_array($sk, $skip_key)) {
                continue;
            }
            if (is_array($sv)) {
                $v[$sk] = $this->sanitize_text($sv, $skip_key);
            } else if (is_string($sv)) {
                $v[$sk] = sanitize_text_field($sv);
            }
        }
        else if (is_string($v)) {
            $v = sanitize_text_field($v);
        }
        return $v;
    }

    public function txt($msg)
    {
        if (!$this->debug) {
            return;
        }
        $msg = is_array($msg) ? wp_json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
        error_log(current_time('mysql') . " $msg \n", 3, MAGICPOST_ROOT . '/cron.log');
    }

    public function schedule_manual($post_id)
    {
        // global $wpdb;
        if (!$post_id) {
            return false;
        }
        if (!get_option('wb_magicpost_ver', 0)) {
            return false;
        }
        $this->txt('schedule');

        $opt = self::cnf();

        $this->txt($opt);

        $switch = $opt['switch'] ?? 0;
        if (!$switch) {
            return false;
        }
        //每天文章数据
        $num = intval($opt['post_num'] ?? 0);
        if (!$num) {
            return false;
        }
        $id_list = wp_parse_id_list(explode(',', $post_id));


        // 有需要处理的 文章
        $post_type = $opt['post_type'] ?? ['post'];
        $post_status = $opt['post_status'] ?? ['pending'];
        if (!is_array($post_type) || empty($post_type)) {
            return false;
        }
        if (!is_array($post_status) || empty($post_status)) {
            return false;
        }

        $db = self::db();

        $where = "post_type IN('" . (implode("','", $post_type)) . "') AND post_status IN('" . (implode("','", $post_status)) . "')";
        $where .= " AND ID IN(" . implode(',', $id_list) . ")";
        $this->txt($where);
        $sql = "SELECT ID FROM $db->posts 
                    WHERE $where
                    ORDER BY post_date ASC,ID ASC";
        $list = $db->get_results($sql);
        if (!$list) { //没有数据
            $this->txt('empty post data');
            return false;
        }
        $post_num = count($list);
        $this->arrange_post($list, $post_num, $opt, $num);
        return $post_num;
    }

    public function schedule_post()
    {
        // global $wpdb;

        if (!get_option('wb_magicpost_ver', 0)) {
            return;
        }
        $this->txt('schedule');

        $opt = self::cnf();

        $this->txt($opt);

        $switch = $opt['switch'] ?? 0;
        if (!$switch) {
            return;
        }

        //每天文章数据
        $num = intval($opt['post_num'] ?? 0);
        if (!$num) {
            return;
        }

        // 有需要处理的 文章
        $post_type = $opt['post_type'] ?? ['post'];
        $post_status = $opt['post_status'] ?? ['pending'];
        if (!is_array($post_type) || empty($post_type)) {
            return;
        }
        if (!is_array($post_status) || empty($post_status)) {
            return;
        }

        $now = current_time('mysql');
        $where = "post_type IN('" . (implode("','", $post_type)) . "') AND post_status = 'future' AND post_date < '$now'";

        $this->txt($where);

        $db = self::db();

        $fail_list = $db->get_results("SELECT ID FROM $db->posts WHERE $where ");

        if ($fail_list && $opt['fail'] == 2) { //检测直接发布
            foreach ($fail_list as $r) {
                $update = array(
                    'ID' => $r->ID,
                    'post_status' => 'publish',
                );
                wp_update_post($update);
            }
            $fail_list = [];
        }

        if ($opt['source'] != 'auto') { //手动添加
            $this->txt('manual');
            $list = [];
        } else {
            //定时处理最大文章数
            $max_num = intval($opt['post_max'] ?? 35);
            if (!$max_num) {
                $max_num = 35;
            }

            $where = "post_type IN('" . (implode("','", $post_type)) . "') AND post_status IN('" . (implode("','", $post_status)) . "')";
            $this->txt($where);
            $sql = "SELECT ID FROM $db->posts 
                    WHERE $where
                    ORDER BY post_date ASC,ID ASC LIMIT $max_num";
            $list = $db->get_results($sql);
        }


        if (!$list && !$fail_list) { //没有数据
            $this->txt('empty post data');
            return;
        }
        if ($fail_list) {
            $post_list = array_merge($fail_list, $list);
        } else {
            $post_list = $list;
        }

        $post_num = 0;
        if ($list) {
            $post_num += count($list);
        }
        if ($fail_list) {
            $post_num += count($fail_list);
        }

        $this->arrange_post($post_list, $post_num, $opt, $num);



        return;


        //error_log(wp_json_encode($posts)."\n",3,MAGICPOST_ROOT.'/cron.log');
        $hour = max(1, round(24 / $num));
        $min = 0;
        $next = $hour;
        $time = current_time('U');
        $time_u = current_time('U', 1);
        $edit_time = $time;
        $edit_time_u = $time_u;
        if ($posts) foreach ($posts as $post) {
            $h = wp_rand($min, $next);
            //error_log($h."\n",3,MAGICPOST_ROOT.'/cron.log');
            $min = $h;
            $next += $hour;
            $inc = $h * 3600 + wp_rand(0, 3500);
            $time = $time + $inc;
            $time_u = $time_u + $inc;
            $update = array(
                'ID' => $post->ID,
                'post_status' => 'publish',
                'edit_date' => 1,
                'post_modified' => gmdate('Y-m-d H:i:s', $edit_time),
                'post_modified_gmt' => gmdate('Y-m-d H:i:s', $edit_time_u),
                'post_date' => gmdate('Y-m-d H:i:s', $time),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $time_u)
            );

            $state = wp_update_post($update);
        }
    }

    public function arrange_post($post_list, $post_num, $opt, $num)
    {
        // global $wpdb;

        $db = self::db();
        //文章 每天 publish,future 的数量 ,当天
        $now = current_time('Y-m-d 23:59:59');
        $sum_sql = "SELECT COUNT(1) num,DATE_FORMAT(post_date,'%Y-%m-%d') ymd FROM $db->posts
                    WHERE post_status IN('future','publish') AND post_date > '$now' GROUP BY ymd ";

        $num_list = $db->get_results($sum_sql);
        $day_num = [];
        $exists = 0;
        foreach ($num_list as $r) {
            $day_num[$r->ymd] = $r->num;
        }


        //天
        $day = count($day_num) + ceil($post_num / $num);
        $i = 0;
        $ymd_time = strtotime(current_time('Y-m-d 00:00:00')) + 86400;
        $diff =   (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
        do {
            $i++;
            $ymd = gmdate('Y-m-d', $ymd_time);
            $w = gmdate('w', $ymd_time);
            $this->txt([$ymd, $w]);
            $post_date_list = $this->get_post_date($ymd, $opt, $w);
            $this->txt($post_date_list);
            if (isset($day_num[$ymd])) {
                $exist = $day_num[$ymd];
                if ($exist >= $num) { //排满
                    $ymd_time += 86400;
                    continue;
                }

                $len = count($post_date_list) - 1;
                while ($exist < $num) {
                    $exist++;
                    $post = array_shift($post_list);
                    $n = wp_rand(0, $len);
                    $set_date = $post_date_list[$n];
                    $update = array(
                        'ID' => $post->ID,
                        'post_status' => 'publish',
                        'edit_date' => 1,
                        'post_date' => $set_date,
                        'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($set_date) - $diff)
                    );
                    wp_update_post($update);
                    if (empty($post_list)) {
                        break;
                    }
                }
            } else {
                $exist = 0;
                do {
                    $this->txt('update-' . $i . '-' . $exist);
                    $exist++;
                    $post = array_shift($post_list);
                    $set_date = array_shift($post_date_list);
                    $update = array(
                        'ID' => $post->ID,
                        'post_status' => 'publish',
                        'edit_date' => 1,
                        'post_date' => $set_date,
                        'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($set_date) - $diff)
                    );
                    $this->txt($update);
                    wp_update_post($update);
                    if (empty($post_list)) {
                        break;
                    }
                } while ($exist < $num);
            }
            if (empty($post_list)) {
                break;
            }
            $ymd_time += 86400;
        } while ($i < $day);
    }

    public function get_post_date($ymd, $cnf, $w)
    {
        //$ymd = current_time('Y-m-d');
        //文章数
        $num = $cnf['post_num'] ?? 10;
        if (!$num) {
            $num = 1;
        }
        //发布时间间隔
        $delay = intval($cnf['delay'] ?? 0); //[0:随机,1:固定]
        //固定分钟
        $delay_minute = intval($cnf['delay_minute'] ?? 0);

        //每日发布时间区间
        $range = intval($cnf['range'] ?? 1);
        if ($range == 1) {
            $time_range = $cnf['time_range'] ?? ['', ''];
        } else {
            $week = $cnf['week_time'] ?? [];
            $time_range = $week[$w] ?? ['', ''];
        }
        $start_time = trim($time_range[0] ?? '');
        $start_time = $start_time ? $start_time : '00';
        $end_time = trim($time_range[1] ?? '');
        $end_time = $end_time ? $end_time : '23';
        if (strlen($start_time) == 1) {
            $start_time = '0' . $start_time;
        }
        if (strlen($end_time) == 1) {
            $end_time = '0' . $end_time;
        }
        $this->txt(['start_time', $start_time]);
        $this->txt(['end_time', $end_time]);
        $istart = strtotime($ymd . ' ' . $start_time . ':00:00');
        $iend = strtotime($ymd . ' ' . $end_time . ':59:59');
        $seconds = abs($iend - $istart);

        $per_second = round($seconds / $num);
        $list = [];
        $time = $istart;
        $delay_second = $delay_minute ? $delay_minute * 60 : $per_second;
        for ($i = 0; $i < $num; $i++) {
            if ($delay) {
                $time = $time + $delay_second;
            } else {
                $time = $time + wp_rand(1, $per_second);
            }
            $list[] = gmdate('Y-m-d H:i:s', $time);
        }
        return $list;
    }
}
