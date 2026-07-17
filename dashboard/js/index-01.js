
let monthlyHires = true;

$( document ).ready(function() {
    getNewHires()
    getMaleFemale();
});

document.getElementById('clearBtn').addEventListener("click", clearFilter);

$("#year").change(function(){
  if($(this).val() != null){
    getMonthlyHires();
  }
});

$("#branch").change(function(){
  if($(this).val() != null){
    if(monthlyHires){
      getMonthlyHires();
    }else{
      getYearlyHires();
    }
  }
});


$("#btnmonthly").click(function(){
    $('#year').val(null).trigger('change');
    $('#branch').val(null).trigger('change');
    monthlyHires = true;
    getMonthlyHires();
});

$("#btnyearly").click(function(){
    $('#year').val(null).trigger('change');
    $('#branch').val(null).trigger('change');
    monthlyHires = false;
    getYearlyHires();
});

function clearFilter() {
  $('#year').val(null).trigger('change');
  $('#branch').val(null).trigger('change');

  if(monthlyHires){
    getMonthlyHires();
  }else{
    getYearlyHires();
  }
}

function getNewHires(){

  let formdata = new FormData();
  formdata.append("request", "get-new-hires");

  $.ajax({
      url: 'controller/DashboardController.php',
      type: 'POST',
      data: formdata,
      dataType: 'json',
      processing: true, 
      contentType: false,
      processData: false,
      beforeSend: function( xhr ) {
      },
      success: function (response) { 
          $("#newHires").text(response.data.new_hire + " hires");

          getYearFilter()
      }
  });
}

function getYearFilter(){

  let formdata = new FormData();
  formdata.append("request", "get-year-filter");

  $.ajax({
      url: 'controller/DashboardController.php',
      type: 'POST',
      data: formdata,
      dataType: 'json',
      processing: true, 
      contentType: false,
      processData: false,
      beforeSend: function( xhr ) {
          $('#year').attr('disabled',true);
          $('#year').empty();
      },
      success: function (response) { 
          $('#year').append(`<option value="" disabled selected>Select Year</option>`);

          response.data.forEach(option => {
              var option1 = new Option(option.yr, option.yr, false, false);
              $('#year').append(option1);
          });

          $('#year').trigger('change'); 
          $('#year').attr('disabled',false);

          getBranchFilter();
      }
  });
}

function getBranchFilter(){

  let formdata = new FormData();
  formdata.append("request", "get-branch-filter");

  $.ajax({
      url: 'controller/DashboardController.php',
      type: 'POST',
      data: formdata,
      dataType: 'json',
      processing: true, 
      contentType: false,
      processData: false,
      beforeSend: function( xhr ) {
          $('#branch').attr('disabled',true);
          $('#branch').empty();
      },
      success: function (response) { 
          $('#branch').append(`<option value="" disabled selected>Select Branch</option>`);

          response.data.forEach(option => {
              var option1 = new Option(option.branch_name, option.branch_id, false, false);
              $('#branch').append(option1);
          });

          $('#branch').trigger('change'); 
          $('#branch').attr('disabled',false);
      }
  });
}

function getMaleFemale(){

    let formdata = new FormData();
    formdata.append("request", "get-male-female");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            let maleCount = response.data.male;
            let femaleCount = response.data.female;

            $("#male").text(maleCount.toLocaleString());
            $("#female").text(femaleCount.toLocaleString());

            getCivilStatus()
        }
    });
}

function getCivilStatus(){

    let formdata = new FormData();
    formdata.append("request", "get-civil-status");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            var optionsCivil = {  
                chart: {    
                  height: 170,    
                  type: 'pie',    
                  offsetY: -15 
                }, 
                grid: {    
                  padding: {      
                    top: -10,     
                    bottom: -20    
                  }
                },  
                dataLabels: {    
                  enabled: false 
                },  
                plotOptions: {    
                  pie: {      
                    customScale: 0.86,      
                    pie: {        
                      size: '45%',      
                    },     
                    dataLabels: {        
                      enabled: false     
                    }    
                  }  
                },  
                colors:['#ffab00', '#27489B', '#D64933', '#ff751a'],
                series: response.data.series,
                labels: response.data.labels,  
                legend: {    
                  show: true,    
                  position: 'right'  
                }
              }
              
              var chartCivil = new ApexCharts(  
                document.querySelector("#civilstatuschart"),  
                optionsCivil
              );
              chartCivil.render();

              getQuantities();
        }
    });
}

function getQuantities(){

    let formdata = new FormData();
    formdata.append("request", "get-quantities");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            let employees = response.data.Employees;
            let clients = response.data.Clients;
            let branches = response.data.Branches;
            let locations = response.data.Locations;

            $("#employees").text(employees.toLocaleString());
            $("#clients").text(clients);
            $("#branches").text(branches);
            $("#locations").text(locations);

            getBranchEmployees();
        }
    });
}

function getBranchEmployees(){

    let formdata = new FormData();
    formdata.append("request", "get-branch-employees");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            var optionsBranch = {
                chart: {
                  height: 250,
                  type: 'bar',
                  parentHeightOffset: 0,
                  fontFamily: 'Public Sans, sans-serif',
                  toolbar: {
                    show: false,
                  },
                },
                grid: {
                  borderColor: '#fff',
                  padding: {
                    top: -10,
                    bottom: 0,
                    right: -10
                  }
                },
                colors: ['#27489B'],
                plotOptions: {
                  bar: {
                    borderRadius: 20,
                    borderRadiusApplication: 'end',
                    horizontal: false,
                    columnWidth: '50%',
                    endingShape: 'rounded'      
                  },
                },
                responsive: [{
                  breakpoint: 650,
                  options: {
                    plotOptions: {
                      bar: {
                        borderRadius: 5,
                        borderRadiusApplication: 'end',
                        horizontal: false,
                        columnWidth: '60%',
                        endingShape: 'rounded'
                      },
                    },
                  }
                }],
                dataLabels: {
                  enabled: false,
                  position: 'bottom',
                  style: {
                    colors: ['#adbfeb']
                  },
                  offsetY: 0
                },
                stroke: {
                  show: true,
                  width: 1,
                  colors: ['transparent']
                },
                series: [
                  {
                    name: 'Employees',
                    data: response.data.series,
                  }    
                ],
                xaxis: {
                  categories: response.data.labels,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    },
                  },
                  axisBorder: {
                    color: '#fff',
                  },
                  axisTicks: {
                    show: false
                  }
                },
                yaxis: {
                  show: true,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    }
                  },
                  axisBorder: {
                    color: '#6577b3',
                  },
                  min: 0,
                  max: 1500,
                  tickAmount: 5
                },
                fill: {
                  opacity: 1
                },
                legend: {
                  show: false
                },
                tooltip: {
                  style: {
                    fontSize: '13px',
                    fontFamily: 'Public Sans, sans-serif'
                  },
                  y: {
                    formatter: function (val) {
                      return val
                    }
                  }
                }
              }
              
              var chartBranch = new ApexCharts(
                document.querySelector("#employeePerBranchChart"),
                optionsBranch
              );
              chartBranch.render();

              getEmployeeType()
        }
    });
}


function getEmployeeType(){

    let formdata = new FormData();
    formdata.append("request", "get-employee-type");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            var optionsType = {
                chart: {
                  height: 300,
                  type: 'donut',
                  offsetY: -20
                },
                grid: {
                  padding: {
                    top: 0,
                    bottom: 0
                  }
                },
                responsive: [{
                  breakpoint: 650,
                  options: {
                    chart: {       
                      offsetY: -20
                    },
                  }
                }],
                dataLabels: {
                  enabled: false
                },
                plotOptions: {
                  pie: {
                    customScale: 0.86,
                    donut: {
                      size: '80%',
                      labels: {
                        show: true,
                        total: {
                          show: true,
                          fontSize: '22px',
                          fontFamily: 'Public Sans, sans-serif',
                          color: '#27489B',
                          fontWeight: '600'
                        }
                      }
                    },
                    dataLabels: {
                      enabled: false
                    }
                  }
                },
                colors: ['#27489B', '#ffce27'],
                series: response.data.series,
                labels: response.data.labels,
                legend: {
                  show: true,
                  position: 'bottom'
                }
              }
              
              var chartType = new ApexCharts(
                document.querySelector("#employeeTypeChart"),
                optionsType
              );
              chartType.render();

              getClientEmployees();
        }
    });
}


function getClientEmployees(){

    let formdata = new FormData();
    formdata.append("request", "get-client-employees");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) {

            // ── Group by canonical name from Client Master ────────────────
            // Each HRIS client row carries fd_canonical (e.g. "SCommerce").
            // Clients with the same canonical name are summed under it.
            // Clients with no canonical name keep their raw client_name.
            var canonical = response.data.canonical || [];
            var pairs = (response.data.categories || []).map(function(name, i) {
                return {
                    name:  name,
                    count: response.data.series[i],
                    label: canonical[i] ? canonical[i] : name
                };
            });

            // Aggregate by label
            var buckets = {};
            pairs.forEach(function(p) {
                if (!buckets[p.label]) buckets[p.label] = 0;
                buckets[p.label] += p.count;
            });

            // Build sorted array (largest first)
            var grouped = Object.keys(buckets).map(function(label) {
                return { name: label, count: buckets[label] };
            });
            grouped.sort(function(a, b) { return b.count - a.count; });

            var groupedCategories = grouped.map(function(t) { return t.name; });
            var groupedSeries     = grouped.map(function(t) { return t.count; });
            var chartHeight       = Math.max(320, grouped.length * 52 + 60);
            // ─────────────────────────────────────────────────────────────

            var optionsClient = {
                chart: {
                  height: chartHeight,
                  type: 'bar',
                  parentHeightOffset: 0,
                  fontFamily: 'Public Sans, sans-serif',
                  toolbar: {
                    show: false
                  }
                },
                grid: {
                  borderColor: '#fff',
                  padding: {
                    top: -30,
                    bottom: 10,
                    right: 5
                  }
                },
                colors: ['#ffce27'],
                plotOptions: {
                  bar: {
                    borderRadius: 5,
                    borderRadiusApplication: 'end',
                    horizontal: true,
                    columnWidth: '30%',
                    barHeight: '75%',
                    endingShape: 'rounded'
                  },
                },
                dataLabels: {
                  enabled: true,
                  style: {
                    fontSize: '11px',
                    fontFamily: 'Public Sans, sans-serif',
                    colors: ['#555']
                  },
                  formatter: function(val) { return val.toLocaleString(); }
                },
                stroke: {
                  show: true,
                  width: 1,
                  colors: ['transparent']
                },
                series: [{
                  name: 'Employees',
                  data: groupedSeries
                }],
                xaxis: {
                  categories: groupedCategories,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    },
                  },
                  axisBorder: {
                    color: '#fff',
                  },
                  axisTicks: {
                    show: true
                  }
                },
                yaxis: {
                  show: true,
                  labels: {
                    maxWidth: 180,
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    }
                  },
                  axisBorder: {
                    color: '#aab4d5',
                  }
                },
                fill: {
                  opacity: 1
                },
                legend: {
                  show: false
                },
                tooltip: {
                  style: {
                    fontSize: '12px',
                    fontFamily: 'Public Sans, sans-serif'
                  },
                  y: {
                    formatter: function (val) {
                      return val.toLocaleString() + ' employees';
                    }
                  }
                }
              }
              var chartClient = new ApexCharts(
                document.querySelector("#employeePerClientChart"),
                optionsClient
              );
              chartClient.render();

              getAgeBracket();
        }
    });
}

function getAgeBracket(){

    let formdata = new FormData();
    formdata.append("request", "get-age-bracket");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            var optionsAge = {
                chart: {
                  height: 250,
                  type: 'bar',
                  parentHeightOffset: 0,
                  fontFamily: 'Public Sans, sans-serif',
                  toolbar: {
                    show: false,
                  },
                },
                grid: {
                  borderColor: '#fff',
                  padding: {
                    top: -30,
                    bottom: 10,
                    right: 5
                  }
                },
                colors: ['#27489B'],
                plotOptions: {
                  bar: {
                    borderRadius: 10,
                    borderRadiusApplication: 'end',
                    horizontal: true,
                    columnWidth: '25%',
                    barHeight: '60%',
                    endingShape: 'rounded'
                  },
                },
                dataLabels: {
                  enabled: false
                },
                stroke: {
                  show: true,
                  width: 1,
                  colors: ['transparent']
                },
                series: [{
                  name: '',
                  data: response.data.series
                }],
                xaxis: {
                  categories: response.data.categories,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    },
                  },
                  axisBorder: {
                    color: '#fff',
                  },
                  axisTicks: {
                    show: true
                  }
                },
                yaxis: {
                  show: true,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    }
                  },
                  axisBorder: {
                    color: '#aab4d5',
                  },
                  tickAmount: 3
                },
                fill: {
                  opacity: 1
                },
                legend: {
                  show: false
                },
                tooltip: {
                  style: {
                    fontSize: '13px',
                    fontFamily: 'Public Sans, sans-serif'
                  },
                  y: {
                    formatter: function (val) {
                      return val
                    }
                  }
                }
              }
              
              var chartAge = new ApexCharts(
                document.querySelector("#ageBracketChart"),
                optionsAge
              );
              chartAge.render();

              getLocationEmployees();
        }
    });
}


function getLocationEmployees(){

    let formdata = new FormData();
    formdata.append("request", "get-location-employees");

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
        },
        success: function (response) { 
            var optionsClientLoc = {
                chart: {
                  height: 400,
                  type: 'bar',
                  parentHeightOffset: 0,
                  fontFamily: 'Public Sans, sans-serif',
                  toolbar: {
                    show: false,
                  },
                },
                grid: {
                  borderColor: '#fff',
                  padding: {
                    top: 0,
                    bottom: 0,
                    right: -10
                  }
                },
                colors: ['#ff8533'],
                plotOptions: {
                  bar: {
                    borderRadius: 18,
                    borderRadiusApplication: 'end',
                    horizontal: false,
                    columnWidth: '60%',
                    endingShape: 'rounded'
                  },
                },
                responsive: [{
                  breakpoint: 650,
                  options: {
                    plotOptions: {
                      bar: {
                        borderRadius: 5,
                        borderRadiusApplication: 'end',
                        horizontal: false,
                        columnWidth: '60%',
                        endingShape: 'rounded'
                      },
                    },
                  }
                }],
                dataLabels: {
                  enabled: false
                },
                stroke: {
                  show: true,
                  width: 1,
                  colors: ['transparent']
                },
                series: [
                  {
                    name: 'Employees',
                    data: response.data.series,    
                  }    
                ],
                xaxis: {
                  categories: response.data.categories,    
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    },
                  },
                  axisBorder: {
                    color: '#fff',
                  },
                  axisTicks: {
                    show: false
                  }
                },
                yaxis: {
                  show: true,
                  labels: {
                    style: {
                      colors: '#aab4d5',
                      fontSize: '12px',
                      fontFamily: 'Public Sans, sans-serif'
                    }
                  },
                  axisBorder: {
                    color: '#6577b3',
                  },
                  min: 0,
                  max: 1200,
                  tickAmount: 5
                },
                fill: {
                  opacity: 1
                },
                legend: {
                  show: false
                },
                tooltip: {
                  style: {
                    fontSize: '13px',
                    fontFamily: 'Public Sans, sans-serif'
                  },
                  y: {
                    formatter: function (val) {
                      return val
                    }
                  }
                }
              }
              
              var chartClientLoc = new ApexCharts(
                document.querySelector("#employeePerClientLocChart"),
                optionsClientLoc
              );
              chartClientLoc.render();

              getMonthlyHires();
        }
    });
}


function getMonthlyHires(){
    let year = $("#year").val();
    let branch = $("#branch").val();

    let formdata = new FormData();
    formdata.append("request", "get-monthly-hires");
    formdata.append("year", year);
    formdata.append("branch", branch);

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#totalHiresMonthly").html('');  
            $("#totalHiresMonthly").show();  
            $("#yearDIV").show();  
            $("#totalHiresYearly").hide();  
        },
        success: function (response) { 
            var totaHiresMonthlyEl = document.querySelector('#totalHiresMonthly'),
                totaHiresMonthlyConfig = {
                series: [
                {
                    name: 'Hires',
                    data: response.data.series,
                }
                ],
                chart: {
                    height: 255,
                    parentHeightOffset: 0,
                    parentWidthOffset: 0,
                    toolbar: {
                    show: false
                    },
                    type: 'area'
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: 3,
                    curve: 'smooth'
                },
                legend: {
                    show: false
                },
                colors: ['#27489B'],
                fill: {
                    type: 'gradient',
                    gradient: {
                    shade: '#27489B',
                    shadeIntensity: 0.6,
                    opacityFrom: 0.5,
                    opacityTo: 0.25,
                    stops: [0, 95, 100]
                    }
                },
                grid: {
                    borderColor: '#fff',
                    padding: {
                    top: -15,
                    bottom: 20,
                    right: -10
                    }
                },
                xaxis: {
                    categories: response.data.categories,
                    axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                    },
                    labels: {
                    show: true,
                    style: {
                    fontSize: '12px',
                    fontFamily: 'Public Sans, sans-serif',
                    colors: '#aab4d5'
                    }
                    }
                },
                yaxis: {
                    labels: {
                    show: true,
                    style: {
                        colors: '#aab4d5',
                        fontSize: '12px',
                        fontFamily: 'Public Sans, sans-serif'
                    }
                    },
                    min: 0,
                    max: 300,
                    tickAmount: 5
                }
                };

                const totalHiresMonthly = new ApexCharts(totaHiresMonthlyEl, totaHiresMonthlyConfig);
                totalHiresMonthly.render();
        }
    });
}


function getYearlyHires(){
    let branch = $("#branch").val();

    let formdata = new FormData();
    formdata.append("request", "get-yearly-hires");
    formdata.append("branch", branch);

    $.ajax({
        url: 'controller/DashboardController.php',
        type: 'POST',
        data: formdata,
        dataType: 'json',
        processing: true, 
        contentType: false,
        processData: false,
        beforeSend: function( xhr ) {
            $("#totalHiresYearly").html('');  
            $("#totalHiresMonthly").hide();  
            $("#yearDIV").hide();  
            $("#totalHiresYearly").show();  
        },
        success: function (response) { 
            var totaHiresYearlyEl = document.querySelector('#totalHiresYearly'),
            totaHiresYearlyConfig = {
            series: [
            {
                name: 'Hires',
                data: response.data.series,
            }
            ],
            chart: {
                height: 255,
                parentHeightOffset: 0,
                parentWidthOffset: 0,
                toolbar: {
                show: false
                },
                type: 'area'
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                width: 3,
                curve: 'smooth'
            },
            legend: {
                show: false
            },
            colors: ['#27489B'],
            fill: {
                type: 'gradient',
                gradient: {
                shade: '#27489B',
                shadeIntensity: 0.6,
                opacityFrom: 0.5,
                opacityTo: 0.25,
                stops: [0, 95, 100]
                }
            },
            grid: {
                borderColor: '#fff',
                padding: {
                top: -15,
                bottom: 20,
                right: -10
                }
            },
            xaxis: {
                categories: response.data.categories,
                axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
                },
                labels: {
                show: true,
                style: {
                fontSize: '12px',
                fontFamily: 'Public Sans, sans-serif',
                colors: '#aab4d5'
                }
                }
            },
            yaxis: {
                labels: {
                show: true,
                style: {
                    colors: '#aab4d5',
                    fontSize: '12px',
                    fontFamily: 'Public Sans, sans-serif'
                }
                },
                min: 0,
                max: 900,
                tickAmount: 5
            }
            };

            const totalHiresYearly = new ApexCharts(totaHiresYearlyEl, totaHiresYearlyConfig);
            totalHiresYearly.render();
        }
    });
}
