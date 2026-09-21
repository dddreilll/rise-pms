<?php echo form_open(get_uri("talent_contracts/remove"), array("id" => "talent-contract-remove-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="talent_project_id" value="<?php echo (int) $talent_project_id; ?>" />
        <input type="hidden" name="template_id" value="<?php echo (int) $template_id; ?>" />

        <div class="mb15">
            <strong><?php echo esc($context->preferred_name ? $context->preferred_name : $context->legal_name); ?></strong>
            <span class="text-off ml5"><?php echo esc($context->project_title); ?></span>
        </div>

        <?php if (!$preview) { ?>
            <div class="alert alert-warning mb0"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo app_lang("talent_contract_error_cannot_remove"); ?></div>
        <?php } else { ?>
            <div class="mb10"><?php echo sprintf(app_lang("talent_contract_remove_intro"), "<strong>" . esc($preview["title"]) . "</strong>"); ?></div>
            <div class="text-off mb15">
                <?php echo app_lang("talent_contract_remove_help"); ?>
                <?php if ($preview["records"] > 1) { ?>
                    <?php echo sprintf(app_lang("talent_contract_remove_help_many"), (int) $preview["records"]); ?>
                <?php } ?>
            </div>

            <?php if ($preview["was_signed"]) { ?>
                <div class="alert alert-warning"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo app_lang("talent_contract_remove_help_signed"); ?></div>
            <?php } ?>

            <div class="form-group mb0">
                <div class="row">
                    <label for="talent-contract-remove-reason" class="col-md-3"><?php echo app_lang("talent_contract_remove_reason"); ?></label>
                    <div class="col-md-9">
                        <textarea id="talent-contract-remove-reason" name="reason" rows="3" maxlength="1000" class="form-control"<?php echo $preview["was_signed"] ? " data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'" : ""; ?>></textarea>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <?php if ($preview) { ?>
        <button type="submit" class="btn btn-danger"><span data-feather="trash-2" class="icon-16"></span> <?php echo app_lang("talent_contract_remove"); ?></button>
    <?php } ?>
</div>
<?php echo form_close(); ?>

<?php if ($preview) { ?>
    <script type="text/javascript">
        $(document).ready(function () {
            $("#talent-contract-remove-form").appForm({
                onSuccess: function () {
                    if (window.reloadProjectTalent) {
                        window.reloadProjectTalent();
                    }
                }
            });
        });
    </script>
<?php } ?>
