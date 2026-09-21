<?php
//the events we have a label for; anything else is shown under its raw name
$known_events = array("sent", "email_sent", "email_failed", "viewed", "signed", "declined", "card_confirmed", "confirm_skipped", "expired", "downloaded");
?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="mb15">
            <strong><?php echo esc($contract->title); ?></strong>
            <span class="text-off ml5"><?php echo esc($label); ?></span>
            <span class="ml10"><?php echo talent_contract_status_html($contract->status, $contract->token_expires_at); ?></span>

            <div class="text-off mt5"><?php echo sprintf(app_lang("talent_contract_sent_line"), esc($contract->sent_to_email), esc(format_to_datetime($contract->sent_at, true))); ?></div>
            <?php if ($status === "signed") { ?>
                <div class="text-success mt5">
                    <i data-feather="check-circle" class="icon-16"></i>
                    <?php echo sprintf(app_lang("talent_contract_signed_line"), esc($signer_name), esc(format_to_datetime($contract->signed_at, true))); ?>
                </div>
            <?php } else if ($status === "sent") { ?>
                <div class="text-off"><?php echo sprintf(app_lang("talent_contract_expires_line"), esc(format_to_date($contract->token_expires_at, true))); ?></div>
            <?php } ?>
        </div>

        <div class="b-a bg-white p15" style="max-height: 420px; overflow: auto; overflow-wrap: anywhere;"><?php echo $html; ?></div>

        <h5 class="mt20"><?php echo app_lang("talent_contract_activity"); ?></h5>
        <ul class="list-unstyled mb0">
            <?php
            foreach ($events as $event) {
                $meta = $event->meta ? json_decode($event->meta, true) : array();
                $event_label = in_array($event->event, $known_events, true) ? app_lang("talent_contract_event_" . $event->event) : $event->event;
                //an address is only worth showing for what the talent did
                $show_ip = $event->ip && $event->actor_type === "talent";
                ?>
                <li class="pb5">
                    <span class="text-off"><?php echo esc(format_to_datetime($event->created_at, true)); ?></span>
                    <?php echo esc($event_label); ?>
                    <?php if ($show_ip) { ?><span class="text-off">(<?php echo esc($event->ip); ?>)</span><?php } ?>
                    <?php if ($event->event === "declined" && !empty($meta["reason"])) { ?>
                        <div class="text-off pl15"><?php echo nl2br(esc($meta["reason"])); ?></div>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    </div>
</div>

<div class="modal-footer">
    <?php if ($status === "signed") { ?>
        <?php echo anchor(get_uri("talent_contracts/download/" . (int) $contract->id), "<i data-feather='download' class='icon-16'></i> " . app_lang("talent_contract_download"), array("class" => "btn btn-default")); ?>
    <?php } ?>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
</div>
