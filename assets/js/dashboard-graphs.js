// bar graphs
// emp per branch
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
      borderRadius: 8,
      borderRadiusApplication: 'end',
      horizontal: false,
      columnWidth: '40%',
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
      data: [300, 580, 420, 218, 590, 320, 230],
    }    
  ],
  xaxis: {
    categories: ['Bulacan', 'Cabuyao', 'Cainta', 'Cavite', 'Greenwoods', 'Parian', 'San Pedro'],
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
    max: 600,
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

// emp per client
var optionsClient = {
  chart: {
    height: 642,
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
      borderRadius: 2,
      borderRadiusApplication: 'end',
      horizontal: true,
      columnWidth: '30%',
      barHeight: '75%',
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
    data: [780, 999, 650, 1296, 1011, 951, 753, 852, 963, 741, 698, 478, 1317, 1487, 1222, 444, 666, 884, 951, 753, 852, 963, 741, 698, 478, 797, 365, 951, 753, 852, 963, 741, 698, 478, 797, 365]
  }],
  xaxis: {   
    categories: ['All Inclusive Inc.',
      'Auto 88',
      'Cainiao Logistics Inc',
      'Cavite Light Industrial Park Association Inc',
      'Centro Marilao',
      'Centro Nova',
      'Continum Packaging Corp',
      'Coxon',
      'Creative Paper',
      'Cya Industries Inc',
      'Delta Milling Industries Inc',
      'Euro Med Laboratories Phil Inc.',
      'First Sumiden Circuits Inc.',
      'Fujifilm Optiocs Phils. Inc',
      'Globalmaxx Manufacturing Corp.',
      'Lazada',
      'Leslie Manufacturing Corp.',
      'Marina Sales Inc',
      'Melaor Trading Corp.',
      'MFC',
      'Multimix International Manufacturing Corp.',
      'Newbie',
      'Nikkoplas Inc.',
      'Ohgitani Metal Inc',
      'Sante International',
      'Sealed Air Inc.',
      'Shopee',
      'Sumiden',
      'Super Flow Supply Chain Technology Inc.',
      'Tasscor',
      'Technology Prime Integrated Solution Inc.',
      'Wcl Cold Storage Solutions',
      'Yuanshan Electronics Inc.'],
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
      fontSize: '12px',
      fontFamily: 'Public Sans, sans-serif'
    },
    y: {
      formatter: function (val) {
        return val
      }
    }
  }
}
var chartClient = new ApexCharts(
  document.querySelector("#employeePerClientChart"),
  optionsClient
);
chartClient.render();


// emp per client location
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
      borderRadius: 5,
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
      data: [879, 1000, 560, 866, 601, 590, 1125, 1397, 1254, 888, 1001, 745, 980, 1400, 780, 590],    
    }    
  ],
  xaxis: {
    categories:['Binagonan', 'Bulacan', 'Cabuyao City', 'Cainta', 'Calamba City', 'Canlubang', 'Cavite', 'Cebu', 'Marilao', 'Masinag', 'Novaliches, QC', 'Pampanga', 'Paranaque', 'Pasig City', 'San Pedro City', 'Taytay, Rizal'],    
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

var chartClientLoc = new ApexCharts(
  document.querySelector("#employeePerClientLocChart"),
  optionsClientLoc
);
chartClientLoc.render();

// age bracket
var optionsAge = {
  chart: {
    height: 280,
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
      bottom: 0,
      right: 5
    }
  },
  colors: ['#27489B'],
  plotOptions: {
    bar: {
      borderRadius: 5,
      borderRadiusApplication: 'end',
      horizontal: true,
      columnWidth: '25%',
      barHeight: '50%',
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
    data: [45, 60, 28, 35, 22, 13]
  }],
  xaxis: {
    categories: ['Age 18-25', 'Age 26-35', 'Age 36-45', 'Age 45-55', 'Age 56-65', 'Age 66-75'],
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

/////////////////////////////////////////////////////////////////////////////

// donut graphs
// employee type
var optionsType = {
  chart: {
    height: 280,
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
  series: [1080, 705],
  labels: ['Long Term', 'Seasonal'],
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


// civil status
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
  series: [105, 350, 75, 53],
  labels: ['Single', 'Married', 'Separated', 'Widowed'],
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

// issue per branch
var optionsIssue = {
  chart: {
    height: 300, 
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
  colors:['#ffa600', '#8a508f', '#bc5090', '#ff6361', '#ff8531', '#003f5c'],
  series: [2, 12, 2, 12, 4, 30],
  labels: ['Duplicate Bank Account Number', 'Duplicate Pag Ibig', 'Duplicate Philhealth', 'Duplicate SSS', 'Duplicate TIN', 'Incorrect Format '],
  legend: {
    show: true,
    position: 'right',
    padding: {
      right: -50
    }
  }
}

var chartIssue = new ApexCharts(
  document.querySelector("#issuePerBranch"),
  optionsIssue
);
chartIssue.render();


///////////////////////////////////////////////////////////////////////////////
// line
// total number of hires monthly
var totaHiresMonthlyEl = document.querySelector('#totalHiresMonthly'),
totaHiresMonthlyConfig = {
  series: [
  {
    name: 'Hires',
    data: [120, 90, 320, 218, 600, 620, 500, 300, 180, 87, 98, 55],
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
  // markers: {
  //   size: 6,
  //   colors: 'transparent',
  //   strokeColors: 'transparent',
  //   strokeWidth: 4,
  //   discrete: [{
  //     fillColor: 'white',
  //     seriesIndex: 0,
  //     dataPointIndex: 6,
  //     strokeColor: '#27489B',
  //     strokeWidth: 2,
  //     size: 6,
  //     radius: 8
  //     }],
  //   hover: {
  //     size: 7
  //   }
  // },
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
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
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
    max: 650,
    tickAmount: 5
  }
};

const totalHiresMonthly = new ApexCharts(totaHiresMonthlyEl, totaHiresMonthlyConfig);
totalHiresMonthly.render();

// total number of hires monthly
var totaHiresYearlyEl = document.querySelector('#totalHiresYearly'),
totaHiresYearlyConfig = {
  series: [
  {
    name: 'Hires',
    data: [200, 400, 450, 560, 800, 880, 920, 1005, 400, 520, 690, 780, 950, 200],
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
  // markers: {
  //   size: 6,
  //   colors: 'transparent',
  //   strokeColors: 'transparent',
  //   strokeWidth: 4,
  //   discrete: [{
  //     fillColor: 'white',
  //     seriesIndex: 0,
  //     dataPointIndex: 6,
  //     strokeColor: '#27489B',
  //     strokeWidth: 2,
  //     size: 6,
  //     radius: 8
  //     }],
  //   hover: {
  //     size: 7
  //   }
  // },
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
    categories: ['2012', '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025'],
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
    max: 1100,
    tickAmount: 5
  }
};

const totalHiresYearly = new ApexCharts(totaHiresYearlyEl, totaHiresYearlyConfig);
totalHiresYearly.render();

$(document).ready(function(){
  $("#btnmonthly").click(function(){
      $("#totalHiresMonthly").show();  
      $("#totalHiresYearly").hide();  
      const totalHiresMonthly = new ApexCharts(totaHiresMonthlyEl, totaHiresMonthlyConfig);
      totalHiresMonthly.render();
  });

  $("#btnyearly").click(function(){
      $("#totalHiresMonthly").hide();  
      $("#totalHiresYearly").show();  
      const totalHiresYearly = new ApexCharts(totaHiresYearlyEl, totaHiresYearlyConfig);
      totalHiresYearly.render();
  });

  if ($("#btnmonthly").prop("checked")) {
       $("#totalHiresMonthly").show();
       $("#totalHiresYearly").hide();
   }
});