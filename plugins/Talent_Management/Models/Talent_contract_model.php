<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_contract_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_contracts";
        parent::__construct($this->table);
    }

    //newest contract of a casting link, whatever state it is in; it drives the badge and whether "Send contract" is offered.
    //The frozen text is left out because callers only need the state.
    function get_latest_for_talent_project($talent_project_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);

        $sql = "SELECT $talent_contracts_table.id, $talent_contracts_table.talent_project_id, $talent_contracts_table.title, $talent_contracts_table.status,
                $talent_contracts_table.token_expires_at, $talent_contracts_table.sent_to_email, $talent_contracts_table.sent_at, $talent_contracts_table.signed_at
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.talent_project_id=$talent_project_id
                ORDER BY $talent_contracts_table.id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }
}
