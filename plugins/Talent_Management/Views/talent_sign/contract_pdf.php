<?php echo $body; ?>
<p></p>
<table cellpadding="4" border="0" style="font-size: 9pt; color: #444444;">
    <tr>
        <td colspan="2" style="border-bottom: 1px solid #999999;"><strong><?php echo app_lang("talent_sign_record_title"); ?></strong></td>
    </tr>
    <?php foreach ($record as $label => $value) { ?>
        <tr>
            <td style="width: 28%; color: #777777;"><?php echo esc($label); ?></td>
            <td style="width: 72%;"><?php echo esc($value); ?></td>
        </tr>
    <?php } ?>
</table>
