<?php
/**
 * @file
 * Code for the Productivity time tracking feature.
 */

/**
 * Menu callback; Display monthly employee report.
 */
function productivity_timewatch_monthly_employee_report_callback($account = FALSE, $year = FALSE, $month = FALSE) {
  return productivity_timewatch_monthly_employee_report($account, $year, $month);
}

/**
 * Load all resources for the page.
 * @param $local
 */
function productivity_timewatch_monthly_employee_load_resources($local = FALSE) {
  // Add JavaScript file with the base url of the page.
  drupal_add_js(drupal_get_path('module', 'productivity_timewatch') . '/monthly_employee_report/monthly_employee_report.js');
  drupal_add_css(drupal_get_path('module', 'productivity_timewatch') . '/monthly_employee_report/monthly_employee_report.css');
  // No CDN.
  if ($local) {

    // Add Datepicker lib.
    drupal_add_js(drupal_get_path('theme', 'bootstrap_subtheme') . '/js/bootstrap-datepicker/js/bootstrap-datepicker.js');
    drupal_add_css(drupal_get_path('theme', 'bootstrap_subtheme') . '/js/bootstrap-datepicker/css/bootstrap-datepicker.min.css');// Add Datepicker lib.
    // Add select 2
    drupal_add_js(drupal_get_path('theme', 'bootstrap_subtheme') . '/js/select2/js/select2.js');
    drupal_add_css(drupal_get_path('theme', 'bootstrap_subtheme') . '/js/select2/css/select2.min.css');
  }
  else {
    // Add JavaScript file with the base url of the page.
    drupal_add_css('https://fonts.googleapis.com/css?family=Abril+Fatface', array('type' => 'external'));
    drupal_add_css("//cdnjs.cloudflare.com/ajax/libs/select2/4.0.1/css/select2.min.css", array('type' => 'external'));
    drupal_add_js("//cdnjs.cloudflare.com/ajax/libs/select2/4.0.1/js/select2.min.js", array('type' => 'external'));

    // Add Datepicker lib.
    drupal_add_js("https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.0/js/bootstrap-datepicker.min.js", array('type' => 'external'));
    drupal_add_css("https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.6.0/css/bootstrap-datepicker.min.css", array('type' => 'external'));
  }
}

/**
 */
function productivity_timewatch_monthly_employee_report($account = FALSE, $year = FALSE, $month = FALSE) {
  global $base_url;
  productivity_timewatch_monthly_employee_load_resources();

  $variables = array();
  $variables['year'] = !empty($year) ? $year : date('Y');
  $variables['month']= !empty($month) ? $month : date('m', strtotime("-1 month"));

  if ($year == 'all'){
    $variables['date'] = t('All time');
  }
  elseif ($month == 'all'){
    $variables['date'] = t('Year @year', array('@year' => $year));
  }
  else {
    $variables['date'] = date("F Y", strtotime("$year-$month-01"));
  }


  $variables['employees'] = user_load_multiple(productivity_account_get_employee_uids());
  $variables['current_uid'] = 0;
  $variables['project_title'] = 'Select a project';
  $variables['account'] = '';
  $variables['no_result'] = FALSE;
  $variables['message'] = '';
  $variables['table'] = '';

  drupal_add_js(array(
    'report' => array(
      'base_url' => $base_url,
      'year' => $variables['year'],
      'month' => $variables['month'],
    ),
  ), 'setting');

  // No project given.
  if (!$account) {
    return theme('productivity_timewatch_monthly_employee_report', $variables);
  }

  $wrapper = entity_metadata_wrapper('user', $account);

  $variables['fullname'] = $wrapper->field_full_name->label();

  $node_keys = productivity_timewatch_get_user_sessions($account->uid, $variables['year'], $variables['month']);
  $sessions = array();

  // Get all issues for the project.
  if ($node_keys) {
    $sessions = productivity_timewatch_create_issues_table($node_keys);
  }
  else {
    $variables['no_result'] = TRUE;
    $variables['message'] = t('No work done this month.');
  }

  $variables['theme_path'] = base_path() . drupal_get_path('theme', 'bootstrap_subtheme');

  // Headers.
  $header = array(
    'Date',
    'Week day',
    'Entry',
    'Exit',
    'Total',
    'Project',
    'Source',
    'Actual Total inc Missing',
  );
  $month_array = productivity_timewatch_build_month_skeleton($month, $year);
  foreach ($month_array as $day => &$value) {
    if (isset($sessions[$day])) {
      
      foreach ($sessions[$day] as $session) {
        $month_array[$day] += $session;
      }
    }
  }

  $variables['table'] = theme('table', array(
    'header' => $header,
    'rows' => $month_array
  ));


  // Get all the projects for project input
  $variables['current_uid'] = $account->uid;

//  $variables['table'] = theme('productivity_timewatch_monthly_report_table', $variables);

  return theme('productivity_timewatch_monthly_employee_report', $variables);
}


/**
 * Build monthly array skeleton
 */
function productivity_timewatch_build_month_skeleton($month, $year){
  $array = array();
  $last_day_this_month  = date('t', strtotime('1.' . $month . '.' . $year));
  for ($day = 1; $day <= $last_day_this_month; $day++) {
    // Add leading zeros.
    $key = str_pad($day, 2, '0', STR_PAD_LEFT);
    $date_string = format_string("@month/@day/@year", array(
      '@day' => $day,
      '@month' => $month,
      '@year' => $year,
    ));
    $date = strtotime($date_string);
    $array[$key]  = array(
      'Date' => date('d/m/y', $date),
      'Week day' => date('l', $date),
    );
  }
  return $array;
}

/**
 * Create a matrix of all the issues from GitHub nodes,
 * And the url project.
 *
 * @param $nodes
 *  All the nodes return from the query.
 *
 * @return array
 *  Matrix of all the issues, and the URL project
 */
function productivity_timewatch_create_issues_table($node_keys) {
  $sessions = array();
  foreach($node_keys as $nid) {
    $wrapper = entity_metadata_wrapper('node', $nid);
    $node_account  =$wrapper->uid->value();
    $start_time = $wrapper->field_session_date->value->value();
    $end_time = $wrapper->field_session_date->value2->value();
    $total_time = $end_time - $start_time;
    $hours = floor($total_time / 3600);
    $minutes = floor(($total_time % 3600) / 60);
    $day = date('d', $start_time);

    $sessions[$day][] = array(
      'Entry' => date('H:i', $start_time),
      'Exit' => date('H:i', $end_time),
      'Total' => $hours . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT),
      'Project' => $wrapper->field_project->label(),
      // The source of the data, using time watch or from home (manual).
      'Source' => in_array('timewatch', $node_account->roles) ? 'timewatch' : 'manual',
    );
  }
  ksort($sessions);
  dpm($sessions);
  return $sessions;
}