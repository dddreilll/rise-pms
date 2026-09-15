<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Settings_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_settings";
        parent::__construct($this->table);
    }

    function get_setting($setting_name) {
        $result = $this->db_builder->getWhere(array("setting_name" => $setting_name, "deleted" => 0), 1);
        if ($result->getRow()) {
            return $result->getRow()->setting_value;
        }
        return null;
    }

    function save_setting($setting_name, $setting_value) {
        $existing = $this->db_builder->getWhere(array("setting_name" => $setting_name), 1)->getRow();
        if ($existing) {
            return $this->db_builder->where("setting_name", $setting_name)->update(array(
                "setting_value" => $setting_value,
                "deleted" => 0,
            ));
        }
        return $this->db_builder->insert(array(
            "setting_name" => $setting_name,
            "setting_value" => $setting_value,
            "type" => "app",
            "deleted" => 0,
        ));
    }
}
