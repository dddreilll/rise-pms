<?php

namespace App\Controllers;

use App\Libraries\App_folders;
use App\Libraries\Excel_import;

class Clients extends Security_Controller {
    use App_folders;
    use Excel_import;

    private $client_groups_id_by_title = array();

    function __construct() {
        parent::__construct();

        //check permission to access this module
        $this->init_permission_checker("client");
    }

    private function _validate_client_manage_access($client_id = 0) {
        if (!$this->can_edit_clients($client_id)) {
            app_redirect("forbidden");
        }
    }

    private function _validate_client_view_access($client_id = 0) {
        if (!$this->can_view_clients($client_id)) {
            app_redirect("forbidden");
        }
    }

    /* load clients list view */

    function index($tab = "") {
        $this->access_only_allowed_members();

        $view_data = $this->make_access_permissions_view_data();

        $view_data['can_edit_clients'] = $this->can_edit_clients();
        $view_data["show_project_info"] = $this->can_manage_all_projects() && !$this->has_all_projects_restricted_role();

        $view_data["show_own_clients_only_user_id"] = $this->show_own_clients_only_user_id();
        $view_data["allowed_client_groups"] = $this->allowed_client_groups;

        $view_data['tab'] = clean_data($tab);

        return $this->template->rander("clients/index", $view_data);
    }

    private function _validate_view_file_access() {
        if ($this->login_user->user_type == "staff") {
            $this->access_only_allowed_members();
        } else {
            if (!get_setting("client_can_view_files")) {
                app_redirect("forbidden");
            }
        }
    }

    private function _validate_add_file_access() {
        if ($this->login_user->user_type == "staff") {
            $this->access_only_allowed_members();
        } else {
            if (!get_setting("client_can_add_files")) {
                app_redirect("forbidden");
            }
        }
    }

    /* load client add/edit modal */

    function modal_form() {
        $client_id = $this->request->getPost('id');
        validate_numeric_value($client_id);
        $this->_validate_client_manage_access($client_id);

        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $view_data['label_column'] = "col-md-3";
        $view_data['field_column'] = "col-md-9";

        $view_data["view"] = $this->request->getPost('view'); //view='details' needed only when loading from the client's details view
        $view_data["ticket_id"] = $this->request->getPost('ticket_id'); //needed only when loading from the ticket's details view and created by unknown client
        $view_data['model_info'] = $this->Clients_model->get_one($client_id);
        $view_data["currency_dropdown"] = $this->_get_currency_dropdown_select2_data();

        //prepare groups dropdown list
        $view_data['groups_dropdown'] = $this->_get_groups_dropdown_select2_data();

        $view_data["team_members_dropdown"] = $this->get_team_members_dropdown();

        //prepare label suggestions
        $view_data['label_suggestions'] = $this->make_labels_dropdown("client", $view_data['model_info']->labels);

        //get custom fields
        $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("clients", $client_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        return $this->template->view('clients/modal_form', $view_data);
    }

    /* insert or update a client */

    function save() {
        $client_id = $this->request->getPost('id');
        $this->_validate_client_manage_access($client_id);

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "company_name" => "required"
        ));

        $company_name = $this->request->getPost('company_name');

        $data = array(
            "company_name" => $company_name,
            "type" => $this->request->getPost('account_type'),
            "address" => $this->request->getPost('address'),
            "city" => $this->request->getPost('city'),
            "state" => $this->request->getPost('state'),
            "zip" => $this->request->getPost('zip'),
            "country" => $this->request->getPost('country'),
            "phone" => $this->request->getPost('phone'),
            "website" => $this->request->getPost('website'),
            "vat_number" => $this->request->getPost('vat_number'),
            "gst_number" => $this->request->getPost('gst_number')
        );

        if ($this->login_user->user_type === "staff") {
            $group_ids = $this->request->getPost('group_ids');
            $labels = $this->request->getPost('labels');

            validate_list_of_numbers($group_ids);
            validate_list_of_numbers($labels);

            $data["group_ids"] = $group_ids ? $group_ids : "";
            $data["labels"] = $labels ? $labels : "";
        }


        if (!$client_id) {
            $data["created_date"] = get_current_utc_time();
        }


        if ($this->login_user->is_admin) {
            $data["currency_symbol"] = $this->request->getPost('currency_symbol') ? $this->request->getPost('currency_symbol') : "";
            $data["currency"] = $this->request->getPost('currency') ? $this->request->getPost('currency') : "";
            $data["disable_online_payment"] = $this->request->getPost('disable_online_payment') ? $this->request->getPost('disable_online_payment') : 0;

            //check if the currency is editable
            if ($client_id && get_array_value($data, "currency")) {
                $client_info = $this->Clients_model->get_one($client_id);
                if ($client_info->currency !== $data["currency"] && !$this->Clients_model->is_currency_editable($client_id)) {
                    echo json_encode(array("success" => false, 'message' => app_lang('client_currency_not_editable_message')));
                    exit();
                }
            }
        }

        if ($this->login_user->is_admin || get_array_value($this->login_user->permissions, "client") === "all") {
            //user has access to change created by
            $data["created_by"] = $this->request->getPost('created_by') ? $this->request->getPost('created_by') : $this->login_user->id;
        } else if (!$client_id) {
            //the user hasn't permission to change created by but s/he can create new client
            $data["created_by"] = $this->login_user->id;
        }

        $data = clean_data($data);

        //check duplicate company name, if found then show an error message
        if (get_setting("disallow_duplicate_client_company_name") == "1" && $this->Clients_model->is_duplicate_company_name($data["company_name"], $client_id)) {
            echo json_encode(array("success" => false, 'message' => app_lang("account_already_exists_for_your_company_name")));
            exit();
        }

        $save_id = $this->Clients_model->ci_save($data, $client_id);

        if ($save_id) {
            save_custom_fields("clients", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

            //save client id on the ticket if any ticket id exists
            $ticket_id = $this->request->getPost('ticket_id');
            if ($ticket_id) {
                $ticket_data = array("client_id" => $save_id);
                $this->Tickets_model->ci_save($ticket_data, $ticket_id);
            }

            echo json_encode(array("success" => true, "data" => $this->_row_data($save_id), 'id' => $save_id, 'view' => $this->request->getPost('view'), 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* delete or undo a client */

    function delete() {
        $id = $this->request->getPost('id');
        $this->_validate_client_manage_access($id);

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        if ($this->Clients_model->delete_client_and_sub_items($id)) {
            echo json_encode(array("success" => true, 'message' => app_lang('record_deleted')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
        }
    }

    /* list of clients, prepared for datatable  */

    function list_data() {

        $this->access_only_allowed_members();
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("clients", $this->login_user->is_admin, $this->login_user->user_type);
        $options = array(
            "custom_fields" => $custom_fields,
            "custom_field_filter" => $this->prepare_custom_field_filter_values("clients", $this->login_user->is_admin, $this->login_user->user_type),
            "group_id" => $this->request->getPost("group_id"),
            "show_own_clients_only_user_id" => $this->show_own_clients_only_user_id(),
            "quick_filter" => $this->request->getPost("quick_filter"),
            "created_by" => $this->request->getPost("created_by"),
            "client_groups" => $this->allowed_client_groups,
            "label_id" => $this->request->getPost('label_id')
        );

        $all_options = append_server_side_filtering_commmon_params($options);

        $result = $this->Clients_model->get_details($all_options);

        //by this, we can handel the server side or client side from the app table prams.
        if (get_array_value($all_options, "server_side")) {
            $list_data = get_array_value($result, "data");
        } else {
            $list_data = $result->getResult();
            $result = array();
        }

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = $this->_make_row($data, $custom_fields);
        }

        $result["data"] = $result_data;

        echo json_encode($result);
    }

    /* return a row of client list  table */

    private function _row_data($id) {
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("clients", $this->login_user->is_admin, $this->login_user->user_type);
        $options = array(
            "id" => $id,
            "custom_fields" => $custom_fields
        );
        $data = $this->Clients_model->get_details($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    /* prepare a row of client list table */

    private function _make_row($data, $custom_fields) {


        $image_url = get_avatar($data->contact_avatar);
        $contact = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->primary_contact";
        $primary_contact = get_client_contact_profile_link($data->primary_contact_id, $contact);

        $group_list = "";
        if ($data->client_groups) {
            $groups = explode(",", $data->client_groups);
            foreach ($groups as $group) {
                if ($group) {
                    $group_list .= "<li>" . $group . "</li>";
                }
            }
        }

        if ($group_list) {
            $group_list = "<ul class='pl15'>" . $group_list . "</ul>";
        }


        $due = 0;
        if ($data->invoice_value) {
            $due = ignor_minor_value($data->invoice_value - $data->payment_received);
        }

        $client_labels = make_labels_view_data($data->labels_list, true);

        $row_data = array(
            $data->id,
            anchor(get_uri("clients/view/" . $data->id), $data->company_name),
            $data->primary_contact ? $primary_contact : "",
            $data->phone,
            $group_list,
            $client_labels,
            to_decimal_format($data->total_projects),
            to_currency($data->invoice_value, $data->currency_symbol),
            to_currency($data->payment_received, $data->currency_symbol),
            to_currency($due, $data->currency_symbol)
        );

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = $this->template->view("custom_fields/output_" . $field->field_type, array("value" => $data->$cf_id));
        }

        $row_data[] = modal_anchor(get_uri("clients/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang('edit_client'), "data-post-id" => $data->id))
            . js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete_client'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("clients/delete"), "data-action" => "delete-confirmation"));

        return $row_data;
    }

    /* load client details view */

    function view($client_id = 0, $tab = "", $folder_id = 0) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        if ($client_id) {
            $options = array("id" => $client_id);
            $client_info = $this->Clients_model->get_details($options)->getRow();
            if ($client_info && !$client_info->is_lead) {

                $view_data = $this->make_access_permissions_view_data();

                $view_data["show_note_info"] = (get_setting("module_note")) ? true : false;
                $view_data["show_event_info"] = (get_setting("module_event")) ? true : false;

                $access_info = $this->get_access_info("expense");
                $view_data["show_expense_info"] = (get_setting("module_expense") && $access_info->access_type == "all") ? true : false;

                $view_data['client_info'] = $client_info;

                $view_data["is_starred"] = strpos($client_info->starred_by, ":" . $this->login_user->id . ":") ? true : false;

                $view_data["tab"] = clean_data($tab);

                $view_data["files_tab"] = "";
                $view_data["folder_id"] = $folder_id;
                if ($tab == "file_manager") {
                    $view_data["files_tab"] = "file_manager";
                }

                $view_data["view_type"] = "";

                //even it's hidden, admin can view all information of client
                $view_data['hidden_menu'] = array("");
                $view_data['show_payment_info'] = true;

                return $this->template->rander("clients/view", $view_data);
            } else {
                show_404();
            }
        } else {
            show_404();
        }
    }

    /* add-remove start mark from client */

    function add_remove_star($client_id, $type = "add") {
        validate_numeric_value($client_id);

        if ($client_id) {
            $view_data["client_id"] = clean_data($client_id);

            if ($type === "add") {
                $this->Clients_model->add_remove_star($client_id, $this->login_user->id, $type = "add");
                return $this->template->view('clients/star/starred', $view_data);
            } else {
                $this->Clients_model->add_remove_star($client_id, $this->login_user->id, $type = "remove");
                return $this->template->view('clients/star/not_starred', $view_data);
            }
        }
    }

    function show_my_starred_clients() {
        $view_data["clients"] = $this->Clients_model->get_starred_clients($this->login_user->id, $this->allowed_client_groups)->getResult();
        return $this->template->view('clients/star/clients_list', $view_data);
    }

    /* load projects tab  */

    function projects($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        $view_data['can_create_projects'] = $this->can_create_projects();
        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("projects", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("projects", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['client_id'] = clean_data($client_id);
        $view_data['project_statuses'] = $this->Project_status_model->get_details()->getResult();
        return $this->template->view("clients/projects/index", $view_data);
    }

    /* load payments tab  */

    function payments($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = clean_data($client_id);
            return $this->template->view("clients/payments/index", $view_data);
        }
    }

    /* load tickets tab  */

    function tickets($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {

            $view_data['client_id'] = clean_data($client_id);
            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("tickets", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("tickets", $this->login_user->is_admin, $this->login_user->user_type);

            $view_data['show_project_reference'] = get_setting('project_reference_in_tickets');

            return $this->template->view("clients/tickets/index", $view_data);
        }
    }

    /* load invoices tab  */

    function invoices($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = clean_data($client_id);

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("invoices", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("invoices", $this->login_user->is_admin, $this->login_user->user_type);

            $view_data["can_edit_invoices"] = $this->can_edit_invoices();

            $type_suggestions = array(
                array("id" => "", "text" => "- " . app_lang('type') . " -"),
                array("id" => "invoice", "text" => app_lang("invoice")),
                array("id" => "credit_note", "text" => app_lang("credit_note"))
            );
            $view_data['types_dropdown'] = json_encode($type_suggestions);

            return $this->template->view("clients/invoices/index", $view_data);
        }
    }

    /* load estimates tab  */

    function estimates($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = clean_data($client_id);

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("estimates", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("estimates", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/estimates/estimates", $view_data);
        }
    }

    /* load orders tab  */

    function orders($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = clean_data($client_id);

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("orders", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("orders", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/orders/orders", $view_data);
        }
    }

    /* load estimate requests tab  */

    function estimate_requests($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data['client_id'] = clean_data($client_id);
            return $this->template->view("clients/estimates/estimate_requests", $view_data);
        }
    }

    /* load notes tab  */

    function notes($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        if ($client_id) {
            $view_data['client_id'] = clean_data($client_id);
            return $this->template->view("clients/notes/index", $view_data);
        }
    }

    /* load events tab  */

    function events($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        if ($client_id) {
            $view_data['client_id'] = clean_data($client_id);
            $view_data['calendar_filter_dropdown'] = $this->get_calendar_filter_dropdown("client");
            $view_data['event_labels_dropdown'] = json_encode($this->make_labels_dropdown("event", "", true, app_lang("event") . " " . strtolower(app_lang("label"))));
            return $this->template->view("events/index", $view_data);
        }
    }

    /* load files tab */

    function files($client_id, $view_type = "", $folder_id = 0) {
        validate_numeric_value($client_id);
        $this->_validate_view_file_access();

        if ($this->login_user->user_type == "client") {
            $client_id = $this->login_user->client_id;
        }

        $this->_validate_client_view_access($client_id);

        $view_data['client_id'] = clean_data($client_id);
        $view_data['page_view'] = false;

        $view_data['tab'] = "";
        $view_data['folder_id'] = $folder_id;
        if ($view_type == "file_manager") {
            $view_data['tab'] = "file_manager";
        }

        if ($view_type == "page_view") {
            $view_data['page_view'] = true;
            return $this->template->rander("clients/files/index", $view_data);
        } else {
            return $this->template->view("clients/files/index", $view_data);
        }
    }

    /* file upload modal */

    function file_modal_form() {

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "client_id" => "numeric",
            "context_id" => "numeric",
            "folder_id" => "numeric",
        ));

        $this->_validate_add_file_access();

        $view_data['model_info'] = $this->General_files_model->get_one($this->request->getPost('id'));
        $client_id = $this->request->getPost('client_id') ? $this->request->getPost('client_id') : $view_data['model_info']->client_id;

        if (!$client_id  && $this->request->getPost('context_id')) {
            $client_id = $this->request->getPost('context_id');
        }

        $this->_validate_client_manage_access($client_id);

        $view_data['client_id'] = $client_id;
        $view_data['folder_id'] = $this->request->getPost('folder_id');
        return $this->template->view('clients/files/modal_form', $view_data);
    }

    /* save file data and move temp file to parmanent file directory */

    function save_file() {

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "client_id" => "numeric|required",
            "folder_id" => "numeric"
        ));

        $client_id = $this->request->getPost('client_id');
        $folder_id = $this->request->getPost('folder_id');

        if (!$this->_can_upload_file($folder_id, $client_id)) {
            app_redirect("forbidden");
        }

        $files = $this->request->getPost("files");
        $success = false;
        $now = get_current_utc_time();

        $target_path = getcwd() . "/" . get_general_file_path("client", $client_id);

        //process the fiiles which has been uploaded by dropzone
        if ($files && get_array_value($files, 0)) {
            foreach ($files as $file) {
                $file_name = $this->request->getPost('file_name_' . $file);
                $file_info = move_temp_file($file_name, $target_path);
                if ($file_info) {
                    $data = array(
                        "client_id" => $client_id,
                        "file_name" => get_array_value($file_info, 'file_name'),
                        "file_id" => get_array_value($file_info, 'file_id'),
                        "service_type" => get_array_value($file_info, 'service_type'),
                        "description" => $this->request->getPost('description_' . $file),
                        "file_size" => $this->request->getPost('file_size_' . $file),
                        "created_at" => $now,
                        "uploaded_by" => $this->login_user->id,
                        "folder_id" => $folder_id,
                        "context" => "client",
                        "context_id" => $client_id
                    );
                    $success = $this->General_files_model->ci_save($data);
                } else {
                    $success = false;
                }
            }
        }


        if ($success) {
            echo json_encode(array("success" => true, 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* list of files, prepared for datatable  */

    function files_list_data($client_id = 0) {
        validate_numeric_value($client_id);
        $this->_validate_view_file_access();
        $this->_validate_client_view_access($client_id);

        $options = array("client_id" => $client_id, "context" => "client");
        $list_data = $this->General_files_model->get_details($options)->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_file_row($data);
        }
        echo json_encode(array("data" => $result));
    }

    private function _make_file_row($data) {
        $file_icon = get_file_icon(strtolower(pathinfo($data->file_name, PATHINFO_EXTENSION)));

        $image_url = get_avatar($data->uploaded_by_user_image);
        $uploaded_by = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> $data->uploaded_by_user_name";

        if ($data->uploaded_by_user_type == "staff") {
            $uploaded_by = get_team_member_profile_link($data->uploaded_by, $uploaded_by);
        } else {
            $uploaded_by = get_client_contact_profile_link($data->uploaded_by, $uploaded_by);
        }

        $description = "<div class='float-start'>" .
            js_anchor(remove_file_prefix($data->file_name), array('title' => "", "data-toggle" => "app-modal", "data-sidebar" => "0", "data-group" => "general_files", "data-url" => get_uri("clients/view_file/" . $data->id), "class" => "text-break-space"));

        if ($data->description) {
            $description .= "<div>" . $data->description . "</div></div>";
        } else {
            $description .= "</div>";
        }

        $options = anchor(get_uri("clients/download_file/" . $data->id), "<i data-feather='download-cloud' class='icon-16'></i>", array("title" => app_lang("download")));

        if ($this->login_user->user_type == "staff") {
            $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete_file'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("clients/delete_file"), "data-action" => "delete-confirmation"));
        }


        return array(
            $data->id,
            "<div data-feather='$file_icon' class='mr10 float-start'></div>" . $description,
            convert_file_size($data->file_size),
            $uploaded_by,
            format_to_datetime($data->created_at),
            $options
        );
    }

    function view_file($file_id = 0) {
        validate_numeric_value($file_id);

        $file_info = $this->_get_file_info($file_id);
        if ($file_info) {

            $view_data = get_file_preview_common_data($file_info, $this->_get_file_path($file_info));
            $view_data['can_comment_on_files'] = false;

            return $this->template->view("clients/files/view", $view_data);
        } else {
            show_404();
        }
    }

    /* download a file */

    function download_file($id) {
        validate_numeric_value($id);

        return $this->_download_file($id);
    }


    /* delete a file */

    function delete_file() {

        $this->validate_submitted_data(array(
            "id" => "numeric|required"
        ));

        $id = $this->request->getPost('id');

        if ($this->_delete_file($id)) {
            echo json_encode(array("success" => true, 'message' => app_lang('record_deleted')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
        }
    }

    function contact_profile($contact_id = 0, $tab = "") {
        validate_numeric_value($contact_id);
        $this->access_only_allowed_members_or_contact_personally($contact_id);

        $view_data['user_info'] = $this->Users_model->get_one($contact_id);
        $this->_validate_client_view_access($view_data['user_info']->client_id);
        $view_data['client_info'] = $this->Clients_model->get_one($view_data['user_info']->client_id);
        $view_data['tab'] = clean_data($tab);
        $view_data['can_edit_clients'] = $this->can_edit_clients();
        if ($view_data['user_info']->user_type === "client") {

            $view_data['show_cotact_info'] = true;
            $view_data['show_social_links'] = true;
            $view_data['social_link'] = $this->Social_links_model->get_one($contact_id);
            return $this->template->rander("clients/contacts/view", $view_data);
        } else {
            show_404();
        }
    }

    //show account settings of a user
    function account_settings($contact_id) {
        validate_numeric_value($contact_id);
        $this->access_only_allowed_members_or_contact_personally($contact_id);
        $view_data['user_info'] = $this->Users_model->get_one($contact_id);
        $view_data['can_edit_clients'] = $this->can_edit_clients();
        $this->_validate_client_view_access($view_data['user_info']->client_id);
        return $this->template->view("users/account_settings", $view_data);
    }

    //show my preference settings of a team member
    function my_preferences() {
        $view_data["user_info"] = $this->Users_model->get_one($this->login_user->id);

        //language dropdown
        $view_data['language_dropdown'] = array();
        if (!get_setting("disable_language_selector_for_clients")) {
            $view_data['language_dropdown'] = get_language_list();
        }

        $view_data["hidden_topbar_menus_dropdown"] = $this->get_hidden_topbar_menus_dropdown();

        return $this->template->view("clients/contacts/my_preferences", $view_data);
    }

    function save_my_preferences() {
        //setting preferences
        $settings = array("notification_sound_volume", "disable_push_notification", "disable_keyboard_shortcuts", "reminder_sound_volume", "reminder_snooze_length");

        if (!get_setting("disable_topbar_menu_customization")) {
            array_push($settings, "hidden_topbar_menus");
        }

        foreach ($settings as $setting) {
            $value = $this->request->getPost($setting);
            if (is_null($value)) {
                $value = "";
            }

            $value = clean_data($value);

            $this->Settings_model->save_setting("user_" . $this->login_user->id . "_" . $setting, $value, "user");
        }

        //there was 3 settings in users table.
        //so, update the users table also

        $user_data = array(
            "enable_web_notification" => $this->request->getPost("enable_web_notification"),
            "enable_email_notification" => $this->request->getPost("enable_email_notification"),
        );

        if (!get_setting("disable_language_selector_for_clients")) {
            $user_data["language"] = $this->request->getPost("personal_language");
        }

        $user_data = clean_data($user_data);

        $this->Users_model->ci_save($user_data, $this->login_user->id);

        try {
            app_hooks()->do_action("app_hook_clients_my_preferences_save_data");
        } catch (\Exception $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }

        echo json_encode(array("success" => true, 'message' => app_lang('settings_updated')));
    }

    function save_personal_language($language) {
        if (!get_setting("disable_language_selector_for_clients") && ($language || $language === "0")) {

            $language = clean_data($language);
            $data["language"] = strtolower($language);

            $this->Users_model->ci_save($data, $this->login_user->id);
        }
    }

    /* load contacts tab  */

    function contacts($client_id = 0) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        if ($client_id) {
            $view_data["client_id"] = clean_data($client_id);
            $view_data["view_type"] = "";
        } else {
            $view_data["client_id"] = "";
            $view_data["view_type"] = "list_view";
        }
        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("client_contacts", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("client_contacts", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['can_edit_clients'] = $this->can_edit_clients();

        return $this->template->view("clients/contacts/index", $view_data);
    }

    /* contact add modal */

    function add_new_contact_modal_form() {
        $this->_validate_client_manage_access();

        $view_data['model_info'] = $this->Users_model->get_one(0);
        $view_data['model_info']->client_id = $this->request->getPost('client_id');

        $view_data['add_type'] = $this->request->getPost('add_type');

        $this->_validate_client_manage_access($view_data['model_info']->client_id);

        $view_data["available_menus"] = get_available_menus_for_clients_dropdown();

        $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("client_contacts", $view_data['model_info']->id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();
        return $this->template->view('clients/contacts/modal_form', $view_data);
    }

    /* load contact's general info tab view */

    function contact_general_info_tab($contact_id = 0) {
        validate_numeric_value($contact_id);

        if ($contact_id) {
            $this->access_only_allowed_members_or_contact_personally($contact_id);

            $view_data['model_info'] = $this->Users_model->get_one($contact_id);
            $this->_validate_client_view_access($view_data['model_info']->client_id);
            $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("client_contacts", $contact_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

            $view_data['label_column'] = "col-md-2";
            $view_data['field_column'] = "col-md-10";
            $view_data['can_edit_clients'] = $this->can_edit_clients($view_data['model_info']->client_id);
            return $this->template->view('clients/contacts/contact_general_info_tab', $view_data);
        }
    }

    /* load contact's company info tab view */

    function company_info_tab($client_id = 0) {
        validate_numeric_value($client_id);

        if ($client_id) {
            $this->_validate_client_view_access($client_id);

            $view_data['model_info'] = $this->Clients_model->get_one($client_id);
            $view_data['groups_dropdown'] = $this->_get_groups_dropdown_select2_data();

            $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("clients", $client_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

            $view_data['label_column'] = "col-md-2";
            $view_data['field_column'] = "col-md-10";
            $view_data['can_edit_clients'] = $this->can_edit_clients($client_id);

            $view_data["team_members_dropdown"] = $this->get_team_members_dropdown();
            $view_data["currency_dropdown"] = $this->_get_currency_dropdown_select2_data();
            $view_data['label_suggestions'] = $this->make_labels_dropdown("client", $view_data['model_info']->labels);

            return $this->template->view('clients/contacts/company_info_tab', $view_data);
        }
    }

    /* load contact's social links tab view */

    function contact_social_links_tab($contact_id = 0) {
        validate_numeric_value($contact_id);

        if ($contact_id) {
            $this->access_only_allowed_members_or_contact_personally($contact_id);

            $contact_info = $this->Users_model->get_one($contact_id);
            $this->_validate_client_view_access($contact_info->client_id);

            $view_data['user_id'] = clean_data($contact_id);
            $view_data['user_type'] = "client";
            $view_data['model_info'] = $this->Social_links_model->get_one($contact_id);
            $view_data['can_edit_clients'] = $this->can_edit_clients();
            return $this->template->view('users/social_links', $view_data);
        }
    }

    /* insert/upadate a contact */

    function save_contact() {
        $this->validate_submitted_data(array(
            "first_name" => "required",
            "last_name" => "required",
            "client_id" => "required|numeric",
            "contact_id" => "numeric"
        ));

        $contact_id = $this->request->getPost('contact_id');
        $client_id = $this->request->getPost('client_id');

        $password = $this->request->getPost('login_password');
        $password = clean_data($password);

        $this->_validate_client_manage_access($client_id);

        $this->access_only_allowed_members_or_contact_personally($contact_id);

        $user_data = array(
            "first_name" => $this->request->getPost('first_name'),
            "last_name" => $this->request->getPost('last_name'),
            "phone" => $this->request->getPost('phone'),
            "skype" => $this->request->getPost('skype'),
            "job_title" => $this->request->getPost('job_title'),
            "gender" => is_null($this->request->getPost('gender')) ? "" : $this->request->getPost('gender'),
            "note" => $this->request->getPost('note')
        );

        if (!$contact_id) {
            //inserting new contact. client_id is required

            $this->validate_submitted_data(array(
                "email" => "required|valid_email",
            ));

            //we'll save following fields only when creating a new contact from this form
            $user_data["client_id"] = $client_id;
            $user_data["email"] = trim($this->request->getPost('email'));
            $user_data["password"] = $password ? password_hash($this->request->getPost("login_password"), PASSWORD_DEFAULT) : "";
            $user_data["created_at"] = get_current_utc_time();

            //validate duplicate email address
            if ($this->Users_model->is_email_exists($user_data["email"], 0, $client_id)) {
                echo json_encode(array("success" => false, 'message' => app_lang('duplicate_email')));
                exit();
            }


            //by default, the first contact of a client is the primary contact
            //check existing primary contact. if not found then set the first contact = primary contact
            $primary_contact = $this->Clients_model->get_primary_contact($client_id);
            if (!$primary_contact) {
                $user_data['is_primary_contact'] = 1;
            }

            if (isset($user_data['is_primary_contact']) && $user_data['is_primary_contact'] == 1) {
                $user_data['client_permissions'] = "all";
            } else {
                $can_access_everything = $this->request->getPost('can_access_everything');
                $specific_permissions = $this->request->getPost('specific_permissions');

                if ($can_access_everything == 1) {
                    $user_data['client_permissions'] = 'all';
                } else {
                    $user_data['client_permissions'] = $specific_permissions;
                }

                if (get_setting("disable_client_login")) {
                    $user_data['client_permissions'] = get_setting("default_permissions_for_non_primary_contact");
                }
            }

            if (!$user_data['client_permissions']) {
                echo json_encode(array("success" => false, 'message' => app_lang('permission_is_required')));
                exit();
            }
        }

        $user_data = clean_data($user_data);

        $save_id = $this->Users_model->ci_save($user_data, $contact_id);
        if ($save_id) {

            save_custom_fields("client_contacts", $save_id, $this->login_user->is_admin, $this->login_user->user_type);

            //send login details to user only for first time. when creating  a new contact
            if (!$contact_id && $this->request->getPost('email_login_details')) {
                $email_template = $this->Email_templates_model->get_final_template("login_info"); //use default template since creating a new contact

                $parser_data["SIGNATURE"] = $email_template->signature;
                $parser_data["USER_FIRST_NAME"] = $user_data["first_name"];
                $parser_data["USER_LAST_NAME"] = $user_data["last_name"];
                $parser_data["USER_LOGIN_EMAIL"] = $user_data["email"];
                $parser_data["USER_LOGIN_PASSWORD"] = $password;
                $parser_data["DASHBOARD_URL"] = base_url();
                $parser_data["LOGO_URL"] = get_logo_url();

                $message = $this->parser->setData($parser_data)->renderString($email_template->message);
                $subject = $this->parser->setData($parser_data)->renderString($email_template->subject);

                send_app_mail($this->request->getPost('email'), $subject, $message);
            }

            echo json_encode(array("success" => true, "data" => $this->_contact_row_data($save_id), 'id' => $contact_id, "client_id" => $client_id, 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    //save social links of a contact
    function save_contact_social_links($contact_id = 0) {
        $contact_id = clean_data($contact_id);

        $this->access_only_allowed_members_or_contact_personally($contact_id);

        $contact_info = $this->Users_model->get_one($contact_id);
        $this->_validate_client_manage_access($contact_info->client_id);

        $id = 0;

        //find out, the user has existing social link row or not? if found update the row otherwise add new row.
        $has_social_links = $this->Social_links_model->get_one($contact_id);
        if (isset($has_social_links->id)) {
            $id = $has_social_links->id;
        }

        $social_link_data = array(
            "facebook" => $this->request->getPost('facebook'),
            "twitter" => $this->request->getPost('twitter'),
            "linkedin" => $this->request->getPost('linkedin'),
            "digg" => $this->request->getPost('digg'),
            "youtube" => $this->request->getPost('youtube'),
            "pinterest" => $this->request->getPost('pinterest'),
            "instagram" => $this->request->getPost('instagram'),
            "github" => $this->request->getPost('github'),
            "tumblr" => $this->request->getPost('tumblr'),
            "vine" => $this->request->getPost('vine'),
            "whatsapp" => $this->request->getPost('whatsapp'),
            "user_id" => $contact_id,
            "id" => $id ? $id : $contact_id
        );

        $social_link_data = clean_data($social_link_data);

        $this->Social_links_model->ci_save($social_link_data, $id);
        echo json_encode(array("success" => true, 'message' => app_lang('record_updated')));
    }

    //save account settings of a client contact (user)
    function save_account_settings($user_id) {
        validate_numeric_value($user_id);
        $this->access_only_allowed_members_or_contact_personally($user_id);

        $contact_info = $this->Users_model->get_one($user_id);
        $this->_validate_client_manage_access($contact_info->client_id);

        $this->validate_submitted_data(array(
            "email" => "required|valid_email"
        ));

        $email = $this->request->getPost('email');
        $password = $this->request->getPost("password");
        $password = clean_data($password);

        if ($this->Users_model->is_email_exists($email, $user_id, $contact_info->client_id)) {
            echo json_encode(array("success" => false, 'message' => app_lang('duplicate_email')));
            exit();
        }

        $account_data = array(
            "email" => $email
        );

        //don't reset password if user doesn't entered any password
        if ($password) {
            $this->Users_model->update_password($email, password_hash($password, PASSWORD_DEFAULT));
        }

        //only admin can disable other users login permission
        if ($this->login_user->is_admin || $this->can_edit_clients()) {
            $account_data['disable_login'] = $this->request->getPost('disable_login');
        }


        if ($this->Users_model->ci_save($account_data, $user_id)) {

            //resend new password to client contact
            if ($this->request->getPost('email_login_details')) {

                $first_name = clean_data($this->request->getPost('first_name'));
                $last_name = clean_data($this->request->getPost('last_name'));

                $email_template = $this->Email_templates_model->get_final_template("login_info", true);

                $user_language = $this->Users_model->get_one($user_id)->language;
                $parser_data["SIGNATURE"] = get_array_value($email_template, "signature_$user_language") ? get_array_value($email_template, "signature_$user_language") : get_array_value($email_template, "signature_default");
                $parser_data["USER_FIRST_NAME"] = $first_name;
                $parser_data["USER_LAST_NAME"] = $last_name;
                $parser_data["USER_LOGIN_EMAIL"] = $account_data["email"];
                $parser_data["USER_LOGIN_PASSWORD"] = $password;
                $parser_data["DASHBOARD_URL"] = base_url();
                $parser_data["LOGO_URL"] = get_logo_url();

                $message = get_array_value($email_template, "message_$user_language") ? get_array_value($email_template, "message_$user_language") : get_array_value($email_template, "message_default");
                $subject = get_array_value($email_template, "subject_$user_language") ? get_array_value($email_template, "subject_$user_language") : get_array_value($email_template, "subject_default");

                $message = $this->parser->setData($parser_data)->renderString($message);
                $subject = $this->parser->setData($parser_data)->renderString($subject);
                send_app_mail($email, $subject, $message);
            }

            echo json_encode(array("success" => true, 'message' => app_lang('record_updated')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    //save profile image of a contact
    function save_profile_image($user_id = 0) {
        validate_numeric_value($user_id);
        $this->access_only_allowed_members_or_contact_personally($user_id);
        $user_info = $this->Users_model->get_one($user_id);
        $this->_validate_client_manage_access($user_info->client_id);

        //process the the file which has uploaded by dropzone
        $profile_image = str_replace("~", ":", $this->request->getPost("profile_image"));

        if ($profile_image) {
            $profile_image = serialize(move_temp_file("avatar.png", get_setting("profile_image_path"), "", $profile_image));

            //delete old file
            delete_app_files(get_setting("profile_image_path"), array(@unserialize($user_info->image)));

            $image_data = array("image" => $profile_image);
            $this->Users_model->ci_save($image_data, $user_id);
            echo json_encode(array("success" => true, 'message' => app_lang('profile_image_changed')));
        }

        //process the the file which has uploaded using manual file submit
        if ($_FILES) {
            $profile_image_file = get_array_value($_FILES, "profile_image_file");
            $image_file_name = get_array_value($profile_image_file, "tmp_name");
            $image_file_size = get_array_value($profile_image_file, "size");
            if ($image_file_name) {
                if (!$this->check_profile_image_dimension($image_file_name)) {
                    echo json_encode(array("success" => false, 'message' => app_lang('profile_image_error_message')));
                    exit();
                }

                $profile_image = serialize(move_temp_file("avatar.png", get_setting("profile_image_path"), "", $image_file_name, "", "", false, $image_file_size));

                //delete old file
                if ($user_info->image) {
                    delete_app_files(get_setting("profile_image_path"), array(@unserialize($user_info->image)));
                }

                $image_data = array("image" => $profile_image);
                $this->Users_model->ci_save($image_data, $user_id);
                echo json_encode(array("success" => true, 'message' => app_lang('profile_image_changed'), "reload_page" => true));
            }
        }
    }

    /* delete or undo a contact */

    function delete_contact() {
        if (!$this->can_edit_clients()) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost('id');

        $contact_info = $this->Users_model->get_one($id);
        $this->_validate_client_manage_access($contact_info->client_id);

        if ($this->request->getPost('undo')) {
            if ($this->Users_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_contact_row_data($id), "message" => app_lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, app_lang('error_occurred')));
            }
        } else {
            if ($this->Users_model->delete($id)) {
                echo json_encode(array("success" => true, 'message' => app_lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
            }
        }
    }

    /* list of contacts, prepared for datatable  */

    function contacts_list_data($client_id = 0) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("client_contacts", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array(
            "user_type" => "client",
            "client_id" => $client_id,
            "custom_fields" => $custom_fields,
            "show_own_clients_only_user_id" => $this->show_own_clients_only_user_id(),
            "custom_field_filter" => $this->prepare_custom_field_filter_values("client_contacts", $this->login_user->is_admin, $this->login_user->user_type),
            "quick_filter" => $this->request->getPost("quick_filter"),
            "client_groups" => $this->allowed_client_groups
        );

        $all_options = append_server_side_filtering_commmon_params($options);

        $result = $this->Users_model->get_details($all_options);

        //by this, we can handel the server side or client side from the app table prams.
        if (get_array_value($all_options, "server_side")) {
            $list_data = get_array_value($result, "data");
        } else {
            $list_data = $result->getResult();
            $result = array();
        }

        $hide_primary_contact_label = false;
        if (!$client_id) {
            $hide_primary_contact_label = true;
        }

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = $this->_make_contact_row($data, $custom_fields, $hide_primary_contact_label);
        }

        $result["data"] = $result_data;

        echo json_encode($result);
    }

    /* return a row of contact list table */

    private function _contact_row_data($id) {
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("client_contacts", $this->login_user->is_admin, $this->login_user->user_type);
        $options = array(
            "id" => $id,
            "user_type" => "client",
            "custom_fields" => $custom_fields
        );
        $data = $this->Users_model->get_details($options)->getRow();
        return $this->_make_contact_row($data, $custom_fields);
    }

    /* prepare a row of contact list table */

    private function _make_contact_row($data, $custom_fields, $hide_primary_contact_label = false) {
        $image_url = get_avatar($data->image);
        $user_avatar = "<span class='avatar avatar-xs'><img src='$image_url' alt='...'></span>";
        $full_name = $data->first_name . " " . $data->last_name . " ";
        $primary_contact = "";
        if ($data->is_primary_contact == "1" && !$hide_primary_contact_label) {
            $primary_contact = "<span class='bg-info badge text-white'>" . app_lang('primary_contact') . "</span>";
        }

        $removal_request_pending = "";
        if ($this->login_user->user_type == "staff" && $data->requested_account_removal) {
            $removal_request_pending = "<span class='bg-danger badge'>" . app_lang("removal_request_pending") . "</span>";
        }

        $contact_link = anchor(get_uri("clients/contact_profile/" . $data->id), $full_name . $primary_contact) . $removal_request_pending;
        if ($this->login_user->user_type === "client") {
            $contact_link = $full_name; //don't show clickable link to client
        }

        $client_info = $this->Clients_model->get_one($data->client_id);

        $contact_permissions = modal_anchor(get_uri("clients/contact_permissions_modal_form"), "<i data-feather='key' class='icon-16'></i>", array("class" => "edit", "title" => app_lang('user_permissions'), "data-post-id" => $data->id));

        $row_data = array(
            $user_avatar,
            $contact_link,
            anchor(get_uri("clients/view/" . $data->client_id), $client_info->company_name),
            $data->job_title,
            $data->email,
            $data->phone ? $data->phone : "-",
            $data->skype ? $data->skype : "-",
            $contact_permissions
        );

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = $this->template->view("custom_fields/output_" . $field->field_type, array("value" => $data->$cf_id));
        }

        $row_data[] = js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete_contact'), "class" => "delete", "data-id" => "$data->id", "data-action-url" => get_uri("clients/delete_contact"), "data-action" => "delete"));

        return $row_data;
    }

    /* open invitation modal */

    function invitation_modal() {
        if (get_setting("disable_user_invitation_option_by_clients") && $this->login_user->user_type == "client") {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "client_id" => "required|numeric"
        ));

        $client_id = $this->request->getPost('client_id');
        $this->_validate_client_manage_access($client_id);

        $view_data["client_info"] = $this->Clients_model->get_one($client_id);

        $view_data["available_menus"] = get_available_menus_for_clients_dropdown();
        $view_data['label_column'] = "col-md-3";
        $view_data['field_column'] = "col-md-9";

        return $this->template->view('clients/contacts/invitation_modal', $view_data);
    }

    //send a client contact invitation to an email address
    function send_invitation() {
        if (get_setting("disable_user_invitation_option_by_clients") && $this->login_user->user_type == "client") {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "client_id" => "required|numeric",
            "email" => "required|valid_email"
        ));

        $client_id = $this->request->getPost('client_id');
        $this->_validate_client_manage_access($client_id);

        $email = trim($this->request->getPost('email'));

        $can_access_everything = $this->request->getPost('can_access_everything');
        $specific_permissions = $this->request->getPost('specific_permissions');

        if ($can_access_everything == 1) {
            $client_permission = 'all';
        } else {
            $client_permission = $specific_permissions;
        }

        if (!$client_permission) {
            echo json_encode(array("success" => false, 'message' => app_lang('permission_is_required')));
            exit();
        }

        $email_template = $this->Email_templates_model->get_final_template("client_contact_invitation"); //use default template since sending new invitation

        $parser_data["INVITATION_SENT_BY"] = $this->login_user->first_name . " " . $this->login_user->last_name;
        $parser_data["SIGNATURE"] = $email_template->signature;
        $parser_data["SITE_URL"] = get_uri();
        $parser_data["LOGO_URL"] = get_logo_url();
        $code = make_random_string();

        $verification_data = array(
            "type" => "invitation",
            "code" => $code,
            "params" => serialize(array(
                "email" => $email,
                "type" => "client",
                "client_id" => $client_id,
                "expire_time" => time() + (24 * 60 * 60), //make the invitation url with 24hrs validity
                "client_permissions" => $client_permission
            ))
        );

        $save_id = $this->Verification_model->ci_save($verification_data);
        if (!$save_id) {
            echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
            exit();
        }

        $parser_data['INVITATION_URL'] = get_uri("signup/accept_invitation/" . $code);

        //send invitation email
        $message = $this->parser->setData($parser_data)->renderString($email_template->message);
        $subject = $this->parser->setData($parser_data)->renderString($email_template->subject);

        if (send_app_mail($email, $subject, $message)) {
            echo json_encode(array('success' => true, 'message' => app_lang("invitation_sent")));
        } else {
            echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* only visible to client  */

    function users() {
        if ($this->login_user->user_type === "client") {
            $view_data['client_id'] = $this->login_user->client_id;
            return $this->template->rander("clients/contacts/users", $view_data);
        }
    }

    /* show keyboard shortcut modal form */

    function keyboard_shortcut_modal_form() {
        return $this->template->view('team_members/keyboard_shortcut_modal_form');
    }


    /* import clients */

    private function _validate_excel_import_access() {
        return $this->_validate_client_manage_access();
    }

    private function _get_controller_slag() {
        return "clients";
    }

    private function _get_custom_field_context() {
        return "clients";
    }

    private function _get_headers_for_import() {
        return array(
            array("name" => "company_name", "required" => true, "required_message" => app_lang("import_client_error_company_name_field_required")),
            array("name" => "type", "required" => true, "required_message" => app_lang("import_error_type_field_required"), "custom_validation" => function ($type, $row_data) {
                $type = trim(strtolower($type));
                if ($type !== "person" && $type !== "organization") {
                    return array("error" => app_lang("import_error_invalid_type"));
                }
            }),
            array("name" => "contact_first_name", "custom_validation" => function ($contact_first_name, $row_data) {
                //if there is contact first name then the contact last name is required
                if (get_array_value($row_data, "3") && !$contact_first_name) {
                    return array("error" => app_lang("import_client_error_contact_name"));
                }
            }),
            array("name" => "contact_last_name", "custom_validation" => function ($contact_last_name, $row_data) {
                //if there is contact first name then the contact last name is required
                if (get_array_value($row_data, "2") && !$contact_last_name) {
                    return array("error" => app_lang("import_client_error_contact_name"));
                }
            }),
            array("name" => "contact_email", "required" => true, "required_message" => app_lang("import_client_error_contact_email"), "custom_validation" => function ($value, $row_data) {
                //checking duplicate email
                if ($this->Users_model->is_email_exists($value)) {
                    return array("error" => app_lang("duplicate_email"));
                }
            }),
            array("name" => "address"),
            array("name" => "city"),
            array("name" => "state"),
            array("name" => "zip"),
            array("name" => "country"),
            array("name" => "phone"),
            array("name" => "website"),
            array("name" => "vat_number"),
            array("name" => "client_groups"),
            array("name" => "currency"),
            array("name" => "currency_symbol")
        );
    }

    function download_sample_excel_file() {
        $this->access_only_allowed_members();
        return $this->download_app_files(get_setting("system_file_path"), serialize(array(array("file_name" => "import-clients-sample.xlsx"))));
    }

    private function _init_required_data_before_starting_import() {

        $client_groups = $this->Client_groups_model->get_details()->getResult();
        $client_groups_id_by_title = array();
        foreach ($client_groups as $group) {
            $client_groups_id_by_title[$group->title] = $group->id;
        }

        $this->client_groups_id_by_title = $client_groups_id_by_title;
    }

    private function _save_a_row_of_excel_data($row_data) {
        $now = get_current_utc_time();

        $client_data_array = $this->_prepare_client_data($row_data);
        $client_data = get_array_value($client_data_array, "client_data");
        $client_contact_data = get_array_value($client_data_array, "client_contact_data");
        $custom_field_values_array = get_array_value($client_data_array, "custom_field_values_array");

        //couldn't prepare valid data
        if (!($client_data && count($client_data) > 1)) {
            return false;
        }

        if (!isset($client_data["owner_id"])) {
            $client_data["owner_id"] = $this->login_user->id;
        }

        //found information about client, add some additional info
        $client_data["created_date"] = $now;
        $client_contact_data["created_at"] = $now;

        //save client data
        $saved_id = $this->Clients_model->ci_save($client_data);
        if (!$saved_id) {
            return false;
        }

        //save custom fields
        $this->_save_custom_fields($saved_id, $custom_field_values_array);

        //add client id to contact data
        $client_contact_data["client_id"] = $saved_id;
        $this->Users_model->ci_save($client_contact_data);
        return true;
    }

    private function _prepare_client_data($row_data) {

        $client_data = array();
        $client_contact_data = array("user_type" => "client", "is_primary_contact" => 1);
        $custom_field_values_array = array();

        foreach ($row_data as $column_index => $value) {
            if (!$value) {
                continue;
            }

            $column_name = $this->_get_column_name($column_index);
            if ($column_name == "company_name") {
                $client_data["company_name"] = $value;
            } else if ($column_name == "type") {
                $type = strtolower(trim($value));
                $client_data["type"] = $type;
            } else if ($column_name == "contact_first_name") {
                $client_contact_data["first_name"] = $value;
            } else if ($column_name == "contact_last_name") {
                $client_contact_data["last_name"] = $value;
            } else if ($column_name == "contact_email") {
                $client_contact_data["email"] = $value;
            } else if ($column_name == "client_groups") {
                if ($value) {
                    $groups = "";
                    $groups_array = explode(",", $value);
                    foreach ($groups_array as $group) {
                        //get existing groups, if not create new one and add the id
                        $group_id = get_array_value($this->client_groups_id_by_title, trim($group));

                        if ($groups) {
                            $groups .= ",";
                        }

                        if ($group_id) {
                            $groups .= $group_id;
                        } else {
                            $data = array("title" => trim($group));
                            $client_group_id = $this->Client_groups_model->ci_save($data);
                            $groups .= $client_group_id;
                            $this->client_groups_id_by_title[trim($group)] = $client_group_id;
                        }
                    }

                    $client_data["group_ids"] = $groups;
                }
            } else if (strpos($column_name, 'cf') !== false) {
                $this->_prepare_custom_field_values_array($column_name, $value, $custom_field_values_array);
            } else {
                $client_data[$column_name] = $value;
            }
        }

        return array(
            "client_data" => $client_data,
            "client_contact_data" => $client_contact_data,
            "custom_field_values_array" => $custom_field_values_array
        );
    }

    function gdpr() {
        $view_data["user_info"] = $this->Users_model->get_one($this->login_user->id);
        return $this->template->view("clients/contacts/gdpr", $view_data);
    }

    function export_my_data() {
        if (get_setting("enable_gdpr") && get_setting("allow_clients_to_export_their_data")) {
            $user_info = $this->Users_model->get_one($this->login_user->id);

            $txt_file_name = $user_info->first_name . " " . $user_info->last_name . ".txt";

            $data = $this->_make_export_data($user_info);

            $handle = fopen($txt_file_name, "w");
            fwrite($handle, $data);
            fclose($handle);

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($txt_file_name));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($txt_file_name));
            readfile($txt_file_name);

            //delete local file
            if (file_exists($txt_file_name)) {
                unlink($txt_file_name);
            }

            exit;
        }
    }

    private function _make_export_data($user_info) {
        $required_general_info_array = array("first_name", "last_name", "email", "job_title", "phone", "gender", "skype", "created_at");

        $data = strtoupper(app_lang("general_info")) . "\n";

        //add general info
        foreach ($required_general_info_array as $field) {
            if ($user_info->$field) {
                if ($field == "created_at") {
                    $data .= app_lang("created") . ": " . format_to_datetime($user_info->$field) . "\n";
                } else if ($field == "gender") {
                    $data .= app_lang($field) . ": " . ucfirst($user_info->$field) . "\n";
                } else if ($field == "skype") {
                    $data .= "Skype: " . ucfirst($user_info->$field) . "\n";
                } else {
                    $data .= app_lang($field) . ": " . $user_info->$field . "\n";
                }
            }
        }

        $data .= "\n\n";
        $data .= strtoupper(app_lang("client_info")) . "\n";

        //add company info
        $client_info = $this->Clients_model->get_one($user_info->client_id);
        $required_client_info_array = array("company_name", "address", "city", "state", "zip", "country", "phone", "website", "vat_number");
        foreach ($required_client_info_array as $field) {
            if ($client_info->$field) {
                $data .= app_lang($field) . ": " . $client_info->$field . "\n";
            }
        }

        $data .= "\n\n";
        $data .= strtoupper(app_lang("social_links")) . "\n";

        //add social links
        $social_links = $this->Social_links_model->get_one($user_info->id);

        unset($social_links->id);
        unset($social_links->user_id);
        unset($social_links->deleted);

        foreach ($social_links as $key => $value) {
            if ($value) {
                $data .= ucfirst($key) . ": " . $value . "\n";
            }
        }

        return $data;
    }

    function request_my_account_removal() {
        if (get_setting("enable_gdpr") && get_setting("clients_can_request_account_removal")) {

            $user_id = $this->login_user->id;
            $data = array("requested_account_removal" => 1);
            $this->Users_model->ci_save($data, $user_id);

            $client_id = $this->Users_model->get_one($user_id)->client_id;
            log_notification("client_contact_requested_account_removal", array("client_id" => $client_id), $user_id);

            $this->session->setFlashdata("success_message", app_lang("estimate_submission_message"));
            app_redirect("clients/contact_profile/$user_id/gdpr");
        }
    }

    /* load expenses tab  */

    function expenses($client_id) {
        $this->can_access_expenses();
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = clean_data($client_id);

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("expenses", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("expenses", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/expenses/index", $view_data);
        }
    }

    function contracts($client_id) {
        validate_numeric_value($client_id);
        $this->access_only_allowed_members();

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = $client_id;

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("contracts", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/contracts/contracts", $view_data);
        }
    }

    function clients_list() {
        $this->access_only_allowed_members();

        $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("clients", $this->login_user->is_admin, $this->login_user->user_type);

        $access_info = $this->get_access_info("invoice");
        $view_data["show_invoice_info"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;
        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("clients", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['groups_dropdown'] = json_encode($this->_get_groups_dropdown_select2_data(true));
        $view_data['can_edit_clients'] = $this->can_edit_clients();
        $view_data["team_members_dropdown"] = $this->get_team_members_dropdown(true);
        $view_data['labels_dropdown'] = json_encode($this->make_labels_dropdown("client", "", true));

        return $this->template->view("clients/clients_list", $view_data);
    }

    private function make_access_permissions_view_data() {

        $access_invoice = $this->get_access_info("invoice");
        $view_data["show_invoice_info"] = (get_setting("module_invoice") && $access_invoice->access_type == "all") ? true : false;

        $access_estimate = $this->get_access_info("estimate");
        $view_data["show_estimate_info"] = (get_setting("module_estimate") && $access_estimate->access_type == "all") ? true : false;

        $view_data["show_estimate_request_info"] = (get_setting("module_estimate_request") && $access_estimate->access_type == "all") ? true : false;

        $access_order = $this->get_access_info("order");
        $view_data["show_order_info"] = (get_setting("module_order") && $access_order->access_type == "all") ? true : false;

        $access_proposal = $this->get_access_info("proposal");
        $view_data["show_proposal_info"] = (get_setting("module_proposal") && $access_proposal->access_type == "all") ? true : false;

        $access_ticket = $this->get_access_info("ticket");
        $view_data["show_ticket_info"] = (get_setting("module_ticket") && $access_ticket->access_type == "all") ? true : false;

        $access_contract = $this->get_access_info("contract");
        $view_data["show_contract_info"] = (get_setting("module_contract") && $access_contract->access_type == "all") ? true : false;
        $view_data["show_project_info"] = !$this->has_all_projects_restricted_role();

        $access_subscription = $this->get_access_info("subscription");
        $view_data["show_subscription_info"] = (get_setting("module_subscription") && $access_subscription->access_type == "all") ? true : false;

        return $view_data;
    }

    function proposals($client_id) {
        validate_numeric_value($client_id);
        $this->access_only_allowed_members();

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = $client_id;

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("proposals", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("proposals", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/proposals/proposals", $view_data);
        }
    }

    function switch_account($user_id) {
        validate_numeric_value($user_id);
        $this->access_only_clients();

        $options = array(
            'id' => $user_id,
            'email' => $this->login_user->email,
            'status' => 'active',
            'deleted' => 0,
            'disable_login' => 0,
            'user_type' => 'client'
        );

        $user_info = $this->Users_model->get_one_where($options);
        if (!$user_info->id) {
            show_404();
        }

        $session = \Config\Services::session();
        $session->set('user_id', $user_info->id);

        app_redirect('dashboard/view');
    }

    /* load tasks tab  */

    function tasks($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);
        $this->restrict_client_access();

        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("tasks", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data["can_create_task"] = $this->can_edit_clients();

        $view_data['client_id'] = clean_data($client_id);
        return $this->template->view("clients/tasks/index", $view_data);
    }

    function subscriptions($client_id) {
        validate_numeric_value($client_id);
        $this->_validate_client_view_access($client_id);

        if ($client_id) {
            $view_data["client_info"] = $this->Clients_model->get_one($client_id);
            $view_data['client_id'] = $client_id;

            $view_data["can_edit_subscriptions"] = $this->can_edit_subscriptions();

            $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("subscriptions", $this->login_user->is_admin, $this->login_user->user_type);
            $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("subscriptions", $this->login_user->is_admin, $this->login_user->user_type);

            return $this->template->view("clients/subscriptions/index", $view_data);
        }
    }

    function contact_permissions($contact_id) {
        validate_numeric_value($contact_id);

        $user_info = $this->Users_model->get_one($contact_id);
        $this->_validate_client_manage_access($user_info->client_id);
        $view_data["available_menus"] = get_available_menus_for_clients_dropdown();
        $view_data["user_info"] = $user_info;
        $view_data['label_column'] = "col-md-2";
        $view_data['field_column'] = "col-md-10";
        return $this->template->view("clients/contacts/contact_permissions", $view_data);
    }

    function save_contact_permissions($contact_id) {
        validate_numeric_value($contact_id);

        $user_info = $this->Users_model->get_one($contact_id);
        $this->_validate_client_manage_access($user_info->client_id);

        $primary_contact = $this->Clients_model->get_primary_contact($user_info->client_id);

        //only admin can change existing primary contact
        $is_primary_contact = $this->request->getPost('is_primary_contact');
        if ($is_primary_contact && $this->login_user->is_admin) {
            $user_data['is_primary_contact'] = 1;
        }

        if (isset($user_data['is_primary_contact']) && $user_data['is_primary_contact'] == 1) {
            $user_data['client_permissions'] = "all";
        } else {
            $can_access_everything = $this->request->getPost('can_access_everything');
            $specific_permissions = $this->request->getPost('specific_permissions');

            if ($can_access_everything == 1) {
                $user_data['client_permissions'] = 'all';
            } else {
                $user_data['client_permissions'] = $specific_permissions;
            }
        }

        if (!$user_data['client_permissions']) {
            echo json_encode(array("success" => false, 'message' => app_lang('permission_is_required')));
            exit();
        }

        if ($this->Users_model->ci_save($user_data, $contact_id)) {
            //has changed the existing primary contact? updete previous primary contact and set is_primary_contact=0
            if ($is_primary_contact) {
                $user_data = array("is_primary_contact" => 0);
                $this->Users_model->ci_save($user_data, $primary_contact);
            }

            echo json_encode(array("success" => true, 'message' => app_lang('record_updated')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    function contact_permissions_modal_form() {
        $contact_id = $this->request->getPost('id');
        $user_info = $this->Users_model->get_one($contact_id);
        $this->_validate_client_manage_access($user_info->client_id);
        $view_data["available_menus"] = get_available_menus_for_clients_dropdown();
        $view_data["user_info"] = $user_info;
        $view_data['label_column'] = "col-md-3";
        $view_data['field_column'] = "col-md-9";

        return $this->template->view('clients/contacts/contact_permissions_modal_form', $view_data);
    }

    private function restrict_client_access() {
        if ($this->login_user->user_type === "client") {
            app_redirect("forbidden");
        }
    }

    //used by App_folders
    private function _folder_items($folder_id = "", $context_type = "", $context_id = 0) {
        $options = array(
            "folder_id" => $folder_id,
            "context_type" => $context_type,
            "is_admin" => $this->login_user->is_admin
        );

        $options["client_id"] = $context_id;
        return $this->General_files_model->get_details($options)->getResult();
    }

    //used by App_folders
    private function _folder_config() {
        $info = new \stdClass();
        $info->controller_slag = "clients";
        $info->add_files_modal_url = get_uri("clients/file_modal_form");

        $info->file_preview_url = get_uri("clients/view_file");
        $info->show_file_preview_sidebar = false;
        return $info;
    }

    //used by App_folders
    private function _shareable_options() {
        return array();
    }

    //used by App_folders
    private function _get_file_path($file_info) {
        return get_general_file_path("client", $file_info->client_id);
    }

    //used by App_folders
    private function _get_file_info($file_id) {
        validate_numeric_value($file_id);
        $this->_validate_view_file_access();
        $file_info = $this->General_files_model->get_details(array("id" => $file_id))->getRow();

        if ($file_info) {
            if (!$file_info->client_id) {
                app_redirect("forbidden");
            }

            $this->_validate_client_view_access($file_info->client_id);
        }

        return $file_info;
    }

    //used by App_folders
    private function _download_file($id) {
        validate_numeric_value($id);
        $file_info = $this->_get_file_info($id);

        if ($file_info) {
            //serilize the path
            $file_data = serialize(array(make_array_of_file($file_info)));
            return $this->download_app_files(get_general_file_path("client", $file_info->client_id), $file_data);
        }
    }

    //used by App_folders
    private function _delete_file($id) {
        validate_numeric_value($id);
        $info = $this->General_files_model->get_one($id);

        if (!$info || !$info->client_id || ($this->login_user->user_type == "client" && $info->uploaded_by !== $this->login_user->id)) {
            app_redirect("forbidden");
        }

        $this->_validate_client_manage_access($info->client_id);

        if ($this->General_files_model->delete($id)) {
            delete_app_files(get_general_file_path("client", $info->client_id), array(make_array_of_file($info)));
            return true;
        }
    }

    //used by App_folders
    private function _move_file_to_another_folder($file_id, $folder_id) {
        validate_numeric_value($file_id);
        validate_numeric_value($folder_id);

        $data = array("folder_id" => $folder_id);
        $data = clean_data($data);

        $save_id = $this->General_files_model->ci_save($data, $file_id);

        if ($save_id) {
            echo json_encode(array("success" => true, "data" => "", 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    //used by App_folders
    private function _get_all_files_of_folder($folder_id, $client_id) {
        validate_numeric_value($folder_id);
        validate_numeric_value($client_id);

        $this->_validate_view_file_access();

        if ($this->login_user->user_type == "client") {
            if ($this->login_user->client_id != $client_id) {
                app_redirect("forbidden");
            }
        } else {
            $this->_validate_client_view_access($client_id);
        }

        return $this->General_files_model->get_all_where(array("folder_id" => $folder_id, "client_id" => $client_id))->getResult();
    }

    //used by App_folders
    private function _can_create_folder($parent_folder_id = 0, $context_id = 0) {
        validate_numeric_value($context_id);

        if ($this->login_user->is_admin) {
            return true;
        } else {
            $this->_validate_add_file_access();
            // client or team members both can create folder
            if ($this->login_user->user_type == "client" && $this->login_user->client_id == $context_id) {
                return true;
            } else if ($this->login_user->user_type == "staff") {
                return true;
            }
        }
    }

    //used by App_folders

    private function _can_manage_folder($parent_folder_id = 0, $context_id = 0) {
        if ($this->login_user->is_admin) {
            return true;
        } else {
            $this->_validate_add_file_access();

            if (($this->login_user->user_type != "staff")) {
                return false;
            }
        }
    }

    //used by App_folders
    private function _can_upload_file($folder_id = 0, $context_id = 0) {
        validate_numeric_value($context_id);

        if ($this->login_user->is_admin) {
            return true;
        }

        $this->_validate_add_file_access();

        if ($this->login_user->user_type == "client" && $this->login_user->client_id != $context_id) {
            return false;
        }
        if ($this->login_user->user_type == "staff") {
            $this->_validate_client_manage_access($context_id);
        }

        return true;
    }
}

/* End of file clients.php */
/* Location: ./app/controllers/clients.php */