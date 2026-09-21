<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

//the agreements a project requires of its talent before they can be confirmed
class Talent_project_agreement_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_project_agreements";
        parent::__construct($this->table);
    }

    //the project's required agreements in the order they were picked. A template that has since been deleted no longer counts.
    function get_templates_for_project($project_id) {
        $agreements_table = $this->db->prefixTable("talent_project_agreements");
        $templates_table = $this->db->prefixTable("talent_contract_templates");

        $project_id = $this->_get_clean_value($project_id);

        $sql = "SELECT $templates_table.id, $templates_table.title
                FROM $agreements_table
                INNER JOIN $templates_table ON $templates_table.id=$agreements_table.template_id AND $templates_table.deleted=0
                WHERE $agreements_table.project_id=$project_id
                ORDER BY $agreements_table.sort ASC, $agreements_table.id ASC";

        return $this->db->query($sql)->getResult();
    }

    //ids of the templates that can be picked (all the live ones), for checking what a form posts
    function get_live_template_ids() {
        $templates_table = $this->db->prefixTable("talent_contract_templates");

        $ids = array();
        foreach ($this->db->query("SELECT id FROM $templates_table WHERE deleted=0")->getResult() as $row) {
            $ids[] = (int) $row->id;
        }
        return $ids;
    }

    function project_exists($project_id) {
        $projects_table = $this->db->prefixTable("projects");

        $project_id = $this->_get_clean_value($project_id);

        return $this->db->query("SELECT id FROM $projects_table WHERE id=$project_id AND deleted=0")->getRow() ? true : false;
    }

    //Replaces the project's list with $template_ids, in that order. All or nothing. Returns false if anything went wrong.
    function replace_for_project($project_id, $template_ids, $user_id) {
        $agreements_table = $this->db->prefixTable("talent_project_agreements");

        $project_id = (int) $project_id;

        $this->db->transBegin();

        try {
            $this->db->query("DELETE FROM $agreements_table WHERE project_id=$project_id");

            $now = get_current_utc_time();
            foreach (array_values($template_ids) as $index => $template_id) {
                $this->db->table($agreements_table)->insert(array(
                    "project_id" => $project_id,
                    "template_id" => (int) $template_id,
                    "sort" => $index + 1,
                    "created_by" => (int) $user_id,
                    "created_at" => $now,
                ));
            }

            $this->db->transCommit();
            return true;
        } catch (\Throwable $ex) {
            $this->db->transRollback();
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
            return false;
        }
    }
}
