<div id="page-content" class="page-wrapper pb0 clearfix full-width-button">
    <div class="card">
        <ul class="nav nav-tabs bg-white title" role="tablist">
            <li class="title-tab">
                <h4 class="pl15 pt10 pr15"><?php echo app_lang("talent"); ?></h4>
            </li>

            <?php echo view('Talent_Management\Views\talent\tabs', array("active_tab" => "talent_status")); ?>

            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php echo modal_anchor(get_uri("talent_status/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_talent_status'), array("class" => "btn btn-default", "title" => app_lang('add_talent_status'))); ?>
                </div>
            </div>
        </ul>

        <div class="table-responsive">
            <table id="talent-status-table" class="display no-thead b-b-only no-hover" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-status-table").appTable({
            source: '<?php echo_uri("talent_status/list_data") ?>',
            order: [[0, "asc"]],
            hideTools: true,
            displayLength: 100,
            columns: [
                {visible: false},
                {title: '<?php echo app_lang("title"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ],
            onInitComplete: function () {
                $("#talent-status-table").find("tbody").attr("id", "talent-status-table-sortable");
                var $selector = $("#talent-status-table-sortable");

                Sortable.create($selector[0], {
                    animation: 150,
                    chosenClass: "sortable-chosen",
                    ghostClass: "sortable-ghost",
                    onUpdate: function (e) {
                        appLoader.show();
                        var data = "";
                        $.each($selector.find(".field-row"), function (index, ele) {
                            if (data) {
                                data += ",";
                            }
                            data += $(ele).attr("data-id") + "-" + index;
                        });

                        $.ajax({
                            url: '<?php echo_uri("talent_status/update_field_sort_values") ?>',
                            type: "POST",
                            data: {sort_values: data},
                            success: function () {
                                appLoader.hide();
                            }
                        });
                    }
                });
            }
        });
    });
</script>
