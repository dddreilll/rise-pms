<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo app_lang("date"); ?></th>
                    <th><?php echo app_lang("agreements_audit_event"); ?></th>
                    <th><?php echo app_lang("agreements_audit_actor"); ?></th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($audit_logs) {
                    foreach ($audit_logs as $log) { ?>
                        <tr>
                            <td><?php echo $log->created_at; ?></td>
                            <td><?php echo $log->event; ?></td>
                            <td><?php echo trim($log->actor_name . " " . $log->actor_email); ?></td>
                            <td><?php echo $log->ip; ?></td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr><td colspan="4"><?php echo app_lang("no_record_found"); ?></td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
