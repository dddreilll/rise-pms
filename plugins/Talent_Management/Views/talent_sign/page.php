<!DOCTYPE html>
<html lang="en">
    <?php echo view('includes/head'); ?>
    <body>
        <style>
            .talent-sign-wrap { max-width: 900px; margin: 0 auto; }
            .talent-contract-body { padding: 30px; overflow-wrap: anywhere; }
            .talent-contract-body img { max-width: 100%; height: auto; }
            #talent-signature-canvas { display: block; width: 100%; height: 200px; background: #fff; touch-action: none; cursor: crosshair; }
            @media (max-width: 575px) { .talent-contract-body { padding: 15px; } }
        </style>

        <div id="talent-sign-scrollbar">
            <div id="page-content" class="page-wrapper clearfix">
                <?php
                load_css(array("assets/css/invoice.css"));
                load_js(array("assets/js/signature/signature_pad.min.js"));
                ?>

                <div class="talent-sign-wrap">
                    <div class="card p15 no-border">
                        <div class="clearfix">
                            <img class="dashboard-image float-start" src="<?php echo get_logo_url(); ?>" />
                            <div class="float-end text-end">
                                <strong><?php echo esc($contract->title); ?></strong>
                                <div class="text-off"><?php echo esc($label); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card no-border">
                        <div class="talent-contract-body bg-white"><?php echo $html; ?></div>
                    </div>

                    <?php if ($status === "sent") { ?>
                        <div class="card p15 mt15">
                            <h4><?php echo app_lang("talent_sign_heading"); ?></h4>
                            <div class="text-off mb15"><?php echo sprintf(app_lang("talent_sign_expires"), esc(format_to_date($contract->token_expires_at, true))); ?></div>

                            <?php echo form_open(get_uri("talent_sign/sign"), array("id" => "talent-sign-form", "class" => "general-form", "role" => "form")); ?>
                            <input type="hidden" name="bundle_id" value="<?php echo (int) $contract->bundle_id; ?>" />
                            <input type="hidden" name="token" value="<?php echo esc($token); ?>" />

                            <div class="form-group">
                                <?php echo sprintf(app_lang("talent_sign_signing_as"), "<strong>" . esc($signer_name) . "</strong>"); ?>
                            </div>

                            <div class="form-group">
                                <label for="talent-sign-email"><?php echo app_lang("talent_sign_email"); ?></label>
                                <input type="email" name="email" id="talent-sign-email" class="form-control" autocomplete="email"
                                       data-rule-required="true" data-msg-required="<?php echo app_lang("field_required"); ?>"
                                       data-rule-email="true" data-msg-email="<?php echo app_lang("enter_valid_email"); ?>" />
                                <div class="text-off mt5"><?php echo sprintf(app_lang("talent_sign_email_help"), esc($masked_email)); ?></div>
                            </div>

                            <div class="form-group">
                                <label><?php echo app_lang("talent_sign_signature"); ?></label>
                                <div class="b-a"><canvas id="talent-signature-canvas" height="200"></canvas></div>
                                <input type="hidden" name="signature" id="talent-signature-data" class="validate-hidden"
                                       data-rule-required="true" data-msg-required="<?php echo app_lang("talent_sign_signature_required"); ?>" />
                                <a href="javascript:;" id="talent-signature-clear" class="mt5 d-inline-block"><?php echo app_lang("talent_sign_clear"); ?></a>
                            </div>

                            <div class="form-group">
                                <label class="d-flex align-items-start">
                                    <input type="checkbox" name="contract_ids[]" value="<?php echo (int) $contract->id; ?>" class="mt5 mr10"
                                           data-rule-required="true" data-msg-required="<?php echo app_lang("talent_sign_consent_required"); ?>" />
                                    <span><?php echo esc(app_lang("talent_sign_consent")); ?></span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-success"><i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("talent_sign_submit"); ?></button>
                            <a href="javascript:;" id="talent-decline-toggle" class="ml15"><?php echo app_lang("talent_sign_decline_link"); ?></a>
                            <?php echo form_close(); ?>

                            <div id="talent-decline-panel" class="hide mt20 pt15 b-t">
                                <p><?php echo app_lang("talent_sign_decline_intro"); ?></p>
                                <textarea id="talent-decline-reason" class="form-control mb10" rows="3" maxlength="1000" placeholder="<?php echo app_lang("talent_sign_decline_reason"); ?>"></textarea>
                                <button type="button" id="talent-decline-submit" class="btn btn-danger"><?php echo app_lang("talent_sign_decline_submit"); ?></button>
                            </div>
                        </div>
                    <?php } else if ($status === "signed") { ?>
                        <div class="card p15 mt15">
                            <div class="text-success mb10"><i data-feather="check-circle" class="icon-16"></i>
                                <?php echo sprintf(app_lang("talent_sign_signed_banner"), esc($contract->signer_name), esc(format_to_date($contract->signed_at, true))); ?>
                            </div>
                            <div>
                                <?php echo anchor(get_uri("talent_sign/download/" . (int) $contract->bundle_id . "/" . $token . "/" . (int) $contract->id), "<i data-feather='download' class='icon-16'></i> " . app_lang("talent_sign_download"), array("class" => "btn btn-default")); ?>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="card p15 mt15">
                            <div class="text-off"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("talent_sign_state_" . $status); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            $(document).ready(function () {
                initScrollbar('#talent-sign-scrollbar', {setHeight: $(window).height()});
                $("#custom-theme-color").remove();

<?php if ($status === "sent") { ?>
                    var canvas = document.getElementById("talent-signature-canvas");
                    //slightly heavier than the library's default pen, so the signature still reads once it is scaled onto a page
                    var pad = new SignaturePad(canvas, {backgroundColor: "rgb(255, 255, 255)", minWidth: 1, maxWidth: 3, onEnd: storeSignature});

                    //what the form posts: the drawing as a PNG, or nothing while the pad is empty
                    function storeSignature() {
                        $("#talent-signature-data").val(pad.isEmpty() ? "" : pad.toDataURL("image/png"));
                    }

                    //the pad is drawn at device resolution; a phone rotating (new width) redraws what was already signed
                    function fitCanvas() {
                        var strokes = pad.toData();
                        var ratio = Math.max(window.devicePixelRatio || 1, 1);
                        canvas.width = canvas.offsetWidth * ratio;
                        canvas.height = canvas.offsetHeight * ratio;
                        canvas.getContext("2d").scale(ratio, ratio);
                        pad.clear();
                        pad.fromData(strokes);
                        storeSignature();
                    }

                    var canvasWidth = canvas.offsetWidth;
                    $(window).on("resize orientationchange", function () {
                        if (canvas.offsetWidth !== canvasWidth) {
                            canvasWidth = canvas.offsetWidth;
                            fitCanvas();
                        }
                    });
                    fitCanvas();

                    $("#talent-signature-clear").on("click", function () {
                        pad.clear();
                        storeSignature();
                    });

                    $("#talent-sign-form").appForm({
                        isModal: false,
                        onSuccess: function (result) {
                            appAlert.success(result.message, {duration: 8000});
                            setTimeout(function () {
                                location.reload();
                            }, 900);
                        }
                    });

                    $("#talent-decline-toggle").on("click", function () {
                        $("#talent-decline-panel").toggleClass("hide");
                    });

                    $("#talent-decline-submit").on("click", function () {
                        var $button = $(this).attr("disabled", "disabled");
                        $.ajax({
                            url: "<?php echo get_uri("talent_sign/decline"); ?>",
                            type: "POST",
                            dataType: "json",
                            data: {bundle_id: "<?php echo (int) $contract->bundle_id; ?>", contract_id: "<?php echo (int) $contract->id; ?>", token: "<?php echo esc($token); ?>", reason: $("#talent-decline-reason").val()},
                            success: function (result) {
                                if (result.success) {
                                    appAlert.success(result.message, {duration: 8000});
                                    setTimeout(function () {
                                        location.reload();
                                    }, 900);
                                } else {
                                    appAlert.error(result.message);
                                    $button.removeAttr("disabled");
                                }
                            },
                            error: function () {
                                $button.removeAttr("disabled");
                            }
                        });
                    });
<?php } ?>
            });
        </script>
    </body>
</html>
