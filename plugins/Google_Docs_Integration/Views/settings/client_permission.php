<div class="form-group form-switch">
    <div class="row">
        <label for="client_can_access_google_docs" class="col-md-4 col-xs-8 col-sm-4"><?php echo app_lang("client_can_access_google_docs"); ?></label>
        <div class="col-md-8 col-xs-4 col-sm-8">
            <?php
            echo form_checkbox("client_can_access_google_docs", "1", get_setting("client_can_access_google_docs") ? true : false, "id='client_can_access_google_docs' class='form-check-input'");
            ?>
        </div>
    </div>
</div>
