<div class="tab-content">
    <?php echo form_open(get_uri("talent/save_contact_info/" . $model_info->id), array("id" => "talent-contact-info-form", "class" => "general-form dashed-row white", "role" => "form")); ?>
    <div class="card border-top-0 rounded-top-0">
        <div class="card-header">
            <h4><?php echo app_lang('talent_tab_contact_information'); ?></h4>
        </div>
        <div class="card-body">
            <div class="form-group">
                <div class="row">
                    <label for="contact_number" class="col-md-2"><?php echo app_lang("contact_number"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_input(array(
                            "id" => "contact_number",
                            "name" => "contact_number",
                            "value" => $model_info->contact_number,
                            "class" => "form-control",
                            "placeholder" => app_lang("contact_number"),
                        ));
                        ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="email" class="col-md-2"><?php echo app_lang("talent_email_address"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_input(array(
                            "id" => "email",
                            "name" => "email",
                            "value" => $model_info->email,
                            "class" => "form-control",
                            "placeholder" => app_lang("talent_email_address"),
                        ));
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer rounded-0">
            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-contact-info-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                setTimeout(function () {
                    window.location.href = "<?php echo get_uri("talent/view/" . $model_info->id); ?>" + "/contact";
                }, 500);
            }
        });
    });
</script>
