<?php

namespace Config;

$routes = Services::routes();

$namespace = 'Talent_Management\Controllers';

//talent CRUD + list pages
$routes->get('talent', 'Talent::index', ['namespace' => $namespace]);
$routes->get('talent/index', 'Talent::index', ['namespace' => $namespace]);
$routes->get('talent/view/(:num)', 'Talent::view/$1', ['namespace' => $namespace]);
$routes->get('talent/view/(:num)/(:any)', 'Talent::view/$1/$2', ['namespace' => $namespace]);
$routes->get('talent/general_info/(:num)', 'Talent::general_info/$1', ['namespace' => $namespace]);
$routes->get('talent/contact_info/(:num)', 'Talent::contact_info/$1', ['namespace' => $namespace]);
$routes->get('talent/social_links/(:num)', 'Talent::social_links/$1', ['namespace' => $namespace]);
$routes->get('talent/preferences/(:num)', 'Talent::preferences/$1', ['namespace' => $namespace]);
$routes->get('talent/additional_info/(:num)', 'Talent::additional_info/$1', ['namespace' => $namespace]);
$routes->post('talent/save_contact_info/(:num)', 'Talent::save_contact_info/$1', ['namespace' => $namespace]);
$routes->post('talent/save_preferences/(:num)', 'Talent::save_preferences/$1', ['namespace' => $namespace]);
$routes->post('talent/save_additional_info/(:num)', 'Talent::save_additional_info/$1', ['namespace' => $namespace]);
$routes->get('talent/projects_info/(:num)', 'Talent::projects_info/$1', ['namespace' => $namespace]);
$routes->post('talent/save_general_info/(:num)', 'Talent::save_general_info/$1', ['namespace' => $namespace]);
$routes->post('talent/save_social_links/(:num)', 'Talent::save_social_links/$1', ['namespace' => $namespace]);
$routes->get('talent/custom_fields', 'Talent::custom_fields', ['namespace' => $namespace]);
$routes->post('talent/list_data', 'Talent::list_data', ['namespace' => $namespace]);
$routes->post('talent/modal_form', 'Talent::modal_form', ['namespace' => $namespace]);
$routes->post('talent/save', 'Talent::save', ['namespace' => $namespace]);
$routes->post('talent/delete', 'Talent::delete', ['namespace' => $namespace]);
$routes->post('talent/save_profile_image/(:num)', 'Talent::save_profile_image/$1', ['namespace' => $namespace]);

//pipeline stage settings (one shared list of stages for all projects)
$routes->get('talent_status', 'Talent_status::index', ['namespace' => $namespace]);
$routes->post('talent_status/list_data', 'Talent_status::list_data', ['namespace' => $namespace]);
$routes->post('talent_status/modal_form', 'Talent_status::modal_form', ['namespace' => $namespace]);
$routes->post('talent_status/save', 'Talent_status::save', ['namespace' => $namespace]);
$routes->post('talent_status/delete', 'Talent_status::delete', ['namespace' => $namespace]);
$routes->post('talent_status/update_field_sort_values', 'Talent_status::update_field_sort_values', ['namespace' => $namespace]);

//contract templates (admin only: the wording is legally sensitive)
$routes->get('talent_contract_templates', 'Talent_contract_templates::index', ['namespace' => $namespace]);
$routes->post('talent_contract_templates/list_data', 'Talent_contract_templates::list_data', ['namespace' => $namespace]);
$routes->post('talent_contract_templates/modal_form', 'Talent_contract_templates::modal_form', ['namespace' => $namespace]);
$routes->post('talent_contract_templates/save', 'Talent_contract_templates::save', ['namespace' => $namespace]);
$routes->get('talent_contract_templates/form/(:num)', 'Talent_contract_templates::form/$1', ['namespace' => $namespace]);
$routes->post('talent_contract_templates/save_content', 'Talent_contract_templates::save_content', ['namespace' => $namespace]);
$routes->post('talent_contract_templates/delete', 'Talent_contract_templates::delete', ['namespace' => $namespace]);

//sending a contract (staff side)
$routes->post('talent_contracts/agreements_modal', 'Talent_contracts::agreements_modal', ['namespace' => $namespace]);
$routes->post('talent_contracts/send_modal_form', 'Talent_contracts::send_modal_form', ['namespace' => $namespace]);
$routes->post('talent_contracts/preview', 'Talent_contracts::preview', ['namespace' => $namespace]);
$routes->post('talent_contracts/send', 'Talent_contracts::send', ['namespace' => $namespace]);
$routes->post('talent_contracts/view_modal_form', 'Talent_contracts::view_modal_form', ['namespace' => $namespace]);
$routes->get('talent_contracts/download/(:num)', 'Talent_contracts::download/$1', ['namespace' => $namespace]);
$routes->post('talent_contracts/resend', 'Talent_contracts::resend', ['namespace' => $namespace]);
$routes->post('talent_contracts/void', 'Talent_contracts::void', ['namespace' => $namespace]);
$routes->post('talent_contracts/paper_modal_form', 'Talent_contracts::paper_modal_form', ['namespace' => $namespace]);
$routes->post('talent_contracts/save_paper', 'Talent_contracts::save_paper', ['namespace' => $namespace]);

//the agreements a project requires (the list behind the Confirmed gate)
$routes->post('talent_project_agreements/modal_form', 'Talent_project_agreements::modal_form', ['namespace' => $namespace]);
$routes->post('talent_project_agreements/save', 'Talent_project_agreements::save', ['namespace' => $namespace]);

//the talent's signing page: public, reached from the emailed link with no login (the 40-character token in the URL is the credential)
$routes->get('talent_sign/(:num)/(:alphanum)', 'Talent_sign::index/$1/$2', ['namespace' => $namespace]);
$routes->post('talent_sign/sign', 'Talent_sign::sign', ['namespace' => $namespace]);
$routes->post('talent_sign/decline', 'Talent_sign::decline', ['namespace' => $namespace]);
$routes->get('talent_sign/download/(:num)/(:alphanum)', 'Talent_sign::download/$1/$2', ['namespace' => $namespace]);

//project <-> talent linkage (list view)
$routes->get('talent_projects/project_tab/(:num)', 'Talent_projects::project_tab/$1', ['namespace' => $namespace]);
$routes->post('talent_projects/list_data/(:num)', 'Talent_projects::list_data/$1', ['namespace' => $namespace]);
$routes->post('talent_projects/modal_assign_form/(:num)', 'Talent_projects::modal_assign_form/$1', ['namespace' => $namespace]);
$routes->post('talent_projects/assign', 'Talent_projects::assign', ['namespace' => $namespace]);
$routes->post('talent_projects/unassign', 'Talent_projects::unassign', ['namespace' => $namespace]);
$routes->post('talent_projects/list_for_talent/(:num)', 'Talent_projects::list_for_talent/$1', ['namespace' => $namespace]);

//per-project kanban (status now lives on the casting link)
$routes->post('talent_projects/kanban_data/(:num)', 'Talent_projects::kanban_data/$1', ['namespace' => $namespace]);
$routes->post('talent_projects/save_sort_and_status', 'Talent_projects::save_sort_and_status', ['namespace' => $namespace]);
