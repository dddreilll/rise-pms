<div class="card no-border clearfix mb0">
    <?php if ($document->document_type === "pdf") { ?>
        <div class="p20">
            <?php if ($pdf_url) { ?>
                <iframe src="<?php echo $pdf_url; ?>" class="agreements-pdf-frame mt15" style="width:100%;height:520px;border:1px solid #ddd;"></iframe>
            <?php } else { ?>
                <p><?php echo app_lang("agreements_pdf_file"); ?></p>
            <?php } ?>
        </div>
    <?php } else { ?>
        <?php echo form_open(get_uri("agreements/save_content"), array("id" => "agreement-editor-form", "class" => "general-form", "role" => "form")); ?>
        <div class="bg-all-white pt15">
            <input type="hidden" name="id" value="<?php echo $document->id; ?>" />

            <div class="form-group mb15 pl15 pr15">
                <div class="clearfix pl5 pr5 pb10 preview-editor-button-group">
                    <?php if ($can_manage) { ?>
                        <button type="submit" class="btn btn-primary float-end"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
                    <?php } ?>
                </div>
                <div class="col-md-12">
                    <?php
                    $editor_attrs = array(
                        "id" => "agreement-view-editor",
                        "name" => "content",
                        "value" => $document->content,
                        "placeholder" => app_lang("agreements_content"),
                        "class" => "form-control",
                        "data-toolbar" => "pdf_friendly_toolbar",
                        "data-height" => 600,
                        "data-encode_ajax_post_data" => "1",
                    );
                    if (!$can_manage) {
                        $editor_attrs["readonly"] = "readonly";
                    }
                    echo form_textarea($editor_attrs);
                    ?>
                </div>
            </div>

            <div class="p15 pt0">
                <strong><?php echo app_lang("agreements_available_variables"); ?></strong>:
                <?php foreach ($merge_keys as $key) {
                    echo "{{" . $key . "}}, ";
                } ?>
            </div>
        </div>
        <?php echo form_close(); ?>

        <script type="text/javascript">
            $(document).ready(function () {
                <?php if ($can_manage) { ?>
                $("#agreement-editor-form").appForm({
                    isModal: false,
                    onSuccess: function (response) {
                        appAlert.success(response.message, {duration: 10000});
                    }
                });
                initWYSIWYGEditor("#agreement-view-editor");
                <?php } ?>
            });
        </script>
    <?php } ?>
</div>
