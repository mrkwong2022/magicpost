<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?>

<div id="J_MPDLCont" class="magicpost-cont-wp">
  <div class="magicpost-cont-inner">
    <div class="magicpost-cont-hd">
      <svg class="wb-icon-magicpost wbsico-magicpost-local">
        <use xlink:href="#wbsico-magicpost-local"></use>
      </svg> <span><?php _ex('相关文件下载地址', 'front', WB_MAGICPOST_TD); ?></span>
    </div>

    <div class="magicpost-cont-bd">
      <?php if ($need_pay && !$is_buy) :

        /**
         * 付费
         */
        echo $pay_tips_content;

      else :
        if ($need_login && !$is_login) { //if login 
          $login_url = '<a class="link" href="' . esc_url(wp_login_url(get_permalink())) . '">' . __('登录', 'front', WB_MAGICPOST_TD) . '</a>';
      ?>
          <div class="wb-tips"><?php printf(_x('该资源需登录后下载，去%s', 'front, %s登录link', WB_MAGICPOST_TD), $login_url); ?></div>

        <?php } elseif ($need_comment && !$is_comment) { //else if need comment 
          $comment_url = '<a class="link" href="#commentform">' . __('发表评论', 'front', WB_MAGICPOST_TD) . '</a>';
        ?>
          <div class="wb-tips">*<?php printf(_x('该资源需回复评论后下载，马上去%s', 'front, %s发表评论link', WB_MAGICPOST_TD), $comment_url); ?></div>

        <?php } else { //else if login 
        ?>
          <input class="with-psw" style="z-index: 0; opacity: 0; position: absolute; width:20px;" id="WBDL_PSW">

          <?php foreach ($dl_info as $k => $v) :
            $icon = isset($v['icon']) ? $v['icon'] : $k;
          ?>
            <a class="magicpost-dl-btn j-wbdlbtn-magicpost" data-rid="<?php echo esc_attr($k); ?>" data-pid="<?php echo esc_attr($post_id); ?>">
              <svg class="wb-icon-magicpost wbsico-magicpost-<?php echo esc_attr($icon); ?>">
                <use xlink:href="#wbsico-magicpost-<?php echo esc_attr($icon); ?>"></use>
              </svg><span><?php echo esc_html($v['name']); ?></span>
            </a>
      <?php endforeach;
        } //end if login 
      endif; //end if pay 
      ?>
    </div>

    <div class="magicpost-cont-ft">
      <?php echo $remark_info ? $remark_info : __('&copy;下载资源版权归作者所有；本站所有资源均来源于网络，仅供学习使用，请支持正版！', 'front', WB_MAGICPOST_TD); ?>
    </div>
  </div>
</div>