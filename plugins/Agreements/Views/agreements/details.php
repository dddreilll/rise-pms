<div class="clearfix default-bg details-view-container">
    <div class="row">
        <div class="col-md-9 d-flex">
            <div class="card p15 w-100 pt0">
                <div id="page-content" class="clearfix grid-button">
                    <div style="max-width: 1000px; margin: auto;">
                        <div class="no-border clearfix">
                            <ul data-bs-toggle="ajax-tab" class="nav nav-tabs bg-white title" role="tablist">
                                <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#agreement-signatories"><?php echo app_lang("agreements_signatories"); ?></a></li>
                                <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("agreements/editor/" . $document->id); ?>" data-bs-target="#agreement-editor"><?php echo app_lang("agreements_editor"); ?></a></li>
                                <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("agreements/preview/" . $document->id); ?>" data-bs-target="#agreement-preview" data-reload="true"><?php echo app_lang("preview"); ?></a></li>
                            </ul>

                            <div class="tab-content">
                                <div role="tabpanel" class="tab-pane fade" id="agreement-signatories">
                                    <?php echo view("Agreements\Views\agreements\signatories_panel"); ?>
                                </div>
                                <div role="tabpanel" class="tab-pane fade" id="agreement-editor"></div>
                                <div role="tabpanel" class="tab-pane fade" id="agreement-preview"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 d-grid">
            <div class="card p20">
                <div class="card-body">
                    <div id="agreement-status-bar">
                        <?php echo view("Agreements\Views\agreements\status_bar"); ?>
                    </div>
                </div>
            </div>

            <?php echo view("Agreements\Views\agreements\signer_info"); ?>
        </div>
    </div>
</div>
