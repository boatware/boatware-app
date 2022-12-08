<?php
namespace Deployer;

require 'deploy.drupal9.php';

// Project name
set('application', '');

// Project repository
set('repository', '');

host('main')
  ->set('hostname', '') #!
  ->set('deploy_path', '') #!
  ->set('branch', 'main')
  ->set('remote_user', '') #!
  ->set('http_user', '') #!
  ->set('bin/php', '')
;
