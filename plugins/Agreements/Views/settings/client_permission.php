<div class="form-group form-switch">
    <div class="row">
        <label for="client_can_access_agreements" class="col-md-4 col-xs-8 col-sm-4"><?php echo app_lang("agreements_client_can_access"); ?></label>
        <div class="col-md-8 col-xs-4 col-sm-8">
            <?php
            echo form_checkbox("client_can_access_agreements", "1", agreements_get_setting("client_can_access_agreements") === "1", "id='client_can_access_agreements' class='form-check-input'");
            ?>
        </div>
    </div>
</div>
