<?php
//the events we have a label for; anything else is shown under its raw name
$known_events = array("sent", "email_sent", "email_failed", "viewed", "signed", "signed_paper", "declined", "card_confirmed", "card_reverted", "confirm_skipped", "expired", "downloaded", "resent", "voided");

//what staff can do with it: a link that is out (or has lapsed) can be sent again, a waiting or signed contract can be withdrawn
$can_resend = $is_latest && ($status === "sent" || $status === "expired");
$can_void = $status === "sent" || ($status === "signed" && $can_void_signed);

//a link renewed from here also renews the other agreements of its bundle that are still waiting or have lapsed
$resend_covers_others = false;
foreach ($bundle_mates as $mate) {
    $resend_covers_others = $resend_covers_others || $mate->status === "sent" || $mate->status === "expired";
}
$mate_titles = array_map(function ($mate) {
    return esc($mate->title);
}, $bundle_mates);
?>
<?php //general-form is what gives RISE's fields (the reason box, the link box) their soft grey look outside a <form> ?>
<div class="modal-body clearfix general-form">
    <div class="container-fluid">
        <div id="talent-contract-detail">
        <div class="mb15">
            <strong><?php echo esc($contract->title); ?></strong>
            <span class="text-off ml5"><?php echo esc($label); ?></span>
            <span class="ml10"><?php echo talent_contract_status_html($contract->status, $contract->token_expires_at); ?></span>

            <?php if ($contract->sent_at) { ?>
                <div class="text-off mt5"><?php echo sprintf(app_lang("talent_contract_sent_line"), esc($contract->sent_to_email), esc(format_to_datetime($contract->sent_at, true))); ?></div>
            <?php } ?>
            <?php if ($mate_titles) { ?>
                <div class="text-off"><?php echo sprintf(app_lang("talent_contract_sent_together_with"), implode(", ", $mate_titles)); ?></div>
            <?php } ?>
            <?php if ($was_signed && $is_paper) { ?>
                <div class="text-success mt5">
                    <i data-feather="check-circle" class="icon-16"></i>
                    <?php echo sprintf(app_lang("talent_contract_signed_paper_line"), esc(format_to_date($contract->signed_at, false))); ?>
                </div>
            <?php } else if ($was_signed) { ?>
                <div class="text-success mt5">
                    <i data-feather="check-circle" class="icon-16"></i>
                    <?php echo sprintf(app_lang("talent_contract_signed_line"), esc($signer_name), esc(format_to_datetime($contract->signed_at, true))); ?>
                </div>
            <?php } else if ($status === "sent") { ?>
                <div class="text-off"><?php echo sprintf(app_lang("talent_contract_expires_line"), esc(format_to_date($contract->token_expires_at, true))); ?></div>
            <?php } ?>
        </div>

        <?php if ($is_paper) { ?>
            <div class="alert alert-info mb0"><i data-feather="file-text" class="icon-16"></i> <?php echo app_lang("talent_contract_paper_only_note"); ?></div>
        <?php } else { ?>
            <div class="b-a bg-white p15" style="max-height: 420px; overflow: auto; overflow-wrap: anywhere;"><?php echo $html; ?></div>
        <?php } ?>

        <?php if ($can_void) { ?>
            <div id="talent-contract-void-area" class="hide mt15 p15 b-a bg-white">
                <div class="text-off mb10"><?php echo app_lang($status === "signed" ? "talent_contract_void_help_signed" : "talent_contract_void_help_pending"); ?></div>
                <label for="talent-contract-void-reason"><?php echo app_lang("talent_contract_void_reason"); ?></label>
                <textarea id="talent-contract-void-reason" class="form-control" rows="3" maxlength="1000"></textarea>
                <button type="button" id="talent-contract-void-confirm" class="btn btn-danger mt10"><i data-feather="slash" class="icon-16"></i> <?php echo app_lang("talent_contract_void"); ?></button>
            </div>
        <?php } ?>

        <h5 class="mt20"><?php echo app_lang("talent_contract_activity"); ?></h5>
        <ul class="list-unstyled mb0">
            <?php
            foreach ($events as $event) {
                $meta = $event->meta ? json_decode($event->meta, true) : array();
                $event_label = in_array($event->event, $known_events, true) ? app_lang("talent_contract_event_" . $event->event) : $event->event;
                //an address is only worth showing for what the talent did
                $show_ip = $event->ip && $event->actor_type === "talent";
                ?>
                <li class="pb5">
                    <span class="text-off"><?php echo esc(format_to_datetime($event->created_at, true)); ?></span>
                    <?php echo esc($event_label); ?>
                    <?php if ($show_ip) { ?><span class="text-off">(<?php echo esc($event->ip); ?>)</span><?php } ?>
                    <?php if (($event->event === "declined" || $event->event === "voided") && !empty($meta["reason"])) { ?>
                        <div class="text-off pl15"><?php echo nl2br(esc($meta["reason"])); ?></div>
                    <?php } ?>
                    <?php if ($event->event === "signed_paper") { ?>
                        <?php if (!empty($meta["file_name"])) { ?><div class="text-off pl15"><?php echo esc($meta["file_name"]); ?></div><?php } ?>
                        <?php if (!empty($meta["note"])) { ?><div class="text-off pl15"><?php echo nl2br(esc($meta["note"])); ?></div><?php } ?>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
        </div>

        <?php if ($can_resend) { ?>
            <?php //what is shown once the link has been sent again: the new link, to copy, as after sending ?>
            <div id="talent-contract-resend-result" class="hide">
                <div id="talent-contract-resend-message"></div>
                <div class="mt15">
                    <label for="talent-contract-link"><?php echo app_lang("talent_contract_link"); ?></label>
                    <div class="input-group">
                        <input type="text" id="talent-contract-link" class="form-control" readonly="readonly" />
                        <button type="button" id="talent-contract-copy-button" class="btn btn-default"><?php echo app_lang("talent_contract_copy_link"); ?></button>
                    </div>
                    <div class="text-off mt5"><?php echo app_lang("talent_contract_link_help"); ?></div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <div id="talent-contract-actions" class="d-inline">
    <?php if ($was_signed) { ?>
        <?php echo anchor(get_uri("talent_contracts/download/" . (int) $contract->id), "<i data-feather='download' class='icon-16'></i> " . app_lang("talent_contract_download"), array("class" => "btn btn-default")); ?>
    <?php } ?>
    <?php if ($can_resend) { ?>
        <button type="button" id="talent-contract-resend-button" class="btn btn-default"><i data-feather="send" class="icon-16"></i> <?php echo app_lang("talent_contract_resend"); ?></button>
    <?php } ?>
    <?php if ($can_void) { ?>
        <button type="button" id="talent-contract-void-button" class="btn btn-default text-danger"><i data-feather="slash" class="icon-16"></i> <?php echo app_lang("talent_contract_void"); ?></button>
    <?php } ?>
    </div>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
</div>

<?php if ($can_resend || $can_void) { ?>
    <script type="text/javascript">
        $(document).ready(function () {
            var contractId = "<?php echo (int) $contract->id; ?>";
            var isSigned = <?php echo $status === "signed" ? "true" : "false"; ?>;

            var refreshList = function () {
                if (window.reloadProjectTalent) {
                    window.reloadProjectTalent();
                }
            };

            $("#talent-contract-resend-button").on("click", function () {
                if (!window.confirm("<?php echo esc(app_lang($resend_covers_others ? "talent_contract_resend_confirm_bundle" : "talent_contract_resend_confirm"), "js"); ?>")) {
                    return;
                }

                var $button = $(this).prop("disabled", true);
                appLoader.show();
                $.ajax({
                    url: "<?php echo get_uri("talent_contracts/resend"); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {contract_id: contractId},
                    success: function (result) {
                        appLoader.hide();
                        if (!result.success) {
                            $button.prop("disabled", false);
                            appAlert.error(result.message);
                            return;
                        }

                        refreshList();

                        //the link only exists right now, so it is shown for copying (also when the mail went out: it may be shared another way)
                        $("#ajaxModalTitle").text("<?php echo esc(app_lang("talent_contract_resend"), "js"); ?>");
                        $("#talent-contract-resend-message").attr("class", result.emailed ? "" : "alert alert-warning").text(result.message);
                        $("#talent-contract-link").val(result.link);
                        $("#talent-contract-detail, #talent-contract-actions").addClass("hide");
                        $("#talent-contract-resend-result").removeClass("hide");
                    },
                    error: function () {
                        appLoader.hide();
                        $button.prop("disabled", false);
                    }
                });
            });

            $("#talent-contract-copy-button").on("click", function () {
                var $link = $("#talent-contract-link");
                $link.trigger("focus").trigger("select");

                var done = function () {
                    appAlert.success("<?php echo esc(app_lang("talent_contract_link_copied"), "js"); ?>", {duration: 3000});
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText($link.val()).then(done);
                } else if (document.execCommand("copy")) {
                    done();
                }
            });

            $("#talent-contract-void-button").on("click", function () {
                $("#talent-contract-void-area").toggleClass("hide");
                $("#talent-contract-void-reason").trigger("focus");
            });

            $("#talent-contract-void-confirm").on("click", function () {
                var reason = $.trim($("#talent-contract-void-reason").val());
                if (isSigned && !reason) {
                    appAlert.error("<?php echo esc(app_lang("talent_contract_error_void_reason"), "js"); ?>");
                    return;
                }

                var $button = $(this).prop("disabled", true);
                appLoader.show();
                $.ajax({
                    url: "<?php echo get_uri("talent_contracts/void"); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {contract_id: contractId, reason: reason},
                    success: function (result) {
                        appLoader.hide();
                        if (!result.success) {
                            $button.prop("disabled", false);
                            appAlert.error(result.message);
                            return;
                        }

                        refreshList();
                        $("#ajaxModal").modal("hide");
                        appAlert.success(result.message, {duration: 5000});
                    },
                    error: function () {
                        appLoader.hide();
                        $button.prop("disabled", false);
                    }
                });
            });
        });
    </script>
<?php } ?>
