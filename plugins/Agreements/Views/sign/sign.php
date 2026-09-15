<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $document->title; ?></title>
    <link rel="stylesheet" href="<?php echo base_url("assets/bootstrap/css/bootstrap.min.css"); ?>" />
    <link rel="stylesheet" href="<?php echo base_url(PLUGIN_URL_PATH . "Agreements/assets/css/agreements.css"); ?>" />
    <script src="<?php echo base_url("assets/js/jquery-3.5.1.min.js"); ?>"></script>
    <script src="<?php echo base_url("assets/js/signature/signature_pad.min.js"); ?>"></script>
    <?php if ($document->document_type === "pdf" && $pdf_url) { ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <?php } ?>
</head>
<body class="agreements-sign-page">
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0"><?php echo $document->title; ?></h3>
            <small><?php echo $signatory->name; ?> &lt;<?php echo $signatory->email; ?>&gt;</small>
        </div>
        <div class="card-body">
            <?php if ($already_signed) { ?>
                <div class="alert alert-success"><?php echo app_lang("agreements_already_signed"); ?></div>
            <?php } else if (!$can_act) { ?>
                <div class="alert alert-warning"><?php echo app_lang("agreements_not_your_turn"); ?></div>
            <?php } ?>

            <?php if ($document->document_type === "html") { ?>
                <div class="agreements-content-preview mb-4"><?php echo $content_html; ?></div>
            <?php } else if ($pdf_url) { ?>
                <div id="sign-pdf-stage" class="agreements-pdf-stage mb-4">
                    <canvas id="sign-pdf-canvas"></canvas>
                </div>
                <div class="mb-2">
                    <button type="button" class="btn btn-sm btn-secondary" id="sign-prev-page">Prev</button>
                    <span>Page <span id="sign-page-num">1</span></span>
                    <button type="button" class="btn btn-sm btn-secondary" id="sign-next-page">Next</button>
                </div>
            <?php } ?>

            <form id="sign-form">
                <h5><?php echo app_lang("agreements_your_signature"); ?></h5>
                <canvas id="main-signature-pad" class="border" width="500" height="150"></canvas>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-secondary" id="clear-main-sig">Clear</button>
                </div>
                <input type="hidden" name="signature_data" id="signature_data" />

                <?php if ($can_act && !$already_signed) { ?>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="submit-sign"><?php echo app_lang("agreements_sign"); ?></button>
                        <button type="button" class="btn btn-outline-danger" id="decline-btn"><?php echo app_lang("agreements_decline"); ?></button>
                    </div>
                    <div id="decline-box" class="mt-3 hide">
                        <textarea id="decline_reason" class="form-control mb-2" placeholder="<?php echo app_lang("agreements_decline_reason"); ?>"></textarea>
                        <button type="button" class="btn btn-danger" id="confirm-decline"><?php echo app_lang("agreements_decline"); ?></button>
                    </div>
                <?php } ?>
            </form>
            <div id="sign-message" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
    window.AgreementsSign = {
        token: <?php echo json_encode($token); ?>,
        submitUrl: <?php echo json_encode(get_uri("agreements_sign/submit/" . $token)); ?>,
        declineUrl: <?php echo json_encode(get_uri("agreements_sign/decline/" . $token)); ?>,
        pdfUrl: <?php echo json_encode($pdf_url); ?>,
        documentType: <?php echo json_encode($document->document_type); ?>
    };
</script>
<script src="<?php echo base_url(PLUGIN_URL_PATH . "Agreements/assets/js/agreements_sign.js"); ?>"></script>
</body>
</html>
