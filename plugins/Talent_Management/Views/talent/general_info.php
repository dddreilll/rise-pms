<div class="tab-content">
    <?php echo form_open(get_uri("talent/save_general_info/" . $model_info->id), array("id" => "talent-general-info-form", "class" => "general-form dashed-row white", "role" => "form")); ?>
    <div class="card border-top-0 rounded-top-0">
        <div class="card-header">
            <h4><?php echo app_lang('talent_tab_general_information'); ?></h4>
        </div>
        <div class="card-body">
            <?php foreach (array("legal_name", "preferred_name", "pronouns") as $field_name) { ?>
                <div class="form-group">
                    <div class="row">
                        <label for="<?php echo $field_name; ?>" class="col-md-2"><?php echo app_lang($field_name); ?></label>
                        <div class="col-md-10">
                            <?php
                            $attributes = array(
                                "id" => $field_name,
                                "name" => $field_name,
                                "value" => $model_info->$field_name,
                                "class" => "form-control",
                                "placeholder" => app_lang($field_name),
                            );
                            if ($field_name === "legal_name") {
                                $attributes["data-rule-required"] = true;
                                $attributes["data-msg-required"] = app_lang("field_required");
                            }
                            echo form_input($attributes);
                            ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="form-group">
                <div class="row">
                    <label for="address" class="col-md-2"><?php echo app_lang("address"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_textarea(array(
                            "id" => "address",
                            "name" => "address",
                            "value" => $model_info->address,
                            "class" => "form-control",
                            "placeholder" => "ex. Burgos, Rodriguez, Rizal",
                        ));
                        ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="profession" class="col-md-2"><?php echo app_lang("profession"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_input(array(
                            "id" => "profession",
                            "name" => "profession",
                            "value" => $model_info->profession,
                            "class" => "form-control",
                            "placeholder" => app_lang("profession"),
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
        $("#talent-general-info-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                setTimeout(function () {
                    window.location.href = "<?php echo get_uri("talent/view/" . $model_info->id); ?>" + "/general";
                }, 500);
            }
        });
    });
</script>
