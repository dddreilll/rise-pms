<?php

namespace Config;

$routes = Services::routes();
$namespace = 'Agreements\Controllers';

$routes->get('agreements', 'Agreements::index', ['namespace' => $namespace]);
$routes->get('agreements/index', 'Agreements::index', ['namespace' => $namespace]);
$routes->get('agreements/view/(:num)', 'Agreements::view/$1', ['namespace' => $namespace]);
$routes->get('agreements/editor/(:num)', 'Agreements::editor/$1', ['namespace' => $namespace]);
$routes->get('agreements/preview/(:num)', 'Agreements::preview/$1', ['namespace' => $namespace]);
$routes->get('agreements/audit/(:num)', 'Agreements::audit/$1', ['namespace' => $namespace]);
$routes->post('agreements/save_content', 'Agreements::save_content', ['namespace' => $namespace]);
$routes->get('agreements/list_data', 'Agreements::list_data', ['namespace' => $namespace]);
$routes->post('agreements/list_data', 'Agreements::list_data', ['namespace' => $namespace]);
$routes->post('agreements/modal_form', 'Agreements::modal_form', ['namespace' => $namespace]);
$routes->post('agreements/save', 'Agreements::save', ['namespace' => $namespace]);
$routes->post('agreements/delete', 'Agreements::delete', ['namespace' => $namespace]);
$routes->post('agreements/send', 'Agreements::send', ['namespace' => $namespace]);
$routes->post('agreements/resend', 'Agreements::resend', ['namespace' => $namespace]);
$routes->post('agreements/cancel', 'Agreements::cancel', ['namespace' => $namespace]);
$routes->post('agreements/amend', 'Agreements::amend', ['namespace' => $namespace]);
$routes->post('agreements/renew', 'Agreements::renew', ['namespace' => $namespace]);
$routes->post('agreements/save_signatories', 'Agreements::save_signatories', ['namespace' => $namespace]);
$routes->post('agreements/save_recipients', 'Agreements::save_recipients', ['namespace' => $namespace]);
$routes->post('agreements/upload_pdf', 'Agreements::upload_pdf', ['namespace' => $namespace]);
$routes->get('agreements/download_signed/(:num)', 'Agreements::download_signed/$1', ['namespace' => $namespace]);
$routes->post('agreements/save_as_template', 'Agreements::save_as_template', ['namespace' => $namespace]);

$routes->get('agreements_templates', 'Agreements_Templates::index', ['namespace' => $namespace]);
$routes->get('agreements_templates/settings_list', 'Agreements_Templates::settings_list', ['namespace' => $namespace]);
$routes->get('agreements_templates/list_data', 'Agreements_Templates::list_data', ['namespace' => $namespace]);
$routes->post('agreements_templates/list_data', 'Agreements_Templates::list_data', ['namespace' => $namespace]);
$routes->post('agreements_templates/modal_form', 'Agreements_Templates::modal_form', ['namespace' => $namespace]);
$routes->post('agreements_templates/save', 'Agreements_Templates::save', ['namespace' => $namespace]);
$routes->post('agreements_templates/delete', 'Agreements_Templates::delete', ['namespace' => $namespace]);

$routes->get('agreements_settings', 'Agreements_Settings::index', ['namespace' => $namespace]);
$routes->post('agreements_settings/save', 'Agreements_Settings::save', ['namespace' => $namespace]);

$routes->get('agreements_sign/(:segment)', 'Agreements_Sign::index/$1', ['namespace' => $namespace]);
$routes->post('agreements_sign/submit/(:segment)', 'Agreements_Sign::submit/$1', ['namespace' => $namespace]);
$routes->post('agreements_sign/decline/(:segment)', 'Agreements_Sign::decline/$1', ['namespace' => $namespace]);
