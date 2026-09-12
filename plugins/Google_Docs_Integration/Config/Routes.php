<?php

namespace Config;

$routes = Services::routes();

$namespace = 'Google_Docs_Integration\Controllers';

$routes->get('google_docs', 'Google_Docs::index', ['namespace' => $namespace]);
$routes->get('google_docs/index', 'Google_Docs::index', ['namespace' => $namespace]);
$routes->get('google_docs/view/(:num)', 'Google_Docs::view/$1', ['namespace' => $namespace]);
$routes->get('google_docs/project_docs/(:num)', 'Google_Docs::project_docs/$1', ['namespace' => $namespace]);
$routes->get('google_docs/list_data', 'Google_Docs::list_data', ['namespace' => $namespace]);
$routes->get('google_docs/list_data/(:num)', 'Google_Docs::list_data/$1', ['namespace' => $namespace]);
$routes->post('google_docs/list_data', 'Google_Docs::list_data', ['namespace' => $namespace]);
$routes->post('google_docs/list_data/(:num)', 'Google_Docs::list_data/$1', ['namespace' => $namespace]);
$routes->post('google_docs/modal_form', 'Google_Docs::modal_form', ['namespace' => $namespace]);
$routes->post('google_docs/save', 'Google_Docs::save', ['namespace' => $namespace]);
$routes->post('google_docs/delete', 'Google_Docs::delete', ['namespace' => $namespace]);
$routes->post('google_docs/get_members_and_teams_dropdown', 'Google_Docs::get_members_and_teams_dropdown', ['namespace' => $namespace]);

$routes->get('google_docs_integration_settings', 'Google_Docs_Integration_Settings::index', ['namespace' => $namespace]);
$routes->post('google_docs_integration_settings/save', 'Google_Docs_Integration_Settings::save', ['namespace' => $namespace]);

$routes->get('google_docs_api', 'Google_Docs_Api::index', ['namespace' => $namespace]);
$routes->get('google_docs_api/authorize', 'Google_Docs_Api::authorize', ['namespace' => $namespace]);
$routes->get('google_docs_api/save_access_token', 'Google_Docs_Api::save_access_token', ['namespace' => $namespace]);
