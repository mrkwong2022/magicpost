<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?><div class="wbd-inner" id="J_wbTabCHT">
  <div class="tab-navs">
    <div class="tab-nav-item current" data-index="0"><i class="wbsico wbsico-cht-tag"></i> <span><?php _ex('设置标签', 'admin', WB_MAGICPOST_TD); ?></span></div>
    <div class="tab-nav-item" data-index="1"><i class="wbsico wbsico-cht-attr"></i><span><?php _ex('设置属性', 'admin', WB_MAGICPOST_TD); ?></span></div>
    <div class="tab-nav-item" data-index="2"><i class="wbsico wbsico-cht-stag"></i><span><?php _ex('标签及内容', 'admin', WB_MAGICPOST_TD); ?></span></div>
    <div class="tab-nav-item" data-index="3"><i class="wbsico wbsico-cht-format"></i><span><?php _ex('格式优化', 'admin', WB_MAGICPOST_TD); ?></span></div>
    <div class="tab-nav-item" data-index="4"><i class="wbsico wbsico-cht-replace"></i><span><?php _ex('搜索替换', 'admin', WB_MAGICPOST_TD); ?></span></div>
  </div>
  <div class="tab-conts">
    <div class="tab-cont current">
      <div class="wb-checkbox-list">
        <?php
        $tags_group_name = array('normal' => __('常用标签', WB_MAGICPOST_TD), 'table' => __('表格标签', WB_MAGICPOST_TD), 'list' => __('列表标签', WB_MAGICPOST_TD), 'other' => __('其他标签', WB_MAGICPOST_TD));
        foreach ($cht_cnf['tags'] as $k => $g) : ?>
          <strong><?php echo esc_html($tags_group_name[$k]); ?></strong>
          <div>
            <?php foreach ($g as $v) : ?>
              <label>
                <input type="checkbox" class="wb-cls-tag" name="cls-tag-items" value="<?php echo esc_attr($v); ?>" <?php if (in_array($v, $cht_opt['tags'])) echo 'checked'; ?>>
                &lt;<?php echo esc_html($v); ?>&gt;
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <?php if (isset($cht_opt['custom']['tags']) && $cht_opt['custom']['tags']) : ?>
          <strong><?php _ex('自定义', 'admin', WB_MAGICPOST_TD); ?></strong>
          <div>
            <?php foreach ($cht_opt['custom']['tags'] as $v) : ?>
              <label>
                <input type="checkbox" class="wb-cls-tag" name="cls-tag-items" value="<?php echo esc_attr($v); ?>" checked>
                &lt;<?php echo esc_html($v); ?>&gt;
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="tab-cont">
      <div class="wb-checkbox-list">
        <strong><?php _ex('常用属性', 'admin', WB_MAGICPOST_TD); ?></strong>
        <div>
          <?php foreach ($cht_cnf['attr'] as $v) : ?>
            <label>
              <input type="checkbox" class="wb-cls-attr" name="cls-attr-item" value="<?php echo esc_attr($v); ?>" <?php if (in_array($v, $cht_opt['attr'])) echo 'checked'; ?>>
              <?php echo esc_html($v); ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($cht_opt['custom']['attr']) && $cht_opt['custom']['attr']) : ?>
          <strong><?php _ex('自定义', 'admin', WB_MAGICPOST_TD); ?></strong>
          <div>
            <?php foreach ($cht_opt['custom']['attr'] as $v) : ?>
              <label>
                <input type="checkbox" class="wb-cls-attr" name="cls-tag-items" value="<?php echo esc_attr($v); ?>" checked>
                <?php echo esc_html($v); ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="tab-cont">
      <div class="wb-checkbox-list">
        <strong><?php _ex('标签及内容删除', 'admin', WB_MAGICPOST_TD); ?></strong>

        <?php if (isset($cht_opt['stag']) && $cht_opt['stag']) : ?>
          <strong><?php _ex('自定义', 'admin', WB_MAGICPOST_TD); ?></strong>
          <div>
            <?php foreach ($cht_opt['stag'] as $v) : ?>
              <label>
                <input type="checkbox" class="wb-cls-stag" name="cls-stag-items" value="<?php echo esc_attr($v); ?>" checked>
                <?php echo esc_html($v); ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="tab-cont">
      <div class="wb-checkbox-list">
        <?php foreach ($cht_cnf['format'] as $k => $v) : ?>
          <label class="block">
            <input type="checkbox" class="wb-cls-format" name="cls-format-item" value="<?php echo esc_attr($k); ?>" <?php if ($cht_opt['format'][$k]) echo 'checked'; ?>>
            <span><?php echo esc_html($v); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-cont">
      <div class="wb-checkbox-list">
        <?php foreach ($cht_opt['txt_replace'] as $k => $v) :
          /*$v['s'] = trim($v['s']);
            $v['r'] = trim($v['r']);*/
        ?>
          <div class="wb-item">
            <input type="text" value="<?php echo esc_html($v['s']); ?>" class="cls-re-s">
            <span><?php _ex('替换', 'admin', WB_MAGICPOST_TD); ?></span>
            <input type="text" value="<?php echo esc_html($v['r']); ?>" class="cls-re-r">
            <label>
              <input class="cls-re-c" type="checkbox" <?php echo $v['c'] ? ' checked' : ''; ?>>
              <?php _ex('启用', 'admin', WB_MAGICPOST_TD); ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <p class="description">
    <?php
    $setting_url = '<a class="link" href="' . admin_url('admin.php?page=magicpost#/clean') . '" target="_blank">' . _x('设置界面', 'admin', WB_MAGICPOST_TD) . '</a>';
    printf(_x('温馨提示：此处设置仅作用于当前文章编辑，如需设置插件默认项，需进入插件%s操作。', 'admin, %s设置界面', WB_MAGICPOST_TD), $setting_url); ?>
  </p>
</div>