<div class="tab-content">
    <?php echo form_open(get_uri("talent/save_social_links/" . $model_info->id), array("id" => "talent-social-links-form", "class" => "general-form dashed-row white", "role" => "form")); ?>
    <div class="card border-top-0 rounded-top-0">
        <div class="card-header">
            <h4><?php echo app_lang('social_links'); ?></h4>
        </div>
        <div class="card-body">
            <div id="social-links-repeater">
                <?php
                $social_links_to_render = $social_links ? $social_links : array(array("platform" => "", "url" => ""));
                foreach ($social_links_to_render as $social_link) {
                    ?>
                    <div class="row mb10 social-link-row">
                        <div class="col-md-3">
                            <?php
                            echo form_input(array(
                                "name" => "social_platform[]",
                                "value" => get_array_value($social_link, "platform"),
                                "class" => "form-control",
                                "placeholder" => app_lang("platform"),
                            ));
                            ?>
                        </div>
                        <div class="col-md-8">
                            <?php
                            echo form_input(array(
                                "name" => "social_url[]",
                                "value" => get_array_value($social_link, "url"),
                                "class" => "form-control",
                                "placeholder" => app_lang("url"),
                            ));
                            ?>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-default remove-social-link"><i data-feather="x" class="icon-16"></i></button>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <button type="button" id="add-social-link" class="btn btn-default btn-sm mt5"><i data-feather="plus-circle" class="icon-14"></i> <?php echo app_lang("add_social_link"); ?></button>
        </div>
        <div class="card-footer rounded-0">
            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
        </div>
    </div>
    <?php echo form_close(); ?>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-social-links-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 10000});
                setTimeout(function () {
                    window.location.href = "<?php echo get_uri("talent/view/" . $model_info->id); ?>" + "/social";
                }, 500);
            }
        });

        $("#add-social-link").on("click", function () {
            var row = $(".social-link-row").first().clone();
            row.find("input").val("");
            $("#social-links-repeater").append(row);
            feather.replace();
        });

        $("#social-links-repeater").on("click", ".remove-social-link", function () {
            if ($(".social-link-row").length > 1) {
                $(this).closest(".social-link-row").remove();
            } else {
                $(this).closest(".social-link-row").find("input").val("");
            }
        });
    });
</script>
