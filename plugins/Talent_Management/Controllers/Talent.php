<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Models\Talent_model;
use Talent_Management\Models\Talent_project_model;
use Talent_Management\Models\Talent_status_model;

class Talent extends Security_Controller {

    private $Talent_model;
    private $Talent_project_model;
    private $Talent_status_model;

    function __construct() {
        parent::__construct();
        $this->Talent_model = new Talent_model();
        $this->Talent_project_model = new Talent_project_model();
        $this->Talent_status_model = new Talent_status_model();
    }

    private function _can_access() {
        if (!talent_can_access_staff()) {
            app_redirect("forbidden");
        }
    }

    function index() {
        $this->_can_access();

        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("talent", $this->login_user->is_admin, $this->login_user->user_type);

        return $this->template->rander('Talent_Management\Views\talent\index', $view_data);
    }

    function custom_fields() {
        $this->_can_access();
        return $this->template->rander('Talent_Management\Views\talent\custom_fields');
    }

    function modal_form() {
        $this->_can_access();

        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $talent_id = $this->request->getPost("id");

        $view_data["model_info"] = $this->Talent_model->get_one($talent_id);
        $view_data["label_column"] = "col-md-3";
        $view_data["field_column"] = "col-md-9";

        return $this->template->view('Talent_Management\Views\talent\modal_form', $view_data);
    }

    function save() {
        $this->_can_access();

        $talent_id = $this->request->getPost("id");

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "legal_name" => "required"
        ));

        //the modal only carries the General Information fields; the other tabs save their own columns
        $data = $this->_get_posted_general_info();

        if (!$talent_id) {
            $data["created_by"] = $this->login_user->id;
            $data["created_at"] = get_current_utc_time();
        }

        $data = clean_data($data);

        $save_id = $this->Talent_model->ci_save($data, $talent_id);
        if ($save_id) {
            echo json_encode(array("success" => true, "data" => $this->_row_data($save_id), "id" => $save_id, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function delete() {
        $this->_can_access();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Talent_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Talent_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    function list_data() {
        $this->_can_access();

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("talent", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array(
            "custom_fields" => $custom_fields,
        );

        $list_data = $this->Talent_model->get_details($options)->getResult();

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = $this->_make_row($data, $custom_fields);
        }

        echo json_encode(array("data" => $result_data));
    }

    private function _row_data($id) {
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("talent", $this->login_user->is_admin, $this->login_user->user_type);
        $data = $this->Talent_model->get_details(array("id" => $id, "custom_fields" => $custom_fields))->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    private function _make_row($data, $custom_fields) {
        $image_url = get_avatar($data->profile_image);
        $name = "<span class='avatar avatar-xs mr10'><img src='$image_url' alt='...'></span> " . ($data->preferred_name ? $data->preferred_name : $data->legal_name);

        $row_data = array(
            anchor(get_uri("talent/view/" . $data->id), $name, array("class" => "js-selection-id", "data-id" => $data->id)),
            $data->profession ?: "-",
            $data->contact_number ?: "-",
            $data->email ?: "-",
        );

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = $this->template->view("custom_fields/output_" . $field->field_type, array("value" => $data->$cf_id));
        }

        $row_data[] = modal_anchor(get_uri("talent/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("edit_talent"), "data-post-id" => $data->id))
            . js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("delete_talent"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("talent/delete"), "data-action" => "delete-confirmation"));

        return $row_data;
    }

    private function _get_posted_social_links() {
        $social_platforms = $this->request->getPost("social_platform");
        $social_urls = $this->request->getPost("social_url");
        $social_links = array();
        if (is_array($social_platforms)) {
            foreach ($social_platforms as $index => $platform) {
                $url = get_array_value($social_urls, $index);
                if ($platform && $url) {
                    $social_links[] = array("platform" => $platform, "url" => $url);
                }
            }
        }
        return $social_links;
    }

    //returns the talent record or 404s
    private function _get_talent_or_404($talent_id) {
        validate_numeric_value($talent_id);

        $model_info = $this->Talent_model->get_one($talent_id);
        if (!$model_info->id) {
            show_404();
        }
        return $model_info;
    }

    function view($talent_id = 0, $tab = "") {
        $this->_can_access();
        $model_info = $this->_get_talent_or_404($talent_id);

        $view_data["model_info"] = $model_info;
        $view_data["social_links"] = $model_info->social_links ? json_decode($model_info->social_links, true) : array();
        $view_data["tab"] = clean_data($tab);
        $view_data["show_additional_info_tab"] = count($this->_get_custom_fields($talent_id)) > 0;

        //header widgets: total projects + the first few pipeline stages (kept to a tidy number)
        $counts = $this->Talent_project_model->get_stage_counts($talent_id);
        $widgets = array(array("title" => app_lang("talent_total_projects"), "count" => array_sum($counts)));
        foreach (array_slice($this->Talent_status_model->get_details()->getResult(), 0, 3) as $status) {
            $widgets[] = array("title" => $status->title, "count" => get_array_value($counts, $status->id) ?: 0);
        }
        $view_data["widgets"] = $widgets;

        return $this->template->rander('Talent_Management\Views\talent\view', $view_data);
    }

    //1st tab: the fields shared with the Add/Edit modal
    private function _get_posted_general_info() {
        return array(
            "legal_name" => $this->request->getPost("legal_name"),
            "preferred_name" => $this->request->getPost("preferred_name"),
            "pronouns" => $this->request->getPost("pronouns"),
            "address" => $this->request->getPost("address"),
            "profession" => $this->request->getPost("profession"),
        );
    }

    //saves only the given columns of one talent record and answers the tab's ajax form
    private function _save_tab_data($talent_id, $data) {
        //ci_save() takes its data by reference, so it needs a variable
        $data = clean_data($data);

        if ($this->Talent_model->ci_save($data, $talent_id)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_updated")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function general_info($talent_id = 0) {
        $this->_can_access();
        $view_data["model_info"] = $this->_get_talent_or_404($talent_id);

        return $this->template->view('Talent_Management\Views\talent\general_info', $view_data);
    }

    function save_general_info($talent_id = 0) {
        $this->_can_access();
        $this->_get_talent_or_404($talent_id);

        $this->validate_submitted_data(array(
            "legal_name" => "required"
        ));

        $this->_save_tab_data($talent_id, $this->_get_posted_general_info());
    }

    //2nd tab
    function contact_info($talent_id = 0) {
        $this->_can_access();
        $view_data["model_info"] = $this->_get_talent_or_404($talent_id);

        return $this->template->view('Talent_Management\Views\talent\contact_info', $view_data);
    }

    function save_contact_info($talent_id = 0) {
        $this->_can_access();
        $this->_get_talent_or_404($talent_id);

        $this->_save_tab_data($talent_id, array(
            "contact_number" => $this->request->getPost("contact_number"),
            "email" => $this->request->getPost("email"),
        ));
    }

    //4th tab
    function preferences($talent_id = 0) {
        $this->_can_access();
        $view_data["model_info"] = $this->_get_talent_or_404($talent_id);

        return $this->template->view('Talent_Management\Views\talent\preferences', $view_data);
    }

    function save_preferences($talent_id = 0) {
        $this->_can_access();
        $this->_get_talent_or_404($talent_id);

        $this->_save_tab_data($talent_id, array(
            "on_screen_title" => $this->request->getPost("on_screen_title"),
            "dietary_restrictions" => $this->request->getPost("dietary_restrictions"),
            "safety_comfort_notes" => $this->request->getPost("safety_comfort_notes"),
        ));
    }

    //admin-defined custom fields (tab only appears once at least one exists)
    function additional_info($talent_id = 0) {
        $this->_can_access();
        $view_data["model_info"] = $this->_get_talent_or_404($talent_id);
        $view_data["custom_fields"] = $this->_get_custom_fields($talent_id);

        return $this->template->view('Talent_Management\Views\talent\additional_info', $view_data);
    }

    function save_additional_info($talent_id = 0) {
        $this->_can_access();
        $this->_get_talent_or_404($talent_id);

        save_custom_fields("talent", $talent_id, $this->login_user->is_admin, $this->login_user->user_type);
        echo json_encode(array("success" => true, "message" => app_lang("record_updated")));
    }

    private function _get_custom_fields($talent_id) {
        return $this->Custom_fields_model->get_combined_details("talent", $talent_id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();
    }

    function social_links($talent_id = 0) {
        $this->_can_access();
        $model_info = $this->_get_talent_or_404($talent_id);

        $view_data["model_info"] = $model_info;
        $view_data["social_links"] = $model_info->social_links ? json_decode($model_info->social_links, true) : array();

        return $this->template->view('Talent_Management\Views\talent\social_links', $view_data);
    }

    function save_social_links($talent_id = 0) {
        $this->_can_access();
        $this->_get_talent_or_404($talent_id);

        $social_links = $this->_get_posted_social_links();
        $data = clean_data(array("social_links" => $social_links ? json_encode($social_links) : ""));

        if ($this->Talent_model->ci_save($data, $talent_id)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_updated")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function projects_info($talent_id = 0) {
        $this->_can_access();
        $view_data["model_info"] = $this->_get_talent_or_404($talent_id);

        return $this->template->view('Talent_Management\Views\talent\projects_info', $view_data);
    }

    //save headshot (crop upload) - mirrors Team_members::save_profile_image()
    function save_profile_image($talent_id = 0) {
        $this->_can_access();
        validate_numeric_value($talent_id);

        $talent_info = $this->Talent_model->get_one($talent_id);

        $profile_image = str_replace("~", ":", $this->request->getPost("profile_image"));

        if ($profile_image) {
            $profile_image = serialize(move_temp_file("avatar.png", get_setting("profile_image_path"), "talent", $profile_image));

            delete_app_files(get_setting("profile_image_path"), array(@unserialize($talent_info->profile_image)));

            $image_data = array("profile_image" => $profile_image);
            $this->Talent_model->ci_save($image_data, $talent_id);
            echo json_encode(array("success" => true, "message" => app_lang("profile_image_changed")));
            return;
        }

        if ($_FILES) {
            $profile_image_file = get_array_value($_FILES, "profile_image_file");
            $image_file_name = get_array_value($profile_image_file, "tmp_name");
            $image_file_size = get_array_value($profile_image_file, "size");
            if ($image_file_name) {
                if (!$this->check_profile_image_dimension($image_file_name)) {
                    echo json_encode(array("success" => false, "message" => app_lang("profile_image_error_message")));
                    return;
                }

                $profile_image = serialize(move_temp_file("avatar.png", get_setting("profile_image_path"), "talent", $image_file_name, "", "", false, $image_file_size));

                if ($talent_info->profile_image) {
                    delete_app_files(get_setting("profile_image_path"), array(@unserialize($talent_info->profile_image)));
                }

                $image_data = array("profile_image" => $profile_image);
                $this->Talent_model->ci_save($image_data, $talent_id);
                echo json_encode(array("success" => true, "message" => app_lang("profile_image_changed"), "reload_page" => true));
            }
        }
    }
}
