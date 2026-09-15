<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("agreements"); ?></h1>
            <div class="title-button-group">
                <?php
                if ($can_manage) {
                    echo modal_anchor(get_uri("agreements/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("agreements_add"), array("class" => "btn btn-default", "title" => app_lang("agreements_add")));
                    echo anchor(get_uri("agreements_templates"), "<i data-feather='copy' class='icon-16'></i> " . app_lang("agreements_templates"), array("class" => "btn btn-default"));
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
            <table id="agreements-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#agreements-table").appTable({
            source: '<?php echo_uri("agreements/list_data"); ?>',
            order: [[5, "desc"]],
            columns: [
                {title: '<?php echo app_lang("agreements_title"); ?>', "class": "all"},
                {title: '<?php echo app_lang("client"); ?>'},
                {title: '<?php echo app_lang("agreements_status"); ?>'},
                {title: '<?php echo app_lang("agreements_signing_mode"); ?>'},
                {title: '<?php echo app_lang("created_by"); ?>'},
                {title: '<?php echo app_lang("created_date"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ],
            printColumns: [0, 1, 2, 3, 4, 5]
        });
    });
</script>
