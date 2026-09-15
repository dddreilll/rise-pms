<?php echo form_open(get_uri("agreements/save"), array("id" => "agreements-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="title" class="col-md-3"><?php echo app_lang('agreements_title'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array("id" => "title", "name" => "title", "value" => $model_info->title, "class" => "form-control", "placeholder" => app_lang('agreements_title'), "autofocus" => true, "data-rule-required" => true, "data-msg-required" => app_lang("field_required"))); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="signing_mode" class="col-md-3"><?php echo app_lang('agreements_signing_mode'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown("signing_mode", array(
                        "parallel" => app_lang("agreements_signing_mode_parallel"),
                        "sequential" => app_lang("agreements_signing_mode_sequential"),
                    ), $model_info->signing_mode ? $model_info->signing_mode : "parallel", "class='select2 mini'");
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="client_id" class="col-md-3"><?php echo app_lang('client'); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown("client_id", $clients_dropdown, $model_info->client_id, "class='select2' id='client_id'"); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="project_id" class="col-md-3"><?php echo app_lang('project'); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown("project_id", $projects_dropdown, $model_info->project_id, "class='select2' id='project_id'"); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="expires_at" class="col-md-3"><?php echo app_lang('agreements_expires_at'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array("id" => "expires_at", "name" => "expires_at", "value" => $model_info->expires_at, "class" => "form-control", "placeholder" => "YYYY-MM-DD HH:MM:SS")); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="reminder_days" class="col-md-3"><?php echo app_lang('agreements_reminder_days'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array("id" => "reminder_days", "name" => "reminder_days", "value" => $model_info->reminder_days !== "" ? $model_info->reminder_days : "3", "class" => "form-control", "type" => "number")); ?>
                </div>
            </div>
        </div>

        <?php if ($model_info->id) { ?>
            <div class="form-group">
                <div class="row">
                    <label for="document_type" class="col-md-3"><?php echo app_lang('agreements_document_type'); ?></label>
                    <div class="col-md-9">
                        <?php
                        echo form_dropdown("document_type", array(
                            "html" => app_lang("agreements_document_type_html"),
                            "pdf" => app_lang("agreements_document_type_pdf"),
                        ), $model_info->document_type ? $model_info->document_type : "html", "class='select2 mini' id='document_type'");
                        ?>
                    </div>
                </div>
            </div>
            <div class="form-group agreements-pdf-fields <?php echo $model_info->document_type === "pdf" ? "" : "hide"; ?>">
                <div class="row">
                    <label for="pdf_file" class="col-md-3"><?php echo app_lang('agreements_pdf_file'); ?></label>
                    <div class="col-md-9">
                        <?php echo form_upload(array("id" => "pdf_file", "name" => "pdf_file", "class" => "form-control")); ?>
                        <?php if ($model_info->pdf_path) { ?>
                            <div class="mt10"><?php echo basename($model_info->pdf_path); ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#agreements-form .select2").select2();

        $("#document_type").on("change", function () {
            if ($(this).val() === "pdf") {
                $(".agreements-pdf-fields").removeClass("hide");
            } else {
                $(".agreements-pdf-fields").addClass("hide");
            }
        });

        $("#agreements-form").appForm({
            onSuccess: function (result) {
                if (result.redirect_url) {
                    window.location = result.redirect_url;
                } else {
                    $("#agreements-table").appTable({reload: true});
                }
            }
        });
    });
</script>
