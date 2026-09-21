<div class="card border-top-0 rounded-top-0">
    <ul class="nav nav-tabs bg-white title" role="tablist">
        <li class="title-tab">
            <h4 class="pl15 pt10 pr15"><?php echo app_lang("talent"); ?></h4>
        </li>

        <li class="nav-item" role="presentation">
            <a class="nav-link active" data-bs-toggle="tab" href="javascript:;" data-bs-target="#project-talent-list-panel"><?php echo app_lang("list"); ?></a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" data-bs-toggle="tab" href="javascript:;" data-bs-target="#project-talent-kanban-panel" id="project-talent-kanban-tab-link"><?php echo app_lang("kanban"); ?></a>
        </li>

        <div class="tab-title clearfix no-border">
            <div class="title-button-group">
                <?php echo modal_anchor(get_uri("talent_project_agreements/modal_form"), "<i data-feather='file-text' class='icon-16'></i> " . app_lang("talent_project_agreements"), array("class" => "btn btn-default", "title" => app_lang("talent_project_agreements"), "data-post-project_id" => $project_id)); ?>
                <?php echo modal_anchor(get_uri("talent_projects/modal_assign_form/" . $project_id), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("assign_talent"), array("class" => "btn btn-default", "title" => app_lang("assign_talent"))); ?>
            </div>
        </div>
    </ul>

    <div class="tab-content">
        <div class="text-off pl15 pr15 pt10" id="project-talent-agreements-summary"><?php echo esc($agreements_summary); ?></div>

        <div role="tabpanel" class="tab-pane fade show active" id="project-talent-list-panel">
            <div class="table-responsive">
                <table id="project-talent-table" class="display" cellspacing="0" width="100%"></table>
            </div>
        </div>

        <div role="tabpanel" class="tab-pane fade" id="project-talent-kanban-panel">
            <div id="project-talent-kanban-filters"></div>
            <div id="project-talent-load-kanban"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var projectTalentKanbanLoaded = false;

    //the Send contract modal calls this, so the badge and the card's new stage show up without a page reload
    window.reloadProjectTalent = function () {
        $("#project-talent-table").appTable({reload: true});

        if (projectTalentKanbanLoaded) {
            $.ajax({
                url: '<?php echo_uri("talent_projects/kanban_data/" . $project_id) ?>',
                type: "POST",
                success: function (result) {
                    $("#project-talent-load-kanban").html(result);
                }
            });
        }
    };

    $(document).ready(function () {
        $("#project-talent-table").appTable({
            source: '<?php echo_uri("talent_projects/list_data/" . $project_id); ?>',
            columns: [
                {title: '<?php echo app_lang("name"); ?>', "class": "all"},
                {title: '<?php echo app_lang("on_screen_title"); ?>'},
                {title: '<?php echo app_lang("status"); ?>'},
                {title: '<?php echo app_lang("talent_contract"); ?>'},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ]
        });

        $("#project-talent-kanban-tab-link").on("shown.bs.tab", function () {
            if (projectTalentKanbanLoaded) {
                return;
            }
            projectTalentKanbanLoaded = true;

            $("#project-talent-kanban-filters").appFilters({
                source: '<?php echo_uri("talent_projects/kanban_data/" . $project_id) ?>',
                targetSelector: '#project-talent-load-kanban',
                smartFilterIdentity: "project_talent_kanban_<?php echo $project_id; ?>",
            });
        });
    });
</script>
