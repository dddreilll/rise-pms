<?php $warnings = isset($warnings) ? $warnings : array(); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <?php if ($error) { ?>
            <div class="text-danger"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo app_lang("talent_update_failed"); ?></div>
            <div class="mt10 text-off"><?php echo esc($error); ?></div>
        <?php } else if ($changes) { ?>
            <div class="text-success"><i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("talent_update_done"); ?></div>
            <ul class="mt10">
                <?php foreach ($changes as $change) { ?>
                    <li><?php echo esc($change); ?></li>
                <?php } ?>
            </ul>
        <?php } else { ?>
            <div class="text-success"><i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("talent_update_up_to_date"); ?></div>
        <?php } ?>

        <?php foreach ($warnings as $warning) { ?>
            <div class="alert alert-warning mt15 mb0"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo esc($warning); ?></div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
</div>
