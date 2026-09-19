<div id="page-content" class="page-wrapper pb0 clearfix full-width-button">
    <div class="card">
        <ul class="nav nav-tabs bg-white title" role="tablist">
            <li class="title-tab">
                <h4 class="pl15 pt10 pr15"><?php echo app_lang("talent"); ?></h4>
            </li>

            <?php echo view('Talent_Management\Views\talent\tabs', array("active_tab" => "talent_list")); ?>

            <div class="tab-title clearfix no-border">
                <div class="title-button-group">
                    <?php echo modal_anchor(get_uri("talent/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_talent'), array("class" => "btn btn-default", "title" => app_lang('add_talent'))); ?>
                </div>
            </div>
        </ul>

        <div class="table-responsive">
            <table id="talent-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-table").appTable({
            source: '<?php echo_uri("talent/list_data"); ?>',
            order: [[0, "asc"]],
            columns: [
                {title: '<?php echo app_lang("name"); ?>', "class": "all"},
                {title: '<?php echo app_lang("profession"); ?>'},
                {title: '<?php echo app_lang("contact_number"); ?>'},
                {title: '<?php echo app_lang("talent_email_address"); ?>'}
                <?php echo $custom_field_headers; ?>,
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-right option w100"}
            ],
            printColumns: [0, 1, 2, 3]
        });
    });
</script>
