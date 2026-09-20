<div id="page-content" class="page-wrapper pb0 clearfix">
    <div class="card">
        <ul class="nav nav-tabs bg-white title" role="tablist">
            <li class="title-tab">
                <h4 class="pl15 pt10 pr15"><?php echo app_lang("talent"); ?></h4>
            </li>

            <?php echo view('Talent_Management\Views\talent\tabs', array("active_tab" => "talent_contract_templates")); ?>

            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php echo modal_anchor(get_uri("talent_contract_templates/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_talent_contract_template'), array("class" => "btn btn-default", "title" => app_lang('add_talent_contract_template'))); ?>
                </div>
            </div>
        </ul>

        <div class="p15">
            <div class="row">
                <div class="col-md-4">
                    <div class="table-responsive">
                        <table id="talent-contract-template-table" class="display clickable no-thead b-b-only" cellspacing="0" width="100%"></table>
                    </div>
                </div>
                <div class="col-md-8">
                    <div id="talent-contract-template-details-section">
                        <div class="text-center p15 box card" style="min-height: 150px;">
                            <div class="box-content" style="vertical-align: middle; height: 100%">
                                <div><?php echo app_lang("talent_contract_select_a_template"); ?></div>
                                <span data-feather="code" width="6rem" height="6rem" style="color:rgba(128, 128, 128, 0.1)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-contract-template-table").appTable({
            source: '<?php echo_uri("talent_contract_templates/list_data") ?>',
            columns: [
                {title: '<?php echo app_lang("title"); ?>'},
                {title: '', "class": "text-center option w100"}
            ],
            hideTools: true,
            displayLength: 1000
        });

        //load the clicked template's editor into the side panel (the edit/delete icons keep their own behavior)
        $("#talent-contract-template-table").on("click", "tr", function (e) {
            if ($(e.target).closest("a.edit, a.delete").length || $(this).hasClass("active")) {
                return;
            }

            var templateId = $(this).find(".talent-contract-template-row").attr("data-id");
            if (!templateId) {
                return;
            }

            appLoader.show();
            $("#talent-contract-template-table tr.active").removeClass("active");
            $(this).addClass("active");

            $.ajax({
                url: "<?php echo get_uri("talent_contract_templates/form"); ?>/" + templateId,
                success: function (result) {
                    appLoader.hide();
                    $("#talent-contract-template-details-section").html(result);
                }
            });
        });
    });
</script>
