<div class="p15 mb15">
    <?php if ($can_manage) { ?>
        <h4 class="mt0"><?php echo app_lang("agreements_signatories"); ?></h4>
        <?php echo form_open(get_uri("agreements/save_signatories"), array("id" => "signatories-form", "class" => "general-form mb20")); ?>
        <input type="hidden" name="document_id" value="<?php echo $document->id; ?>" />
        <div class="table-responsive">
            <table class="table" id="signatories-table">
                <thead>
                    <tr>
                        <th><?php echo app_lang("agreements_signatory_type"); ?></th>
                        <th><?php echo app_lang("name"); ?></th>
                        <th><?php echo app_lang("email"); ?></th>
                        <th><?php echo app_lang("agreements_signing_order"); ?></th>
                        <th><?php echo app_lang("agreements_signatory_staff"); ?></th>
                        <th><?php echo app_lang("agreements_status"); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($signatories) {
                        foreach ($signatories as $s) {
                            ?>
                            <tr>
                                <td>
                                    <select name="signatory_type[]" class="form-control">
                                        <option value="external" <?php echo $s->type === "external" ? "selected" : ""; ?>><?php echo app_lang("agreements_signatory_external"); ?></option>
                                        <option value="staff" <?php echo $s->type === "staff" ? "selected" : ""; ?>><?php echo app_lang("agreements_signatory_staff"); ?></option>
                                        <option value="client" <?php echo $s->type === "client" ? "selected" : ""; ?>><?php echo app_lang("agreements_signatory_client"); ?></option>
                                    </select>
                                </td>
                                <td><input type="text" name="signatory_name[]" class="form-control" value="<?php echo $s->name; ?>" /></td>
                                <td><input type="email" name="signatory_email[]" class="form-control" value="<?php echo $s->email; ?>" /></td>
                                <td><input type="number" name="signatory_order[]" class="form-control" value="<?php echo $s->signing_order; ?>" /></td>
                                <td><?php echo form_dropdown("signatory_user_id[]", $staff_dropdown, $s->user_id, "class='form-control'"); ?></td>
                                <td><?php echo $s->status; ?></td>
                                <td><button type="button" class="btn btn-default btn-sm remove-row"><i data-feather="x" class="icon-16"></i></button></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-default mb10" id="add-signatory-row"><?php echo app_lang("agreements_add_signatory"); ?></button>
        <button type="submit" class="btn btn-primary mb10"><?php echo app_lang("save"); ?></button>
        <?php echo form_close(); ?>

        <h4><?php echo app_lang("agreements_recipients"); ?></h4>
        <p class="text-off"><?php echo app_lang("agreements_recipients_help"); ?></p>
        <?php echo form_open(get_uri("agreements/save_recipients"), array("id" => "recipients-form", "class" => "general-form mb20")); ?>
        <input type="hidden" name="document_id" value="<?php echo $document->id; ?>" />
        <div class="table-responsive">
            <table class="table" id="recipients-table">
                <thead>
                    <tr>
                        <th><?php echo app_lang("name"); ?></th>
                        <th><?php echo app_lang("email"); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipients as $r) { ?>
                        <tr>
                            <td><input type="text" name="recipient_name[]" class="form-control" value="<?php echo $r->name; ?>" /></td>
                            <td><input type="email" name="recipient_email[]" class="form-control" value="<?php echo $r->email; ?>" /></td>
                            <td><button type="button" class="btn btn-default btn-sm remove-row"><i data-feather="x" class="icon-16"></i></button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-default mb10" id="add-recipient-row"><?php echo app_lang("agreements_add_recipient"); ?></button>
        <button type="submit" class="btn btn-primary mb10"><?php echo app_lang("save"); ?></button>
        <?php echo form_close(); ?>
    <?php } else { ?>
        <ul class="list-group">
            <?php foreach ($signatories as $s) { ?>
                <li class="list-group-item"><?php echo $s->name; ?> (<?php echo $s->email; ?>) — <?php echo $s->status; ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

</div>

<script type="text/javascript">
    $(document).ready(function () {
        feather.replace();

        $("#signatories-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 3000});
                location.reload();
            }
        });
        $("#recipients-form").appForm({
            isModal: false,
            onSuccess: function (result) {
                appAlert.success(result.message, {duration: 3000});
                location.reload();
            }
        });

        $("#add-signatory-row").on("click", function () {
            var row = `<tr>
                <td><select name="signatory_type[]" class="form-control"><option value="external"><?php echo app_lang("agreements_signatory_external"); ?></option><option value="staff"><?php echo app_lang("agreements_signatory_staff"); ?></option><option value="client"><?php echo app_lang("agreements_signatory_client"); ?></option></select></td>
                <td><input type="text" name="signatory_name[]" class="form-control" /></td>
                <td><input type="email" name="signatory_email[]" class="form-control" /></td>
                <td><input type="number" name="signatory_order[]" class="form-control" value="1" /></td>
                <td><?php echo str_replace(array("\n", "\r"), "", form_dropdown("signatory_user_id[]", $staff_dropdown, "", "class='form-control'")); ?></td>
                <td>pending</td>
                <td><button type="button" class="btn btn-default btn-sm remove-row"><i data-feather="x" class="icon-16"></i></button></td>
            </tr>`;
            $("#signatories-table tbody").append(row);
            feather.replace();
        });

        $("#add-recipient-row").on("click", function () {
            $("#recipients-table tbody").append(`<tr>
                <td><input type="text" name="recipient_name[]" class="form-control" /></td>
                <td><input type="email" name="recipient_email[]" class="form-control" /></td>
                <td><button type="button" class="btn btn-default btn-sm remove-row"><i data-feather="x" class="icon-16"></i></button></td>
            </tr>`);
            feather.replace();
        });

        $(document).on("click", ".remove-row", function () {
            $(this).closest("tr").remove();
        });
    });
</script>
