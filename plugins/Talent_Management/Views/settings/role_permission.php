<li>
    <span data-feather="video" class="icon-14 ml-20"></span>
    <h5><?php echo app_lang("talent"); ?>:</h5>
    <div>
        <?php
        echo form_checkbox("talent_management", "1", $talent_management ? true : false, "id='talent_management_permission' class='form-check-input'");
        ?>
        <label for="talent_management_permission"><?php echo app_lang("talent_management_permission"); ?></label>
    </div>
</li>
