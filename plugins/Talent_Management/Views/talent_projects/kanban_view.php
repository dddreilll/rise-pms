<div id="project-talent-kanban-wrapper">
    <?php
    $columns_data = array();

    foreach ($assignments as $item) {

        $existing_items = get_array_value($columns_data, $item->talent_status_id);
        if (!$existing_items) {
            $existing_items = "";
        }

        $name = $item->preferred_name ? $item->preferred_name : $item->legal_name;
        $profession = $item->on_screen_title ? "<div class='float-start'><i data-feather='briefcase' class='icon-14 text-off mr5'></i> " . $item->on_screen_title . "</div>" : "";

        $open_in_new_tab = anchor(get_uri("talent/view/" . $item->id), "<i data-feather='external-link' class='icon-14'></i>", array("target" => "_blank", "class" => "float-end", "title" => app_lang("details")));

        $card = $existing_items . "<span class='lead-kanban-item kanban-item' data-id='$item->talent_project_id' data-sort='$item->new_sort' data-post-id='$item->talent_project_id'>
                    <div class='selection-pe-none'><span class='avatar'><img src='" . get_avatar($item->profile_image) . "'></span>" . anchor(get_uri("talent/view/" . $item->id), $name) . $open_in_new_tab . "</div><div class='clearfix'></div>" .
            "<div class='mt15'>" . $profession . "</div>" . "<div class='clearfix'></div>" .
            "<div class='mt10'>" . talent_contract_cell_html($item) . "</div></span>";

        $columns_data[$item->talent_status_id] = $card;
    }
    ?>

    <ul id="project-talent-kanban-container" class="kanban-container clearfix">

        <?php foreach ($columns as $column) { ?>
            <li class="kanban-col">
                <div class="kanban-col-title" style="border-bottom: 3px solid <?php echo $column->color ? $column->color : "#2e4053"; ?>;"> <?php echo $column->title; ?> </div>

                <div id="project-talent-kanban-item-list-<?php echo $column->id; ?>" class="kanban-item-list" data-talent_status_id="<?php echo $column->id; ?>">
                    <?php echo get_array_value($columns_data, $column->id); ?>
                </div>
            </li>
        <?php } ?>

    </ul>
</div>

<img id="project-talent-move-icon" class="hide" src="<?php echo get_file_uri("assets/images/move.png"); ?>" alt="..." />

<script type="text/javascript">
    var projectTalentKanbanContainerWidth = "";

    var adjustProjectTalentKanbanHeightWidth = function () {

        if (!$("#project-talent-kanban-container").length) {
            return false;
        }

        var totalColumns = "<?php echo $total_columns ?>";
        var columnWidth = (335 * totalColumns) + 5;

        if (columnWidth > projectTalentKanbanContainerWidth) {
            $("#project-talent-kanban-container").css({width: columnWidth + "px"});
        } else {
            $("#project-talent-kanban-container").css({width: "100%"});
        }

        if ($("#project-talent-kanban-wrapper")[0].offsetWidth < $("#project-talent-kanban-wrapper")[0].scrollWidth) {
            $("#project-talent-kanban-wrapper").css("overflow-x", "scroll");
        } else {
            $("#project-talent-kanban-wrapper").css("overflow-x", "hidden");
        }

        $("#project-talent-kanban-container .kanban-item-list").each(function (index) {
            if ($(this)[0].offsetHeight < $(this)[0].scrollHeight) {
                $(this).css("overflow-y", "scroll");
            } else {
                $(this).css("overflow-y", "hidden");
            }
        });
    };

    var saveProjectTalentStatusAndSort = function ($item, status) {
        appLoader.show();

        var $prev = $item.prev(),
                $next = $item.next(),
                prevSort = 0, nextSort = 0, newSort = 0,
                step = 100000, stepDiff = 500,
                id = $item.attr("data-id");

        if ($prev && $prev.attr("data-sort")) {
            prevSort = $prev.attr("data-sort") * 1;
        }

        if ($next && $next.attr("data-sort")) {
            nextSort = $next.attr("data-sort") * 1;
        }

        if (!prevSort && nextSort) {
            newSort = nextSort - stepDiff;
        } else if (!nextSort && prevSort) {
            newSort = prevSort + step;
        } else if (prevSort && nextSort) {
            newSort = (prevSort + nextSort) / 2;
        } else if (!prevSort && !nextSort) {
            newSort = step * 100;
        }

        $item.attr("data-sort", newSort);

        $.ajax({
            url: '<?php echo_uri("talent_projects/save_sort_and_status") ?>',
            type: "POST",
            data: {id: id, sort: newSort, talent_status_id: status},
            success: function () {
                appLoader.hide();
            }
        });
    };

    $(document).ready(function () {
        projectTalentKanbanContainerWidth = $("#project-talent-kanban-container").width();

        var isChrome = !!window.chrome && !!window.chrome.webstore;

        $("#project-talent-kanban-container .kanban-item-list").each(function (index) {
            var id = this.id;

            var options = {
                animation: 150,
                group: "project-talent-kanban-item-list",
                onAdd: function (e) {
                    saveProjectTalentStatusAndSort($(e.item), $(e.item).closest(".kanban-item-list").attr("data-talent_status_id"));
                },
                onUpdate: function (e) {
                    saveProjectTalentStatusAndSort($(e.item));
                }
            };

            if (isChrome) {
                options.setData = function (dataTransfer, dragEl) {
                    var img = document.createElement("img");
                    img.src = $("#project-talent-move-icon").attr("src");
                    img.style.opacity = 1;
                    dataTransfer.setDragImage(img, 5, 10);
                };

                options.ghostClass = "kanban-sortable-ghost";
                options.chosenClass = "kanban-sortable-chosen";
            }

            Sortable.create($("#" + id)[0], options);
        });

        adjustProjectTalentKanbanHeightWidth();

        $('[data-bs-toggle="tooltip"]').tooltip();
    });

    $(window).resize(function () {
        adjustProjectTalentKanbanHeightWidth();
    });
</script>
