<div class="tab-content">
    <?php echo form_open(get_uri("talent/save_additional_info/" . $model_info->id), array("id" => "talent-additional-info-form", "class" => "general-form dashed-row white", "role" => "form")); ?>
    <div class="card border-top-0 rounded-top-0">
        <div class="card-header">
            <h4><?php echo app_lang('talent_tab_additional_information'); ?></h4>
        </div>
        <div class="card-body">
            <?php echo view("custom_fields/form/prepare_context_fields", array("custom_fields" => $custom_fields, "label_column" => "col-md-2", "field_column" => "col-md-10")); ?>
        </div>
        <div class="card-footer rounded-0">
            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-additional-info-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                setTimeout(function () {
                    window.location.href = "<?php echo get_uri("talent/view/" . $model_info->id); ?>" + "/additional";
                }, 500);
            }
        });
    });
</script>
