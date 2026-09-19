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

        $project_id = $this->_get_clean_value($project_id);

        $sql = "SELECT $talent_projects_table.id AS talent_project_id,
                IF($talent_projects_table.sort!=0, $talent_projects_table.sort, $talent_projects_table.id) AS new_sort,
                $talent_projects_table.talent_status_id, $talent_table.*,
                $talent_status_table.title AS talent_status_title, $talent_status_table.color AS talent_status_color
                FROM $talent_projects_table
                LEFT JOIN $talent_table ON $talent_table.id=$talent_projects_table.talent_id
                LEFT JOIN $talent_status_table ON $talent_status_table.id=$talent_projects_table.talent_status_id
                WHERE $talent_projects_table.deleted=0 AND $talent_table.deleted=0 AND $talent_projects_table.project_id=$project_id
                ORDER BY $talent_projects_table.id DESC";

        return $this->db->query($sql);
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
