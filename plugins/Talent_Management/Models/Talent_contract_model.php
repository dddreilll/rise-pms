<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_contract_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_contracts";
        parent::__construct($this->table);
    }

    //the columns a state check needs; the frozen text and the signature are left out because callers only need the state
    private function _state_columns() {
        $t = $this->db->prefixTable("talent_contracts");
        return "$t.id, $t.talent_project_id, $t.template_id, $t.title, $t.status, $t.token_expires_at, $t.sent_to_email, $t.sent_at, $t.signed_at, $t.signed_via";
    }

    //Newest contract of one agreement (template) on a casting link, whatever state it is in. Contracts are tracked per casting link AND
    //agreement, so this is what says whether that agreement can be sent, resent or is signed.
    function get_latest_for_agreement($talent_project_id, $template_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);
        $template_id = $this->_get_clean_value($template_id);

        $sql = "SELECT " . $this->_state_columns() . "
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.talent_project_id=$talent_project_id AND $talent_contracts_table.template_id=$template_id
                ORDER BY $talent_contracts_table.id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    //every contract of one agreement on a casting link that is still in sight, oldest first (the newest is the last one)
    function get_records_for_agreement($talent_project_id, $template_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);
        $template_id = $this->_get_clean_value($template_id);

        $sql = "SELECT " . $this->_state_columns() . "
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.talent_project_id=$talent_project_id AND $talent_contracts_table.template_id=$template_id
                ORDER BY $talent_contracts_table.id ASC";

        return $this->db->query($sql)->getResult();
    }

    //the newest contract of every agreement that has one on this casting link, oldest agreement first
    function get_latest_per_agreement($talent_project_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);

        $sql = "SELECT " . $this->_state_columns() . "
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.talent_project_id=$talent_project_id
                AND $talent_contracts_table.id=(
                    SELECT MAX(newest.id) FROM $talent_contracts_table AS newest
                    WHERE newest.deleted=0 AND newest.talent_project_id=$talent_contracts_table.talent_project_id AND newest.template_id=$talent_contracts_table.template_id
                )
                ORDER BY $talent_contracts_table.id ASC";

        return $this->db->query($sql)->getResult();
    }

    //ids of the agreements this casting link has a signature for. A voided or declined contract doesn't count, only a signed one.
    function get_signed_template_ids($talent_project_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $talent_project_id = $this->_get_clean_value($talent_project_id);

        $sql = "SELECT DISTINCT $talent_contracts_table.template_id
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.status='signed' AND $talent_contracts_table.talent_project_id=$talent_project_id";

        $ids = array();
        foreach ($this->db->query($sql)->getResult() as $row) {
            $ids[] = (int) $row->template_id;
        }
        return $ids;
    }

    //ids of the contracts still waiting for a signature (link not yet used), for one casting link or for every casting link of a talent
    function get_pending_ids($options = array()) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");
        $talent_projects_table = $this->db->prefixTable("talent_projects");

        $where = "";
        $talent_project_id = $this->_get_clean_value($options, "talent_project_id");
        if ($talent_project_id) {
            $where .= " AND $talent_contracts_table.talent_project_id=$talent_project_id";
        }

        $talent_id = $this->_get_clean_value($options, "talent_id");
        if ($talent_id) {
            $where .= " AND $talent_contracts_table.talent_project_id IN (SELECT $talent_projects_table.id FROM $talent_projects_table WHERE $talent_projects_table.talent_id=$talent_id)";
        }

        //no filter at all would mean every waiting contract in the system
        if (!$where) {
            return array();
        }

        $sql = "SELECT $talent_contracts_table.id
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.status='sent' $where
                ORDER BY $talent_contracts_table.id ASC";

        $ids = array();
        foreach ($this->db->query($sql)->getResult() as $row) {
            $ids[] = (int) $row->id;
        }

        return $ids;
    }

    //everything the public signing page needs except the signed PDF, which can be large and is fetched on its own
    function get_public($contract_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $contract_id = $this->_get_clean_value($contract_id);

        $sql = "SELECT $talent_contracts_table.id, $talent_contracts_table.talent_project_id, $talent_contracts_table.template_id, $talent_contracts_table.bundle_id, $talent_contracts_table.title,
                $talent_contracts_table.content, $talent_contracts_table.content_hash, $talent_contracts_table.token_hash, $talent_contracts_table.token_expires_at,
                $talent_contracts_table.status, $talent_contracts_table.sent_to_email, $talent_contracts_table.sent_at, $talent_contracts_table.signer_name,
                $talent_contracts_table.signer_email, $talent_contracts_table.signed_at, $talent_contracts_table.signed_via, $talent_contracts_table.signature_data, $talent_contracts_table.pdf_hash,
                $talent_contracts_table.decline_reason, $talent_contracts_table.deleted
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.id=$contract_id";

        return $this->db->query($sql)->getRow();
    }

    //the columns of get_public(), for every contract a bundle delivered (oldest first); withdrawn ones are included so the signing page can say so
    function get_for_bundle($bundle_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $bundle_id = $this->_get_clean_value($bundle_id);

        $sql = "SELECT $talent_contracts_table.id, $talent_contracts_table.talent_project_id, $talent_contracts_table.template_id, $talent_contracts_table.bundle_id, $talent_contracts_table.title,
                $talent_contracts_table.content, $talent_contracts_table.content_hash, $talent_contracts_table.token_hash, $talent_contracts_table.token_expires_at,
                $talent_contracts_table.status, $talent_contracts_table.sent_to_email, $talent_contracts_table.sent_at, $talent_contracts_table.signer_name,
                $talent_contracts_table.signer_email, $talent_contracts_table.signed_at, $talent_contracts_table.signed_via, $talent_contracts_table.signature_data, $talent_contracts_table.pdf_hash,
                $talent_contracts_table.decline_reason, $talent_contracts_table.deleted
                FROM $talent_contracts_table
                WHERE $talent_contracts_table.deleted=0 AND $talent_contracts_table.bundle_id=$bundle_id
                ORDER BY $talent_contracts_table.id ASC";

        return $this->db->query($sql)->getResult();
    }

    //id and title of the other contracts delivered in the same bundle, for the staff view ("sent together with")
    function get_bundle_mates($contract_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $contract_id = $this->_get_clean_value($contract_id);

        $sql = "SELECT mate.id, mate.title, mate.status, mate.token_expires_at
                FROM $talent_contracts_table AS mate
                INNER JOIN $talent_contracts_table AS mine ON mine.bundle_id=mate.bundle_id AND mine.id=$contract_id
                WHERE mate.deleted=0 AND mine.bundle_id>0 AND mate.id!=mine.id
                ORDER BY mate.id ASC";

        return $this->db->query($sql)->getResult();
    }

    function get_signed_pdf_data($contract_id) {
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $contract_id = $this->_get_clean_value($contract_id);

        $row = $this->db->query("SELECT $talent_contracts_table.signed_pdf_data FROM $talent_contracts_table WHERE $talent_contracts_table.id=$contract_id")->getRow();
        return $row ? (string) $row->signed_pdf_data : "";
    }
}
