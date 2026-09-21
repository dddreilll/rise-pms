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
                    <?php if ($can_manage_templates && !$has_any_templates) { ?>
                        <a href="<?php echo get_uri("talent_contract_templates"); ?>"><?php echo app_lang("talent_contract_templates"); ?></a>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="form-group">
                    <div class="row">
                        <label for="template_ids" class="col-md-3"><?php echo app_lang("talent_contract_agreements"); ?></label>
                        <div class="col-md-9">
                            <?php
                            echo form_input(array(
                                "id" => "template_ids",
                                "name" => "template_ids",
                                "value" => $selected_ids,
                                "class" => "form-control validate-hidden",
                                "placeholder" => app_lang("talent_contract_select_agreements"),
                                "data-rule-required" => "true",
                                "data-msg-required" => app_lang("talent_contract_error_pick_agreement"),
                            ));
                            ?>
                            <?php if ($required_ids) { ?>
                                <a href="javascript:;" id="talent-select-required" class="mt5 d-inline-block"><?php echo app_lang("talent_contract_select_all_required"); ?></a>
                            <?php } ?>

                            <div id="talent-contract-separate-wrap" class="mt10 hide">
                                <label class="d-flex align-items-start mb0">
                                    <input type="checkbox" name="separate" id="talent-contract-separate" value="1" class="mt5 mr10" />
                                    <span><?php echo app_lang("talent_contract_send_separately"); ?></span>
                                </label>
                                <div class="text-off"><?php echo app_lang("talent_contract_send_separately_help"); ?></div>
                            </div>
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

                <?php if ($moves_back_ids) { ?>
                    <div id="talent-contract-moves-back" class="alert alert-info hide">
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
            <div id="talent-contract-send-links"></div>
            <div class="text-off mt15"><?php echo app_lang("talent_contract_link_help"); ?></div>
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
        var $ids = $("#template_ids");

        //the agreements that can go out now (a required one is marked); select2 posts the choice as "3,7,9"
        $ids.select2({multiple: true, data: <?php echo $templates_json; ?>});

        var requiredIds = <?php echo json_encode(array_map('strval', $required_ids)); ?>;
        //sending a required agreement to someone who is confirmed takes them back to Contract Signing; an extra doesn't
        var movesBackIds = <?php echo json_encode(array_map('strval', $moves_back_ids)); ?>;

        var selectedIds = function () {
            var value = $.trim($ids.val() || "");
            return value === "" ? [] : value.split(",");
        };

        var refreshChoice = function () {
            var chosen = selectedIds();

            //several agreements can go in one link (the default) or each in its own email
            $("#talent-contract-separate-wrap").toggleClass("hide", chosen.length < 2);
            if (chosen.length < 2) {
                $("#talent-contract-separate").prop("checked", false);
            }

            var movesBack = false;
            $.each(chosen, function (index, id) {
                movesBack = movesBack || movesBackIds.indexOf(String(id)) !== -1;
            });
            $("#talent-contract-moves-back").toggleClass("hide", !movesBack);
        };
        $ids.on("change", refreshChoice);
        refreshChoice();

        $("#talent-select-required").on("click", function () {
            $ids.select2("val", requiredIds);
            refreshChoice();
        });

        var copyLabel = "<?php echo esc(app_lang("talent_contract_copy_link"), "js"); ?>";
        var copiedLabel = "<?php echo esc(app_lang("talent_contract_link_copied"), "js"); ?>";
        var copyLink = function ($link) {
            $link.trigger("focus").trigger("select");

            var done = function () {
                appAlert.success(copiedLabel, {duration: 3000});
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText($link.val()).then(done);
            } else if (document.execCommand("copy")) {
                done();
            }
        };

        $("#talent-contract-send-form").appForm({
            //the modal stays open so the signing links can be copied
            closeModalOnSuccess: false,
            onSuccess: function (result) {
                //appForm masks the modal while it submits and only lifts the mask when it closes the modal, which this one doesn't
                $(".modal-mask").remove();
                $("#talent-contract-send-form .modal-body").removeClass("hide");

                $("#talent-contract-send-fields").addClass("hide");
                $("#talent-contract-send-submit").addClass("hide");
                $("#talent-contract-send-result-message").text(result.message);

                //one link per email sent, each with the agreements it carries (shown as text, never as markup)
                var $links = $("#talent-contract-send-links").empty();
                $.each(result.bundles || [], function (index, bundle) {
                    var $link = $("<input type='text' class='form-control' readonly='readonly' />").val(bundle.link);
                    var $button = $("<button type='button' class='btn btn-default talent-copy-link'></button>").text(copyLabel).on("click", function () {
                        copyLink($link);
                    });
                    $links.append($("<div class='mt15'></div>")
                            .append($("<label></label>").text((bundle.titles || []).join(", ")))
                            .append($("<div class='input-group'></div>").append($link).append($button)));
                });
                $("#talent-contract-send-result").removeClass("hide");

                if (window.reloadProjectTalent) {
                    window.reloadProjectTalent();
                }
            }
        });

        $("#talent-contract-preview-button").on("click", function () {
            var $preview = $("#talent-contract-preview");
            if (!selectedIds().length) {
                appAlert.error("<?php echo esc(app_lang("talent_contract_error_pick_agreement"), "js"); ?>");
                return;
            }

            appLoader.show();
            $.ajax({
                url: "<?php echo get_uri("talent_contracts/preview"); ?>",
                type: "POST",
                dataType: "json",
                data: {talent_project_id: "<?php echo $talent_project_id; ?>", template_ids: selectedIds().join(","), notes: $("#notes").val()},
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
    });
</script>
