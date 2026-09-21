<?php
//The talent's page for one signing link: one or several agreements, each with its text and state, then one signing section (one drawn
//signature, a tick per agreement). $documents: array(contract, status, html, label), $waiting_count, $bundle, $token, $signer_name, $masked_email.
$multi = count($documents) > 1;

//the agreement that is open when the page loads: the first one still waiting, else the first
$open_index = 0;
foreach ($documents as $index => $document) {
    if ($document["status"] === "sent") {
        $open_index = $index;
        break;
    }
}
$consent_key = $multi ? "talent_sign_consent_many" : "talent_sign_consent";
?>
<!DOCTYPE html>
<html lang="en">
    <?php echo view('includes/head'); ?>
    <body>
        <style>
            .talent-sign-wrap { max-width: 900px; margin: 0 auto; }
            .talent-contract-body { padding: 30px; overflow-wrap: anywhere; }
            .talent-contract-body img { max-width: 100%; height: auto; }
            .talent-doc-head { cursor: pointer; }
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
                                <?php if ($multi) { ?>
                                    <strong><?php echo sprintf(app_lang("talent_sign_bundle_title"), count($documents)); ?></strong>
                                    <div class="text-off"><?php echo esc($company_name); ?></div>
                                <?php } else { ?>
                                    <strong><?php echo esc($documents[0]["contract"]->title); ?></strong>
                                    <div class="text-off"><?php echo esc($documents[0]["label"]); ?></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <?php foreach ($documents as $index => $document) { ?>
                        <?php
                        $contract = $document["contract"];
                        $status = $document["status"];
                        $is_open = !$multi || $index === $open_index;
                        ?>
                        <div class="card no-border mt15 talent-document" id="talent-document-<?php echo (int) $contract->id; ?>">
                            <?php if ($multi) { ?>
                                <div class="p15 b-b talent-doc-head" data-document="<?php echo (int) $contract->id; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo esc($contract->title); ?></strong>
                                            <span class="text-off ml5"><?php echo esc($document["label"]); ?></span>
                                        </div>
                                        <div class="text-end" style="white-space: nowrap;">
                                            <?php echo talent_contract_status_html($contract->status, $contract->token_expires_at); ?>
                                            <span class="ml10 text-off talent-doc-toggle-label"><?php echo app_lang($is_open ? "talent_sign_hide" : "talent_sign_read"); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="talent-contract-body bg-white <?php echo $is_open ? "" : "hide"; ?>" id="talent-document-body-<?php echo (int) $contract->id; ?>"><?php echo $document["html"]; ?></div>

                            <?php if ($status === "signed") { ?>
                                <div class="p15 b-t">
                                    <div class="text-success mb10"><i data-feather="check-circle" class="icon-16"></i>
                                        <?php echo sprintf(app_lang("talent_sign_signed_banner"), esc($contract->signer_name), esc(format_to_date($contract->signed_at, true))); ?>
                                    </div>
                                    <div>
                                        <?php echo anchor(get_uri("talent_sign/download/" . (int) $bundle->id . "/" . $token . "/" . (int) $contract->id), "<i data-feather='download' class='icon-16'></i> " . app_lang("talent_sign_download"), array("class" => "btn btn-default")); ?>
                                    </div>
                                </div>
                            <?php } else if ($status === "sent") { ?>
                                <div class="p15 b-t">
                                    <a href="javascript:;" class="talent-decline-toggle" data-contract="<?php echo (int) $contract->id; ?>"><?php echo app_lang($multi ? "talent_sign_decline_this" : "talent_sign_decline_link"); ?></a>

                                    <div class="hide mt15" id="talent-decline-panel-<?php echo (int) $contract->id; ?>">
                                        <p><?php echo app_lang("talent_sign_decline_intro"); ?></p>
                                        <textarea id="talent-decline-reason-<?php echo (int) $contract->id; ?>" class="form-control mb10" rows="3" maxlength="1000" placeholder="<?php echo app_lang("talent_sign_decline_reason"); ?>"></textarea>
                                        <button type="button" class="btn btn-danger talent-decline-submit" data-contract="<?php echo (int) $contract->id; ?>"><?php echo app_lang("talent_sign_decline_submit"); ?></button>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <div class="p15 b-t">
                                    <div class="text-off"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("talent_sign_state_" . $status); ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if ($waiting_count > 0) { ?>
                        <div class="card p15 mt15">
                            <h4><?php echo app_lang($multi ? "talent_sign_heading_many" : "talent_sign_heading"); ?></h4>
                            <div class="text-off mb15"><?php echo sprintf(app_lang("talent_sign_expires"), esc(format_to_date($bundle->token_expires_at, true))); ?></div>

                            <?php echo form_open(get_uri("talent_sign/sign"), array("id" => "talent-sign-form", "class" => "general-form", "role" => "form")); ?>
                            <input type="hidden" name="bundle_id" value="<?php echo (int) $bundle->id; ?>" />
                            <input type="hidden" name="token" value="<?php echo esc($token); ?>" />

                            <div class="form-group">
                                <?php echo sprintf(app_lang("talent_sign_signing_as"), "<strong>" . esc($signer_name) . "</strong>"); ?>
                            </div>

                            <div class="form-group">
                                <label for="talent-sign-email"><?php echo app_lang("talent_sign_email"); ?></label>
                                <input type="email" name="email" id="talent-sign-email" class="form-control" autocomplete="email"
                                       data-rule-required="true" data-msg-required="<?php echo app_lang("field_required"); ?>"
                                       data-rule-email="true" data-msg-email="<?php echo app_lang("enter_valid_email"); ?>" />
                                <div class="text-off mt5"><?php echo sprintf(app_lang($multi ? "talent_sign_email_help_many" : "talent_sign_email_help"), esc($masked_email)); ?></div>
                            </div>

                            <div class="form-group">
                                <label><?php echo app_lang("talent_sign_signature"); ?></label>
                                <div class="b-a"><canvas id="talent-signature-canvas" height="200"></canvas></div>
                                <input type="hidden" name="signature" id="talent-signature-data" class="validate-hidden"
                                       data-rule-required="true" data-msg-required="<?php echo app_lang("talent_sign_signature_required"); ?>" />
                                <a href="javascript:;" id="talent-signature-clear" class="mt5 d-inline-block"><?php echo app_lang("talent_sign_clear"); ?></a>
                                <?php if ($multi) { ?>
                                    <div class="text-off mt5"><?php echo app_lang("talent_sign_signature_reused"); ?></div>
                                <?php } ?>
                            </div>

                            <div class="form-group">
                                <?php if ($multi) { ?>
                                    <label class="mb10"><?php echo app_lang("talent_sign_tick_help"); ?></label>
                                    <?php if ($waiting_count > 1) { ?>
                                        <div class="mb10">
                                            <label class="d-flex align-items-start mb5">
                                                <input type="checkbox" id="talent-tick-all" class="mt5 mr10" />
                                                <span class="text-off"><?php echo app_lang("talent_sign_tick_all"); ?></span>
                                            </label>
                                        </div>
                                    <?php } ?>
                                <?php } ?>

                                <?php foreach ($documents as $document) { ?>
                                    <?php if ($document["status"] === "sent") { ?>
                                        <label class="d-flex align-items-start mb5">
                                            <input type="checkbox" name="contract_ids[]" value="<?php echo (int) $document["contract"]->id; ?>" class="talent-consent-box mt5 mr10" />
                                            <span>
                                                <?php if ($multi) { ?>
                                                    <strong><?php echo esc($document["contract"]->title); ?></strong>
                                                    <a href="#talent-document-<?php echo (int) $document["contract"]->id; ?>" class="talent-read-link ml5" data-document="<?php echo (int) $document["contract"]->id; ?>"><?php echo app_lang("talent_sign_read"); ?></a>
                                                <?php } else { ?>
                                                    <?php echo esc(app_lang($consent_key)); ?>
                                                <?php } ?>
                                            </span>
                                        </label>
                                    <?php } ?>
                                <?php } ?>

                                <?php if ($multi) { ?>
                                    <div class="text-off mt10"><?php echo esc(app_lang($consent_key)); ?></div>
                                <?php } ?>

                                <input type="hidden" name="consent_any" id="talent-consent-any" class="validate-hidden"
                                       data-rule-required="true" data-msg-required="<?php echo app_lang($multi ? "talent_sign_none_ticked" : "talent_sign_consent_required"); ?>" />
                            </div>

                            <button type="submit" class="btn btn-success"><i data-feather="check-circle" class="icon-16"></i> <span id="talent-sign-submit-label"><?php echo app_lang("talent_sign_submit"); ?></span></button>
                            <?php echo form_close(); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            $(document).ready(function () {
                initScrollbar('#talent-sign-scrollbar', {setHeight: $(window).height()});
                $("#custom-theme-color").remove();

                //open or close one agreement's text
                var toggleDocument = function (id, forceOpen) {
                    var $body = $("#talent-document-body-" + id);
                    var open = forceOpen === true ? true : $body.hasClass("hide");
                    $body.toggleClass("hide", !open);
                    $("#talent-document-" + id).find(".talent-doc-toggle-label").text(open ? "<?php echo esc(app_lang("talent_sign_hide"), "js"); ?>" : "<?php echo esc(app_lang("talent_sign_read"), "js"); ?>");
                };
                $(".talent-doc-head").on("click", function () {
                    toggleDocument($(this).attr("data-document"));
                });
                $(".talent-read-link").on("click", function (event) {
                    event.preventDefault();
                    var id = $(this).attr("data-document");
                    toggleDocument(id, true);
                    document.getElementById("talent-document-" + id).scrollIntoView({behavior: "smooth", block: "start"});
                });

<?php if ($waiting_count > 0) { ?>
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

                    //the ticked agreements: the form needs at least one, and the button says how many will be signed
                    var singularLabel = "<?php echo esc(app_lang("talent_sign_submit"), "js"); ?>";
                    var manyLabel = "<?php echo esc(app_lang("talent_sign_submit_many"), "js"); ?>";
                    var updateTicks = function () {
                        var total = $(".talent-consent-box").length;
                        var ticked = $(".talent-consent-box:checked").length;
                        $("#talent-consent-any").val(ticked ? "1" : "");
                        $("#talent-sign-submit-label").text(ticked > 1 ? manyLabel.replace("%s", ticked) : singularLabel);
                        $("#talent-tick-all").prop("checked", total > 0 && ticked === total);
                    };
                    $(".talent-consent-box").on("change", updateTicks);
                    $("#talent-tick-all").on("change", function () {
                        $(".talent-consent-box").prop("checked", this.checked);
                        updateTicks();
                    });
                    updateTicks();

                    $("#talent-sign-form").appForm({
                        isModal: false,
                        onSuccess: function (result) {
                            appAlert.success(result.message, {duration: 8000});
                            setTimeout(function () {
                                location.reload();
                            }, 900);
                        }
                    });

                    $(".talent-decline-toggle").on("click", function () {
                        $("#talent-decline-panel-" + $(this).attr("data-contract")).toggleClass("hide");
                    });

                    //declining one agreement leaves the others in the link as they are
                    $(".talent-decline-submit").on("click", function () {
                        var $button = $(this).attr("disabled", "disabled");
                        var contractId = $button.attr("data-contract");
                        $.ajax({
                            url: "<?php echo get_uri("talent_sign/decline"); ?>",
                            type: "POST",
                            dataType: "json",
                            data: {bundle_id: "<?php echo (int) $bundle->id; ?>", contract_id: contractId, token: "<?php echo esc($token); ?>", reason: $("#talent-decline-reason-" + contractId).val()},
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
