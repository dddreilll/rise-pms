<link rel="stylesheet" type="text/css" href="<?php echo base_url(PLUGIN_URL_PATH . "Agreements/assets/css/agreements.css"); ?>" />

<div class="page-content clearfix agreement-details-view">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="agreement-title-section">
                    <div class="page-title no-bg clearfix mb5 no-border">
                        <h1 class="pl0">
                            <span><i data-feather="file-text" class="icon"></i></span>
                            <?php echo agreements_get_id($document->id) . ": " . $document->title; ?>
                        </h1>

                        <div class="title-button-group mr0">
                            <span class="dropdown inline-block mt15">
                                <button class="btn btn-info text-white dropdown-toggle caret mt0 mb0" type="button" data-bs-toggle="dropdown" aria-expanded="true">
                                    <i data-feather="tool" class="icon-16"></i> <?php echo app_lang('actions'); ?>
                                </button>
                                <ul class="dropdown-menu" role="menu">
                                    <?php if ($can_manage) { ?>
                                        <li role="presentation"><?php echo modal_anchor(get_uri("agreements/modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang('agreements_edit'), array("title" => app_lang('agreements_edit'), "data-post-id" => $document->id, "class" => "dropdown-item")); ?></li>
                                        <li role="presentation" class="dropdown-divider"></li>
                                        <?php if (in_array($document->status, array("draft", "sent", "partially_signed"))) { ?>
                                            <li role="presentation"><?php echo js_anchor("<i data-feather='send' class='icon-16'></i> " . app_lang('agreements_send'), array("id" => "agreements-send-btn", "class" => "dropdown-item")); ?></li>
                                            <li role="presentation"><?php echo js_anchor("<i data-feather='mail' class='icon-16'></i> " . app_lang('agreements_resend'), array("id" => "agreements-resend-btn", "class" => "dropdown-item")); ?></li>
                                        <?php } ?>
                                        <?php if (!in_array($document->status, array("cancelled", "completed"))) { ?>
                                            <li role="presentation"><?php echo js_anchor("<i data-feather='x-circle' class='icon-16'></i> " . app_lang('agreements_cancel'), array("id" => "agreements-cancel-btn", "class" => "dropdown-item")); ?></li>
                                        <?php } ?>
                                        <?php if (in_array($document->status, array("completed", "declined", "expired", "cancelled"))) { ?>
                                            <li role="presentation"><?php echo js_anchor("<i data-feather='git-branch' class='icon-16'></i> " . app_lang('agreements_amend'), array("id" => "agreements-amend-btn", "class" => "dropdown-item")); ?></li>
                                            <li role="presentation"><?php echo js_anchor("<i data-feather='refresh-cw' class='icon-16'></i> " . app_lang('agreements_renew'), array("id" => "agreements-renew-btn", "class" => "dropdown-item")); ?></li>
                                        <?php } ?>
                                        <li role="presentation"><?php echo js_anchor("<i data-feather='copy' class='icon-16'></i> " . app_lang('agreements_save_as_template'), array("id" => "agreements-save-template-btn", "class" => "dropdown-item")); ?></li>
                                    <?php } ?>
                                    <?php if ($document->final_pdf_path) { ?>
                                        <li role="presentation" class="dropdown-divider"></li>
                                        <li role="presentation"><?php echo anchor(get_uri("agreements/download_signed/" . $document->id), "<i data-feather='download' class='icon-16'></i> " . app_lang('agreements_download_signed'), array("class" => "dropdown-item")); ?></li>
                                    <?php } ?>
                                    <li role="presentation" class="dropdown-divider"></li>
                                    <li role="presentation"><?php echo anchor(get_uri("agreements"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang('back'), array("class" => "dropdown-item")); ?></li>
                                </ul>
                            </span>
                        </div>
                    </div>

                    <ul id="agreement-tabs" data-bs-toggle="ajax-tab" class="nav nav-pills rounded classic mb20 scrollable-tabs border-white" role="tablist">
                        <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#agreement-details-section"><?php echo app_lang("details"); ?></a></li>
                        <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("agreements/audit/" . $document->id); ?>" data-bs-target="#agreement-audit-section"><?php echo app_lang("agreements_audit_log"); ?></a></li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade" id="agreement-details-section">
                        <?php echo view("Agreements\Views\agreements\details"); ?>
                    </div>
                    <div role="tabpanel" class="tab-pane fade grid-button" id="agreement-audit-section"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        function postAction(url, data, reload) {
            $.ajax({
                url: url,
                type: "POST",
                dataType: "json",
                data: data,
                success: function (result) {
                    if (result.success) {
                        appAlert.success(result.message, {duration: 3000});
                        if (result.redirect_url) {
                            window.location = result.redirect_url;
                        } else if (reload !== false) {
                            location.reload();
                        }
                    } else {
                        appAlert.error(result.message || "Error");
                    }
                }
            });
        }

        $("#agreements-send-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/send"); ?>", {id: <?php echo $document->id; ?>});
        });
        $("#agreements-resend-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/resend"); ?>", {id: <?php echo $document->id; ?>});
        });
        $("#agreements-cancel-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/cancel"); ?>", {id: <?php echo $document->id; ?>});
        });
        $("#agreements-amend-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/amend"); ?>", {id: <?php echo $document->id; ?>});
        });
        $("#agreements-renew-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/renew"); ?>", {id: <?php echo $document->id; ?>});
        });
        $("#agreements-save-template-btn").on("click", function (e) {
            e.preventDefault();
            postAction("<?php echo get_uri("agreements/save_as_template"); ?>", {id: <?php echo $document->id; ?>}, false);
        });
    });
</script>
