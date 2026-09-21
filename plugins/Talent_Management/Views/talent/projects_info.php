<div class="card border-top-0 rounded-top-0">
    <div class="card-header">
        <h4><?php echo app_lang("projects"); ?></h4>
    </div>
    <div class="table-responsive">
        <table id="talent-assigned-projects-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#talent-assigned-projects-table").appTable({
            source: '<?php echo_uri("talent_projects/list_for_talent/" . $model_info->id); ?>',
            columns: [
                {title: '<?php echo app_lang("project"); ?>', "class": "all"},
                {title: '<?php echo app_lang("status"); ?>'},
                {title: '<?php echo app_lang("talent_contract_agreements"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ]
        });
    });

    //the Send contract modal calls this after sending, so the new badge shows without a page reload
    window.reloadProjectTalent = function () {
        $("#talent-assigned-projects-table").appTable({reload: true});
    };
</script>
