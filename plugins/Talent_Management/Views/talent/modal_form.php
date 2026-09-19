<?php echo form_open(get_uri("talent/save"), array("id" => "talent-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <?php foreach (array("legal_name", "preferred_name", "pronouns") as $field_name) { ?>
            <div class="form-group">
                <div class="row">
                    <label for="<?php echo $field_name; ?>" class="<?php echo $label_column; ?>"><?php echo app_lang($field_name); ?></label>
                    <div class="<?php echo $field_column; ?>">
                        <?php
                        $attributes = array(
                            "id" => $field_name,
                            "name" => $field_name,
                            "value" => $model_info->$field_name,
                            "class" => "form-control",
                            "placeholder" => app_lang($field_name),
                        );
                        if ($field_name === "legal_name") {
                            $attributes["autofocus"] = true;
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
                <label for="address" class="<?php echo $label_column; ?>"><?php echo app_lang("address"); ?></label>
                <div class="<?php echo $field_column; ?>">
                    <?php
                    echo form_textarea(array(
                        "id" => "address",
                        "name" => "address",
                        "value" => $model_info->address,
                        "class" => "form-control",
                        "placeholder" => app_lang("address"),
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="profession" class="<?php echo $label_column; ?>"><?php echo app_lang("profession"); ?></label>
                <div class="<?php echo $field_column; ?>">
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
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-form").appForm({
            onSuccess: function (result) {
                var $table = $("#talent-table");
                if ($table.length) {
                    $table.appTable({newData: result.data, dataId: result.id});
                } else {
                    location.reload();
                }
            }
        });
    });
</script>
