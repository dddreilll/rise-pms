<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Templates_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_templates";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $templates_table = $this->db->prefixTable("agreements_templates");
        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $templates_table.id=$id";
        }
        $sql = "SELECT * FROM $templates_table WHERE deleted=0 $where ORDER BY id DESC";
        return $this->db->query($sql);
    }
}
