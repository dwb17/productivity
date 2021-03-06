<?php
/**
 * @file
 * Migrate class to create Productivity content.
 */

/**
 * Class ProductivityDemoCreateProductivityContent
 */
class ProductivityMigrateUser extends Migration {
  public $entityType = 'user';

  /**
   * CSV Column mapping.
   *
   * @var array
   */
  public $csvColumns = array(
    array('name', 'name'),
    array('mail', 'mail'),
    array('status', 'status'),//(employed/laidoff/resigned/future)
    array('date_start', 'date_start'),
    array('date_end', 'date_end'),
    array('pass', 'pass'),
    array('job_type', 'job_type'),
    array('field_github_username', 'field_github_username'),
  );

  /**
   * Configure the object during creation.
   */
  public function __construct($arguments) {
    parent::__construct($arguments);

    $this->description = t('Import users from CSV file.');
    // Create a map object for tracking the relationships between source rows.
    $key = array(
      'name' => array(
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
      ),
    );
    $this->map = new MigrateSQLMap(
      $this->machineName,
      $key,
      MigrateDestinationEntityAPI::getKeySchema('user')
    );

    // Create a MigrateSource object.
    $csv_path = drupal_get_path('module', 'productivity_migrate') . '/csv';
    $this->source = new MigrateSourceCSV(
      $csv_path . '/user/user.csv',
      $this->csvColumns,
      array('header_rows' => 1)
    );
    $this->destination = new MigrateDestinationUser();

    $field_names = array(
      'name',
      'mail',
      'pass',
      'field_github_username',
    );

    $this->addSimpleMappings($field_names);
    $this->addFieldMapping('field_employment_status', 'status');
    $this->addFieldMapping('field_job_type', 'job_type');

    $this->addFieldMapping('field_date', 'date_start');
    $this->addFieldMapping('field_date:to', 'date_end');
    $this->addFieldMapping('field_date:timezone')
      ->defaultValue('Asia/Jerusalem');

  }

  function prepare($entity, $row) {
    if (!empty($row->pass)) {
      $entity->status = TRUE;
    }

  }
}
