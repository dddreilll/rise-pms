<?php echo form_open(get_uri("agreements_templates/save"), array("id" => "agreements-template-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />
        <div class="form-group">
            <div class="row">
                <label for="title" class="col-md-3"><?php echo app_lang('agreements_title'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array("id" => "title", "name" => "title", "value" => $model_info->title, "class" => "form-control", "autofocus" => true, "data-rule-required" => true, "data-msg-required" => app_lang("field_required"))); ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label for="document_type" class="col-md-3"><?php echo app_lang('agreements_document_type'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown("document_type", array(
                        "html" => app_lang("agreements_document_type_html"),
                        "pdf" => app_lang("agreements_document_type_pdf"),
                    ), $model_info->document_type ? $model_info->document_type : "html", "class='select2 mini' id='tpl_document_type'");
                    ?>
                </div>
            </div>
        </div>
        <div class="form-group tpl-html-fields">
            <div class="row">
                <label for="content" class="col-md-3"><?php echo app_lang('agreements_content'); ?></label>
                <div class="col-md-9">
                    <?php echo form_textarea(array("id" => "tpl_content", "name" => "content", "value" => $model_info->content, "class" => "form-control")); ?>
                </div>
            </div>
        </div>
        <div class="form-group tpl-pdf-fields hide">
            <div class="row">
                <label for="pdf_file" class="col-md-3"><?php echo app_lang('agreements_pdf_file'); ?></label>
                <div class="col-md-9">
                    <?php echo form_upload(array("id" => "pdf_file", "name" => "pdf_file", "class" => "form-control")); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#agreements-template-form .select2").select2();
        initWYSIWYGEditor("#tpl_content", {height: 200});
        function toggleType() {
            if ($("#tpl_document_type").val() === "pdf") {
                $(".tpl-html-fields").addClass("hide");
                $(".tpl-pdf-fields").removeClass("hide");
            } else {
                $(".tpl-pdf-fields").addClass("hide");
                $(".tpl-html-fields").removeClass("hide");
            }
        }
        $("#tpl_document_type").on("change", toggleType);
        toggleType();
        $("#agreements-template-form").appForm({
            onSuccess: function () {
                $("#agreements-templates-table").appTable({reload: true});
            }
        });
    });
</script>
