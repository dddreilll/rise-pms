<li>
    <span data-feather="file-text" class="icon-14 ml-20"></span>
    <h5><?php echo app_lang("google_docs"); ?>:</h5>
    <div>
        <?php
        echo form_checkbox("google_docs", "1", $google_docs ? true : false, "id='google_docs_permission' class='form-check-input'");
        ?>
        <label for="google_docs_permission"><?php echo app_lang("google_docs_manage_permission"); ?></label>
    </div>
</li>
