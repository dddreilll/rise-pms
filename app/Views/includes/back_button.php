<?php
if (isset($button_url) && $button_url) {
    $url = echo_uri($button_url);
    $extra_class = "";
} else {
    $url = "javascript:;";
    $extra_class = "navigate-back";
}

$button_text = isset($button_text) ? $button_text : app_lang('back');
?>

<div class="d-sm-none">
    <a class="back-action-btn <?php echo $extra_class; ?>" href="<?php echo $url; ?>"><i data-feather='chevron-left' class='icon-18'></i> <?php echo app_lang("tickets"); ?></a>
</div>