<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Fields_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_fields";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $fields_table = $this->db->prefixTable("agreements_fields");
        $where = "";
        $document_id = $this->_get_clean_value($options, "document_id");
        if ($document_id) {
            $where .= " AND $fields_table.document_id=$document_id";
        }
        $template_id = $this->_get_clean_value($options, "template_id");
        if ($template_id) {
            $where .= " AND $fields_table.template_id=$template_id";
        }
        $sql = "SELECT * FROM $fields_table WHERE deleted=0 $where ORDER BY sort ASC, id ASC";
        return $this->db->query($sql);
    }
}
