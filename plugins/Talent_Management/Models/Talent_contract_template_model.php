<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_contract_template_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_contract_templates";
        parent::__construct($this->table);
    }

    //the list doesn't need each template's full text, so content is left out (get_one() has it for the editor)
    function get_details($options = array()) {
        $talent_contract_templates_table = $this->db->prefixTable("talent_contract_templates");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $talent_contract_templates_table.id=$id";
        }

        $sql = "SELECT $talent_contract_templates_table.id, $talent_contract_templates_table.title, $talent_contract_templates_table.created_by, $talent_contract_templates_table.created_at
        FROM $talent_contract_templates_table
        WHERE $talent_contract_templates_table.deleted=0 $where
        ORDER BY $talent_contract_templates_table.title ASC";
        return $this->db->query($sql);
    }
}
