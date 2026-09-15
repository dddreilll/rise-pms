<div class="card no-border clearfix mb0">
    <div class="page-title clearfix p15">
        <h4><?php echo app_lang("agreements_templates"); ?></h4>
        <div class="title-button-group">
            <?php echo modal_anchor(get_uri("agreements_templates/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("agreements_add_template"), array("class" => "btn btn-default", "title" => app_lang("agreements_add_template"))); ?>
        </div>
    </div>
    <div class="table-responsive">
        <table id="agreements-settings-templates-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#agreements-settings-templates-table").appTable({
            source: '<?php echo_uri("agreements_templates/list_data"); ?>',
            columns: [
                {title: '<?php echo app_lang("agreements_title"); ?>'},
                {title: '<?php echo app_lang("agreements_document_type"); ?>'},
                {title: '<?php echo app_lang("created_date"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w120"}
            ]
        });
    });
</script>
