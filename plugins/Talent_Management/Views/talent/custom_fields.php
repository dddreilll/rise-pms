<div id="page-content" class="page-wrapper pb0 clearfix full-width-button">
    <div class="card">
        <ul class="nav nav-tabs bg-white title" role="tablist">
            <li class="title-tab">
                <h4 class="pl15 pt10 pr15"><?php echo app_lang("talent"); ?></h4>
            </li>

            <?php echo view('Talent_Management\Views\talent\tabs', array("active_tab" => "talent_custom_fields")); ?>

            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php echo modal_anchor(get_uri("custom_fields/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_field'), array("class" => "btn btn-default", "title" => app_lang('add_field'), "data-post-related_to" => "talent")); ?>
                </div>
            </div>
        </ul>

        <div class="mb0 p20">
            <div class="table-responsive general-form">
                <table id="talent-custom-field-table" class="display no-thead b-t b-b-only no-hover" cellspacing="0" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-custom-field-table").appTable({
            source: '<?php echo_uri("custom_fields/list_data") ?>' + "/talent",
            order: [[1, "asc"]],
            hideTools: true,
            displayLength: 100,
            columns: [
                {title: '<?php echo app_lang("title") ?>'},
                {visible: false},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-right option w100"}
            ],
            onInitComplete: function () {
                $("#talent-custom-field-table").find("tbody").attr("id", "talent-custom-field-table-sortable");
                var $selector = $("#talent-custom-field-table-sortable");

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
                            url: '<?php echo_uri("custom_fields/update_field_sort_values") ?>' + "/talent",
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
