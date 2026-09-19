<div class="tab-content">
    <?php echo form_open(get_uri("talent/save_preferences/" . $model_info->id), array("id" => "talent-preferences-form", "class" => "general-form dashed-row white", "role" => "form")); ?>
    <div class="card border-top-0 rounded-top-0">
        <div class="card-header">
            <h4><?php echo app_lang('talent_tab_preferences'); ?></h4>
        </div>
        <div class="card-body">
            <div class="form-group">
                <div class="row">
                    <label for="on_screen_title" class="col-md-2"><?php echo app_lang("on_screen_title"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_input(array(
                            "id" => "on_screen_title",
                            "name" => "on_screen_title",
                            "value" => $model_info->on_screen_title,
                            "class" => "form-control",
                            "placeholder" => "ex. Animator, 3D Animator, Animation Student, Freelance Illustrator",
                        ));
                        ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="dietary_restrictions" class="col-md-2"><?php echo app_lang("dietary_restrictions"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_textarea(array(
                            "id" => "dietary_restrictions",
                            "name" => "dietary_restrictions",
                            "value" => $model_info->dietary_restrictions,
                            "class" => "form-control",
                            "placeholder" => app_lang("dietary_restrictions"),
                        ));
                        ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="row">
                    <label for="safety_comfort_notes" class="col-md-2"><?php echo app_lang("safety_comfort_notes"); ?></label>
                    <div class="col-md-10">
                        <?php
                        echo form_textarea(array(
                            "id" => "safety_comfort_notes",
                            "name" => "safety_comfort_notes",
                            "value" => $model_info->safety_comfort_notes,
                            "class" => "form-control",
                            "placeholder" => app_lang("safety_comfort_notes_placeholder"),
                        ));
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer rounded-0">
            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-preferences-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                setTimeout(function () {
                    window.location.href = "<?php echo get_uri("talent/view/" . $model_info->id); ?>" + "/preferences";
                }, 500);
            }
        });
    });
</script>
