<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_project_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_projects";
        parent::__construct($this->table);
    }

    //talent assigned to a given project, with each casting link's own status
    function get_details_for_project($project_id) {
        $talent_projects_table = $this->db->prefixTable("talent_projects");
        $talent_table = $this->db->prefixTable("talent");
        $talent_status_table = $this->db->prefixTable("talent_status");
        $talent_contracts_table = $this->db->prefixTable("talent_contracts");

        $project_id = $this->_get_clean_value($project_id);

        //each casting link also carries its newest contract (any state) for the badge and the "Send contract" button
        $sql = "SELECT $talent_projects_table.id AS talent_project_id,
                IF($talent_projects_table.sort!=0, $talent_projects_table.sort, $talent_projects_table.id) AS new_sort,
                $talent_projects_table.talent_status_id, $talent_table.*,
                $talent_status_table.title AS talent_status_title, $talent_status_table.color AS talent_status_color,
                $talent_contracts_table.id AS contract_id, $talent_contracts_table.status AS contract_status,
                $talent_contracts_table.token_expires_at AS contract_expires_at, $talent_contracts_table.sent_at AS contract_sent_at
                FROM $talent_projects_table
                LEFT JOIN $talent_table ON $talent_table.id=$talent_projects_table.talent_id
                LEFT JOIN $talent_status_table ON $talent_status_table.id=$talent_projects_table.talent_status_id
                LEFT JOIN $talent_contracts_table ON $talent_contracts_table.id=(
                    SELECT MAX(latest_contract.id) FROM $talent_contracts_table AS latest_contract
                    WHERE latest_contract.talent_project_id=$talent_projects_table.id AND latest_contract.deleted=0
                )
                WHERE $talent_projects_table.deleted=0 AND $talent_table.deleted=0 AND $talent_projects_table.project_id=$project_id
                ORDER BY $talent_projects_table.id DESC";

        return $this->db->query($sql);
    }

    //one casting link with the talent and project details a contract merges into its text
    function get_context($talent_project_id) {
        $talent_projects_table = $this->db->prefixTable("talent_projects");
        $talent_table = $this->db->prefixTable("talent");
        $projects_table = $this->db->prefixTable("projects");
        $talent_status_table = $this->db->prefixTable("talent_status");

        $talent_project_id = $this->_get_clean_value($talent_project_id);

        $sql = "SELECT $talent_projects_table.id AS talent_project_id, $talent_projects_table.project_id, $talent_projects_table.talent_id, $talent_projects_table.talent_status_id,
                $talent_table.legal_name, $talent_table.preferred_name, $talent_table.email, $talent_table.address, $talent_table.profession, $talent_table.on_screen_title,
                $projects_table.title AS project_title, $projects_table.start_date AS project_start_date, $projects_table.deadline AS project_deadline,
                $talent_status_table.title AS talent_status_title, $talent_status_table.system_key AS talent_status_key
                FROM $talent_projects_table
                LEFT JOIN $talent_table ON $talent_table.id=$talent_projects_table.talent_id
                LEFT JOIN $projects_table ON $projects_table.id=$talent_projects_table.project_id
                LEFT JOIN $talent_status_table ON $talent_status_table.id=$talent_projects_table.talent_status_id
                WHERE $talent_projects_table.deleted=0 AND $talent_table.deleted=0 AND $projects_table.deleted=0 AND $talent_projects_table.id=$talent_project_id";

        return $this->db->query($sql)->getRow();
    }

    //projects a given talent is cast on, with that talent's status on each one
    function get_details_for_talent($talent_id) {
        $talent_projects_table = $this->db->prefixTable("talent_projects");
        $projects_table = $this->db->prefixTable("projects");
        $talent_status_table = $this->db->prefixTable("talent_status");

        $talent_id = $this->_get_clean_value($talent_id);

        $sql = "SELECT $talent_projects_table.id AS talent_project_id, $projects_table.id AS project_id, $projects_table.title AS project_title, $projects_table.status AS project_status,
                $talent_status_table.title AS talent_status_title, $talent_status_table.color AS talent_status_color
                FROM $talent_projects_table
                LEFT JOIN $projects_table ON $projects_table.id=$talent_projects_table.project_id
                LEFT JOIN $talent_status_table ON $talent_status_table.id=$talent_projects_table.talent_status_id
                WHERE $talent_projects_table.deleted=0 AND $projects_table.deleted=0 AND $talent_projects_table.talent_id=$talent_id
                ORDER BY $talent_projects_table.id DESC";

        return $this->db->query($sql);
    }

    //number of projects a talent is cast on, per pipeline stage: array(status_id => count)
    function get_stage_counts($talent_id) {
        $talent_projects_table = $this->db->prefixTable("talent_projects");
        $projects_table = $this->db->prefixTable("projects");

        $talent_id = $this->_get_clean_value($talent_id);

        $sql = "SELECT $talent_projects_table.talent_status_id, COUNT($talent_projects_table.id) AS total
                FROM $talent_projects_table
                LEFT JOIN $projects_table ON $projects_table.id=$talent_projects_table.project_id
                WHERE $talent_projects_table.deleted=0 AND $projects_table.deleted=0 AND $talent_projects_table.talent_id=$talent_id
                GROUP BY $talent_projects_table.talent_status_id";

        $counts = array();
        foreach ($this->db->query($sql)->getResult() as $row) {
            $counts[$row->talent_status_id] = $row->total;
        }
        return $counts;
    }

    function is_already_assigned($talent_id, $project_id) {
        $row = $this->get_one_where(array("talent_id" => $talent_id, "project_id" => $project_id, "deleted" => 0));
        return $row && $row->id ? true : false;
    }
}
