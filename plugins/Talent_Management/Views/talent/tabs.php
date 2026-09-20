<li><a class="<?php echo ($active_tab == 'talent_list') ? 'active' : ''; ?>" href="<?php echo_uri('talent'); ?>"><?php echo app_lang("list"); ?></a></li>
<li><a class="<?php echo ($active_tab == 'talent_status') ? 'active' : ''; ?>" href="<?php echo_uri('talent_status'); ?>"><?php echo app_lang('pipeline_stages'); ?></a></li>
<li><a class="<?php echo ($active_tab == 'talent_custom_fields') ? 'active' : ''; ?>" href="<?php echo_uri('talent/custom_fields'); ?>"><?php echo app_lang('custom_fields'); ?></a></li>
<?php if (talent_can_manage_contract_templates()) { ?>
    <li><a class="<?php echo ($active_tab == 'talent_contract_templates') ? 'active' : ''; ?>" href="<?php echo_uri('talent_contract_templates'); ?>"><?php echo app_lang('talent_contract_templates'); ?></a></li>
<?php } ?>
