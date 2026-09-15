<li>
    <span data-feather="file-text" class="icon-14 ml-20"></span>
    <h5><?php echo app_lang("agreements"); ?>:</h5>
    <div>
        <?php
        echo form_checkbox("agreements", "1", $agreements ? true : false, "id='agreements_permission' class='form-check-input'");
        ?>
        <label for="agreements_permission"><?php echo app_lang("agreements_manage_permission"); ?></label>
    </div>
</li>
