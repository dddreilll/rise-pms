<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Audit_Logs_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_audit_logs";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $table = $this->db->prefixTable("agreements_audit_logs");
        $where = "";
        $document_id = $this->_get_clean_value($options, "document_id");
        if ($document_id) {
            $where .= " AND $table.document_id=$document_id";
        }
        $sql = "SELECT * FROM $table WHERE 1=1 $where ORDER BY id DESC";
        return $this->db->query($sql);
    }
}
