<?php echo view("includes/cropbox"); ?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="bg-primary card mb0 rounded-bottom-0">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="row p20">
                        <?php echo view('Talent_Management\Views\talent\profile_image_section', array("model_info" => $model_info, "social_links" => $social_links)); ?>
                    </div>
                </div>

                <div class="col-md-6 text-center cover-widget">
                    <div class="row p20">
                        <?php foreach (array_chunk($widgets, 2) as $widget_row) { ?>
                            <div class="box">
                                <?php foreach ($widget_row as $index => $widget) { ?>
                                    <div class="box-content <?php echo ($index === 0 && count($widget_row) > 1) ? "b-r" : ""; ?>">
                                        <div class="card-body">
                                            <h1><?php echo $widget["count"]; ?></h1>
                                            <span class="text-off uppercase"><?php echo $widget["title"]; ?></span>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul id="talent-view-tabs" data-bs-toggle="ajax-tab" class="nav nav-tabs scrollable-tabs rounded-0 border-top-0" role="tablist">
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/general_info/" . $model_info->id); ?>" data-bs-target="#tab-general-info"> <?php echo app_lang('talent_tab_general_information'); ?></a></li>
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/contact_info/" . $model_info->id); ?>" data-bs-target="#tab-contact-info"> <?php echo app_lang('talent_tab_contact_information'); ?></a></li>
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/social_links/" . $model_info->id); ?>" data-bs-target="#tab-social-links"> <?php echo app_lang('social_links'); ?></a></li>
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/preferences/" . $model_info->id); ?>" data-bs-target="#tab-preferences"> <?php echo app_lang('talent_tab_preferences'); ?></a></li>
        <?php if ($show_additional_info_tab) { ?>
            <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/additional_info/" . $model_info->id); ?>" data-bs-target="#tab-additional-info"> <?php echo app_lang('talent_tab_additional_information'); ?></a></li>
        <?php } ?>
        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("talent/projects_info/" . $model_info->id); ?>" data-bs-target="#tab-projects-info"> <?php echo app_lang('projects'); ?></a></li>
    </ul>

    <div class="tab-content">
        <div role="tabpanel" class="tab-pane fade" id="tab-general-info"></div>
        <div role="tabpanel" class="tab-pane fade" id="tab-contact-info"></div>
        <div role="tabpanel" class="tab-pane fade" id="tab-social-links"></div>
        <div role="tabpanel" class="tab-pane fade" id="tab-preferences"></div>
        <div role="tabpanel" class="tab-pane fade" id="tab-additional-info"></div>
        <div role="tabpanel" class="tab-pane fade" id="tab-projects-info"></div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $(".upload").change(function () {
            if (typeof FileReader == 'function' && !$(this).hasClass("hidden-input-file")) {
                showCropBox(this);
            } else {
                $("#profile-image-form").submit();
            }
        });
        $("#profile_image").change(function () {
            $("#profile-image-form").submit();
        });

        $("#profile-image-form").appForm({
            isModal: false,
            beforeAjaxSubmit: function (data) {
                $.each(data, function (index, obj) {
                    if (obj.name === "profile_image") {
                        data[index]["value"] = replaceAll(":", "~", data[index]["value"]);
                    }
                });
            },
            onSuccess: function (result) {
                if (typeof FileReader == 'function' && !result.reload_page) {
                    appAlert.success(result.message, {duration: 10000});
                } else {
                    location.reload();
                }
            }
        });

        //open the requested tab, general info by default
        setTimeout(function () {
            var tab = "<?php echo $tab; ?>";
            var targets = {
                contact: "#tab-contact-info",
                social: "#tab-social-links",
                preferences: "#tab-preferences",
                additional: "#tab-additional-info",
                projects: "#tab-projects-info"
            };
            $("[data-bs-target='" + (targets[tab] || "#tab-general-info") + "']").trigger("click");
        }, 210);
    });
</script>
