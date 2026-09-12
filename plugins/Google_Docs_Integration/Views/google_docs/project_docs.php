<div class="card border-top-0 rounded-top-0">
    <div class="tab-title clearfix">
        <h4><?php echo app_lang("google_docs"); ?></h4>
        <div class="title-button-group">
            <?php
            if ($can_manage) {
                echo modal_anchor(get_uri("google_docs/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_document"), array("class" => "btn btn-default", "title" => app_lang("add_document"), "data-post-project_id" => $project_id));
            }
            ?>
        </div>
    </div>
    <div class="table-responsive">
        <table id="project-google-docs-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#project-google-docs-table").appTable({
            source: '<?php echo_uri("google_docs/list_data/" . $project_id); ?>',
            order: [[0, "asc"]],
            columns: [
                {title: '<?php echo app_lang("title"); ?>', "class": "all"},
                {title: '<?php echo app_lang("description"); ?>'},
                {title: '<?php echo app_lang("created_by"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ],
            printColumns: [0, 1, 2]
        });
    });
</script>
