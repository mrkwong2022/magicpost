<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?>

<div style=" display:none;">
  <svg aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
    <defs>
      <symbol id="sico-upload" viewBox="0 0 16 13">
        <path d="M9 8v3H7V8H4l4-4 4 4H9zm4-2.9V5a5 5 0 0 0-5-5 4.9 4.9 0 0 0-4.9 4.3A4.4 4.4 0 0 0 0 8.5C0 11 2 13 4.5 13H12a4 4 0 0 0 1-7.9z" fill="#666" fill-rule="evenodd" />
      </symbol>
    </defs>
  </svg>
</div>

<div class="wbp-magicpost-meta-box wbs-meta-panel">
  <div class="selector-bar switch-bar">
    <label>
      <strong><?php _ex('下载功能', 'metabox, 开关', WB_MAGICPOST_TD); ?></strong>
      <input class="wb-switch" id="J_MPDL_SWITCH" type="checkbox" data-target="#J_MPDLMain" name="wb_dl_type" <?php echo $wb_dipp_switch ? ' checked' : ''; ?> value="1"></label>
  </div>

  <div class="wbsp-main" id="J_MPDLMain">
    <h3><strong><?php _ex('文件上传方式', 'metabox', WB_MAGICPOST_TD); ?></strong></h3>

    <?php foreach ($dlt_items_actived as $key => $slug) :
      $item_cnf = $dl_type_items_cnf[$slug];
      $tips = $item_cnf['meta_placeholder'] ?? '';
      $remark = $item_cnf['meta_remark'] ?? '';

      if ($slug == 'local'):
    ?>
        <label class="wb-post-sitting-item section-upload">
          <span class="wb-form-label"><?php echo esc_html($item_cnf['label']); ?></span>
          <div class="wbs-upload-box">
            <input class="wbs-input upload-input" type="text" placeholder="<?php echo esc_attr($tips); ?>" name="wb_down_local_url" value="<?php echo esc_attr($meta_value['wb_down_local_url']); ?>">
            <button type="button" class="wbs-btn wbs-upload-btn">
              <svg class="wb-icon sico-upload">
                <use xlink:href="#sico-upload"></use>
              </svg><span><?php _ex('上传', 'metabox', WB_MAGICPOST_TD); ?></span>
            </button>
          </div>
        </label>
      <?php
        continue;
      elseif ($slug == 'baidu'):
        $remark = __('填入百度网盘客户端或者网页端分享链接及提取码，可自动识别链接和提取码填入哦。', 'metabox', WB_MAGICPOST_TD);
      ?>
        <div class="wb-post-sitting-item magicpost-item-bdp">
          <label class="bdp-url-item">
            <span class="wb-form-label"><?php echo esc_html($item_cnf['label']); ?></span>
            <input class="wbs-input wbmgp-dl-with-psw" type="text" name="wb_down_url" placeholder="<?php echo esc_attr($tips); ?>" value="<?php echo esc_attr($meta_value['wb_down_url']); ?>">
          </label>
          <label class="bdp-psw-item">
            <input class="wbs-input wbmgp-dl-psw" type="text" name="wb_down_pwd" placeholder="<?php _ex('提取码', 'metabox', WB_MAGICPOST_TD); ?>" id="wb_down_pwd" value="<?php echo esc_attr($meta_value['wb_down_pwd']); ?>">
          </label>
        </div>
      <?php
        continue;
      endif;
      // 其他网盘或自定义网盘
      $value = $meta_value['wb_down_url_' . $slug] ?? '';
      $psw = $meta_value['wb_down_pwd_' . $slug] ?? '';
      ?>
      <div class="wb-post-sitting-item magicpost-item-bdp">
        <label class="bdp-url-item">
          <span class="wb-form-label"><?php echo esc_html($item_cnf['label']); ?></span>
          <input class="wbs-input wbmgp-dl-with-psw" type="text" name="<?php echo esc_attr('wb_down_url_' . $slug); ?>"
            placeholder="<?php echo esc_attr($tips); ?>" value="<?php echo esc_attr($value); ?>">
        </label>
        <label class="bdp-psw-item">
          <input class="wbs-input wbmgp-dl-psw" type="text" name="<?php echo esc_attr('wb_down_pwd_' . $slug); ?>"
            value="<?php echo esc_attr($psw); ?>" placeholder="<?php _ex('提取码', 'metabox', WB_MAGICPOST_TD); ?>">
        </label>
      </div>
      <?php if ($remark) : ?>
        <div class="wb-tip-txt"><?php echo esc_html($remark); ?></div>
      <?php endif; ?>
    <?php endforeach; ?>


    <h3><strong><?php _ex('下载方式', 'metabox', WB_MAGICPOST_TD); ?></strong></h3>

    <div class="wb-post-sitting-item">
      <span class="wb-form-label"><?php _ex('选择方式', 'metabox', WB_MAGICPOST_TD); ?></span>
      <div class="selector-bar">
        <label><input class="wbs-radio" type="radio" name="wb_dl_mode" <?php echo !$dl_mode ? ' checked="checked"' : ''; ?> value="0"> <?php _ex('免费下载', 'metabox', WB_MAGICPOST_TD); ?></label>
        <label><input class="wbs-radio" type="radio" name="wb_dl_mode" <?php echo $dl_mode == '1' ? ' checked="checked"' : ''; ?> value="1"> <?php _ex('回复后下载', 'metabox', WB_MAGICPOST_TD); ?></label>
        <label><input class="wbs-radio" type="radio" name="wb_dl_mode" <?php echo $dl_mode == '2' ? ' checked="checked"' : ''; ?> value="2"> <?php _ex('付费下载', 'metabox', WB_MAGICPOST_TD); ?></label>
      </div>
    </div>

    <div class="default-hidden-box set-price-box<?php echo $dl_mode == '2' ? ' active' : ''; ?>" id="J_WBDLSetPrice">
      <?php if (!$wpvk_install || !$wpvk_active) : ?>
        <p class="notice inline notice-warning notice-alt"><?php _ex('付费下载需安装"Wordpress付费内容插件"，', 'metabox', WB_MAGICPOST_TD); ?>
          <?php
          if (!$wpvk_install) { ?>
            <span><?php _ex('未检测到该插件，', 'metabox', WB_MAGICPOST_TD); ?></span>
            <a class="link" href="<?php echo esc_url(admin_url('plugin-install.php?s=Wordpress付费内容插件+WP+VK&tab=search&type=term')); ?>" target="set_plugin"><?php _ex('立即下载', 'metabox', WB_MAGICPOST_TD); ?></a>
          <?php } else if (!$wpvk_active) { ?>
            <span><?php _ex('未检测到该插件启用，', 'metabox', WB_MAGICPOST_TD); ?></span>
            <a class="link" href="<?php echo esc_url(admin_url('plugin-install.php?s=Wordpress付费内容插件+WP+VK&tab=search&type=term')); ?>" target="set_plugin"><?php _ex('立即启用', 'metabox', WB_MAGICPOST_TD); ?></a>
          <?php } ?>
        </p>
      <?php else : ?>
        <input class="wbs-input w8em" type="hidden" name="wb_down_price" id="wb_down_price" placeholder="" value="<?php echo esc_attr($meta_value['wb_down_price']); ?>">
        <div class="wb-tip-txt">
          <label>
            <span><?php _ex('设置价格: ', 'metabox', WB_MAGICPOST_TD); ?></span>
            <input class="wbs-input wbs-input-short" type="number" name="wb_down_vk_price" value="<?php echo esc_attr($meta_value_vk_price); ?>" onchange="document.querySelector('#vk_price').value=this.value;document.querySelector('#wb_down_price').value=this.value">
          </label>
          <p><?php _ex('* 当前文章启用了付费下载，付费阅读功能失效。', 'metabox', WB_MAGICPOST_TD); ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>