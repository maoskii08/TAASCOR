$(document).ready(function () {
    $('.collapse').collapse({
      toggle: false
    });
  });

  new DataTable('#emp_mgmnt_tbl', {
    responsive: true,
    lengthChange: false,
    paging: true,
    searching: true,
    ordering: false,
    info: true,
    scrollX: true,
    layout: {
      topStart: 'buttons'
    },
    buttons: [
      {
        text: '<i class="bx bx-upload"></i> Upload',
        className: 'btn btn-sm btn-outline-primary mb-2'
      },
      {
        extend: 'excel',
        text: '<i class="bx bx-download"></i> Download',
        className: 'btn btn-sm btn-outline-secondary mb-2',
        filename:'Employee Management sample',
        title: ''
      }
    ]
  });

  $('#client').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Client'
  });

  $('#branch').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Branch'
  });

  $('#add-client').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Client',
    dropdownParent: $('#addEmployeeModal')
  });

  $('#add-branch').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Branch',
    dropdownParent: $('#addEmployeeModal')
  });

  $('#add-department').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Department',
    dropdownParent: $('#addEmployeeModal')
  });

  $('#add-position').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Position',
    dropdownParent: $('#addEmployeeModal')
  });

  $('#edit-client').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Client',
    dropdownParent: $('#editEmployeeModal')
  });

  $('#edit-branch').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Branch',
    dropdownParent: $('#editEmployeeModal')
  });

  $('#edit-department').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Department',
    dropdownParent: $('#editEmployeeModal')
  });

  $('#edit-position').select2({
    theme: "bootstrap-5",
    width: '100%',
    placeholder: 'Select Position',
    dropdownParent: $('#editEmployeeModal')
  });