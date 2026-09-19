<?php echo form_open(get_uri("talent_projects/assign"), array("id" => "talent-assign-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="talent_id" class="col-md-3"><?php echo app_lang("talent"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown("talent_id", $talent_dropdown, "", "id='talent_id' class='form-control select2' data-rule-required='true'");
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent_id").appDropdown();

        $("#talent-assign-form").appForm({
            onSuccess: function (result) {
                $("#project-talent-table").appTable({reload: true});
            }
        });
    });
</script>
