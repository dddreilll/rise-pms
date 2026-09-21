<?php echo form_open(get_uri("talent_project_agreements/save"), array("id" => "talent-project-agreements-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>" />

        <div class="text-off mb15"><?php echo app_lang("talent_project_agreements_intro"); ?></div>

        <?php if (!$has_templates) { ?>
            <div class="alert alert-warning mb0">
                <i data-feather="alert-triangle" class="icon-16"></i> <?php echo app_lang("talent_contract_error_no_templates"); ?>
                <?php if ($can_manage_templates) { ?>
                    <a href="<?php echo get_uri("talent_contract_templates"); ?>"><?php echo app_lang("talent_contract_templates"); ?></a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="form-group">
                <div class="row">
                    <label for="template_ids" class="col-md-3"><?php echo app_lang("talent_project_agreements_label"); ?></label>
                    <div class="col-md-9">
                        <?php
                        echo form_input(array(
                            "id" => "template_ids",
                            "name" => "template_ids",
                            "value" => $selected_ids,
                            "class" => "form-control",
                            "placeholder" => app_lang("talent_project_agreements_placeholder")
                        ));
                        ?>
                        <div class="text-off mt5"><?php echo app_lang("talent_project_agreements_help"); ?></div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <?php if ($has_templates) { ?>
        <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
    <?php } ?>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#template_ids").select2({multiple: true, data: <?php echo $templates_dropdown; ?>});

        $("#talent-project-agreements-form").appForm({
            onSuccess: function (result) {
                //the line under the tab title, and the rows' "x of y signed" counts
                $("#project-talent-agreements-summary").text(result.summary);
                if (window.reloadProjectTalent) {
                    window.reloadProjectTalent();
                }
            }
        });
    });
</script>
