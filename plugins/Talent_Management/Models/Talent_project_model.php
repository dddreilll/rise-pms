<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_project_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_projects";
        parent::__construct($this->table);
    }

    //Where a casting link stands on agreements, as columns for a list query:
    //  required_total   how many agreements the project requires (its list, live templates only)
    //  required_signed  how many of those this casting link has a signature for
    //  waiting_count    agreements sent and still waiting for a signature (link not lapsed)
    //  signed_count     agreements signed, whether they are required or extras
    //$casting_link_id and $project_id are column expressions of the query this goes into.
    private function _agreement_counts_sql($casting_link_id, $project_id) {
        $contracts = $this->db->prefixTable("talent_contracts");
        $agreements = $this->db->prefixTable("talent_project_agreements");
        $templates = $this->db->prefixTable("talent_contract_templates");

        return "(SELECT COUNT(*) FROM $agreements AS required
                    INNER JOIN $templates AS required_template ON required_template.id=required.template_id AND required_template.deleted=0
                    WHERE required.project_id=$project_id) AS required_total,
                (SELECT COUNT(*) FROM $agreements AS required
                    INNER JOIN $templates AS required_template ON required_template.id=required.template_id AND required_template.deleted=0
                    WHERE required.project_id=$project_id AND EXISTS (
                        SELECT 1 FROM $contracts AS signed_contract
                        WHERE signed_contract.talent_project_id=$casting_link_id AND signed_contract.template_id=required.template_id AND signed_contract.status='signed' AND signed_contract.deleted=0
                    )) AS required_signed,
                (SELECT COUNT(*) FROM $contracts AS waiting_contract
                    WHERE waiting_contract.talent_project_id=$casting_link_id AND waiting_contract.status='sent' AND waiting_contract.deleted=0
                    AND (waiting_contract.token_expires_at IS NULL OR waiting_contract.token_expires_at>UTC_TIMESTAMP())) AS waiting_count,
                (SELECT COUNT(DISTINCT signed_contract.template_id) FROM $contracts AS signed_contract
                    WHERE signed_contract.talent_project_id=$casting_link_id AND signed_contract.status='signed' AND signed_contract.deleted=0) AS signed_count";
    }

    //talent assigned to a given project, with each casting link's own status
    function get_details_for_project($project_id) {
        $talent_projects_table = $this->db->prefixTable("talent_projects");
        $talent_table = $this->db->prefixTable("talent");
        $talent_status_table = $this->db->prefixTable("talent_status");

        $project_id = $this->_get_clean_value($project_id);

        //each casting link also carries its agreement counts for the "2 of 3 signed" badge
        $sql = "SELECT $talent_projects_table.id AS talent_project_id,
                IF($talent_projects_table.sort!=0, $talent_projects_table.sort, $talent_projects_table.id) AS new_sort,
                $talent_projects_table.talent_status_id, $talent_table.*,
                $talent_status_table.title AS talent_status_title, $talent_status_table.color AS talent_status_color,
                " . $this->_agreement_counts_sql($talent_projects_table . ".id", $talent_projects_table . ".project_id") . "
                FROM $talent_projects_table
                LEFT JOIN $talent_table ON $talent_table.id=$talent_projects_table.talent_id
                LEFT JOIN $talent_status_table ON $talent_status_table.id=$talent_projects_table.talent_status_id
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

        //each project also carries its agreement counts, like the project's own talent list does
        $sql = "SELECT $talent_projects_table.id AS talent_project_id, $projects_table.id AS project_id, $projects_table.title AS project_title, $projects_table.status AS project_status,
                $talent_status_table.title AS talent_status_title, $talent_status_table.color AS talent_status_color,
                " . $this->_agreement_counts_sql($talent_projects_table . ".id", $projects_table . ".id") . "
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
