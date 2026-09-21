<div class="modal-body clearfix p0">
    <div class="p15">
        <div>
            <strong><?php echo esc($context->preferred_name ? $context->preferred_name : $context->legal_name); ?></strong>
            <span class="text-off ml5"><?php echo esc($context->project_title); ?></span>

            <div class="mt5">
                <?php if ($requirement["has_list"]) { ?>
                    <?php
                    $required_total = count($requirement["required"]);
                    $required_signed = $required_total - count($requirement["missing"]);
                    $any_waiting = false;
                    foreach ($states as $waiting_state) {
                        $any_waiting = $any_waiting || $waiting_state["status"] === "sent";
                    }
                    //the same colours as the badge on the list: green when done, orange when under way, grey when nothing has happened
                    $progress_color = $requirement["complete"] ? "#2e7d32" : (($required_signed > 0 || $any_waiting) ? "#ef6c00" : "#7c8798");
                    ?>
                    <span class="badge" style="background-color: <?php echo $progress_color; ?>"><?php echo sprintf(app_lang("talent_contract_progress"), $required_signed, $required_total); ?></span>
                    <span class="text-off ml5"><?php echo app_lang($requirement["complete"] ? "talent_contract_agreements_complete" : "talent_contract_agreements_incomplete"); ?></span>
                <?php } else { ?>
                    <span class="text-off"><?php echo app_lang("talent_project_agreements_none"); ?></span>
                <?php } ?>
            </div>
        </div>

    </div>

    <?php if (!$states) { ?>
        <div class="p15 pt0"><div class="alert alert-info mb0"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("talent_contract_agreements_none"); ?></div></div>
    <?php } else { ?>
        <?php //the same table markup as the Reactors list (RISE's DataTable look), with the actions as RISE's round icon buttons ?>
        <div class="table-responsive">
            <table class="display dataTable no-footer" id="talent-agreements-table" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th><?php echo app_lang("talent_contract_agreement"); ?></th>
                        <th><?php echo app_lang("status"); ?></th>
                        <th class="text-center option" style="width: 170px;"><i data-feather="menu" class="icon-16"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($states as $state) { ?>
                        <?php $contract = $state["contract"]; ?>
                        <tr>
                            <td>
                                <?php echo esc($state["title"]); ?>
                                <?php if ($state["required"]) { ?>
                                    <span class="badge badge-light text-black ml5"><?php echo app_lang("talent_contract_required_label"); ?></span>
                                <?php } else { ?>
                                    <span class="badge text-black-50 ml5"><?php echo app_lang("talent_contract_optional_label"); ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($contract) { ?>
                                    <?php echo talent_contract_status_html($contract->status, $contract->token_expires_at); ?>
                                    <?php if ($state["status"] === "signed" && $contract->signed_at) { ?>
                                        <span class="text-off ml5"><?php echo esc($contract->signed_via === "paper" ? sprintf(app_lang("talent_contract_signed_on_paper_short"), format_to_date($contract->signed_at, false)) : format_to_date($contract->signed_at, true)); ?></span>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-off"><?php echo app_lang("talent_contract_not_sent"); ?></span>
                                <?php } ?>
                            </td>
                            <td class="text-center option" style="white-space: nowrap;">
                                <?php
                                if ($contract) {
                                    echo talent_contract_view_action_html($contract->id);
                                }
                                if ($state["can_send"]) {
                                    echo talent_contract_send_action_html($talent_project_id, $state["template_id"], $state["status"] !== "", true, true);
                                }
                                if ($state["status"] !== "signed" && $state["template_id"]) {
                                    echo talent_contract_paper_action_html($talent_project_id, $state["template_id"], true, true);
                                }
                                //a withdrawn agreement can be taken off the list, by an admin
                                if ($can_remove && $state["status"] === "voided" && $state["template_id"]) {
                                    echo talent_contract_remove_action_html($talent_project_id, $state["template_id"]);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<div class="modal-footer">
    <?php if ($sendable_count) { ?>
        <?php echo talent_contract_send_action_html($talent_project_id, 0, false, false); ?>
        <?php echo talent_contract_paper_action_html($talent_project_id, 0, false); ?>
    <?php } ?>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
</div>
