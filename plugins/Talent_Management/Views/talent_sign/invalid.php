<!DOCTYPE html>
<html lang="en">
    <?php echo view('includes/head'); ?>
    <body>
        <div id="page-content" class="page-wrapper clearfix">
            <div class="card p30 text-center" style="max-width: 560px; margin: 60px auto;">
                <img class="dashboard-image mb20" src="<?php echo get_logo_url(); ?>" style="margin: 0 auto;" />
                <h4><?php echo app_lang("talent_sign_invalid_title"); ?></h4>
                <p class="text-off mb0"><?php echo app_lang("talent_sign_invalid_message"); ?></p>
            </div>
        </div>
        <script type="text/javascript">
            $(document).ready(function () {
                $("#custom-theme-color").remove();
            });
        </script>
    </body>
</html>
