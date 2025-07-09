<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

class WB_MagicPost_Move extends WB_MagicPost_Base
{

    public function __construct()
    {

        if (is_admin()) {
            add_action('wp_ajax_magicpost', array($this, 'magicpost_ajax'));
        }
    }


    public function magicpost_ajax()
    {
        global $wp_taxonomies, $wp_post_types;

        $op = sanitize_text_field(self::param('op'));
        if (!$op) {
            return;
        }
        $arrow = [
            'move_cnf',
            'move_query',
            'move_cat',
            'move_tag',
            'move_del_tag',
            'move_update_tag',
            'move_new_tag',
            'move_tag_cnf',
            'move_tag_query',
            'move_tag_post_query',
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
            case 'move_cnf':
                $ret = array('code' => 0, 'desc' => 'success');
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
                $cat_type = [];
                foreach ($post_types as $t => $n) {
                    $tax = get_object_taxonomies([$t]);
                    foreach ($tax as $tx) {
                        $o = get_taxonomy($tx);
                        if (!$o) {
                            continue;
                        }
                        if (preg_match('#categor#', $o->meta_box_cb)) {
                            $cat_tax[$t] = $tx;
                            $cat_type[$t] = $n;
                            break;
                        }
                    }
                }

                $cat_list = [];
                $cat_table = [];

                $tree = [];
                foreach ($cat_tax as $post => $tax) {
                    $cat_list[$post] = $this->get_category($tax);
                    $cat_table[$post] = $this->category_table($cat_list[$post], 0);
                    //$tree[$post] = wp_dropdown_categories( ['echo'=>0,'taxonomy'=>$tax,'hierarchical'=>1] );
                }

                $ret['cnf'] = [
                    'post_type' => $cat_type ? $cat_type : ['post' => _x('文章', 'post type', WB_MAGICPOST_TD)],
                    'cat_tax' => $cat_tax,
                    //'tree' => $tree,
                    'cat_list' => $cat_list,
                    'cat_table' => $cat_table,
                ];
                self::ajax_resp($ret);
                break;

            case 'move_query':
                // $ret = array('code' => 0, 'desc' => 'success');
                $db = self::db();
                $q = $this->sanitize_text(self::param('q', []));
                $post_type = ($q['post_type'] ?? 'post');
                $query_child = ($q['query_child'] ?? 'lv1');
                if (!$post_type) {
                    $post_type = 'post';
                }
                $cat = intval($q['cat'] ?? 0);
                $keyword = ($q['keyword'] ?? '');
                $tax = sanitize_text_field(self::param('tax', 'category'));

                $where = array(
                    $db->prepare("a.post_type = %s", $post_type),
                );
                $post_status = ($q['post_status'] ?? '');
                if ($post_status) {
                    $where[] = $db->prepare("a.post_status=%s", $post_status);
                } else {
                    $where[] = "a.post_status IN('publish','draft','pending','future')";
                }



                //get_post_statuses()
                if ($cat) {
                    $cat_id = [];
                    if ($query_child == 'lvn') {
                        $child = get_term_children($cat, $tax);
                        if ($child) {
                            $cat_id = $child;
                        }
                    }

                    array_push($cat_id, $cat);
                    $term_id = implode(',', wp_parse_id_list($cat_id));
                    $where[] = " EXISTS( 
                        SELECT t3.object_id FROM $db->term_taxonomy t2,$db->term_relationships t3
                         WHERE t2.term_id IN($term_id) AND t2.term_taxonomy_id=t3.term_taxonomy_id AND t3.object_id= a.ID
                         )";
                }

                if ($keyword) {
                    $where[] = $db->prepare('a.post_title LIKE %s', '%' . $keyword . '%');
                }


                $num = absint(self::param('num', 20));
                if (!$num) {
                    $num = 20;
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


                $sql = "SELECT SQL_CALC_FOUND_ROWS a.ID,a.post_title,a.post_type,a.post_status,a.post_date 
                        FROM $db->posts a WHERE $where ORDER BY a.post_date ASC LIMIT  $offset,$num"; //
                set_transient('magicpost_move_query', preg_replace('#LIMIT\s+\d+,\d+#', '', $sql));
                $list = $db->get_results($sql);
                $total = $db->get_var("SELECT FOUND_ROWS()");
                foreach ($list as $r) {
                    $r->post_link = get_permalink($r->ID);

                    $cat = [];
                    if ($tax) {
                        $cat_list = wp_get_object_terms($r->ID, $tax, []);
                        if ($cat_list) foreach ($cat_list as $c) {
                            $cat[] = $c->name;
                        }
                    }
                    $r->post_category = $cat;
                }


                $ret = array(
                    'q' => $q,
                    //'sql'=>$sql,
                    'tx' => $tax,
                    'num' => $num,
                    'total' => $total,
                    'code' => 0,
                    'data' => $list,
                );
                self::ajax_resp($ret);
                break;

            case 'move_cat':
                $ret = array('code' => 0, 'desc' => 'success');
                do {

                    if (!get_option('wb_magicpost_ver', 0)) {
                        break;
                    }
                    $post_id = sanitize_text_field(self::param('post'));
                    $cat = sanitize_text_field(self::param('cat'));
                    $tax = sanitize_text_field(self::param('tax', 'category'));
                    $type = intval(self::param('type', 0));
                    if (!$tax || !$cat || !$post_id || !$type) {
                        break;
                    }
                    $db = self::db();
                    $id_list = wp_parse_id_list(explode(',', $post_id));
                    $category = wp_parse_id_list(explode(',', $cat));

                    $batch_op = sanitize_text_field(self::param('batch_op'));
                    if ($batch_op == 'all') {
                        $query_sql = get_transient('magicpost_move_query');
                        if ($query_sql) {
                            $post_list = $db->get_results($query_sql);
                            $post_id_list = [];
                            if ($post_list) foreach ($post_list as $r) {
                                $post_id_list[] = $r->ID;
                            }
                            if ($post_id_list) {
                                $id_list = $post_id_list;
                            }
                        }
                    }

                    if ($type == 1) { //重置文章分类为目标分类
                        foreach ($id_list as $ID) {
                            wp_set_post_terms($ID, $category, $tax, false);
                        }
                    } else if ($type == 2) { //添加目标分类至文章
                        foreach ($id_list as $ID) {
                            wp_set_post_terms($ID, $category, $tax, true);
                        }
                    } else if ($type == 3) { //将文章从目标分类移出
                        foreach ($id_list as $ID) {
                            wp_remove_object_terms($ID, $category, $tax);
                        }
                    }
                } while (0);
                self::ajax_resp($ret);
                break;
            case 'move_tag':
                $ret = array('code' => 0, 'desc' => 'success');
                do {
                    if (!get_option('wb_magicpost_ver', 0)) {
                        break;
                    }
                    $source = sanitize_text_field(self::param('source'));
                    $target = sanitize_text_field(self::param('target'));
                    $tax = sanitize_text_field(self::param('tax', 'post_tag'));
                    $post_type = sanitize_text_field(self::param('post_type', 'post'));
                    $type = intval(self::param('type', 0));
                    if (!$tax || !$source || !$target) {
                        break;
                    }
                    $db = self::db();
                    $source_list = wp_parse_id_list(explode(',', $source));
                    $target_list = wp_parse_id_list(explode(',', $target));

                    $batch_op = sanitize_text_field(self::param('batch_op'));
                    if ($batch_op == 'all' && in_array($type, [3, 4])) {
                        $query_sql = get_transient('magicpost_move_tag_post_query');
                        if ($query_sql) {
                            $post_list = $db->get_results($query_sql);
                            $post_id_list = [];
                            if ($post_list) foreach ($post_list as $r) {
                                $post_id_list[] = $r->ID;
                            }
                            if ($post_id_list) {
                                $source_list = $post_id_list;
                            }
                        }
                    }

                    if ($type == 1) { //迁移原标签文章至目标标签
                        $list = query_posts(['post_type' => $post_type, 'tag__in' => $source_list]);
                        foreach ($list as $r) {
                            wp_set_post_terms($r->ID, $target_list, $tax, true);
                            wp_remove_object_terms($r->ID, $source_list, $tax);
                        }
                    } else if ($type == 2) { //原标签文章增加目标标签
                        $list = query_posts(['post_type' => $post_type, 'tag__in' => $source_list]);
                        foreach ($list as $r) {
                            wp_set_post_terms($r->ID, $target_list, $tax, true);
                        }
                    } else if ($type == 3) { //移除文章中的目标标签
                        foreach ($source_list as $ID) {
                            if (!$ID) continue;
                            wp_remove_object_terms($ID, $target_list, $tax);
                        }
                    } else if ($type == 4) { //新增目标标签至文章
                        foreach ($source_list as $ID) {
                            if (!$ID) continue;
                            wp_set_post_terms($ID, $target_list, $tax, true);
                        }
                    }
                } while (0);
                self::ajax_resp($ret);
                break;
            case 'move_del_tag':
                $ret = array('code' => 0, 'desc' => 'success');
                do {
                    $term_id = intval(self::param('id', 0));
                    $tax = sanitize_text_field(self::param('tax', 'post_tag'));
                    if (!$tax || !$term_id) {
                        break;
                    }
                    wp_delete_term($term_id, $tax);
                } while (0);
                self::ajax_resp($ret);

                break;
            case 'move_update_tag':
                $ret = array('code' => 0, 'desc' => 'success');
                do {

                    $term_id = intval(self::param('id', 0));
                    $name = sanitize_text_field(self::param('name'));
                    $slug = sanitize_text_field(self::param('slug'));
                    $tax = sanitize_text_field(self::param('tax', 'post_tag'));
                    if (!$tax || !$term_id || !$name || !$slug) {
                        break;
                    }
                    $result = wp_update_term($term_id, $tax, ['name' => $name, 'slug' => $slug]);
                    if (is_wp_error($result)) {
                        $ret['code'] = 1;
                        $ret['desc'] = $result->get_error_message();
                        break;
                    }
                } while (0);
                self::ajax_resp($ret);
                break;
            case 'move_new_tag':
                $ret = array('code' => 0, 'desc' => 'success');
                do {

                    $name = sanitize_text_field(self::param('name', ''));
                    $slug = sanitize_text_field(self::param('slug', ''));
                    $tax = sanitize_text_field(self::param('tax', 'post_tag'));
                    if (!$tax || !$name) {
                        $ret['code'] = 1;
                        $ret['desc'] = _x('参数不能为空', 'log', WB_MAGICPOST_TD);
                        break;
                    }
                    $result = wp_insert_term($name, $tax, ['slug' => $slug]);
                    if (is_wp_error($result)) {
                        $ret['code'] = 1;
                        $ret['desc'] = $result->get_error_message();
                        break;
                    }

                    $ret['data'] = $result;
                } while (0);
                self::ajax_resp($ret);
                break;

            case 'move_tag_cnf':
                $ret = array('code' => 0, 'desc' => 'success');
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
                $cat_type = [];
                foreach ($post_types as $t => $n) {
                    $tax = get_object_taxonomies([$t]);
                    foreach ($tax as $tx) {
                        $o = get_taxonomy($tx);
                        if (!$o) {
                            continue;
                        }
                        //print_r($o->meta_box_cb);
                        if (preg_match('#tags#', $o->meta_box_cb)) {
                            $cat_tax[$t] = $tx;
                            $cat_type[$t] = $n;
                            break;
                        }
                    }
                }

                $cat_tax2 = [];
                $cat_type2 = [];
                foreach ($post_types as $t => $n) {
                    $tax = get_object_taxonomies([$t]);
                    foreach ($tax as $tx) {
                        $o = get_taxonomy($tx);
                        if (!$o) {
                            continue;
                        }
                        //print_r($o->meta_box_cb);
                        if (preg_match('#categor#', $o->meta_box_cb)) {
                            $cat_tax2[$t] = $tx;
                            $cat_type2[$t] = $n;
                            break;
                        }
                    }
                }


                $cat_list = [];
                $cat_table = [];

                foreach ($cat_tax2 as $post => $tax) {
                    $cat_list[$post] = $this->get_category($tax);
                    $cat_table[$post] = $this->category_table($cat_list[$post], 0);
                }

                $ret['cnf'] = [
                    'post_type' => $cat_type ? $cat_type : ['post' => _x('文章', 'post type', WB_MAGICPOST_TD)],
                    'cat_tax' => $cat_tax,
                    'cat_tax2' => $cat_tax2,
                    'cat_list' => $cat_list,
                    'cat_table' => $cat_table,
                ];
                self::ajax_resp($ret);
                break;

            case 'move_tag_query':

                $ret = array('code' => 0, 'desc' => 'success');
                $pos = sanitize_text_field(self::param('pos', ''));
                $q = $this->sanitize_text(self::param('q', []));
                $post_type = trim($q['post_type'] ?? 'post');

                $keyword = trim($q['keyword'] ?? '');
                $tag_id = intval($q['tag_id'] ?? 0);
                $tax = trim(sanitize_text_field(self::param('tax', 'post_tag')));

                $param = ['taxonomy' => $tax, 'hide_empty' => false, 'pad_counts' => true, 'suppress_filter' => true];

                if ($tag_id) {
                    $param['include'] = $tag_id;
                }
                if ($keyword) {
                    $param['search'] = $keyword;
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


                $param['number'] = $num;
                $param['offset'] = $offset;



                $list = get_terms($param);
                $total = intval(self::param('total', 0));
                if ($page < 2) {
                    $total = wp_count_terms($param);
                }



                $ret = array(
                    'param' => $param,
                    'q' => $q,
                    'tx' => $tax,
                    'num' => $num,
                    'total' => $total,
                    'code' => 0,
                    'data' => $list,
                );
                self::ajax_resp($ret);
                break;
            case 'move_tag_post_query':

                $ret = array('code' => 0, 'desc' => 'success');
                $pos = sanitize_text_field(self::param('pos'));
                $q = $this->sanitize_text(self::param('q', []));
                $post_type = trim($q['post_type'] ?? 'post');
                if (!$post_type) {
                    $post_type = 'post';
                }
                $keyword = trim($q['keyword'] ?? '');
                $tag = trim($q['tag'] ?? '');
                $tag_id = intval($q['tag_id'] ?? 0);
                $tax = sanitize_text_field(self::param('tax', 'post_tag'));
                $tax2 = sanitize_text_field(self::param('tax2', 'category'));



                $db = self::db();

                $query_child = trim($q['query_child'] ?? 'lv1');
                $cat = intval($q['cat'] ?? 0);


                $where = array(
                    $db->prepare("a.post_type = %s", $post_type),
                );
                $post_status = trim(sanitize_text_field($q['post_status'] ?? ''));
                if ($post_status) {
                    $where[] = $db->prepare("a.post_status=%s", $post_status);
                } else {
                    $where[] = "a.post_status IN('publish','draft','pending','future')";
                }

                if ($cat) {
                    $cat_id = [];
                    if ($query_child == 'lvn') {
                        $child = get_term_children($cat, $tax2);
                        if ($child) {
                            $cat_id = $child;
                        }
                    }

                    array_push($cat_id, $cat);
                    $term_id = implode(',', wp_parse_id_list($cat_id));
                    $where[] = " EXISTS( 
                        SELECT t3.object_id FROM $db->term_taxonomy t2,$db->term_relationships t3
                         WHERE t2.term_id IN($term_id) AND t2.term_taxonomy_id=t3.term_taxonomy_id AND t3.object_id= a.ID
                         )";
                }

                if ($tag_id) {
                    $where[] = $db->prepare(" EXISTS( 
                        SELECT t3.object_id FROM $db->term_taxonomy t2,$db->term_relationships t3
                         WHERE t2.term_id = %d AND t2.term_taxonomy_id=t3.term_taxonomy_id AND t3.object_id= a.ID
                        AND t2.taxonomy = %s )", $tag_id, $tax);
                }

                if ($tag) {
                    $where[] = $db->prepare(" EXISTS( 
                        SELECT t3.object_id FROM $db->term_taxonomy t2,$db->term_relationships t3,$db->terms t1
                         WHERE t2.term_taxonomy_id=t3.term_taxonomy_id AND t3.object_id= a.ID AND t2.term_id=t1.term_id
                        AND t2.taxonomy = %s AND CONCAT_WS('',t1.name,t1.slug) LIKE %s  )", $tax, '%' . $tag . '%');
                }


                if ($keyword) {
                    $where[] = $db->prepare('a.post_title LIKE %s', '%' . $keyword . '%');
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


                $sql = "SELECT SQL_CALC_FOUND_ROWS a.ID,a.post_title,a.post_type,a.post_status,a.post_date 
                        FROM $db->posts a WHERE $where ORDER BY a.post_date ASC LIMIT  $offset,$num"; //
                set_transient('magicpost_move_tag_post_query', preg_replace('#LIMIT\s+\d+,\d+#', '', $sql));
                $list = $db->get_results($sql);
                $total = $db->get_var("SELECT FOUND_ROWS()");
                foreach ($list as $r) {
                    $cat = [];
                    if ($tax) {
                        $cat_list = wp_get_object_terms($r->ID, $tax, []);
                        if ($cat_list) foreach ($cat_list as $c) {
                            $cat[] = $c->name;
                        }
                    }
                    $r->post_category = $cat;
                }

                $ret = array(
                    'q' => $q,
                    //'sql'=>$sql,
                    'tx' => $tax,
                    'num' => $num,
                    'total' => $total,
                    'code' => 0,
                    'data' => $list,
                );
                self::ajax_resp($ret);
                break;
        }
    }

    public function category_table($list, $lv)
    {
        $new_list = [];
        foreach ($list as $r) {
            $r->lv = $lv;
            $new_list[] = $r;
            if ($r->child) {
                $new_list = array_merge($new_list, $this->category_table($r->child, $lv + 1));
            }
        }
        return $new_list;
    }

    public function get_category($tax)
    {
        $data_list = get_categories(['taxonomy' => $tax, 'hide_empty' => false, 'pad_counts' => true]);
        $category = [];
        if ($data_list) {
            $parent_list = [];
            foreach ($data_list as $r) {
                if (!isset($parent_list[$r->category_parent])) {
                    $parent_list[$r->category_parent] = [];
                }
                $parent_list[$r->category_parent][] = $r;
            }
            foreach ($parent_list[0] as $r) {
                $child = isset($parent_list[$r->cat_ID]) ? $parent_list[$r->cat_ID] : [];
                if ($child) {
                    $this->walk_category($child, $parent_list);
                }
                $r->child = $child;
                $category[] = $r;
            }
        }
        return $category;
    }

    public function walk_category($list, $parent_list)
    {
        foreach ($list as $r) {
            $child = isset($parent_list[$r->cat_ID]) ? $parent_list[$r->cat_ID] : [];
            if ($child) {
                $child = $this->walk_category($child, $parent_list);
            }
            $r->child = $child;
        }
        return $list;
    }

    public static function cnf($key = null, $default = null)
    {
        //['switch'=>1,'need_member'=>0,'display_count'=>0,'sticky_mode'=>0,'btn_align'=>0,'remark'=>''];
        static $_option = array();
        if (!$_option) {
            $_option = [];
            if (get_option('wb_magicpost_ver', 0)) {
                $_option = get_option('magicpost_move');
            }
            if (!$_option || !is_array($_option)) {
                $_option = [];
            }
            $default_conf = [
                'switch' => 0,
            ];
            foreach ($default_conf as $k => $v) {
                if (!isset($_option[$k])) $_option[$k] = $v;
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
        $opt = self::cnf();
        $opt['switch'] = $switch;
        update_option('magicpost_move', $opt);
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
}
