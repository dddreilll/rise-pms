<div id="page-content" class="page-wrapper clearfix">
    <link rel="stylesheet" type="text/css" href="<?php echo base_url(PLUGIN_URL_PATH . "Google_Docs_Integration/assets/css/google_docs.css"); ?>" />
    <div class="google-docs-viewer-title clearfix">
        <h4>
            <i data-feather="file-text" class="icon-16"></i>
            <?php echo $doc_info->title; ?>
        </h4>
    </div>
    <div class="google-docs-viewer-wrapper">
        <iframe id="google-docs-iframe" src="<?php echo $embed_url; ?>" allow="autoplay"></iframe>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        feather.replace();
    });
</script>
