//donut and pie
//payroll cost breakdown
var optionsPayrollCostBreakdown = {
  chart: {
    height: 230,
    type: 'donut',
    offsetY: -15
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
    enabled: false,
  },
  plotOptions: {
    pie: {
      customScale: 1,
      donut: {
        size: '78%',
        labels: {
          show: true,
          value: {
            formatter: function (value) {
              return Number(value).toLocaleString(); // <-- Cast to number
            }
          },
          total: {
            show: true,
            fontSize: '18px',
            fontFamily: 'Public Sans, sans-serif',
            color: '#27489B',
            fontWeight: '600',
            formatter: function (value) {
              let total = value.globals.seriesTotals.reduce((a, b) => a + b, 0);
              return total.toLocaleString();
            }
          }  
        }
      }
    }
  },
  colors: ['#27489B', '#476f95', '#7593af'],
  series: [50340, 8048, 3459],
  labels: ['Contributions', 'Deductions', 'Expenses'],
  legend: {
    show: true,
    position: 'bottom'
  },
  tooltip: {
    y: {
        formatter: (val) => {
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); // Format with thousand separators
        }
    }
  }
}

var chartPayrollCostBreakdown = new ApexCharts(
  document.querySelector("#payrollCostBreakdown"),
  optionsPayrollCostBreakdown
);
chartPayrollCostBreakdown.render();

//deductions
var optionsDeductions = {
  chart: {
    height: 280,
    type: 'pie',
    offsetY: 0 
  },
  grid: {
    padding: {
      top: -10,
      bottom: 0 
    }
  }, 
  dataLabels: {
    enabled: false 
  },
  responsive: [{
    breakpoint: 1500,
    options: {
      chart: {       
        height: 280,
        offsetY: -10
      },
      grid: {
        padding: {
          top: 0
        }
      }
    }
  }],
  plotOptions: {
    pie: {
      customScale: 0.90,
      pie: {
        size: '85%',
      },
      dataLabels: {
        enabled: false
      }
    }
  },  
  colors: ['#5f9747','#6aa84f', '#78b060', '#87b972', '#96c283', '#a5ca95'],  
  series: [30333, 9800, 12390, 15234, 8000, 10300],
  labels: ['Government Contributions', 'HMO', 'Loans', 'Tax', 'Cash Advance', 'Insurance'],
  legend: {
    show: true,
    position: 'bottom',
    fontSize: '12px',
    fontFamily: 'Public Sans, sans-serif',
  },
  tooltip: {
    y: {
        formatter: (val) => {
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); // Format with thousand separators
        }
    }
  }
}

var chartDeductions = new ApexCharts( 
  document.querySelector("#deductionBreakdown"),
  optionsDeductions
);
chartDeductions.render();

//worked hours vs overtime hours
var optionsWorkedVsOT = {
  chart: {
    height: 260,
    type: 'pie',
    offsetY: 0 
  },
  grid: {
    padding: {
      top: -10,
      bottom: 0 
    }
  }, 
  dataLabels: {
    enabled: false 
  },
  responsive: [{
    breakpoint: 1500,
    options: {
      chart: {       
        height: 260,
        offsetY: 0
      },
      grid: {
        padding: {
          top: 0
        }
      }
    }
  }],
  plotOptions: {
    pie: {
      customScale: 0.90,
      pie: {
        size: '90%',
      }
    }
  },  
  colors: ['#27489b','#ffe17d'],  
  series: [5000, 250],
  labels: ['Worked Hours', 'Overtime Hours'],
  legend: {
    show: true,
    position: 'bottom',
    fontSize: '12px',
    fontFamily: 'Public Sans, sans-serif',
  },
  tooltip: {
    y: {
        formatter: (val) => {
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); // Format with thousand separators
        }
    }
  }
}

var chartWorkedVsOT = new ApexCharts( 
  document.querySelector("#workedVsOvertime"),
  optionsWorkedVsOT
);
chartWorkedVsOT.render();

/////////////////////////////////////////////////////////////////////////////////////////

//radial (gauge js)
//payroll accuracy rate
var optPayroll = {
  angle: -0.0, // The span of the gauge arc
  lineWidth: 0.25, // The line thickness
  radiusScale: 1, // Relative radius
  pointer: {
    length: 0, // // Relative to gauge radius
    strokeWidth: 0, // The thickness
    color: '#fff' // Fill color
  },
  // pointer: {
  //   length: 0.60, // // Relative to gauge radius
  //   strokeWidth: 0.030, // The thickness
  //   color: '#303030' // Fill color
  // },
  limitMax: false,     // If false, max value increases automatically if value > maxValue
  limitMin: false,     // If true, the min value of the gauge will be fixed
  colorStart: '#27489b',   // Colors
  colorStop: '#27489b',    
  strokeColor: '#EEEEEE', 
  generateGradient: false,
  highDpiSupport: true,     // High resolution support
  // renderTicks is Optional
  // renderTicks: {
  //   divisions: 8,
  //   divWidth: 1.1,
  //   divLength: 1,
  //   divColor: '#fff',
  //   subDivisions: 0,
  //   subLength: 0.5,
  //   subWidth: 0.6,
  //   subColor: '#666666'
  // },
  // staticZones: [
  //   // {strokeStyle: "#27489b", min: 0, max: 100}, 
  //   // {strokeStyle: "#ffb700", min: 30, max: 70},  
  //   // {strokeStyle: "#62bd20", min: 70, max: 80},  
  //   // {strokeStyle: "#30b32d", min: 80, max: 100}
  //   // {strokeStyle: "#f03e3e", min: 0, max: 20},
  //   // {strokeStyle: "#fa5f31", min: 10, max: 20}, 
  //   // {strokeStyle: "#ff7d22", min: 20, max: 30}, 
  //   // {strokeStyle: "#ff9a0f", min: 30, max: 40}, 
  //   // {strokeStyle: "#ffb700", min: 40, max: 50},  
  //   // {strokeStyle: "#f1c200", min: 50, max: 60},  
  //   // {strokeStyle: "#adce02", min: 60, max: 70},  
  //   // {strokeStyle: "#89c612", min: 70, max: 80},  
  //   // {strokeStyle: "#62bd20", min: 80, max: 90},  
  //   // {strokeStyle: "#30b32d", min: 90, max: 100},
  // ]
};

var targetPayroll = document.getElementById('payrollrate');
var gaugePayroll = new Gauge(targetPayroll).setOptions(optPayroll); 
gaugePayroll.maxValue = 100; // set max gauge value
gaugePayroll.setMinValue(0);  // Prefer setter over gauge.minValue = 0
gaugePayroll.animationSpeed = 20; // set animation speed (32 is default value)
gaugePayroll.set(93); // set actual value
gaugePayroll.setTextField(document.getElementById('payroll-value'));

//tax and contribution compliance
var optTax = {
  angle: -0.0, // The span of the gauge arc
  lineWidth: 0.25, // The line thickness
  radiusScale: 1, // Relative radius
  pointer: {
    length: 0.00, // // Relative to gauge radius
    strokeWidth: 0.0, // The thickness
    color: '#303030' // Fill color
  },
  limitMax: false,     // If false, max value increases automatically if value > maxValue
  limitMin: false,     // If true, the min value of the gauge will be fixed
  colorStart: '#ffce27',   // Colors
  colorStop: '#ffce27',    
  strokeColor: '#EEEEEE', 
  generateGradient: false,
  highDpiSupport: true,     // High resolution support
  // renderTicks is Optional
  // renderTicks: {
  //   divisions: 8,
  //   divWidth: 1.1,
  //   divLength: 1,
  //   divColor: '#fff',
  //   subDivisions: 0,
  //   subLength: 0.5,
  //   subWidth: 0.6,
  //   subColor: '#666666'
  // },
  // staticZones: [
  //   {strokeStyle: "#f03e3e", min: 0, max: 30}, 
  //   {strokeStyle: "#ffb700", min: 30, max: 70},  
  //   {strokeStyle: "#62bd20", min: 70, max: 80},  
  //   {strokeStyle: "#30b32d", min: 80, max: 100}
  // ]
};

var targetTax = document.getElementById('taxrate');
var gaugeTax = new Gauge(targetTax).setOptions(optTax); 
gaugeTax.maxValue = 100; // set max gauge value
gaugeTax.setMinValue(0);  // Prefer setter over gauge.minValue = 0
gaugeTax.animationSpeed = 20; // set animation speed (32 is default value)
gaugeTax.set(82); // set actual value
gaugeTax.setTextField(document.getElementById('tax-value'));

//employee performance rate
var optPerformance = {
  angle: -0.0, // The span of the gauge arc
  lineWidth: 0.25, // The line thickness
  radiusScale: 1, // Relative radius
  pointer: {
    length: 0.00, // // Relative to gauge radius
    strokeWidth: 0.0, // The thickness
    color: '#303030' // Fill color
  },
  limitMax: false,     // If false, max value increases automatically if value > maxValue
  limitMin: false,     // If true, the min value of the gauge will be fixed
  colorStart: '#6aa84f',   // Colors
  colorStop: '#6aa84f',    
  strokeColor: '#EEEEEE', 
  generateGradient: false,
  highDpiSupport: true,     // High resolution support
  // renderTicks is Optional
  // renderTicks: {
  //   divisions: 8,
  //   divWidth: 1.1,
  //   divLength: 1,
  //   divColor: '#fff',
  //   subDivisions: 0,
  //   subLength: 0.5,
  //   subWidth: 0.6,
  //   subColor: '#666666'
  // },
  // staticZones: [
  //   {strokeStyle: "#f03e3e", min: 0, max: 30}, 
  //   {strokeStyle: "#ffb700", min: 30, max: 70},  
  //   {strokeStyle: "#62bd20", min: 70, max: 80},  
  //   {strokeStyle: "#30b32d", min: 80, max: 100}
  // ]
};

var targetPerformance = document.getElementById('perfrate');
var gaugePerformance = new Gauge(targetPerformance).setOptions(optPerformance); 
gaugePerformance.maxValue = 100; // set max gauge value
gaugePerformance.setMinValue(0);  // Prefer setter over gauge.minValue = 0
gaugePerformance.animationSpeed = 20; // set animation speed (32 is default value)
gaugePerformance.set(91); // set actual value
gaugePerformance.setTextField(document.getElementById('perf-value'));

//absenteeism rate
var optAbsenteeism = {
  angle: -0.0, // The span of the gauge arc
  lineWidth: 0.25, // The line thickness
  radiusScale: 1, // Relative radius
  pointer: {
    length: 0.00, // // Relative to gauge radius
    strokeWidth: 0.0, // The thickness
    color: '#303030' // Fill color
  },
  limitMax: false,     // If false, max value increases automatically if value > maxValue
  limitMin: false,     // If true, the min value of the gauge will be fixed
  colorStart: '#BC1D2B',   // Colors
  colorStop: '#BC1D2B',    
  strokeColor: '#EEEEEE', 
  generateGradient: false,
  highDpiSupport: true,     // High resolution support
  // renderTicks is Optional
  // renderTicks: {
  //   divisions: 8,
  //   divWidth: 1.1,
  //   divLength: 1,
  //   divColor: '#fff',
  //   subDivisions: 0,
  //   subLength: 0.5,
  //   subWidth: 0.6,
  //   subColor: '#666666'
  // },
  // staticZones: [
  //   {strokeStyle: "#f03e3e", min: 0, max: 30}, 
  //   {strokeStyle: "#ffb700", min: 30, max: 70},  
  //   {strokeStyle: "#62bd20", min: 70, max: 80},  
  //   {strokeStyle: "#30b32d", min: 80, max: 100}
  // ]
};

var targetAbsenteeism = document.getElementById('absrate');
var gaugeAbsenteeism = new Gauge(targetAbsenteeism).setOptions(optAbsenteeism); 
gaugeAbsenteeism.maxValue = 100; // set max gauge value
gaugeAbsenteeism.setMinValue(0);  // Prefer setter over gauge.minValue = 0
gaugeAbsenteeism.animationSpeed = 20; // set animation speed (32 is default value)
gaugeAbsenteeism.set(13.4); // set actual value
gaugeAbsenteeism.setTextField(document.getElementById('abs-value'));

//////////////////////////////////////////////////////////////////////////////////////

//bar
//sss contributions 
var optionsSSSContributions = {
  chart: {
    height: 305,
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
      bottom: 10,
      right: 0
    }
  },
  colors: ['#ffce27', '#27489b'],
  plotOptions: {
    bar: {
      borderRadius: 3,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '60%',
      // columnWidth: '80%',
      endingShape: 'rounded'
    },
  },
  responsive: [{
    breakpoint: 500,
    options: {
      plotOptions: {
        bar: {
          borderRadius: 2,
          borderRadiusApplication: 'end',
          horizontal: false,
          columnWidth: '100%',
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
    width: 2,
    colors: ['transparent']
  },
  series: [{
    name: 'Paid',
    data: [2050, 1300]
  }, {
    name: 'Not Paid',
    data: [100, 900]
  }],
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    // categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      },
    },
    axisBorder: {
      color: '#e6e6e6',
    },
    axisTicks: {
      show: false
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      }
    },
    axisBorder: {
      color: '#6577b3',
    },
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

var chartSSSContributions = new ApexCharts(
  document.querySelector("#sss-contributions"),
  optionsSSSContributions
);
chartSSSContributions.render();

// philhealth contributions
var optionsPhilhealthContributions = {
  chart: {
    height: 305,
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
      bottom: 10,
      right: 0
    }
  },
  colors: ['#ffce27', '#27489b'],
  plotOptions: {
    bar: {
      borderRadius: 3,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '60%',
      // columnWidth: '80%',
      endingShape: 'rounded'
    },
  },
  responsive: [{
    breakpoint: 500,
    options: {
      plotOptions: {
        bar: {
          borderRadius: 2,
          borderRadiusApplication: 'end',
          horizontal: false,
          columnWidth: '100%',
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
    width: 2,
    colors: ['transparent']
  },
  series: [{
    name: 'Paid',
    data: [2300, 1500]
  }, {
    name: 'Not Paid',
    data: [0, 800]
  }],
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    // categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      },
    },
    axisBorder: {
      color: '#e6e6e6',
    },
    axisTicks: {
      show: false
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      }
    },
    axisBorder: {
      color: '#6577b3',
    },
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

var chartPhilhealthContributions = new ApexCharts(
  document.querySelector("#philhealth-contributions"),
  optionsPhilhealthContributions
);
chartPhilhealthContributions.render();

// pagibig contributions
var optionsPagibigContributions = {
  chart: {
    height: 305,
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
      bottom: 10,
      right: 0
    }
  },
  colors: ['#ffce27', '#27489b'],
  plotOptions: {
    bar: {
      borderRadius: 3,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '60%',
      // columnWidth: '80%',
      endingShape: 'rounded'
    },
  },
  responsive: [{
    breakpoint: 500,
    options: {
      plotOptions: {
        bar: {
          borderRadius: 2,
          borderRadiusApplication: 'end',
          horizontal: false,
          columnWidth: '100%',
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
    width: 2,
    colors: ['transparent']
  },
  series: [{
    name: 'Paid',
    data: [2300, 2300]
  }, {
    name: 'Not Paid',
    data: [0, 0]
  }],
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    // categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      },
    },
    axisBorder: {
      color: '#e6e6e6',
    },
    axisTicks: {
      show: false
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      }
    },
    axisBorder: {
      color: '#6577b3',
    },
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

var chartPagibigContributions = new ApexCharts(
  document.querySelector("#pagibig-contributions"),
  optionsPagibigContributions
);
chartPagibigContributions.render();

// average gross salary
var optionsAveGrossSalary = {
  chart: {
    height: 300,
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
      bottom: 5,
      right: -10
    }
  },
  colors: ['#27489B'],
  plotOptions: {
    bar: {
      borderRadius: 5,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '40%',
      // columnWidth: '80%',
      endingShape: 'rounded'
    },
  },
  responsive: [{
    breakpoint: 500,
    options: {
      plotOptions: {
        bar: {
          borderRadius: 2,
          borderRadiusApplication: 'end',
          horizontal: false,
          columnWidth: '100%',
          endingShape: 'rounded'
        },
      },
    },
    grid: {
      borderColor: '#fff',
      padding: {
        top: 0,
        bottom: 0,
        right: 0,
        left: -40
      }
    },
  }],
  dataLabels: {
    enabled: false
  },
  stroke: {
    show: true,
    width: 2,
    colors: ['transparent']
  },
  series: [{
    name: 'Average Gross Salary',
    data: [18500, 16000, 19500, 18500, 17000, 18000, 15000]
  }],
  xaxis: {
    categories: ['Bulacan', 'Cabuyao', 'Cainta', 'Cavite', 'Greenwoods', 'Parian', 'San Pedro'],
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      },
    },
    axisBorder: {
      color: '#e6e6e6',
    },
    axisTicks: {
      show: false
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      }
    },
    axisBorder: {
      color: '#6577b3',
    },
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

var chartAveGrossSalary = new ApexCharts(
  document.querySelector("#averageGrossSalary"),
  optionsAveGrossSalary
);
chartAveGrossSalary.render();

// gender pay analysis
var optionsGenderPay = {
  chart: {
    height: 245,
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
      bottom: 5,
      right: -10
    }
  },
  colors: ['#27489b', '#BC1D2B'],
  plotOptions: {
    bar: {
      borderRadius: 3,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '60%',
      // columnWidth: '80%',
      endingShape: 'rounded'
    },
  },
  responsive: [{
    breakpoint: 500,
    options: {
      plotOptions: {
        bar: {
          borderRadius: 2,
          borderRadiusApplication: 'end',
          horizontal: false,
          columnWidth: '100%',
          endingShape: 'rounded'
        },
      },
    },
    grid: {
      borderColor: '#fff',
      padding: {
        top: 0,
        bottom: 0,
        right: 0,
        left: -40
      }
    },
  }],
  dataLabels: {
    enabled: false
  },
  stroke: {
    show: true,
    width: 2,
    colors: ['transparent']
  },
  series: [{
    name: 'Male',
    data: [20000, 16000, 19500, 18500, 17000, 18000, 15000]
  }, {
    name: 'Female',
    data: [18500, 14500, 20000, 16000, 15000, 19500, 13500]
  }],
  xaxis: {
    categories: ['Bulacan', 'Cabuyao', 'Cainta', 'Cavite', 'Greenwoods', 'Parian', 'San Pedro'],
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      },
    },
    axisBorder: {
      color: '#e6e6e6',
    },
    axisTicks: {
      show: false
    }
  },
  yaxis: {
    labels: {
      style: {
        colors: '#adb5b8',
        fontSize: '12px',
        fontFamily: 'Public Sans, sans-serif'
      }
    },
    axisBorder: {
      color: '#6577b3',
    },
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

var chartGenderPay = new ApexCharts(
  document.querySelector("#genderPayAnalysis"),
  optionsGenderPay
);
chartGenderPay.render();

// select2
$('#branch').select2({
  theme: "bootstrap-5",
  width: '100%',
  placeholder: 'Select Branch'
});

$('#year').select2({
  theme: "bootstrap-5",
  width: '100%',
  placeholder: 'Select Year'
});

$('#page-month').select2({
  theme: "bootstrap-5",
  width: '100%',
  placeholder: 'Select Month'
});

$('#page-year').select2({
  theme: "bootstrap-5",
  width: '100%',
  placeholder: 'Select Year'
});

// toggle between sss, philhealth and pagibig
$(document).ready(function () {
  if ($("#ssscontri").prop("checked")) {
    $("#sss-contributions").show();
    $("#philhealth-contributions").hide();
    $("#pagibig-contributions").hide();
  }

  $("#ssscontri").click(function () {
    $("#sss-contributions").html('');
    $("#sss-contributions").show();
    $("#philhealth-contributions").hide();
    $("#pagibig-contributions").hide();

    var chartSSSContributions = new ApexCharts(
      document.querySelector("#sss-contributions"),
      optionsSSSContributions
    );
    chartSSSContributions.render();
  });

  $("#philcontri").click(function () {
    $("#sss-contributions").hide();
    $("#pagibig-contributions").hide();
    $("#philhealth-contributions").html('');
    $("#philhealth-contributions").show();

    var chartPhilhealthContributions = new ApexCharts(
      document.querySelector("#philhealth-contributions"),
      optionsPhilhealthContributions
    );
    chartPhilhealthContributions.render();
  });

  $("#pagibigcontri").click(function () {
    $("#sss-contributions").hide();
    $("#philhealth-contributions").hide();
    $("#pagibig-contributions").html('');
    $("#pagibig-contributions").show();

    var chartPagibigContributions = new ApexCharts(
      document.querySelector("#pagibig-contributions"),
      optionsPagibigContributions
    );
    chartPagibigContributions.render();
  });
});