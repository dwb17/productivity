<div class="report-container">
  <div>
    <ul class="nav nav-tabs noprint" role="tablist">
      <?php foreach ($tables as $name => $data): ?>
        <!-- Nav tabs -->
        <li role="presentation" class="<?php print $data['class']; ?>">
          <a class="" href="#<?php print $name; ?>" aria-controls="<?php print $name; ?>" role="tab" data-toggle="tab"><?php print $data['title']; ?>
          <span class="badge badge-primary"><?php print $data['totals']['data']['actual']; ?></span>
          <span class="badge badge-danger"><?php print $data['totals']['data']['overtime']['data']; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="tab-content">
      <?php foreach ($tables as $name => $data): ?>
        <!-- Tab panes -->
        <div role="tabpanel" class="tab-pane <?php print $data['class']; ?>" id="<?php print $name; ?>"><?php print $data['table']; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<style>
  @media print {
    .report-container {
      margin: 0 10px;
    }

    .table {
      font-size: 9pt;
    }
    .table > tbody > tr > td {
      padding: 2px !important;
    }

    thead {
      margin-top: 15px;
    }

    @page {
      size: auto;
      margin-top: -20px;
      margin-right: 10px;
      margin-bottom: 10px;
      margin-left: 10px;
    }
    #search-filter, .page-header, #nav-col, #footer-copyright, .noprint, .sticky-header, .tabs--primary {
      display: none;
    }

    .sticky-header {
       position: relative !important;
    }

    a[href]:after {
      content: none !important; }

    .show-only-on-print {
      display: block !important; }

    #header {
      position: relative;
    }

    .table-striped > tbody > tr:nth-child(odd) > td, .table-striped > tbody > tr:nth-child(odd) > th {
      background-color: #f9f9f9 !important;
    }

    .well {
      border: none !important; }
  }
</style>