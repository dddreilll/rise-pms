<?php if ($signatories) { ?>
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("agreements_signatories"); ?></h4>
        </div>
        <div class="p15">
            <?php foreach ($signatories as $s) { ?>
                <div class="mb15 b-b pb10">
                    <div><strong><?php echo app_lang("name"); ?>: </strong><?php echo $s->name; ?></div>
                    <div><strong><?php echo app_lang("email"); ?>: </strong><?php echo $s->email; ?></div>
                    <div><strong><?php echo app_lang("agreements_status"); ?>: </strong><?php echo $s->status; ?></div>
                    <?php if ($s->signed_at) { ?>
                        <div><strong><?php echo app_lang("date"); ?>: </strong><?php echo $s->signed_at; ?></div>
                    <?php } ?>
                    <?php if ($s->signature_path) {
                        $sig_url = agreements_files_url($s->signature_path);
                        ?>
                        <div class="mt5"><strong><?php echo app_lang("signature"); ?>: </strong><br />
                            <img class="signature-image" src="<?php echo $sig_url; ?>" alt="signature" style="max-height:60px;" />
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>
