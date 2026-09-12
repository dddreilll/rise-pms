<div class="card no-border clearfix mb0">
    <?php echo form_open(get_uri("google_docs_integration_settings/save"), array("id" => "google-docs-settings-form", "class" => "general-form dashed-row", "role" => "form")); ?>
    <input type="hidden" name="authorize_after_save" id="authorize_after_save" value="0" />

    <div class="card-body">
        <div class="form-group">
            <div class="row">
                <label class="col-md-12">
                    <?php echo app_lang("get_your_app_credentials_from_here") . " " . anchor("https://console.cloud.google.com/", "Google Cloud Console", array("target" => "_blank")); ?>
                </label>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-12 text-off">
                    <i data-feather="info" class="icon-16"></i> <?php echo app_lang("google_docs_help_message"); ?>
                </label>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="google_docs_client_id" class="col-md-2"><?php echo app_lang("google_docs_client_id"); ?></label>
                <div class="col-md-10">
                    <?php
                    echo form_input(array(
                        "id" => "google_docs_client_id",
                        "name" => "google_docs_client_id",
                        "value" => get_setting("google_docs_client_id"),
                        "class" => "form-control",
                        "placeholder" => app_lang("google_docs_client_id"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="google_docs_client_secret" class="col-md-2"><?php echo app_lang("google_docs_client_secret"); ?></label>
                <div class="col-md-10">
                    <?php
                    echo form_input(array(
                        "id" => "google_docs_client_secret",
                        "name" => "google_docs_client_secret",
                        "value" => get_setting("google_docs_client_secret"),
                        "class" => "form-control",
                        "placeholder" => app_lang("google_docs_client_secret"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="redirect_uri" class="col-md-2"><i data-feather="alert-triangle" class="icon-16"></i> <?php echo app_lang("remember_to_add_this_url_in_authorized_redirect_uri"); ?></label>
                <div class="col-md-10">
                    <?php echo "<pre class='mt5'>" . get_uri("google_docs_api/save_access_token") . "</pre>"; ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="status" class="col-md-2"><?php echo app_lang("status"); ?></label>
                <div class="col-md-10">
                    <?php if (get_setting("google_docs_authorized")) { ?>
                        <span class="ml5 badge bg-success"><?php echo app_lang("authorized"); ?></span>
                    <?php } else { ?>
                        <span class="ml5 badge" style="background:#F9A52D;"><?php echo app_lang("unauthorized"); ?></span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer">
        <button type="button" id="google-docs-save-button" class="btn btn-default"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
        <button type="button" id="google-docs-save-authorize-button" class="btn btn-primary ml5"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save_and_authorize"); ?></button>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#google-docs-settings-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                if (result.authorize && result.authorize_url) {
                    window.location.href = result.authorize_url;
                }
            }
        });

        $("#google-docs-save-button").click(function () {
            $("#authorize_after_save").val("0");
            $("#google-docs-settings-form").trigger("submit");
        });

        $("#google-docs-save-authorize-button").click(function () {
            $("#authorize_after_save").val("1");
            $("#google-docs-settings-form").trigger("submit");
        });

        feather.replace();
    });
</script>
