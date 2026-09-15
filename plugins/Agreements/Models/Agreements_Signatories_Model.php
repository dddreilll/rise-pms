<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Signatories_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_signatories";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $table = $this->db->prefixTable("agreements_signatories");
        $where = "";
        $document_id = $this->_get_clean_value($options, "document_id");
        if ($document_id) {
            $where .= " AND $table.document_id=$document_id";
        }
        $token_hash = $this->_get_clean_value($options, "token_hash");
        if ($token_hash) {
            $where .= " AND $table.token_hash='" . $this->db->escapeString($token_hash) . "'";
        }
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $table.id=$id";
        }
        $sql = "SELECT * FROM $table WHERE deleted=0 $where ORDER BY signing_order ASC, id ASC";
        return $this->db->query($sql);
    }

    function get_by_token($token) {
        if (!$token) {
            return null;
        }
        $hash = agreements_hash_token($token);
        $row = $this->get_details(array("token_hash" => $hash))->getRow();
        return $row ? $row : null;
    }
}
