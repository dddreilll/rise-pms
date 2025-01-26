<?php

namespace App\Controllers;

use App\Libraries\App_folders;

class File_manager extends Security_Controller
{

    use App_folders;

    function __construct()
    {
        parent::__construct();
    }

    //show attendance list view
    function index()
    {
        return $this->explore();
    }

    function file_modal_form()
    {
        return $this->get_file_modal_form();
    }

    private function _get_file_path($client_id = 0, $context = "")
    {
        if ($client_id && $context != "global_files") {
            return get_general_file_path("client", $client_id);
        } else {
            return get_general_file_path("global_files", "all");
        }
    }

    function view_file($file_id = 0, $client_id = 0)
    {
        return $this->get_view_file($file_id, $client_id);
    }

    //used by App_folders
    private function _folder_items($folder_id = "", $context_type = "", $client_id = 0)
    {
        $options = array("folder_id" => $folder_id, "context_type" => $context_type, "client_id" => $client_id, "is_admin" => $this->login_user->is_admin);

        return $this->General_files_model->get_details($options)->getResult();
    }

    //used by App_folders
    private function _folder_config()
    {
        $info = new \stdClass();
        $info->controller_slag = "file_manager";
        $info->add_files_modal_url = get_uri("file_manager/file_modal_form");

        $info->file_preview_url = get_uri("file_manager/view_file");
        $info->show_file_preview_sidebar = false;

        $info->show_file_preview_sidebar = false;

        $info->global_files_path = $this->_get_file_path();

        return $info;
    }

    private function _shareable_options()
    {
        return array('all_team_members', 'team', 'member');
    }
}

/* End of file file_manager.php */
/* Location: ./app/controllers/file_manager.php */