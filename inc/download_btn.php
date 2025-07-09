<?php

/**
 * Author: wbolt team
 * Author URI: https://www.wbolt.com
 */

?>
<?php if ($need_login && !$is_login) { //if login 
?>
  <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"><svg class="wb-icon-magicpost wbsico-download">
      <use xlink:href="#wbsico-magicpost-donwload"></use>
    </svg> <span><?php _ex('去下载', 'front', WB_MAGICPOST_TD); ?></span></a>

<?php } elseif ($need_comment && !$is_comment) { //else if need comment 
?>
  <p class="wb-tips"><?php _ex('* 该资源需回复评论后下载, ', 'front', WB_MAGICPOST_TD); ?> <a href="#comments"><?php _ex('去评论', 'front', WB_MAGICPOST_TD); ?></a></p>
<?php } else { //else if login 
?>

  <a class="wb-btn wb-btn-outlined wb-btn-download" href="#J_MPDLCont">
    <svg class="wb-icon-magicpost wbsico-magicpost-donwload">
      <use xlink:href="#wbsico-magicpost-download"></use>
    </svg>
    <span><?php _ex('去下载', 'front', WB_MAGICPOST_TD); ?></span></a>
<?php } //end if login 
?>