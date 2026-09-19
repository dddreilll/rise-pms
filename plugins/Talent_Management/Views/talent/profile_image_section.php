<div class="box" id="profile-image-section">
    <div class="box-content w200 text-center profile-image">
        <?php echo form_open(get_uri("talent/save_profile_image/" . $model_info->id), array("id" => "profile-image-form", "class" => "general-form", "role" => "form")); ?>
        <div class="file-upload btn mt0 p0 profile-image-upload" data-bs-toggle="tooltip" title="<?php echo app_lang("upload_and_crop"); ?>" data-placement="right">
            <span class="btn color-white"><i data-feather="camera" class="icon-16"></i></span>
            <input id="profile_image_file" class="upload" name="profile_image_file" type="file" data-height="200" data-width="200" data-preview-container="#profile-image-preview" data-input-field="#profile_image" />
        </div>
        <div class="file-upload p0 profile-image-upload profile-image-direct-upload" data-bs-toggle="tooltip" title="<?php echo app_lang("upload"); ?> (200x200 px)" data-placement="right">
            <?php
            echo form_upload(array(
                "id" => "profile_image_file_upload",
                "name" => "profile_image_file",
                "class" => "no-outline hidden-input-file upload"
            ));
            ?>
            <label for="profile_image_file_upload" class="clickable">
                <span class="btn color-white"><i data-feather="upload" class="icon-16"></i></span>
            </label>
        </div>
        <input type="hidden" id="profile_image" name="profile_image" value="" />
        <span class="avatar avatar-lg"><img id="profile-image-preview" src="<?php echo get_avatar($model_info->profile_image); ?>" alt="..."></span>
        <h4><?php echo $model_info->preferred_name ? $model_info->preferred_name : $model_info->legal_name; ?></h4>
        <?php if ($model_info->pronouns) { ?>
            <p class="m0"><?php echo $model_info->pronouns; ?></p>
        <?php } ?>
        <?php echo form_close(); ?>
    </div>

    <div class="box-content pl15">
        <?php if ($model_info->profession || $model_info->on_screen_title) { ?>
            <p class="p10 m0"><label class="badge bg-info"><strong> <?php echo trim($model_info->profession . ($model_info->on_screen_title ? " (" . $model_info->on_screen_title . ")" : "")); ?> </strong></label></p>
        <?php } ?>

        <p class="p10 m0"><i data-feather="mail" class="icon-16"></i> <?php echo $model_info->email ? $model_info->email : "-"; ?></p>
        <p class="p10 m0"><i data-feather="phone" class="icon-16"></i> <?php echo $model_info->contact_number ? $model_info->contact_number : "-"; ?></p>

        <?php if ($social_links) { ?>
            <div class="p10 m0 clearfix">
                <?php foreach ($social_links as $social_link) { ?>
                    <a href="<?php echo get_array_value($social_link, "url"); ?>" target="_blank" class="mr10 color-white" title="<?php echo get_array_value($social_link, "platform"); ?>" data-bs-toggle="tooltip"><i data-feather="<?php echo talent_social_icon(get_array_value($social_link, "platform")); ?>" class="icon-16"></i></a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<script>
    $(document).ready(function () {
        if (isMobile()) {
            $("#profile-image-section").children("div").each(function () {
                $(this).addClass("p0");
                $(this).removeClass("box-content");
            });
        }

        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
