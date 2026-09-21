<?php echo form_open(get_uri("talent_contracts/save_paper"), array("id" => "talent-contract-paper-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="talent_project_id" value="<?php echo (int) $talent_project_id; ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("talent_contract_recipient"); ?></label>
                <div class="col-md-9 pt5">
                    <strong><?php echo esc($context->preferred_name ? $context->preferred_name : $context->legal_name); ?></strong>
                    <div class="text-off"><?php echo esc($context->project_title); ?></div>
                </div>
            </div>
        </div>

        <?php if ($blocked_message) { ?>
            <div class="alert alert-warning mb0"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo $blocked_message; ?></div>
        <?php } else { ?>
            <div class="text-off mb15"><?php echo app_lang("talent_contract_paper_intro"); ?></div>

            <?php if ($withdraws_pending) { ?>
                <div class="alert alert-info"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("talent_contract_paper_withdraws"); ?></div>
            <?php } ?>

            <div class="form-group">
                <div class="row">
                    <label for="paper_title" class="col-md-3"><?php echo app_lang("talent_contract_paper_contract_title"); ?></label>
                    <div class="col-md-9">
                        <input type="text" id="paper_title" name="title" value="<?php echo esc($default_title); ?>" maxlength="255" class="form-control" data-rule-required="true" data-msg-required="<?php echo app_lang("field_required"); ?>" />
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="paper_signed_on" class="col-md-3"><?php echo app_lang("talent_contract_paper_signed_on"); ?></label>
                    <div class="col-md-9">
                        <input type="text" id="paper_signed_on" name="signed_on" value="<?php echo esc($today); ?>" autocomplete="off" class="form-control" data-rule-required="true" data-msg-required="<?php echo app_lang("field_required"); ?>" />
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="paper_scan" class="col-md-3"><?php echo app_lang("talent_contract_paper_scan"); ?></label>
                    <div class="col-md-9">
                        <input type="file" id="paper_scan" name="scan" accept="application/pdf,image/jpeg,image/png" data-rule-required="true" data-msg-required="<?php echo app_lang("field_required"); ?>" />
                        <div class="text-off mt5"><?php echo sprintf(app_lang("talent_contract_paper_scan_help"), $max_mb); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="paper_note" class="col-md-3"><?php echo app_lang("talent_contract_paper_note"); ?></label>
                    <div class="col-md-9">
                        <textarea id="paper_note" name="note" rows="3" maxlength="1000" class="form-control" placeholder="<?php echo esc(app_lang("talent_contract_paper_note_help")); ?>"></textarea>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <?php if (!$blocked_message) { ?>
        <button type="submit" class="btn btn-primary"><span data-feather="upload" class="icon-16"></span> <?php echo app_lang("talent_contract_paper_submit"); ?></button>
    <?php } ?>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-contract-paper-form").appForm({
            onSuccess: function () {
                if (window.reloadProjectTalent) {
                    window.reloadProjectTalent();
                }
            }
        });

        setDatePicker("#paper_signed_on");
    });
</script>
