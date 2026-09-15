<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Documents_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_documents";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $documents_table = $this->db->prefixTable("agreements_documents");
        $users_table = $this->db->prefixTable("users");
        $clients_table = $this->db->prefixTable("clients");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $documents_table.id=$id";
        }

        $client_id = $this->_get_clean_value($options, "client_id");
        if ($client_id) {
            $where .= " AND $documents_table.client_id=$client_id";
        }

        $project_id = $this->_get_clean_value($options, "project_id");
        if ($project_id) {
            $where .= " AND $documents_table.project_id=$project_id";
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $documents_table.status='$status'";
        }

        $open_statuses = $this->_get_clean_value($options, "open_statuses");
        if ($open_statuses) {
            $where .= " AND $documents_table.status IN ('sent','partially_signed')";
        }

        $exclude_draft = $this->_get_clean_value($options, "exclude_draft");
        if ($exclude_draft) {
            $where .= " AND $documents_table.status!='draft'";
        }

        $created_by = $this->_get_clean_value($options, "created_by");
        if ($created_by) {
            $where .= " AND $documents_table.created_by=$created_by";
        }

        $signatory_user_id = $this->_get_clean_value($options, "signatory_user_id");
        $signatory_email = $this->_get_clean_value($options, "signatory_email");
        $signatories_table = $this->db->prefixTable("agreements_signatories");
        $join_signatory = "";
        if ($signatory_user_id || $signatory_email) {
            $join_signatory = " INNER JOIN $signatories_table ON $signatories_table.document_id=$documents_table.id AND $signatories_table.deleted=0 ";
            if ($signatory_user_id) {
                $where .= " AND $signatories_table.user_id=$signatory_user_id";
            }
            if ($signatory_email) {
                $where .= " AND $signatories_table.email='" . $this->db->escapeString($signatory_email) . "'";
            }
        }

        $sql = "SELECT $documents_table.*,
            CONCAT($users_table.first_name, ' ', $users_table.last_name) AS created_by_user,
            $clients_table.company_name AS client_name
            FROM $documents_table
            LEFT JOIN $users_table ON $users_table.id=$documents_table.created_by
            LEFT JOIN $clients_table ON $clients_table.id=$documents_table.client_id
            $join_signatory
            WHERE $documents_table.deleted=0 $where
            GROUP BY $documents_table.id
            ORDER BY $documents_table.id DESC";

        return $this->db->query($sql);
    }
}
