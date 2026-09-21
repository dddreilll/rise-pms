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

    //has this casting link got a signature on file? A voided or declined contract doesn't count, only a signed one.
    function has_signed($talent_project_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);

        $sql = "SELECT $talent_contracts_table.id
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.status='signed' AND $talent_contracts_table.talent_project_id=$talent_project_id
                LIMIT 1";

        return $this->db->query($sql)->getRow() ? true : false;
    }

    //everything the public signing page needs except the signed PDF, which can be large and is fetched on its own
    function get_public($contract_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $contract_id = $this->_get_clean_value($contract_id);

        $sql = "SELECT $talent_contracts_table.id, $talent_contracts_table.talent_project_id, $talent_contracts_table.template_id, $talent_contracts_table.title,
                $talent_contracts_table.content, $talent_contracts_table.content_hash, $talent_contracts_table.token_hash, $talent_contracts_table.token_expires_at,
                $talent_contracts_table.status, $talent_contracts_table.sent_to_email, $talent_contracts_table.sent_at, $talent_contracts_table.signer_name,
                $talent_contracts_table.signer_email, $talent_contracts_table.signed_at, $talent_contracts_table.signature_data, $talent_contracts_table.pdf_hash,
                $talent_contracts_table.decline_reason, $talent_contracts_table.deleted
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.id=$contract_id";

        return $this->db->query($sql)->getRow();
    }

    function get_signed_pdf_data($contract_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $contract_id = $this->_get_clean_value($contract_id);

        $row = $this->db->query("SELECT $talent_contracts_table.signed_pdf_data FROM $talent_contracts_table WHERE $talent_contracts_table.id=$contract_id")->getRow();
        return $row ? (string) $row->signed_pdf_data : "";
    }
}
