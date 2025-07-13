<div class="clearfix ">
    <div class="ticket-details-container d-flex">
        <div class="w-100">
            <div class="card p15 b-t w-100 ticket-comments-section">
                <?php echo view("tickets/view_data"); ?>
            </div>
        </div>
        <div class="flex-shrink-0 details-view-right-section">
            <div class="card" id="ticket-details-ticket-info"><?php echo view("tickets/ticket_info"); ?></div>

            <?php if ($login_user->user_type === "staff") { ?>
                <div class="card">
                    <div class="card-body">
                        <div class="text-center mb10">
                            <?php if ($ticket_info->client_id) { ?>
                                <?php if ($ticket_info->requested_by) { ?>
                                    <div class="avatar avatar-xs mb5">
                                        <img src="<?php echo get_avatar($ticket_info->requested_by_avatar); ?>" alt="..." />
                                    </div>
                                    <div><?php echo anchor(get_uri("clients/contact_profile/" . $ticket_info->requested_by), $ticket_info->requested_by_name ? $ticket_info->requested_by_name : "", array("class" => "dark")); ?></div>
                                <?php } else {
                                    echo $ticket_info->company_name ? anchor(get_uri("clients/view/" . $ticket_info->client_id), $ticket_info->company_name) : "-";
                                } ?>
                            <?php } else {
                                echo $ticket_info->creator_name . " [" . app_lang("unknown_client") . "]";
                            } ?>
                        </div>

                        <ul class="list-group info-list">

                            <?php if ($ticket_info->company_name && $ticket_info->requested_by && ($ticket_info->company_name != $ticket_info->requested_by)) { ?>
                                <li class="list-group-item">
                                    <span title="<?php echo app_lang("client"); ?>"><i data-feather="briefcase" class="icon-16 mr5"></i> <?php echo $ticket_info->company_name ? anchor(get_uri("clients/view/" . $ticket_info->client_id), $ticket_info->company_name, array("class" => "dark")) : "-"; ?></span>
                                </li>
                            <?php } ?>

                            <?php if ($ticket_info->client_id && $ticket_info->company_phone) { ?>
                                <li class="list-group-item">
                                    <span title="<?php echo app_lang("phone"); ?>"><i data-feather="phone" class="icon-16 mr5"></i> <?php echo $ticket_info->company_phone; ?></span>
                                </li>
                            <?php } ?>
                            <?php if ($ticket_info->client_id && ($ticket_info->total_tickets > 1)) { ?>
                                <li class="list-group-item">
                                    <span"><i data-feather="package" class="icon-16 mr5"></i> <?php echo anchor(get_uri("tickets/index/all/0/" . $ticket_info->client_id), $ticket_info->total_tickets . " " . app_lang("tickets"), array("class" => "dark")); ?></span>
                                </li>
                            <?php } else if (!$ticket_info->client_id) { ?>
                                <li class="list-group-item">
                                    <span title="<?php echo app_lang("email"); ?>"><i data-feather="mail" class="icon-16 mr5"></i> <?php echo $ticket_info->creator_email ? $ticket_info->creator_email : "-"; ?></span>
                                </li>
                            <?php } ?>

                        </ul>
                    </div>
                </div>

                <div id="ticket-tasks-section">
                    <?php echo view("tickets/tasks/index", array("ticket_info" => $ticket_info)); ?>
                </div>
            <?php } ?>

            <?php if (can_access_reminders_module()) { ?>
                <div class="card reminders-card" id="ticket-reminders">
                    <div class="card-header fw-bold">
                        <i data-feather="clock" class="icon-16"></i> &nbsp;<?php echo app_lang("reminders") . " (" . app_lang('private') . ")"; ?>
                    </div>
                    <div class="card-body">
                        <?php echo view("reminders/reminders_view_data", array("ticket_id" => $ticket_info->id, "hide_form" => true, "reminder_view_type" => "ticket")); ?>
                    </div>
                </div>
            <?php } ?>

            <?php
            $pinned_status = "hide";
            if (count($pinned_comments)) {
                $pinned_status = "";
            }
            ?>

            <div class="card <?php echo $pinned_status; ?>" id="ticket-pinned-comments">
                <div class="card-header fw-bold">
                    <i data-feather="map-pin" class="icon-16"></i> &nbsp;<?php echo app_lang("pinned_comments"); ?>
                </div>
                <div class="card-body">
                    <?php echo view("lib/pin_comments/comments_list"); ?>
                </div>
            </div>
        </div>
    </div>
</div>