<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Models\Talent_contract_template_model;
use Talent_Management\Models\Talent_project_agreement_model;

//which agreements a project requires of its talent (the "Required agreements" list in the project's Talent tab)
class Talent_project_agreements extends Security_Controller {

    private $Talent_project_agreement_model;
    private $Talent_contract_template_model;

    function __construct() {
        parent::__construct();

        if (!talent_can_access_staff()) {
            app_redirect("forbidden");
        }

        talent_ensure_schema_once();
        $this->Talent_project_agreement_model = new Talent_project_agreement_model();
        $this->Talent_contract_template_model = new Talent_contract_template_model();
    }

    function modal_form() {
        $this->validate_submitted_data(array(
            "project_id" => "required|numeric"
        ));

        $project_id = $this->request->getPost("project_id");
        if (!$this->Talent_project_agreement_model->project_exists($project_id)) {
            show_404();
        }

        $options = array();
        foreach ($this->Talent_contract_template_model->get_details()->getResult() as $template) {
            $options[] = array("id" => $template->id, "text" => $template->title);
        }

        $selected = array();
        foreach ($this->Talent_project_agreement_model->get_templates_for_project($project_id) as $template) {
            $selected[] = $template->id;
        }

        $view_data["project_id"] = $project_id;
        $view_data["templates_dropdown"] = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP);
        $view_data["selected_ids"] = implode(",", $selected);
        $view_data["has_templates"] = count($options) > 0;
        $view_data["can_manage_templates"] = talent_can_manage_contract_templates();

        return $this->template->view('Talent_Management\Views\talent_project_agreements\modal_form', $view_data);
    }

    //replaces the whole list; an empty list is allowed (the project then has no requirement and staff move cards themselves)
    function save() {
        $this->validate_submitted_data(array(
            "project_id" => "required|numeric"
        ));

        $project_id = $this->request->getPost("project_id");
        if (!$this->Talent_project_agreement_model->project_exists($project_id)) {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            return;
        }

        //the select posts its choices as "3,7,9"; only live templates are kept, once each, in the order chosen
        $live = $this->Talent_project_agreement_model->get_live_template_ids();
        $template_ids = array();
        foreach (explode(",", (string) $this->request->getPost("template_ids")) as $value) {
            $value = trim($value);
            if ($value === "") {
                continue;
            }

            if (!ctype_digit($value) || !in_array((int) $value, $live, true)) {
                echo json_encode(array("success" => false, "message" => app_lang("talent_project_agreements_error_template")));
                return;
            }

            if (!in_array((int) $value, $template_ids, true)) {
                $template_ids[] = (int) $value;
            }
        }

        if ($this->Talent_project_agreement_model->replace_for_project($project_id, $template_ids, $this->login_user->id)) {
            echo json_encode(array("success" => true, "message" => app_lang("record_saved")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }
}
