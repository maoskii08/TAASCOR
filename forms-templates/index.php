<!doctype html>
<html lang="en" class="light-style layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free" data-style="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>Forms and Templates</title>
  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/png/logo.png" />

  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Select2 -->
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2.min.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/select2/select2-bootstrap-5-theme.min.css" />

  <!-- Datatable -->
  <link rel="stylesheet" href="https://cdn.datatables.net/2.1.6/css/dataTables.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/css/datatables.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-responsive-bs5/responsive.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-buttons-bs5/buttons.bootstrap5.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/datatable-bs5/datatables-rowgroup-bs5/rowgroup.bootstrap5.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <?php require('../includes/nav-bar.php') ?>   
      <!--home-->
      <div class="home">
        <nav
          class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
          id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
              <i class="bx bx-menu bx-md"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <!-- breadcrumb -->
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb">
                    <li class="breadcrumb-item active" aria-current="page">Forms and Templates</li>
                  </ol>
                </nav>
              </div>
            </div>
            <!-- /breadcrumb -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../assets/img/avatars/user-icon.png" alt class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <h6 class="mb-0"><?php echo $_SESSION['taascor_employee_full_name'] ?></h6>
                          <small class="text-muted"><?php echo $_SESSION['taascor_access_description'] ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider my-1"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="/<?php echo $pathParts[6];?>/login/logout.php">
                      <i class="bx bx-power-off bx-md me-3"></i><span>Log Out</span>
                    </a>
                  </li>
                </ul>
              </li>
              <!--/ User -->
            </ul>
          </div>
        </nav>

        <div class="content-wrapper">
          <!-- Content -->

          <div class="container-xxl flex-grow-1 container-p-y">
            <div class="row mb-2">
              <div class="col-sm-12 col-lg-12 mb-2">
                <div class="accordion accordion-popout mt-3" id="accordionPopout">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutOne">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutOne" aria-expanded="false" aria-controls="accordionPopoutOne">
                        Employee Management
                      </button>
                    </h2>

                    <div id="accordionPopoutOne" class="accordion-collapse collapse" aria-labelledby="headingPopoutOne"
                      data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/Employee_Management.xlsx" class="template-link" download>
                              Employee Management Template
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutTwo">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutTwo" aria-expanded="false"
                        aria-controls="accordionPopoutTwo">Payroll</button>
                    </h2>
                    <div id="accordionPopoutTwo" class="accordion-collapse collapse" aria-labelledby="headingPopoutTwo"
                      data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/DTR_Template.xlsx" class="template-link" download>
                              DTR Template
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/Additional_Template.xlsx" class="template-link" download>
                              Addition Template
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/Deduction_Template.xlsx" class="template-link" download>
                              Deduction Template
                            </a>
                          </li>
                          <!-- <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="" class="template-link" download>
                              ATM Application Form
                            </a>
                          </li> -->
                        </ul>
                      </div>
                    </div>
                  </div>

                  <!-- <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutFour">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutFour" aria-expanded="false"
                        aria-controls="accordionPopoutFour">Loans</button>
                    </h2>
                    <div id="accordionPopoutFour" class="accordion-collapse collapse"
                      aria-labelledby="headingPopoutFour" data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="" class="template-link" download>
                              Company Loan Form
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/SSS_Loan_Form.pdf" class="template-link" download>
                              SSS Salary Loan Form
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/SLF065_MultiPurposeLoanApplicationForm_V08.pdf" class="template-link"
                              download>
                              Pag-Ibig Multi Purpose Loan Application Form
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/SLF066_CalamityLoanApplicationForm_V09.pdf" class="template-link"
                              download>
                              Pag-Ibig Calamity Loan Application Form
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div> -->

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutThree">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutThree" aria-expanded="false"
                        aria-controls="accordionPopoutThree">SSS</button>
                    </h2>
                    <div id="accordionPopoutThree" class="accordion-collapse collapse"
                      aria-labelledby="headingPopoutThree" data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/E4.pdf" class="template-link" download>
                              SSS Member Data Change Request (E4)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/SSS_MAT2.pdf" class="template-link" download>
                              SSS Maternity Reimbursement Form (MAT2)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/SSS_Form_B-309.pdf" class="template-link" download>
                              SSS SC Claim Form
                            </a>
                            <small class="text-xs ms-3 text-muted fst-italic">For Accident/Sickness Report SSS Form
                              B-309</small>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutFive">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutFive" aria-expanded="false"
                        aria-controls="accordionPopoutFive">Pag-Ibig</button>
                    </h2>
                    <div id="accordionPopoutFive" class="accordion-collapse collapse"
                      aria-labelledby="headingPopoutFive" data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/PFF093_RequestConsolidationMergingMembersRecords_V06.pdf"
                              class="template-link" download>
                              Request for Consolidation/Merging of Member's Records (RCMMR)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/PFF049_MembersChangeInformationForm_V10.pdf" class="template-link"
                              download>
                              Member's Change of Information Form (MCIF)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/LoyaltyCardPlusApplicationForm.pdf" class="template-link" download>
                              Loyalty Card Plus App Form
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutSix">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutSix" aria-expanded="false"
                        aria-controls="accordionPopoutSix">Philhealth</button>
                    </h2>
                    <div id="accordionPopoutSix" class="accordion-collapse collapse" aria-labelledby="headingPopoutSix"
                      data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/PhilHealth_Member_Registration_Form_2020.pdf" class="template-link"
                              download>
                              Philhealth Member Registration Form
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/ClaimForm1_092018.pdf" class="template-link" download>
                              Philhealth Claim Form 1 (CFI)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/ClaimSignatureForm_2018.pdf" class="template-link" download>
                              Philhealth Claim Signature Form (CSF)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/er2.pdf" class="template-link" download>
                              Philhealth ER2
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutEight">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutEight" aria-expanded="false"
                        aria-controls="accordionPopoutEight">BIR</button>
                    </h2>
                    <div id="accordionPopoutEight" class="accordion-collapse collapse"
                      aria-labelledby="headingPopoutEight" data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/Updated_1905_form.pdf" class="template-link" download>
                              Application for Information Update (1905)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/1902 Form.pdf" class="template-link" download>
                              Application for Registration (1902)
                            </a>
                          </li>
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="../assets/files/1904 Form.pdf" class="template-link" download>
                              Application for Registration (1904)
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <!-- <div class="accordion-item">
                    <h2 class="accordion-header" id="headingPopoutSeven">
                      <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse"
                        data-bs-target="#accordionPopoutSeven" aria-expanded="false"
                        aria-controls="accordionPopoutSeven">HMO</button>
                    </h2>
                    <div id="accordionPopoutSeven" class="accordion-collapse collapse"
                      aria-labelledby="headingPopoutSeven" data-bs-parent="#accordionPopout">
                      <div class="accordion-body">
                        <ul class="list-group list-group-flush">
                          <li class="list-group-item d-flex align-items-center">
                            <i class='bx bx-file bx-sm text-dark d-block me-1'></i>
                            <a href="#" class="template-link" download>
                              HMO Form
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div> -->
                </div>
              </div>
            </div>

          </div>

          <!-- / Content -->

          <div class="content-backdrop fade"></div>
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
      </div>
    </div>
  </div>
  <!-- / Layout wrapper -->

  <?Php require("../includes/footer.php") ;?>
  <?Php require("../includes/custom-footer.php") ;?>

</body>

</html>