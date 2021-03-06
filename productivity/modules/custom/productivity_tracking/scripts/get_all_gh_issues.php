<?php
/**
 * Fetch issues from GH and create or update tracking nodes.
 */

$repo_user = drush_get_option('gh_user', 'Gizra');
$repo = drush_get_option('gh_repo', 'productivity');
$min_issue_id = drush_get_option('min_issue_id', '1');
$max_issue_id = drush_get_option('max_issue_id', '1');

// TODO: deal with multiple project, in this case we don't want to assign issue
// to new projects, we probably want to set a range per project using a other
// option, for now we have just update to prevent this to be a problem.
// Example u6.5, 6.6, 6.7.

// Add an option to not create new issues, just update.
$dont_create = drush_get_option('dont_create', TRUE);

// Disable issue caching.
$no_cache = drush_get_option('no_cache', FALSE);

for($issue_num = $min_issue_id; $issue_num <= $max_issue_id; $issue_num++) {
  $issue_or_pr = productivity_tracking_get_issue_info($repo, $issue_num, $repo_user, TRUE, $no_cache);
  // If no issue data from GH.
  if (!$issue_or_pr['issue']) {
    productivity_admin_log("Unable to get info for $repo_user/$repo/$issue_num", 'error');
  }
  $gh_username = '';
  $pr = $issue = [];
  productivity_admin_log("Processing $repo_user/$repo/$issue_num", 'success');
  if(isset($issue_or_pr['issue']['pull_request'])) {
    $pr = $issue_or_pr;
    $gh_username = $issue_or_pr['issue']['user']['login'];
    // Don't update PR for now. no need.
    productivity_admin_log("Bypass PR $repo_user/$repo/$issue_num", 'success');
    continue;
  }
  else {
    $issue = $issue_or_pr;
    // The user that opened the issue.
    $gh_username = $issue_or_pr['issue']['user']['login'];
  }
  $repository_info = [];
  $repository_info['full_name'] = "$repo_user/$repo";
  if (!$uid = productivity_tracking_get_uid_by_github_username($gh_username)) {
    //Default uid.
    $uid = 1;
  }

  if (!productivity_tracking_save_tracking($issue, $pr, $uid, $gh_username,$repository_info, $dont_create, FALSE)) {
    productivity_admin_log("Failed to update $repo_user/$repo/$issue_num  this might because the issue did not exist before.", 'error');
  }
  productivity_admin_log("Done $repo_user/$repo/$issue_num", 'success');
}