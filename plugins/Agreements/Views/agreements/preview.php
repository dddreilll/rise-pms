<div class="card no-border clearfix mb0">
    <div class="bg-all-white p20">
        <div class="agreement-preview-container">
            <?php if ($document->document_type === "html") { ?>
                <div class="agreements-content-preview">
                    <?php echo $preview_html; ?>
                </div>
            <?php } else if ($pdf_url) { ?>
                <iframe src="<?php echo $pdf_url; ?>" class="agreements-pdf-frame" style="width:100%;height:640px;border:1px solid #ddd;"></iframe>
            <?php } else { ?>
                <p><?php echo app_lang("no_record_found"); ?></p>
            <?php } ?>
        </div>
    </div>
</div>
