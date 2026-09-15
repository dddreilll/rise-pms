<div class="no-border clearfix">
    <ul data-bs-toggle="ajax-tab" class="nav nav-tabs bg-white title" role="tablist">
        <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#agreements-settings-panel"><?php echo app_lang("agreements_settings"); ?></a></li>
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("agreements_templates/settings_list"); ?>" data-bs-target="#agreements-templates-panel"><?php echo app_lang("agreements_templates"); ?></a></li>
    </ul>

    <div class="tab-content">
        <div role="tabpanel" class="tab-pane fade" id="agreements-settings-panel">
            <div class="card no-border clearfix mb0">
                <?php echo form_open(get_uri("agreements_settings/save"), array("id" => "agreements-settings-form", "class" => "general-form dashed-row", "role" => "form")); ?>
                <div class="card-body">
                    <div class="form-group">
                        <div class="row">
                            <label for="default_template_id" class="col-md-3"><?php echo app_lang("agreements_default_template"); ?></label>
                            <div class="col-md-9">
                                <?php echo form_dropdown("default_template_id", $templates_dropdown, $default_template_id, "class='select2 mini' id='default_template_id'"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <label for="client_can_access_agreements" class="col-md-3"><?php echo app_lang("agreements_client_can_access"); ?></label>
                            <div class="col-md-9">
                                <?php
                                echo form_checkbox("client_can_access_agreements", "1", $client_can_access_agreements === "1", "id='client_can_access_agreements' class='form-check-input form-switch'");
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <label for="default_reminder_days" class="col-md-3"><?php echo app_lang("agreements_reminder_days"); ?></label>
                            <div class="col-md-9">
                                <?php echo form_input(array("id" => "default_reminder_days", "name" => "default_reminder_days", "value" => $default_reminder_days, "class" => "form-control", "type" => "number")); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
                </div>
                <?php echo form_close(); ?>
            </div>
        </div>
        <div role="tabpanel" class="tab-pane fade" id="agreements-templates-panel"></div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#agreements-settings-form .select2").select2();
        $("#agreements-settings-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
            }
        });
    });
</script>
