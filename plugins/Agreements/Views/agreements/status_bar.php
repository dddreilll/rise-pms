<div class="col-md-12 mb15">
    <strong><?php echo app_lang("client"); ?>: </strong>
    <?php
    if (!empty($document->client_id) && !empty($client_name)) {
        echo anchor(get_uri("clients/view/" . $document->client_id), $client_name);
    } else {
        echo "-";
    }
    ?>
</div>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("status"); ?>: </strong>
    <?php echo $status_label; ?>
</div>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("agreements_document_type"); ?>: </strong>
    <?php echo $document->document_type === "pdf" ? app_lang("agreements_document_type_pdf") : app_lang("agreements_document_type_html"); ?>
</div>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("agreements_signing_mode"); ?>: </strong>
    <?php echo $document->signing_mode === "sequential" ? app_lang("agreements_signing_mode_sequential") : app_lang("agreements_signing_mode_parallel"); ?>
</div>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("agreements_version"); ?>: </strong>
    <?php echo $document->version; ?>
</div>

<?php if (!empty($document->project_id) && !empty($project_title)) { ?>
    <div class="col-md-12 mb15">
        <strong><?php echo app_lang("project"); ?>: </strong>
        <?php echo anchor(get_uri("projects/view/" . $document->project_id), $project_title); ?>
    </div>
<?php } ?>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("agreements_expires_at"); ?>: </strong>
    <?php echo $document->expires_at ? $document->expires_at : "-"; ?>
</div>

<div class="col-md-12 mb15">
    <strong><?php echo app_lang("created_by"); ?>: </strong>
    <?php echo !empty($document->created_by_user) ? $document->created_by_user : "-"; ?>
</div>
