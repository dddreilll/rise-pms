<?php echo form_open(get_uri("google_docs/save"), array("id" => "google-docs-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="title" class="col-md-3"><?php echo app_lang("title"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "title",
                        "name" => "title",
                        "value" => $model_info->title,
                        "class" => "form-control",
                        "placeholder" => app_lang("title"),
                        "autofocus" => true,
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="description" class="col-md-3"><?php echo app_lang("description"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_textarea(array(
                        "id" => "description",
                        "name" => "description",
                        "value" => $model_info->description,
                        "class" => "form-control",
                        "placeholder" => app_lang("description"),
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("share_with"); ?></label>
                <div class="col-md-9">
                    <?php echo $get_sharing_options_view; ?>
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
        $("#google-docs-form").appForm({
            onSuccess: function (result) {
                if (result.success) {
                    var $table = $("#google-docs-table");
                    if (!$table.length) {
                        $table = $("#project-google-docs-table");
                    }
                    $table.appTable({newData: result.data, dataId: result.id});
                }
            }
        });
        feather.replace();
    });
</script>
