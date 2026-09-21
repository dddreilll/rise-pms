<?php echo form_open(get_uri("talent_contracts/send"), array("id" => "talent-contract-send-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="talent_project_id" value="<?php echo $talent_project_id; ?>" />

        <div id="talent-contract-send-fields">
            <div class="form-group">
                <div class="row">
                    <label class="col-md-3"><?php echo app_lang("talent_contract_recipient"); ?></label>
                    <div class="col-md-9 pt5">
                        <strong><?php echo esc($context->preferred_name ? $context->preferred_name : $context->legal_name); ?></strong>
                        <?php if ($context->email) { ?>
                            <span class="text-off">&lt;<?php echo esc($context->email); ?>&gt;</span>
                        <?php } ?>
                        <div class="text-off"><?php echo esc($context->project_title); ?></div>
                    </div>
                </div>
            </div>

            <?php if ($blocked_message) { ?>
                <div class="alert alert-warning mb0">
                    <i data-feather="alert-triangle" class="icon-16"></i> <?php echo $blocked_message; ?>
                    <?php if ($can_manage_templates && count($templates_dropdown) < 2) { ?>
                        <a href="<?php echo get_uri("talent_contract_templates"); ?>"><?php echo app_lang("talent_contract_templates"); ?></a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="form-group">
                    <div class="row">
                        <label for="template_id" class="col-md-3"><?php echo app_lang("talent_contract_template"); ?></label>
                        <div class="col-md-9">
                            <?php
                            echo form_dropdown("template_id", $templates_dropdown, $selected_template, "id='template_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'");
                            ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="row">
                        <label for="notes" class="col-md-3"><?php echo app_lang("talent_contract_notes"); ?></label>
                        <div class="col-md-9">
                            <?php
                            echo form_textarea(array(
                                "id" => "notes",
                                "name" => "notes",
                                "class" => "form-control",
                                "rows" => 4,
                                "placeholder" => app_lang("talent_contract_notes_help"),
                            ));
                            ?>
                        </div>
                    </div>
                </div>

                <?php if ($moves_from_confirmed) { ?>
                    <div class="alert alert-info">
                        <i data-feather="info" class="icon-16"></i> <?php echo app_lang("talent_contract_moves_from_confirmed"); ?>
                    </div>
                <?php } ?>

                <div class="form-group">
                    <a href="javascript:;" id="talent-contract-preview-button"><i data-feather="eye" class="icon-16"></i> <?php echo app_lang("talent_contract_preview"); ?></a>
                    <div id="talent-contract-preview" class="mt10 p15 b-a bg-white hide" style="max-height: 320px; overflow: auto;"></div>
                </div>
            <?php } ?>

            <?php if ($show_paper_link) { ?>
                <div class="mt15">
                    <?php echo modal_anchor(get_uri("talent_contracts/paper_modal_form"), "<i data-feather='upload' class='icon-16'></i> " . app_lang("talent_contract_paper_link"), array("title" => app_lang("talent_contract_paper_title"), "data-post-talent_project_id" => $talent_project_id)); ?>
                </div>
            <?php } ?>
        </div>

        <div id="talent-contract-send-result" class="hide">
            <div id="talent-contract-send-result-message"></div>
            <div class="mt15">
                <label for="talent-contract-link"><?php echo app_lang("talent_contract_link"); ?></label>
                <div class="input-group">
                    <input type="text" id="talent-contract-link" class="form-control" readonly="readonly" />
                    <button type="button" id="talent-contract-copy-button" class="btn btn-default"><?php echo app_lang("talent_contract_copy_link"); ?></button>
                </div>
                <div class="text-off mt5"><?php echo app_lang("talent_contract_link_help"); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <?php if (!$blocked_message) { ?>
        <button type="submit" id="talent-contract-send-submit" class="btn btn-primary"><span data-feather="send" class="icon-16"></span> <?php echo app_lang("talent_contract_send"); ?></button>
    <?php } ?>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#template_id").appDropdown();

        $("#talent-contract-send-form").appForm({
            //the modal stays open so the signing link can be copied
            closeModalOnSuccess: false,
            onSuccess: function (result) {
                //appForm masks the modal while it submits and only lifts the mask when it closes the modal, which this one doesn't
                $(".modal-mask").remove();
                $("#talent-contract-send-form .modal-body").removeClass("hide");

                $("#talent-contract-send-fields").addClass("hide");
                $("#talent-contract-send-submit").addClass("hide");
                $("#talent-contract-send-result-message").text(result.message);
                $("#talent-contract-link").val(result.link);
                $("#talent-contract-send-result").removeClass("hide");

                if (window.reloadProjectTalent) {
                    window.reloadProjectTalent();
                }
            }
        });

        $("#talent-contract-preview-button").on("click", function () {
            var $preview = $("#talent-contract-preview");
            if (!$("#template_id").val()) {
                appAlert.error("<?php echo app_lang("talent_contract_select_template"); ?>");
                return;
            }

            appLoader.show();
            $.ajax({
                url: "<?php echo get_uri("talent_contracts/preview"); ?>",
                type: "POST",
                dataType: "json",
                data: {talent_project_id: "<?php echo $talent_project_id; ?>", template_id: $("#template_id").val(), notes: $("#notes").val()},
                success: function (result) {
                    appLoader.hide();
                    if (result.success) {
                        $preview.html(result.html).removeClass("hide");
                    } else {
                        appAlert.error(result.message);
                    }
                }
            });
        });

        $("#talent-contract-copy-button").on("click", function () {
            var $link = $("#talent-contract-link");
            $link.trigger("focus").trigger("select");

            var done = function () {
                appAlert.success("<?php echo app_lang("talent_contract_link_copied"); ?>", {duration: 3000});
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText($link.val()).then(done);
            } else if (document.execCommand("copy")) {
                done();
            }
        });
    });
</script>
